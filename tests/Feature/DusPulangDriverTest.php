<?php

use App\Enums\JenisMutasiStok;
use App\Enums\PeranPengguna;
use App\Livewire\Statistik\DusPulangDriver;
use App\Models\Kendaraan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\RoutingBatch;
use App\Models\StokMutasi;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Dus Pulang Driver: berapa dus yang tersisa di mobil dan dikembalikan ke
 * gudang saat admin menekan "Selesaikan Kendaraan" (`PengirimanService::
 * selesaikanKendaraan()`), direkap per driver. Sumbernya baris StokMutasi
 * bertipe `release` yang punya `kendaraan_id` terisi — penanda PERSIS yang
 * membedakannya dari baris `release` lain yang lahir dari pembatalan
 * pesanan biasa (`kendaraan_id` null, `pesanan_id` terisi).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1_000, 'harga' => 10_000]);
});

/** Kendaraan dibuat langsung lewat Eloquent — murni menguji agregasi DusPulangDriver. */
function buatKendaraanDpd(?User $driver = null, ?CarbonImmutable $tanggal = null): Kendaraan
{
    static $n = 0;
    $n++;

    $batch = RoutingBatch::create([
        'kode' => sprintf('RB-DPD%04d', $n),
        'tanggal' => ($tanggal ?? today())->toDateString(),
        'status' => 'disetujui',
        'dibuat_oleh' => test()->admin->id,
    ]);

    return Kendaraan::create([
        'routing_batch_id' => $batch->id,
        'nomor' => 1,
        'nama' => "Mobil DPD {$n}",
        'driver_id' => $driver?->id,
        'status' => 'selesai',
        'tanggal' => ($tanggal ?? today())->toDateString(),
    ]);
}

/** Baris StokMutasi 'release' berasal dari selesaikanKendaraan() — penanda kendaraan_id. */
function buatMutasiSelesaikan(Kendaraan $kendaraan, int $jumlah): StokMutasi
{
    return StokMutasi::create([
        'produk_id' => test()->produk->id,
        'kendaraan_id' => $kendaraan->id,
        'tipe' => JenisMutasiStok::Release,
        'jumlah' => $jumlah,
        'stok_sesudah' => 0,
        'reserved_sesudah' => 0,
        'user_id' => test()->admin->id,
    ]);
}

/** Baris StokMutasi 'release' berasal dari pembatalan pesanan — penanda pesanan_id, TIDAK terkait driver. */
function buatMutasiBatalPesanan(int $jumlah): StokMutasi
{
    $toko = Toko::create([
        'kode' => 'TK-DPDBTL', 'nama' => 'Toko Batal', 'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Batal', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $pesanan = Pesanan::create([
        'kode' => 'PSN-DPDBTL', 'toko_id' => $toko->id, 'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => test()->admin->id, 'status' => 'cancel', 'jenis' => 'normal',
        'tanggal' => today(), 'total_dus' => $jumlah, 'total_nilai' => $jumlah * 10_000,
    ]);

    return StokMutasi::create([
        'produk_id' => test()->produk->id,
        'pesanan_id' => $pesanan->id,
        'tipe' => JenisMutasiStok::Release,
        'jumlah' => $jumlah,
        'stok_sesudah' => 0,
        'reserved_sesudah' => 0,
        'user_id' => test()->admin->id,
    ]);
}

it('menolak akses selain admin', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($sales)->get(route('statistik.dus-pulang-driver'))->assertForbidden();
});

it('mode default adalah bulanan', function () {
    Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->assertSet('mode', 'bulan');
});

it('hanya menghitung release yang bertanda kendaraan_id, bukan release dari pembatalan pesanan', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver A']);
    $kendaraan = buatKendaraanDpd($driver);

    buatMutasiSelesaikan($kendaraan, 8);
    buatMutasiBatalPesanan(99); // tidak berkaitan dengan driver mana pun, harus dikecualikan

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['nama'])->toBe('Driver A')
        ->and($perDriver[0]['total_dus'])->toBe(8);
});

it('mengelompokkan dan menjumlahkan per driver dengan benar', function () {
    $driverA = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver A']);
    $driverB = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver B']);

    $kendaraanA1 = buatKendaraanDpd($driverA);
    $kendaraanA2 = buatKendaraanDpd($driverA);
    $kendaraanB1 = buatKendaraanDpd($driverB);

    buatMutasiSelesaikan($kendaraanA1, 5);
    buatMutasiSelesaikan($kendaraanA2, 7);
    buatMutasiSelesaikan($kendaraanB1, 20);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(2)
        ->and($perDriver[0]['nama'])->toBe('Driver B')
        ->and($perDriver[0]['total_dus'])->toBe(20)
        ->and($perDriver[0]['total_kendaraan'])->toBe(1)
        ->and($perDriver[1]['nama'])->toBe('Driver A')
        ->and($perDriver[1]['total_dus'])->toBe(12)
        ->and($perDriver[1]['total_kendaraan'])->toBe(2);
});

