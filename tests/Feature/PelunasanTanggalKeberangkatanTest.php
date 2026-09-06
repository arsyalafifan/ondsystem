<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusStop;
use App\Livewire\Pembayaran\Pelunasan;
use App\Models\Kendaraan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Menu Pelunasan mengelompokkan kendaraan menurut tanggal KEBERANGKATANNYA
 * (`RoutingBatch::tanggal`), sinkron dengan `Pesanan::tanggal_pendapatan`
 * (lihat `PendapatanTanggalKeberangkatanTest.php`) — bukan lagi menurut
 * kapan pesanannya benar-benar tuntas dikirim (`Pesanan::selesai_at`).
 * Kendaraan yang berangkat tanggal 20 tapi baru tuntas dikirim tanggal 22
 * tetap muncul di Pelunasan tanggal 20.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 20_000]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
});

/**
 * Kendaraan yang berangkat pada $keberangkatan tapi baru benar-benar
 * tuntas dikirim (selesai_at) pada $selesai — men-simulasikan skenario di
 * permintaan: berangkat tanggal 20, baru tuntas dikirim tanggal 22.
 */
function kendaraanBerangkatSelesaiBelakangan(CarbonImmutable $keberangkatan, CarbonImmutable $selesai): Kendaraan
{
    Storage::fake('public');

    $toko = Toko::create([
        'kode' => 'TK-PLNTGL', 'nama' => 'Toko Pelunasan Tanggal', 'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Tanggal', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $pesanan = test()->pesananService->buat(
        $toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => 5]], test()->sales,
    );
    test()->pesananService->setujui($pesanan, test()->admin);

    $batch = test()->routingService->generate(test()->admin, tanggalKeberangkatan: $keberangkatan);
    test()->routingService->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $stop = $kendaraan->stops->first();

    test()->travelTo($selesai);

    $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');
    test()->pesananService->selesaikanPengiriman($stop, $path, test()->driver);

    test()->travelBack();

    return $kendaraan->fresh();
}

it('kendaraan yang berangkat tanggal 20 tapi baru tuntas dikirim tanggal 22 muncul di Pelunasan tanggal 20', function () {
    $keberangkatan = CarbonImmutable::parse('2026-08-20');
    $selesai = CarbonImmutable::parse('2026-08-22');

    $kendaraan = kendaraanBerangkatSelesaiBelakangan($keberangkatan, $selesai);

    expect($kendaraan->stops->first()->pesanan->tanggal_pendapatan->toDateString())
        ->toBe($keberangkatan->toDateString());

    $adaDiTanggalKeberangkatan = Livewire::actingAs($this->admin)
        ->test(Pelunasan::class)
        ->set('tanggal', $keberangkatan->toDateString())
        ->instance()->kendaraans
        ->contains('id', $kendaraan->id);

    $adaDiTanggalSelesai = Livewire::actingAs($this->admin)
        ->test(Pelunasan::class)
        ->set('tanggal', $selesai->toDateString())
        ->instance()->kendaraans
        ->contains('id', $kendaraan->id);

    expect($adaDiTanggalKeberangkatan)->toBeTrue()
        ->and($adaDiTanggalSelesai)->toBeFalse();
});

it('stop yang belum tuntas dikirim tetap tidak muncul walau tanggal keberangkatannya cocok', function () {
    Storage::fake('public');

    $toko = Toko::create([
        'kode' => 'TK-PLNTGL2', 'nama' => 'Toko Belum Tuntas', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Tanggal 2', 'latitude' => -6.21, 'longitude' => 106.81, 'sumber_koordinat' => 'manual',
    ]);

    $pesanan = $this->pesananService->buat(
        $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 5]], $this->sales,
    );
    $this->pesananService->setujui($pesanan, $this->admin);

    $keberangkatan = CarbonImmutable::parse('2026-08-20');
    $batch = $this->routingService->generate($this->admin, tanggalKeberangkatan: $keberangkatan);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();

    expect($kendaraan->stops->first()->status)->toBe(StatusStop::Pending);

    $tampil = Livewire::actingAs($this->admin)
        ->test(Pelunasan::class)
        ->set('tanggal', $keberangkatan->toDateString())
        ->instance()->kendaraans
        ->contains('id', $kendaraan->id);

    expect($tampil)->toBeFalse();
});
