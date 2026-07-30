<?php

use App\Services\Peta\Koordinat;
use App\Services\Peta\MatriksJarak;
use App\Services\Peta\OsrmClient;
use App\Services\Routing\MesinRouting;
use App\Services\Routing\PengelompokKendaraan;
use App\Services\Routing\PengurutKunjungan;
use App\Services\Routing\RuteKendaraan;
use App\Services\Routing\TitikPengiriman;

/**
 * Mesin routing diuji tanpa OSRM supaya hasilnya tetap sama setiap kali
 * dijalankan dan tidak bergantung pada layanan luar.
 */
beforeEach(function () {
    config(['ond.osrm.enabled' => false]);
});

function depot(): Koordinat
{
    return new Koordinat(-6.200000, 106.816666);
}

function mesin(): MesinRouting
{
    return new MesinRouting(OsrmClient::fromConfig(), new PengelompokKendaraan, new PengurutKunjungan);
}

/**
 * @param  array<int, array{dus: int, lat: float, lng: float, wilayah?: int}>  $spesifikasi
 * @return array<int, TitikPengiriman>
 */
function titikDari(array $spesifikasi): array
{
    return array_map(fn (array $s, int $i) => new TitikPengiriman(
        pesananId: $i + 1,
        tokoId: $i + 1,
        wilayahId: $s['wilayah'] ?? 1,
        namaToko: 'Toko '.($i + 1),
        alamat: 'Alamat '.($i + 1),
        dus: $s['dus'],
        koordinat: new Koordinat($s['lat'], $s['lng']),
    ), $spesifikasi, array_keys($spesifikasi));
}

/**
 * Sebaran toko yang bisa diulang persis, mengelilingi sebuah titik pusat.
 *
 * @return array<int, array{dus: int, lat: float, lng: float, wilayah: int}>
 */
function sebaran(int $jumlah, float $lat, float $lng, int $wilayah, int $benih = 1): array
{
    mt_srand($benih);

    return array_map(fn () => [
        'dus' => mt_rand(5, 40),
        'lat' => $lat + mt_rand(-450, 450) / 10000,
        'lng' => $lng + mt_rand(-450, 450) / 10000,
        'wilayah' => $wilayah,
    ], range(1, $jumlah));
}

it('memasukkan setiap pesanan tepat satu kali ke dalam rute', function () {
    $titik = titikDari(sebaran(60, -6.17, 106.82, 1));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);

    $pesananIds = collect($hasil->rute)
        ->flatMap(fn (RuteKendaraan $r) => array_map(fn (TitikPengiriman $t) => $t->pesananId, $r->titik));

    expect($pesananIds)->toHaveCount(60)
        ->and($pesananIds->unique())->toHaveCount(60)
        ->and($hasil->totalToko())->toBe(60);
});

it('tidak pernah melewati batas 25 toko maupun 220 dus per kendaraan', function () {
    $titik = titikDari(sebaran(120, -6.17, 106.82, 1, benih: 7));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);

    foreach ($hasil->rute as $rute) {
        expect($rute->totalToko())->toBeLessThanOrEqual(25)
            ->and($rute->totalDus())->toBeLessThanOrEqual(220);
    }
});

it('menghormati batas yang diubah admin', function () {
    $titik = titikDari(sebaran(40, -6.17, 106.82, 1, benih: 3));

    $hasil = mesin()->susun($titik, depot(), maxToko: 10, maxDus: 100);

    foreach ($hasil->rute as $rute) {
        expect($rute->totalToko())->toBeLessThanOrEqual(10)
            ->and($rute->totalDus())->toBeLessThanOrEqual(100);
    }
});

it('tidak mencampur dua wilayah dalam satu kendaraan', function () {
    $titik = titikDari(array_merge(
        sebaran(20, -6.17, 106.82, wilayah: 1, benih: 11),
        sebaran(20, -6.26, 106.78, wilayah: 2, benih: 12),
        sebaran(20, -6.15, 106.90, wilayah: 3, benih: 13),
    ));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);

    foreach ($hasil->rute as $rute) {
        $wilayah = collect($rute->titik)->pluck('wilayahId')->unique();

        expect($wilayah)->toHaveCount(1)
            ->and($rute->wilayahId)->toBe($wilayah->first());
    }
});

it('menggabungkan wilayah ketika pemisahan dimatikan', function () {
    $titik = titikDari(array_merge(
        sebaran(6, -6.20, 106.81, wilayah: 1, benih: 21),
        sebaran(6, -6.20, 106.82, wilayah: 2, benih: 22),
    ));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 500, pisahPerWilayah: false);

    expect($hasil->rute)->toHaveCount(1)
        ->and($hasil->rute[0]->totalToko())->toBe(12);
});

