<?php

use App\Enums\PeranPengguna;
use App\Models\Kendaraan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use App\Services\Peta\OsrmClient;
use App\Services\RoutingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Perintah pembersih data lama untuk kendaraan yang garis rutenya sudah
 * tidak lagi melewati toko-tokonya — sisa dari sebelum koreksi koordinat
 * toko otomatis memicu hitung ulang.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 10_000, 'harga' => 10_000]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
});

/** Membuat satu kendaraan siap jalan berisi beberapa toko berkoordinat. */
function siapkanKendaraanGeometry(int $jumlahToko = 3): Kendaraan
{
    static $n = 0;

    for ($i = 0; $i < $jumlahToko; $i++) {
        $n++;

        $toko = Toko::create([
            'kode' => sprintf('TK-GM%04d', $n),
            'nama' => "Toko Geometry {$n}",
            'wilayah_id' => test()->wilayah->id,
            'alamat' => "Jl. Geometry No. {$n}",
            'latitude' => -6.20 + $i * 0.01,
            'longitude' => 106.82 + $i * 0.01,
            'sumber_koordinat' => 'manual',
        ]);

        $pesanan = test()->pesananService->buat(
            $toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => 10]], test()->sales,
        );
        test()->pesananService->setujui($pesanan, test()->admin);
    }

    $batch = test()->routingService->generate(test()->admin);
    test()->routingService->setujui($batch, test()->admin);

    return $batch->fresh()->kendaraans->first()->fresh(['stops.toko']);
}

/**
 * Beberapa kendaraan sekaligus — satu per wilayah, karena mesin routing
 * tidak pernah menggabungkan dua wilayah ke dalam satu mobil.
 *
 * @return Collection<int, Kendaraan>
 */
function siapkanBeberapaKendaraanGeometry(int $jumlahKendaraan): Collection
{
    for ($m = 0; $m < $jumlahKendaraan; $m++) {
        $wilayah = Wilayah::create(['kode' => "WG{$m}", 'nama' => "Wilayah Geometry {$m}"]);

        for ($i = 0; $i < 3; $i++) {
            $toko = Toko::create([
                'kode' => sprintf('TK-GB%02d%02d', $m, $i),
                'nama' => "Toko Geometry {$m}-{$i}",
                'wilayah_id' => $wilayah->id,
                'alamat' => "Jl. Geometry {$m} No. {$i}",
                'latitude' => -6.20 + $m * 0.02 + $i * 0.01,
                'longitude' => 106.82 + $m * 0.02 + $i * 0.01,
                'sumber_koordinat' => 'manual',
            ]);

            $pesanan = test()->pesananService->buat(
                $toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => 10]], test()->sales,
            );
            test()->pesananService->setujui($pesanan, test()->admin);
        }
    }

    $batch = test()->routingService->generate(test()->admin);
    test()->routingService->setujui($batch, test()->admin);

    return $batch->fresh()->kendaraans()->with('stops.toko')->get();
}

it('decodePolyline membaca balik apa yang ditulis encodePolyline', function () {
    $asli = [
        new Koordinat(-6.2088, 106.8456),
        new Koordinat(-6.1751, 106.8650),
        new Koordinat(-6.2297, 106.8295),
    ];

    $hasil = Geo::decodePolyline(Geo::encodePolyline($asli));

    expect($hasil)->toHaveCount(3);

    foreach ($asli as $i => $titik) {
        expect($hasil[$i]->lat)->toBe($titik->lat)
            ->and($hasil[$i]->lng)->toBe($titik->lng);
    }
});

it('membiarkan kendaraan yang garis rutenya masih cocok dengan tokonya', function () {
    $kendaraan = siapkanKendaraanGeometry();
    $geometrySebelum = $kendaraan->geometry;

    $this->artisan('rute:perbaiki-geometry')
        ->expectsOutputToContain('Tidak ada yang perlu diperbaiki')
        ->assertSuccessful();

    expect($kendaraan->fresh()->geometry)->toBe($geometrySebelum);
});

/**
 * Sengaja memakai BEBERAPA kendaraan, bukan satu. Laravel 13 diam-diam
 * memuat relasi yang kurang (autoload) ketika modelnya cuma satu, jadi
 * pelanggaran mode ketat — seperti $kendaraan->batch yang belum diambil di
 * depan — baru kelihatan begitu koleksinya berisi lebih dari satu model.
 * Versi satu-kendaraan dari tes ini lolos padahal perintahnya gagal di
 * pemakaian nyata.
 */
it('memperbaiki semua kendaraan yang garis rutenya sudah tidak melewati tokonya', function () {
    $kendaraans = siapkanBeberapaKendaraanGeometry(3);

    // Koordinat toko digeser jauh LANGSUNG di basis data, meniru data lama
    // yang koordinatnya sudah dikoreksi tanpa rutenya ikut dihitung ulang.
    foreach ($kendaraans as $kendaraan) {
        foreach ($kendaraan->stops as $stop) {
            Toko::where('id', $stop->toko_id)->update([
                'latitude' => (float) $stop->toko->latitude - 0.25,
                'longitude' => (float) $stop->toko->longitude + 0.25,
            ]);
        }
    }

    $geometrySebelum = $kendaraans->pluck('geometry', 'id');

    $this->artisan('rute:perbaiki-geometry')->assertSuccessful();

    expect($kendaraans)->toHaveCount(3);

    foreach ($kendaraans as $kendaraan) {
        expect($kendaraan->fresh()->geometry)->not->toBe($geometrySebelum[$kendaraan->id]);
    }
});

