<?php

use App\Enums\JenisFotoKunjungan;
use App\Enums\PeranPengguna;
use App\Enums\StatusKunjungan;
use App\Livewire\Kunjungan\Kunjungi;
use App\Models\Kunjungan;
use App\Models\PenugasanSales;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\ModeUji;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Mode uji adalah satu-satunya jalan pintas pada aturan "foto wajib dari
 * kamera". Karena itu penjagaannya diuji sekeras aturannya sendiri: harus mati
 * di luar lingkungan lokal, dan harus mati bila penandanya tidak dinyalakan.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->toko = Toko::create([
        'kode' => 'TK-0001',
        'asset_id' => 'IDNAH202528004381',
        'freezer_tipe' => 'SD-280',
        'freezer_pelanggan' => 'IDN Halocoko',
        'nama' => 'Toko Uji',
        'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);

    PenugasanSales::create([
        'sales_id' => $this->sales->id,
        'toko_id' => $this->toko->id,
        'bulan' => CarbonImmutable::today()->startOfMonth()->toDateString(),
        'ditugaskan_oleh' => $this->admin->id,
    ]);
});

it('mati ketika penandanya tidak dinyalakan', function () {
    config(['visit.mode_uji' => false]);

    expect(ModeUji::aktif())->toBeFalse();
});

it('mati di luar lingkungan lokal walau penandanya menyala', function () {
    config(['visit.mode_uji' => true]);
    app()->detectEnvironment(fn () => 'production');

    expect(ModeUji::aktif())->toBeFalse();

    app()->detectEnvironment(fn () => 'testing');
});

it('menolak permintaan jalan pintas ketika mode uji mati', function () {
    config(['visit.mode_uji' => false]);

    Livewire::actingAs($this->sales)
        ->test(Kunjungi::class)
        ->call('prosesQrManual')
        ->assertForbidden();
});

it('menyembunyikan panel mode uji dari tampilan ketika mati', function () {
    config(['visit.mode_uji' => false]);

    Livewire::actingAs($this->sales)
        ->test(Kunjungi::class)
        ->assertDontSee(__('kunjungan.mode_uji'))
        ->assertDontSee(__('kunjungan.foto_contoh'));
});

describe('saat mode uji menyala di lingkungan lokal', function () {
    beforeEach(function () {
        config(['visit.mode_uji' => true]);
        app()->detectEnvironment(fn () => 'local');
    });

    afterEach(function () {
        app()->detectEnvironment(fn () => 'testing');
    });

    it('memulai kunjungan dari isi QR yang ditempel', function () {
        $isi = "客户名称：IDN Halocoko\n资产编号：IDNAH202528004381\n产品型号：SD-280";

        Livewire::actingAs($this->sales)
            ->test(Kunjungi::class)
            ->set('qrManual', $isi)
            ->call('prosesQrManual')
            ->assertSet('tahap', 'kunjungan');

        expect(Kunjungan::where('toko_id', $this->toko->id)->exists())->toBeTrue();
    });

    it('tetap menerapkan seluruh aturan kunjungan pada jalur tempel', function () {
        // Toko yang bukan tanggungan sales ini harus tetap ditolak, sama
        // seperti kalau QR-nya dipindai kamera.
        $tokoLain = Toko::create([
            'kode' => 'TK-0002', 'asset_id' => 'IDNAH202528009999',
            'nama' => 'Toko Bukan Tanggungan', 'wilayah_id' => $this->toko->wilayah_id,
            'alamat' => 'Jl. Lain', 'sumber_koordinat' => 'belum',
        ]);

        Livewire::actingAs($this->sales)
            ->test(Kunjungi::class)
            ->set('qrManual', '资产编号：'.$tokoLain->asset_id)
            ->call('prosesQrManual')
            ->assertSet('tahap', 'pindai');

        expect(Kunjungan::count())->toBe(0);
    });

    it('mengisi keenam foto dan menyelesaikan kunjungan', function () {
        $komponen = Livewire::actingAs($this->sales)
            ->test(Kunjungi::class)
            ->set('qrManual', '资产编号：IDNAH202528004381')
            ->call('prosesQrManual')
            ->call('isiSemuaFotoUji');

        $kunjungan = Kunjungan::with('fotos')->first();

        expect($kunjungan->fotos)->toHaveCount(count(JenisFotoKunjungan::urut()))
            ->and($kunjungan->foto_lengkap)->toBeTrue();

        $komponen->call('selesaikan');

        expect($kunjungan->fresh()->status)->toBe(StatusKunjungan::Selesai);
    });

    it('memberi watermark pada gambar contoh, sama seperti foto sungguhan', function () {
        Livewire::actingAs($this->sales)
            ->test(Kunjungi::class)
            ->set('qrManual', '资产编号：IDNAH202528004381')
            ->call('prosesQrManual')
            ->call('jepretUji', JenisFotoKunjungan::Spanduk->value);

        $foto = Kunjungan::first()->fotos()->first();

        Storage::disk('public')->assertExists($foto->path);

        expect($foto->diambil_at->diffInSeconds(now()))->toBeLessThan(5)
            ->and($foto->lebar)->toBe((int) config('visit.foto.lebar_maks'));
    });

    it('menawarkan isi QR toko tanggungan yang belum dikunjungi', function () {
        $komponen = Livewire::actingAs($this->sales)->test(Kunjungi::class);

        $contoh = $komponen->instance()->contohQr;

        expect($contoh)->toHaveCount(1)
            ->and($contoh[0]['nama'])->toBe('Toko Uji')
            ->and($contoh[0]['qr'])->toContain('IDNAH202528004381');
    });
});