it('memakai jumlah kendaraan seminimal mungkin saat muatan pas', function () {
    // 30 toko x 10 dus = 300 dus. Batas dus memaksa minimal 2 mobil,
    // batas toko juga memaksa minimal 2. Tidak boleh keluar 3.
    $titik = titikDari(array_map(fn (int $i) => [
        'dus' => 10,
        'lat' => -6.20 + cos($i / 30 * 2 * M_PI) * 0.03,
        'lng' => 106.82 + sin($i / 30 * 2 * M_PI) * 0.03,
    ], range(1, 30)));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);

    expect($hasil->rute)->toHaveCount(2);
});

it('membagi muatan secara sebanding, bukan menyisakan mobil hampir kosong', function () {
    $titik = titikDari(sebaran(88, -6.17, 106.82, 1, benih: 42));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);

    $jumlahToko = array_map(fn (RuteKendaraan $r) => $r->totalToko(), $hasil->rute);
    $rataRata = array_sum($jumlahToko) / count($jumlahToko);

    // Mobil paling sepi tetap harus mengangkut setidaknya separuh rata-rata.
    // Inilah yang membedakan pembagian merata dari sweep yang serakah.
    expect(min($jumlahToko))->toBeGreaterThanOrEqual($rataRata / 2);
});

it('memisahkan pesanan yang lebih besar dari kapasitas satu mobil dan memperingatkan admin', function () {
    $titik = titikDari([
        ['dus' => 300, 'lat' => -6.18, 'lng' => 106.83],
        ['dus' => 20, 'lat' => -6.19, 'lng' => 106.84],
        ['dus' => 15, 'lat' => -6.21, 'lng' => 106.85],
    ]);

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);

    expect($hasil->peringatan)->not->toBeEmpty()
        ->and($hasil->totalToko())->toBe(3);

    $besar = collect($hasil->rute)->first(fn (RuteKendaraan $r) => $r->totalDus() === 300);

    expect($besar)->not->toBeNull()
        ->and($besar->totalToko())->toBe(1);
});

it('memberi tahu ketika tidak ada pesanan untuk dirutekan', function () {
    $hasil = mesin()->susun([], depot(), maxToko: 25, maxDus: 220);

    expect($hasil->rute)->toBeEmpty()
        ->and($hasil->peringatan)->not->toBeEmpty();
});

it('menyusun urutan kunjungan lebih pendek daripada urutan sembarang', function () {
    $titik = titikDari(sebaran(18, -6.17, 106.82, 1, benih: 99));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 10_000);

    expect($hasil->rute)->toHaveCount(1);

    $rute = $hasil->rute[0];
    $pengurut = new PengurutKunjungan;

    $koordinat = array_merge([depot()], array_map(fn (TitikPengiriman $t) => $t->koordinat, $rute->titik));
    $matriks = MatriksJarak::dariHaversine($koordinat);

    $durasiHasil = $pengurut->totalDurasi($matriks, range(1, count($rute->titik)));

    // Dibandingkan dengan rata-rata beberapa urutan acak, bukan satu, supaya
    // tes tidak bergantung pada keberuntungan satu pengacakan.
    mt_srand(5);
    $totalAcak = 0.0;

    for ($i = 0; $i < 20; $i++) {
        $acak = range(1, count($rute->titik));
        shuffle($acak);
        $totalAcak += $pengurut->totalDurasi($matriks, $acak);
    }

    expect($durasiHasil)->toBeLessThan($totalAcak / 20 * 0.75);
});

it('mengawali dan mengakhiri setiap rute di depot', function () {
    $titik = titikDari(sebaran(12, -6.17, 106.82, 1, benih: 77));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 10_000);
    $rute = $hasil->rute[0];

    // Jumlah ruas selalu satu lebih banyak dari jumlah toko: berangkat dari
    // depot, antar toko, lalu pulang.
    expect($rute->legs)->toHaveCount($rute->totalToko() + 1);
});

it('tetap menghasilkan rute ketika OSRM tidak bisa dihubungi', function () {
    $titik = titikDari(sebaran(10, -6.17, 106.82, 1, benih: 8));

    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);

    expect($hasil->sumberJarak)->toBe('haversine')
        ->and($hasil->totalToko())->toBe(10)
        ->and($hasil->rute[0]->geometry)->not->toBeEmpty()
        ->and($hasil->rute[0]->totalJarakM)->toBeGreaterThan(0);
});

it('menyelesaikan perhitungan besar dengan cepat', function () {
    $titik = titikDari(array_merge(
        sebaran(100, -6.17, 106.82, wilayah: 1, benih: 31),
        sebaran(100, -6.26, 106.78, wilayah: 2, benih: 32),
        sebaran(100, -6.15, 106.90, wilayah: 3, benih: 33),
    ));

    $mulai = microtime(true);
    $hasil = mesin()->susun($titik, depot(), maxToko: 25, maxDus: 220);
    $detik = microtime(true) - $mulai;

    expect($hasil->totalToko())->toBe(300)
        ->and($detik)->toBeLessThan(5.0);
});
