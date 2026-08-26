<?php

use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarToko;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;
use Livewire\Livewire;

/**
 * Kalau koordinat toko diperbaiki SETELAH rutenya sudah digenerate dan
 * disetujui, garis rute (geometry) dan jarak/ETA tiap kunjungan yang
 * tersimpan jadi mengacu ke lokasi lama — peta lalu menggambar garis rute
 * di posisi lama sementara penandanya sudah pindah ke posisi baru,
 * meskipun sopir belum melakukan aksi lapangan apa pun. Ditemukan dari
 * kendaraan produksi yang koordinat semua tokonya diperbarui lewat impor
 * CSV setelah rutenya berjalan.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 10_000]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
});

it('menghitung ulang rute kendaraan setelah koordinat tokonya diperbaiki lewat form edit', function () {
    $toko = Toko::create([
        'kode' => 'TK-KR01',
        'nama' => 'Toko Koordinat Berubah',
        'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'latitude' => -6.20,
        'longitude' => 106.82,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = $this->pesananService->buat(
        $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales,
    );
    $this->pesananService->setujui($pesanan, $this->admin);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $geometrySebelum = $kendaraan->geometry;
    $jarakSebelum = $kendaraan->total_jarak_m;
    $stopSebelum = $kendaraan->stops->first();

    // Koordinat toko dikoreksi jauh dari titik semula — seperti kalau
    // titik awalnya salah ketik dan baru ketahuan belakangan.
    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('sunting', $toko->id)
        ->set('latitude', -6.30)
        ->set('longitude', 106.95)
        ->call('simpan');

    $kendaraan = $kendaraan->fresh(['stops']);
    $stopSesudah = $kendaraan->stops->first();

    expect($kendaraan->geometry)->not->toBeNull()
        ->and($kendaraan->geometry)->not->toBe($geometrySebelum)
        ->and($kendaraan->total_jarak_m)->not->toBe($jarakSebelum)
        ->and($stopSesudah->jarak_dari_sebelumnya_m)->not->toBe($stopSebelum->jarak_dari_sebelumnya_m);
});

it('tidak menghitung ulang rute kalau toko disunting tapi koordinatnya tidak berubah', function () {
    $toko = Toko::create([
        'kode' => 'TK-KR02',
        'nama' => 'Toko Koordinat Tetap',
        'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji No. 2',
        'latitude' => -6.20,
        'longitude' => 106.82,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = $this->pesananService->buat(
        $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales,
    );
    $this->pesananService->setujui($pesanan, $this->admin);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $geometrySebelum = $kendaraan->geometry;
    $diperbaruiSebelum = $kendaraan->updated_at;

    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('sunting', $toko->id)
        ->set('namaPemilik', 'Pemilik Baru')
        ->call('simpan');

    $kendaraan = $kendaraan->fresh();

    expect($kendaraan->geometry)->toBe($geometrySebelum)
        ->and($kendaraan->updated_at->eq($diperbaruiSebelum))->toBeTrue();
});

it('tidak menghitung ulang rute kendaraan yang sudah berstatus selesai', function () {
    $toko = Toko::create([
        'kode' => 'TK-KR03',
        'nama' => 'Toko Kendaraan Selesai',
        'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji No. 3',
        'latitude' => -6.20,
        'longitude' => 106.82,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = $this->pesananService->buat(
        $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales,
    );
    $this->pesananService->setujui($pesanan, $this->admin);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['status' => 'selesai']);
    $geometrySebelum = $kendaraan->geometry;

    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('sunting', $toko->id)
        ->set('latitude', -6.30)
        ->set('longitude', 106.95)
        ->call('simpan');

    expect($kendaraan->fresh()->geometry)->toBe($geometrySebelum);
});