it('mengecualikan kendaraan tanpa driver', function () {
    $kendaraanTanpaDriver = buatKendaraanDpd(driver: null);
    buatMutasiSelesaikan($kendaraanTanpaDriver, 15);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver)->toBeEmpty();
});

it('menyaring lewat Kendaraan::tanggal pada mode hari', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $tanggal = CarbonImmutable::parse('2026-08-20');
    $kendaraanCocok = buatKendaraanDpd($driver, $tanggal);
    $kendaraanLain = buatKendaraanDpd($driver, $tanggal->addDay());

    buatMutasiSelesaikan($kendaraanCocok, 10);
    buatMutasiSelesaikan($kendaraanLain, 99);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'hari')
        ->set('tanggal', $tanggal->toDateString())
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['total_dus'])->toBe(10);
});

it('mode rentang mencakup tanggal di dalam batas, mengecualikan yang di luar', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $kendaraanDalam = buatKendaraanDpd($driver, CarbonImmutable::parse('2026-08-20'));
    $kendaraanLuar = buatKendaraanDpd($driver, CarbonImmutable::parse('2026-08-25'));

    buatMutasiSelesaikan($kendaraanDalam, 10);
    buatMutasiSelesaikan($kendaraanLuar, 20);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'rentang')
        ->set('dariTanggal', '2026-08-19')
        ->set('sampaiTanggal', '2026-08-21')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['total_dus'])->toBe(10);
});

it('mode bulan hanya mencakup kendaraan pada bulan yang dipilih', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $kendaraanDalam = buatKendaraanDpd($driver, CarbonImmutable::parse('2026-08-15'));
    $kendaraanLuar = buatKendaraanDpd($driver, CarbonImmutable::parse('2026-09-01'));

    buatMutasiSelesaikan($kendaraanDalam, 6);
    buatMutasiSelesaikan($kendaraanLuar, 9);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'bulan')
        ->set('bulan', '2026-08')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['total_dus'])->toBe(6);
});

it('terurut dari total dus terbanyak', function () {
    $driverKecil = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver Kecil']);
    $driverBesar = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver Besar']);

    buatMutasiSelesaikan(buatKendaraanDpd($driverKecil), 4);
    buatMutasiSelesaikan(buatKendaraanDpd($driverBesar), 40);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver[0]['nama'])->toBe('Driver Besar')
        ->and($perDriver[1]['nama'])->toBe('Driver Kecil');
});

it('total dus keseluruhan menjumlahkan semua driver', function () {
    $driverA = User::factory()->create(['role' => PeranPengguna::Driver]);
    $driverB = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatMutasiSelesaikan(buatKendaraanDpd($driverA), 10);
    buatMutasiSelesaikan(buatKendaraanDpd($driverB), 15);

    $total = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'semua')
        ->instance()->totalDusKeseluruhan();

    expect($total)->toBe(25);
});

it('alur nyata: selesaikanKendaraan() sungguhan tercermin di rekap Dus Pulang Driver', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $driver = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver Nyata']);

    $tokoJalan = Toko::create([
        'kode' => 'TK-DPD-1', 'nama' => 'Toko Jalan', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Jalan', 'latitude' => -6.20, 'longitude' => 106.82, 'sumber_koordinat' => 'manual',
    ]);
    $tokoBatal = Toko::create([
        'kode' => 'TK-DPD-2', 'nama' => 'Toko Batal Beneran', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Batal Beneran', 'latitude' => -6.21, 'longitude' => 106.83, 'sumber_koordinat' => 'manual',
    ]);

    $pesananService = app(PesananService::class);
    $routingService = app(RoutingService::class);
    $pengirimanService = app(PengirimanService::class);

    $pesananJalan = $pesananService->buat($tokoJalan, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $sales);
    $pesananService->setujui($pesananJalan, $this->admin);

    $pesananBatal = $pesananService->buat($tokoBatal, [['produk_id' => $this->produk->id, 'jumlah_dus' => 6]], $sales);
    $pesananService->setujui($pesananBatal, $this->admin);

    $batch = $routingService->generate($this->admin);
    $routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => $driver->id]);
    $kendaraan = $kendaraan->fresh(['stops.pesanan']);

    $stopBatal = $kendaraan->stops->first(fn ($s) => $s->pesanan->toko_id === $tokoBatal->id);

    $pengirimanService->batalkanDiLapangan($stopBatal, $driver, 'Toko tutup');
    $pengirimanService->selesaikanKendaraan($kendaraan->fresh(['stops']), $this->admin);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusPulangDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['nama'])->toBe('Driver Nyata')
        ->and($perDriver[0]['total_dus'])->toBe(6);
});
