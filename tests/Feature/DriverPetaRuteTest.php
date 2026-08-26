<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusStop;
use App\Livewire\Driver\DaftarKunjungan;
use App\Livewire\Routing\GenerateRouting;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Livewire\Livewire;

/**
 * Peta rute di layar driver: satu kendaraan saja (bukan banyak seperti
 * halaman admin), penanda diwarnai menurut status kunjungan. Bug nyata yang
 * ditemukan sambil membangun ini ada di App\Enums\StatusStop dan di
 * App\Livewire\Routing\GenerateRouting — lihat tes di bawah yang menguncinya.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 10_000]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
});

function buatTokoPeta(string $nama): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-PR%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Peta Rute No. '.$n,
        'latitude' => -6.20 + $n * 0.001,
        'longitude' => 106.82 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);
}

/** @param  array<int, string>  $namaToko */
function siapkanKendaraanPeta(array $namaToko): Kendaraan
{
    foreach ($namaToko as $nama) {
        $toko = buatTokoPeta($nama);
        $pesanan = test()->pesananService->buat(
            $toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => 10]], test()->sales,
        );
        test()->pesananService->setujui($pesanan, test()->admin);
    }

    $batch = test()->routingService->generate(test()->admin);
    test()->routingService->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id]);

    return $kendaraan->fresh(['stops.toko']);
}

it('menampilkan kartu peta rute di layar driver', function () {
    $kendaraan = siapkanKendaraanPeta(['Toko Peta Satu']);

    Livewire::actingAs($this->driver)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->assertOk()
        ->assertSee(__('driver.peta_rute'));
});

it('dataPeta hanya berisi satu kendaraan, bukan seluruh armada', function () {
    $kendaraan = siapkanKendaraanPeta(['Toko Peta Satu', 'Toko Peta Dua']);

    $data = Livewire::actingAs($this->driver)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->instance()->dataPeta();

    expect($data['kendaraan'])->toHaveCount(1)
        ->and($data['kendaraan'][0]['id'])->toBe($kendaraan->id)
        ->and($data['kendaraan'][0]['stops'])->toHaveCount(2);
});

it('menandai kunjungan yang sudah terkirim dengan centang dan warna hijau', function () {
    $kendaraan = siapkanKendaraanPeta(['Toko Peta Satu']);
    $stop = $kendaraan->stops->first();

    $this->pesananService->selesaikanPengiriman($stop, 'nota/uji.jpg', $this->driver);

    $data = Livewire::actingAs($this->driver)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
        ->instance()->dataPeta();

    $baris = $data['kendaraan'][0]['stops'][0];

    expect($baris['selesai'])->toBeTrue()
        ->and($baris['warnaStatus'])->toBe(StatusStop::Selesai->warna());
});

/**
 * Toko yang dibatalkan di lapangan tuntas secara tanggung jawab tapi TIDAK
 * pernah terkirim — peta tidak boleh menampilkannya seolah sukses (centang),
 * meski warnanya sendiri (merah) tetap membedakannya dari yang masih pending.
 */
it('tidak menandai centang untuk kunjungan yang dibatalkan, tapi warnanya tetap beda', function () {
    $kendaraan = siapkanKendaraanPeta(['Toko Peta Satu']);
    $stop = $kendaraan->stops->first();

    app(PengirimanService::class)->batalkanDiLapangan($stop, $this->driver, 'Toko tutup');

    $data = Livewire::actingAs($this->driver)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
        ->instance()->dataPeta();

    $baris = $data['kendaraan'][0]['stops'][0];

    expect($baris['selesai'])->toBeFalse()
        ->and($baris['warnaStatus'])->toBe(StatusStop::Dibatalkan->warna());
});

it('kunjungan yang masih pending berwarna abu-abu', function () {
    $kendaraan = siapkanKendaraanPeta(['Toko Peta Satu']);

    $data = Livewire::actingAs($this->driver)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->instance()->dataPeta();

    expect($data['kendaraan'][0]['stops'][0]['warnaStatus'])->toBe(StatusStop::Pending->warna());
});

it('konfigPeta tidak membocorkan data kendaraan lain', function () {
    $kendaraan = siapkanKendaraanPeta(['Toko Peta Satu']);

    $config = Livewire::actingAs($this->driver)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->instance()->konfigPeta();

    expect($config)->toHaveKeys(['tileUrl', 'attribution', 'zoom', 'depot', 'bisaDiklik']);
});

/**
 * Bug nyata yang ditemukan saat membangun peta driver: KendaraanStop::status
 * di-cast ke enum StatusStop, tapi dataPeta() GenerateRouting membandingkannya
 * dengan string 'selesai' — perbandingan enum dengan string selalu salah di
 * PHP (===), jadi penanda centang di peta admin TIDAK PERNAH muncul walau
 * kunjungannya sungguh selesai. Diperbaiki jadi perbandingan dengan
 * StatusStop::Selesai; tes ini mengunci perbaikannya.
 */
it('halaman admin juga menandai kunjungan selesai dengan benar di peta (regresi)', function () {
    $kendaraan = siapkanKendaraanPeta(['Toko Peta Satu']);
    $stop = $kendaraan->stops->first();

    $this->pesananService->selesaikanPengiriman($stop, 'nota/uji.jpg', $this->driver);

    $data = Livewire::actingAs($this->admin)
        ->test(GenerateRouting::class, ['batch' => $kendaraan->fresh()->batch])
        ->instance()->dataPeta();

    $barisKendaraan = collect($data['kendaraan'])->firstWhere('id', $kendaraan->id);
    $baris = collect($barisKendaraan['stops'])->first();

    expect($baris['selesai'])->toBeTrue()
        ->and($baris['warnaStatus'])->toBe(StatusStop::Selesai->warna());
});
