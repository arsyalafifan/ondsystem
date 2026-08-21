<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Livewire\Pembayaran\Pelunasan;
use App\Livewire\Pembayaran\Pendapatan;
use App\Models\Kendaraan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PelunasanService;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use App\Support\Bahasa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->produk = Produk::create([
        'kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100_000, 'harga' => 50_000,
    ]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
    $this->pengirimanService = app(PengirimanService::class);
    $this->pelunasanService = app(PelunasanService::class);
});

/**
 * Membuat sejumlah toko berkoordinat beserta pesanan yang sudah berstatus
 * PROCESS, yaitu keadaan tepat sebelum admin menekan Generate Routing.
 *
 * @return array<int, Pesanan>
 */
function siapkanPesananPembayaran(int $jumlah, int $dusPerToko = 20, string $kodeWilayah = 'W1'): array
{
    $wilayah = Wilayah::firstOrCreate(['kode' => $kodeWilayah], ['nama' => "Wilayah {$kodeWilayah}"]);

    $pesanans = [];
    $mulai = Toko::count();

    for ($i = 0; $i < $jumlah; $i++) {
        $sudut = $i / $jumlah * 2 * M_PI;

        $toko = Toko::create([
            'kode' => sprintf('TK-%04d', $mulai + $i + 1),
            'nama' => 'Toko '.($mulai + $i + 1),
            'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. Uji No. '.($i + 1),
            'latitude' => -6.20 + cos($sudut) * 0.03,
            'longitude' => 106.82 + sin($sudut) * 0.03,
            'sumber_koordinat' => 'manual',
        ]);

        $pesanan = test()->pesananService->buat(
            $toko,
            [['produk_id' => test()->produk->id, 'jumlah_dus' => $dusPerToko]],
            test()->sales,
        );

        test()->pesananService->setujui($pesanan, test()->admin);

        $pesanans[] = $pesanan->fresh();
    }

    return $pesanans;
}

/** Membentuk satu kendaraan berisi $jumlah toko, semuanya sampai berstatus SELESAI. */
function kendaraanSelesai(int $jumlah, int $dusPerToko = 20): Kendaraan
{
    Storage::fake('public');

    siapkanPesananPembayaran($jumlah, $dusPerToko);

    $batch = test()->routingService->generate(test()->admin);
    test()->routingService->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();

    foreach ($kendaraan->stops as $stop) {
        $path = UploadedFile::fake()->image("nota-{$stop->id}.jpg")->store('nota', 'public');
        test()->pesananService->selesaikanPengiriman($stop, $path, test()->driver);
    }

    return $kendaraan->fresh();
}

it('menolak menandai lunas atau belum lunas selain pesanan SELESAI', function () {
    $pesanan = $this->pesananService->buat(
        Toko::create([
            'kode' => 'TK-1', 'nama' => 'Toko 1', 'wilayah_id' => Wilayah::create(['kode' => 'W1', 'nama' => 'W1'])->id,
            'alamat' => 'Jl. Uji',
        ]),
        [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]],
        $this->sales,
    );

    expect($pesanan->status)->toBe(StatusPesanan::Order);

    expect(fn () => $this->pelunasanService->tandaiLunas($pesanan, $this->admin))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $this->pelunasanService->tandaiBelumLunas($pesanan, $this->admin))
        ->toThrow(RuntimeException::class);

    $this->pesananService->setujui($pesanan, $this->admin);
    expect(fn () => $this->pelunasanService->tandaiLunas($pesanan->fresh(), $this->admin))
        ->toThrow(RuntimeException::class);
});

it('lunasi sisa kendaraan tidak menimpa toko yang sudah ditandai belum lunas', function () {
    $kendaraan = kendaraanSelesai(10, dusPerToko: 10);

    $pesanans = $kendaraan->stops->map(fn ($s) => $s->pesanan()->first());
    $dipilihBelumLunas = $pesanans->first();

    $this->pelunasanService->tandaiBelumLunas($dipilihBelumLunas, $this->admin);

    $jumlah = $this->pelunasanService->lunasiSisaKendaraan($kendaraan, $this->admin);

    expect($jumlah)->toBe(9)
        ->and($dipilihBelumLunas->fresh()->status_bayar)->toBe(StatusBayar::BelumLunas);

    $sisanya = $pesanans->skip(1);
    expect($sisanya->every(fn (Pesanan $p) => $p->fresh()->status_bayar === StatusBayar::Lunas))->toBeTrue();
});

