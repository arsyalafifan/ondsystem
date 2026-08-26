<?php

use App\Enums\PeranPengguna;
use App\Livewire\Routing\GenerateRouting;
use App\Models\Kendaraan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Admin bisa menetapkan/mengganti driver satu kendaraan dari layar Generate
 * Routing, bukan cuma menunggu driver "mengambil" mobilnya sendiri. Dua
 * aturan yang mengikatnya: driver yang dipilih harus akun berperan driver
 * dan sedang tidak membawa kendaraan aktif lain, dan begitu ada satu saja
 * kunjungan yang tuntas (selesai/dicoret/dibatalkan) di kendaraan itu,
 * drivernya terkunci — tidak boleh dialihkan diam-diam lagi.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Budi Driver']);
    $this->driverLain = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Andi Driver']);

    $this->routingService = app(RoutingService::class);
    $this->pengirimanService = app(PengirimanService::class);
});

/** Satu kendaraan berisi satu toko, sudah disetujui, driver_id masih kosong. */
function kendaraanUjiDriver(): Kendaraan
{
    static $n = 0;
    $n++;

    $wilayah = Wilayah::create(['kode' => "W-RD{$n}", 'nama' => "Wilayah RD {$n}"]);
    $produk = Produk::create(['kode' => "RD{$n}", 'nama' => "Produk RD {$n}", 'stok' => 1000, 'harga' => 50_000]);
    $toko = Toko::create([
        'kode' => "TK-RD{$n}", 'nama' => "Toko RD {$n}", 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. RD', 'latitude' => -6.20 + $n * 0.001, 'longitude' => 106.80 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);

    $pesananService = app(PesananService::class);
    $pesanan = $pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], test()->sales);
    $pesananService->setujui($pesanan, test()->admin);

    $batch = app(RoutingService::class)->generate(test()->admin);
    app(RoutingService::class)->setujui($batch, test()->admin);

    return $batch->fresh()->kendaraans->first();
}

