<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusStop;
use App\Livewire\Driver\DaftarKunjungan;
use App\Models\Kendaraan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use App\Support\KmlRuteBuilder;
use Livewire\Livewire;

/**
 * Unduh KML dari layar driver: supaya kalau kehilangan sinyal di jalan dan
 * tidak bisa membuka aplikasi ini, driver tetap bisa melihat titik-titik
 * toko rutenya lewat aplikasi peta offline (mis. Map Marker) yang membaca
 * berkas KML standar.
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

function buatTokoKml(string $nama): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-KML%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. KML No. '.$n,
        'telepon' => '0812345'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
        'latitude' => -6.20 + $n * 0.001,
        'longitude' => 106.82 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);
}

/** @param  array<int, string>  $namaToko */
function siapkanKendaraanKml(array $namaToko): Kendaraan
{
    foreach ($namaToko as $nama) {
        $toko = buatTokoKml($nama);
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

// =====================================================================
describe('KmlRuteBuilder::build()', function () {
    it('menghasilkan KML valid dengan satu Placemark per toko, urutan longitude-lalu-latitude', function () {
        $kendaraan = siapkanKendaraanKml(['Toko Satu']);
        $stop = $kendaraan->stops->first();

        $kml = KmlRuteBuilder::build($kendaraan, $kendaraan->stops);

        expect($kml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
            ->and($kml)->toContain('<kml xmlns="http://www.opengis.net/kml/2.2">')
            ->and($kml)->toContain('<name>1. Toko Satu</name>')
            ->and($kml)->toContain(sprintf(
                '<coordinates>%.7F,%.7F,0</coordinates>',
                $stop->toko->longitude,
                $stop->toko->latitude,
            ));

        // Berkasnya harus tetap XML yang benar-benar valid.
        $dom = new DOMDocument;
        expect($dom->loadXML($kml))->toBeTrue();
    });

    it('melewati toko tanpa koordinat, tidak ikut jadi Placemark', function () {
        $kendaraan = siapkanKendaraanKml(['Toko Berkoordinat']);
        $stop = $kendaraan->stops->first();
        $stop->toko->update(['latitude' => null, 'longitude' => null]);

        $kml = KmlRuteBuilder::build($kendaraan, $kendaraan->fresh(['stops.toko'])->stops);

        expect($kml)->not->toContain('<Placemark>');
    });

    it('warna Placemark mengikuti status kunjungan (pending/selesai/dibatalkan)', function () {
        $kendaraan = siapkanKendaraanKml(['Toko Selesai', 'Toko Batal', 'Toko Pending']);
        $stops = $kendaraan->stops;

        $this->pesananService->selesaikanPengiriman($stops[0], 'nota/uji.jpg', $this->driver);
        app(PengirimanService::class)->batalkanDiLapangan($stops[1], $this->driver, 'Toko tutup');

        $kml = KmlRuteBuilder::build($kendaraan->fresh(), $kendaraan->fresh(['stops.toko'])->stops);

        expect($kml)->toContain('styleUrl>#stop-'.StatusStop::Selesai->value)
            ->and($kml)->toContain('styleUrl>#stop-'.StatusStop::Dibatalkan->value)
            ->and($kml)->toContain('styleUrl>#stop-'.StatusStop::Pending->value);
    });

    it('nama toko dengan karakter XML khusus tetap menghasilkan KML valid', function () {
        $kendaraan = siapkanKendaraanKml(['Toko "Bintang" & Rekan <Cabang 2>']);
        $stop = $kendaraan->stops->first();

        $kml = KmlRuteBuilder::build($kendaraan, $kendaraan->stops);

        $dom = new DOMDocument;
        expect($dom->loadXML($kml))->toBeTrue()
            ->and($kml)->not->toContain('<name>1. Toko "Bintang" & Rekan <Cabang 2></name>');

        // KML memakai default namespace, jadi query lewat local-name()
        // bukan lewat nama tag polos yang tidak akan pernah cocok.
        $namaTernormalisasi = (new DOMXPath($dom))
            ->query('//*[local-name()="Placemark"]/*[local-name()="name"]')
            ->item(0)->textContent;
        expect($namaTernormalisasi)->toBe('1. '.$stop->toko->nama);
    });
});

// =====================================================================
describe('DaftarKunjungan::unduhKml()', function () {
    it('driver bisa mengunduh KML rutenya sendiri, isinya memuat semua toko', function () {
        $kendaraan = siapkanKendaraanKml(['Toko Unduh Satu', 'Toko Unduh Dua']);
        $isiSeharusnya = KmlRuteBuilder::build($kendaraan, $kendaraan->stops);

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('unduhKml')
            ->assertFileDownloaded(content: $isiSeharusnya);
    });

    it('admin yang memantau juga bisa mengunduh KML kendaraan siapa pun', function () {
        $kendaraan = siapkanKendaraanKml(['Toko Admin Lihat']);

        Livewire::actingAs($this->admin)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('unduhKml')
            ->assertFileDownloaded();
    });

    it('nama berkas memuat nama kendaraan dan tanggal, dan content-type-nya KML', function () {
        $kendaraan = siapkanKendaraanKml(['Toko Nama Berkas']);

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('unduhKml')
            ->assertFileDownloaded(
                filename: 'Rute-'.str_replace(['/', ' '], '-', $kendaraan->nama).'-'.now()->format('Ymd').'.kml',
                contentType: 'application/vnd.google-earth.kml+xml',
            );
    });
});
