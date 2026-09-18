<?php

use App\Enums\LevelBahanBakar;
use App\Enums\PeranPengguna;
use App\Livewire\Monitoring\PenggunaanBahanBakar;
use App\Models\Depot;
use App\Models\Kendaraan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kendaraan\CatatanBbmService;
use App\Services\PesananService;
use App\Services\RoutingService;
use App\Support\DepotContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Monitoring > Penggunaan Bahan Bakar — lihat App\Livewire\Monitoring\PenggunaanBahanBakar. */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver Monitoring']);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 10_000]);
});

function gambarMonitoringBbm(): string
{
    $gambar = imagecreatetruecolor(100, 100);
    imagefilledrectangle($gambar, 0, 0, 100, 100, imagecolorallocate($gambar, 10, 20, 30));

    ob_start();
    imagejpeg($gambar, null, 80);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

/** Kendaraan siap dengan tanggal keberangkatan tertentu, dibawa test()->driver. */
function kendaraanMonitoringBbm(?string $tanggal = null): Kendaraan
{
    static $n = 0;
    $n++;

    $toko = Toko::create([
        'kode' => "TK-MB{$n}", 'nama' => "Toko Monitoring {$n}", 'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Monitoring', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $pesananService = app(PesananService::class);
    $pesanan = $pesananService->buat($toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => 10]], test()->sales);
    $pesananService->setujui($pesanan, test()->admin);

    $tgl = $tanggal !== null ? CarbonImmutable::parse($tanggal) : null;
    $batch = app(RoutingService::class)->generate(test()->admin, tanggalKeberangkatan: $tgl);
    app(RoutingService::class)->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id]);

    return $kendaraan->fresh();
}

it('hanya bisa dibuka admin', function () {
    $this->actingAs($this->sales)->get(route('monitoring.bahan-bakar'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('monitoring.bahan-bakar'))->assertOk();
});

it('menampilkan seluruh catatan bbm kendaraan pada tanggal keberangkatannya', function () {
    $kendaraan = kendaraanMonitoringBbm('2026-09-19');
    $service = app(CatatanBbmService::class);

    $service->berangkat($kendaraan, $this->driver, gambarMonitoringBbm(), 1000, LevelBahanBakar::Penuh);
    $service->pengisian($kendaraan, $this->driver, gambarMonitoringBbm(), liter: 10.0, biaya: 100_000);
    $kendaraan->update(['status' => 'selesai']);
    $service->kembali($kendaraan, $this->driver, gambarMonitoringBbm(), 1100, LevelBahanBakar::Setengah);

    $komponen = Livewire::actingAs($this->admin)
        ->test(PenggunaanBahanBakar::class, ['tanggal' => '2026-09-19']);

    expect($komponen->instance()->catatans)->toHaveCount(3);

    $ringkasan = $komponen->instance()->ringkasan;

    expect($ringkasan['berangkat'])->toBe(1)
        ->and($ringkasan['kembali'])->toBe(1)
        ->and($ringkasan['pengisian'])->toBe(1)
        ->and($ringkasan['liter'])->toBe(10.0)
        ->and($ringkasan['biaya'])->toBe(100_000.0);
});

it('tidak menampilkan catatan dari tanggal keberangkatan lain', function () {
    $kendaraanHariIni = kendaraanMonitoringBbm('2026-09-19');
    $kendaraanBesok = kendaraanMonitoringBbm('2026-09-20');
    $service = app(CatatanBbmService::class);

    $service->berangkat($kendaraanHariIni, $this->driver, gambarMonitoringBbm(), 100, LevelBahanBakar::Penuh);
    $service->berangkat($kendaraanBesok, $this->driver, gambarMonitoringBbm(), 200, LevelBahanBakar::Penuh);

    $komponen = Livewire::actingAs($this->admin)->test(PenggunaanBahanBakar::class, ['tanggal' => '2026-09-19']);

    expect($komponen->instance()->catatans)->toHaveCount(1)
        ->and($komponen->instance()->catatans->first()->kendaraan_id)->toBe($kendaraanHariIni->id);
});

it('filter driver dan jenis mempersempit daftar', function () {
    $driverLain = User::factory()->create(['role' => PeranPengguna::Driver]);
    $kendaraan = kendaraanMonitoringBbm('2026-09-19');
    $kendaraanLain = kendaraanMonitoringBbm('2026-09-19');
    $kendaraanLain->update(['driver_id' => $driverLain->id]);

    $service = app(CatatanBbmService::class);
    $service->berangkat($kendaraan, $this->driver, gambarMonitoringBbm(), 100, LevelBahanBakar::Penuh);
    $service->pengisian($kendaraan, $this->driver, gambarMonitoringBbm());
    $service->berangkat($kendaraanLain, $driverLain, gambarMonitoringBbm(), 100, LevelBahanBakar::Penuh);

    $komponen = Livewire::actingAs($this->admin)->test(PenggunaanBahanBakar::class, ['tanggal' => '2026-09-19']);

    expect($komponen->instance()->catatans)->toHaveCount(3);

    $komponen->set('filterDriver', (string) $this->driver->id);
    expect($komponen->instance()->catatans)->toHaveCount(2);

    $komponen->set('filterJenis', 'pengisian');
    expect($komponen->instance()->catatans)->toHaveCount(1);
});

it('isolasi antar gudang: catatan bbm gudang lain tidak ikut terlihat', function () {
    $kendaraan = kendaraanMonitoringBbm('2026-09-19');
    app(CatatanBbmService::class)->berangkat($kendaraan, $this->driver, gambarMonitoringBbm(), 100, LevelBahanBakar::Penuh);

    $depotLain = Depot::factory()->create(['kode' => 'DEPOTLAIN']);

    DepotContext::jalankanSebagai($depotLain, function () {
        $komponen = Livewire::actingAs($this->admin)->test(PenggunaanBahanBakar::class, ['tanggal' => '2026-09-19']);

        expect($komponen->instance()->catatans)->toHaveCount(0);
    });
});