describe('RoutingService::ubahDriver', function () {
    it('menetapkan driver pada kendaraan yang belum punya driver', function () {
        $kendaraan = kendaraanUjiDriver();

        $this->routingService->ubahDriver($kendaraan, $this->driver);

        $segar = $kendaraan->fresh();
        expect($segar->driver_id)->toBe($this->driver->id)
            ->and($segar->diambil_at)->toBeNull();
    });

    it('mengganti driver yang sudah ditetapkan sebelumnya', function () {
        $kendaraan = kendaraanUjiDriver();
        $this->routingService->ubahDriver($kendaraan, $this->driver);

        $this->routingService->ubahDriver($kendaraan->fresh(), $this->driverLain);

        expect($kendaraan->fresh()->driver_id)->toBe($this->driverLain->id);
    });

    it('bisa mengosongkan driver lagi (null)', function () {
        $kendaraan = kendaraanUjiDriver();
        $this->routingService->ubahDriver($kendaraan, $this->driver);

        $this->routingService->ubahDriver($kendaraan->fresh(), null);

        expect($kendaraan->fresh()->driver_id)->toBeNull();
    });

    it('menolak akun yang bukan berperan driver', function () {
        $kendaraan = kendaraanUjiDriver();

        expect(fn () => $this->routingService->ubahDriver($kendaraan, $this->admin))
            ->toThrow(RuntimeException::class);

        expect($kendaraan->fresh()->driver_id)->toBeNull();
    });

    it('menolak driver yang sedang membawa kendaraan aktif lain', function () {
        $kendaraan1 = kendaraanUjiDriver();
        $kendaraan2 = kendaraanUjiDriver();

        $this->routingService->ubahDriver($kendaraan1, $this->driver);

        expect(fn () => $this->routingService->ubahDriver($kendaraan2, $this->driver))
            ->toThrow(RuntimeException::class);

        expect($kendaraan2->fresh()->driver_id)->toBeNull();
    });

    it('mengizinkan driver yang mobil sebelumnya sudah berstatus selesai', function () {
        $kendaraan1 = kendaraanUjiDriver();
        $kendaraan2 = kendaraanUjiDriver();

        $this->routingService->ubahDriver($kendaraan1, $this->driver);
        $kendaraan1->update(['status' => 'selesai']);

        $this->routingService->ubahDriver($kendaraan2, $this->driver);

        expect($kendaraan2->fresh()->driver_id)->toBe($this->driver->id);
    });

    it('boleh diganti bebas selama batch masih draft, bukan hanya setelah disetujui', function () {
        $wilayah = Wilayah::create(['kode' => 'W-RDD', 'nama' => 'Wilayah Draft']);
        $produk = Produk::create(['kode' => 'RDD1', 'nama' => 'Produk Draft', 'stok' => 1000, 'harga' => 50_000]);
        $toko = Toko::create([
            'kode' => 'TK-RDD1', 'nama' => 'Toko Draft', 'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. Draft', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        ]);
        $pesananService = app(PesananService::class);
        $pesanan = $pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $this->sales);
        $pesananService->setujui($pesanan, $this->admin);
        $batch = $this->routingService->generate($this->admin);
        $kendaraan = $batch->fresh()->kendaraans->first();

        expect($batch->isDraft())->toBeTrue();

        $this->routingService->ubahDriver($kendaraan, $this->driver);

        expect($kendaraan->fresh()->driver_id)->toBe($this->driver->id);
    });

    it('menolak mengganti driver setelah ada kunjungan yang selesai (upload nota)', function () {
        Storage::fake('public');

        $kendaraan = kendaraanUjiDriver();
        $this->routingService->ubahDriver($kendaraan, $this->driver);

        $stop = $kendaraan->fresh()->stops->first();
        $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');
        app(PesananService::class)->selesaikanPengiriman($stop, $path, $this->driver);

        expect(fn () => $this->routingService->ubahDriver($kendaraan->fresh(), $this->driverLain))
            ->toThrow(RuntimeException::class);

        expect($kendaraan->fresh()->driver_id)->toBe($this->driver->id);
    });

    it('menolak mengganti driver setelah ada kunjungan yang dibatalkan di lapangan', function () {
        $kendaraan = kendaraanUjiDriver();
        $this->routingService->ubahDriver($kendaraan, $this->driver);

        $stop = $kendaraan->fresh()->stops->first();
        $this->pengirimanService->batalkanDiLapangan($stop, $this->driver, 'Toko tutup');

        expect(fn () => $this->routingService->ubahDriver($kendaraan->fresh(), $this->driverLain))
            ->toThrow(RuntimeException::class);
    });

    it('menolak mengganti driver setelah nota dicoret', function () {
        Storage::fake('public');

        $kendaraan = kendaraanUjiDriver();
        $this->routingService->ubahDriver($kendaraan, $this->driver);

        $stop = $kendaraan->fresh()->stops->first();
        $item = $stop->pesanan->items->first();
        $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');
        $this->pengirimanService->coretNota($stop, [$item->id => 5], $path, $this->driver);

        expect(fn () => $this->routingService->ubahDriver($kendaraan->fresh(), $this->driverLain))
            ->toThrow(RuntimeException::class);
    });
});

describe('layar Generate Routing', function () {
    it('menampilkan pilihan driver dan menyimpannya lewat komponen', function () {
        $kendaraan = kendaraanUjiDriver();

        Livewire::actingAs($this->admin)
            ->test(GenerateRouting::class, ['batch' => $kendaraan->batch])
            ->assertSet('driverBisaDiubah.'.$kendaraan->id, true)
            ->call('ubahDriver', $kendaraan->id, $this->driver->id)
            ->assertHasNoErrors();

        expect($kendaraan->fresh()->driver_id)->toBe($this->driver->id);
    });

    it('menampilkan notifikasi galat tanpa memutus halaman kalau penetapan ditolak', function () {
        $kendaraan1 = kendaraanUjiDriver();
        $kendaraan2 = kendaraanUjiDriver();
        $this->routingService->ubahDriver($kendaraan1, $this->driver);

        Livewire::actingAs($this->admin)
            ->test(GenerateRouting::class, ['batch' => $kendaraan2->batch])
            ->call('ubahDriver', $kendaraan2->id, $this->driver->id)
            ->assertDispatched('notifikasi');

        expect($kendaraan2->fresh()->driver_id)->toBeNull();
    });

    it('menandai driverBisaDiubah false begitu kendaraan sudah mulai dikerjakan', function () {
        Storage::fake('public');

        $kendaraan = kendaraanUjiDriver();
        $this->routingService->ubahDriver($kendaraan, $this->driver);

        $stop = $kendaraan->fresh()->stops->first();
        $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');
        app(PesananService::class)->selesaikanPengiriman($stop, $path, $this->driver);

        Livewire::actingAs($this->admin)
            ->test(GenerateRouting::class, ['batch' => $kendaraan->fresh()->batch])
            ->assertSet('driverBisaDiubah.'.$kendaraan->id, false);
    });
});