it('--dry-run melaporkan tanpa mengubah apa pun', function () {
    $kendaraan = siapkanKendaraanGeometry();

    foreach ($kendaraan->stops as $stop) {
        Toko::where('id', $stop->toko_id)->update(['latitude' => -6.40, 'longitude' => 107.05]);
    }

    $geometrySebelum = $kendaraan->geometry;

    $this->artisan('rute:perbaiki-geometry --dry-run')
        ->expectsOutputToContain('perlu diperbaiki')
        ->assertSuccessful();

    expect($kendaraan->fresh()->geometry)->toBe($geometrySebelum);
});

/**
 * Rute yang sudah dijalani sopir tidak boleh diacak urutannya, karena
 * sebagian kunjungannya sudah selesai — hanya garis rutenya yang boleh
 * diperbaiki. Tanpa penjaga ini, --urutkan-ulang akan memindahkan toko
 * yang sudah dikirimi barang.
 */
it('tidak mengurutkan ulang kendaraan yang sudah dijalani sopirnya', function () {
    $kendaraan = siapkanKendaraanGeometry(4);

    foreach ($kendaraan->stops as $stop) {
        Toko::where('id', $stop->toko_id)->update(['latitude' => -6.40, 'longitude' => 107.05]);
    }

    $stopPertama = $kendaraan->stops->sortBy('urutan')->first();
    $this->pesananService->selesaikanPengiriman($stopPertama, 'nota/uji.jpg', $this->admin);

    $urutanSebelum = $kendaraan->fresh(['stops'])->stops->sortBy('urutan')->pluck('toko_id')->all();
    $geometrySebelum = $kendaraan->geometry;

    $this->artisan('rute:perbaiki-geometry --urutkan-ulang')
        ->expectsOutputToContain('sudah jalan')
        ->assertSuccessful();

    $kendaraan = $kendaraan->fresh(['stops']);

    expect($kendaraan->stops->sortBy('urutan')->pluck('toko_id')->all())->toBe($urutanSebelum)
        ->and($kendaraan->geometry)->not->toBe($geometrySebelum);
});

/**
 * Kalau koordinat tokonya sendiri yang salah (jatuh jauh dari jalan mana
 * pun), menghitung ulang berapa kali pun tidak akan membuat garis rute
 * menyentuhnya. Perintahnya harus mengatakan itu terus terang, bukan
 * diam-diam melaporkan "diperbaiki" lalu menandainya bermasalah lagi di
 * setiap kali jalan.
 */
it('melaporkan toko yang koordinatnya jauh dari jalan setelah dihitung ulang', function () {
    $kendaraan = siapkanKendaraanGeometry();

    // Digeser ke tengah laut — garis rute tersimpan tidak lagi melewatinya.
    $stopJauh = $kendaraan->stops->sortBy('urutan')->first();
    Toko::where('id', $stopJauh->toko_id)->update([
        'latitude' => -5.60,
        'longitude' => 107.40,
    ]);

    // Perhitungan cadangan (garis lurus) selalu menarik garis tepat lewat
    // koordinat tokonya, jadi keadaan "toko jauh dari jalan" cuma bisa
    // datang dari OSRM sungguhan: ia menempelkan titik yang jatuh di laut
    // ke jalan terdekat yang jauh. Di sini OSRM dipalsukan supaya
    // mengembalikan rute yang tetap berputar di sekitar depot.
    $depot = new Koordinat((float) $this->depot->lat, (float) $this->depot->lng);

    Http::fake([
        '*/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 5000,
                'duration' => 900,
                'geometry' => Geo::encodePolyline([
                    $depot,
                    new Koordinat($depot->lat + 0.005, $depot->lng + 0.005),
                    $depot,
                ]),
                'legs' => [],
            ]],
        ]),
    ]);

    app()->singleton(
        OsrmClient::class,
        fn () => new OsrmClient('http://osrm.uji', null, 5, 100, true),
    );

    $this->artisan('rute:perbaiki-geometry')
        ->expectsOutputToContain('Koordinatnya patut dicurigai salah')
        ->assertSuccessful();
});

it('tidak menyentuh kendaraan yang sudah berstatus selesai', function () {
    $kendaraan = siapkanKendaraanGeometry();

    foreach ($kendaraan->stops as $stop) {
        Toko::where('id', $stop->toko_id)->update(['latitude' => -6.40, 'longitude' => 107.05]);
    }

    $kendaraan->update(['status' => 'selesai']);
    $geometrySebelum = $kendaraan->geometry;

    $this->artisan('rute:perbaiki-geometry')->assertSuccessful();

    expect($kendaraan->fresh()->geometry)->toBe($geometrySebelum);
});