it('lunasiSisaKendaraan pada kendaraan tanpa pesanan yang layak mengembalikan nol', function () {
    $kendaraan = kendaraanSelesai(2, dusPerToko: 10);

    foreach ($kendaraan->stops as $stop) {
        $this->pelunasanService->tandaiLunas($stop->pesanan()->first(), $this->admin);
    }

    expect($this->pelunasanService->lunasiSisaKendaraan($kendaraan, $this->admin))->toBe(0);
});

it('pendapatan mengikuti tanggal pelunasan, bukan tanggal pengiriman', function () {
    $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
    $pesanan = $kendaraan->stops->first()->pesanan()->first();

    $this->pelunasanService->tandaiBelumLunas($pesanan, $this->admin);

    $tanggalAsli = $pesanan->fresh()->tanggal->toDateString();

    $this->travelTo(now()->addWeek());

    $this->pelunasanService->tandaiLunas($pesanan->fresh(), $this->admin);

    $segar = $pesanan->fresh();

    expect($segar->status_bayar)->toBe(StatusBayar::Lunas)
        ->and($segar->tanggal_lunas->toDateString())->toBe(today()->toDateString())
        ->and($segar->tanggal_lunas->toDateString())->not->toBe($tanggalAsli);
});

it('tagihan pesanan kurang kirim memakai jumlah yang benar-benar terkirim', function () {
    Storage::fake('public');

    siapkanPesananPembayaran(1, dusPerToko: 20);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $stop = $kendaraan->stops->first();
    $item = $stop->pesanan()->first()->items()->first();

    $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');
    $this->pengirimanService->coretNota($stop, [$item->id => 15], $path, $this->driver);

    $pesanan = $stop->pesanan()->with('items')->first();

    expect($pesanan->kurang_kirim)->toBeTrue()
        ->and($pesanan->tagihan)->toBe(15 * 50_000.0)
        ->and($pesanan->tagihan)->toBeLessThan((float) $pesanan->total_nilai);
});

it('tagihan pesanan normal sama persis dengan total_nilai', function () {
    $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
    $pesanan = $kendaraan->stops->first()->pesanan()->with('items')->first();

    expect($pesanan->kurang_kirim)->toBeFalse()
        ->and($pesanan->tagihan)->toBe((float) $pesanan->total_nilai);
});

it('menampilkan ketiga halaman pembayaran untuk admin', function () {
    kendaraanSelesai(6, dusPerToko: 10);

    $this->actingAs($this->admin)->get(route('pembayaran.pelunasan'))->assertOk();
    $this->actingAs($this->admin)->get(route('pembayaran.belum-lunas'))->assertOk();
    $this->actingAs($this->admin)->get(route('pembayaran.pendapatan'))->assertOk();
});

it('menolak sales dan driver mengakses menu pembayaran', function () {
    $this->actingAs($this->sales)->get(route('pembayaran.pelunasan'))->assertForbidden();
    $this->actingAs($this->driver)->get(route('pembayaran.pelunasan'))->assertForbidden();
});

it('bisa lunas semua lalu muncul di pendapatan hari ini lewat layar sungguhan', function () {
    $kendaraan = kendaraanSelesai(3, dusPerToko: 10);

    Livewire::actingAs($this->admin)
        ->test(Pelunasan::class)
        ->call('konfirmasiLunasSemua', $kendaraan->id)
        ->call('proses');

    expect(Pesanan::where('status_bayar', StatusBayar::Lunas)->count())->toBe(3);

    Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->assertSee(Bahasa::rupiah(3 * 10 * 50_000));
});

it('superadmin juga bisa memproses pelunasan, tidak ditolak oleh guard isAdmin', function () {
    $superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);
    $kendaraan = kendaraanSelesai(2, dusPerToko: 10);

    Livewire::actingAs($superadmin)
        ->test(Pelunasan::class)
        ->call('konfirmasiLunasSemua', $kendaraan->id)
        ->call('proses')
        ->assertHasNoErrors();

    expect(Pesanan::where('status_bayar', StatusBayar::Lunas)->count())->toBe(2);
});
