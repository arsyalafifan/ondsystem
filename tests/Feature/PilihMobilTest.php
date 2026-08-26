<?php

use App\Enums\PeranPengguna;
use App\Livewire\Driver\PilihMobil;
use App\Models\Kendaraan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;
use Livewire\Livewire;

/**
 * Superadmin (dan admin, lewat bypass peran di PastikanPeran) bisa membuka
 * /driver untuk keperluan dukungan. Sebelum perbaikan ini, mengklik "Ambil
 * mobil ini" pada mobil kosong mengunci driver_id ke akun mereka — driver
 * aslinya lalu tidak bisa mengambil mobil itu lagi sama sekali, baik lewat
 * daftar (kendaraans() menyaringnya) maupun lewat URL langsung (mount()
 * DaftarKunjungan menolak dengan 403).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);
    $this->driverLain = User::factory()->create(['role' => PeranPengguna::Driver]);
});

/** Satu kendaraan kosong (driver_id null), sudah disetujui dan siap diambil. */
function kendaraanSiapDiambil(): Kendaraan
{
    $wilayah = Wilayah::create(['kode' => 'W-PM', 'nama' => 'Wilayah Pilih Mobil']);
    $produk = Produk::create(['kode' => 'PM1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 50_000]);
    $toko = Toko::create([
        'kode' => 'TK-PM1', 'nama' => 'Toko Pilih Mobil', 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Pilih Mobil', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $pesananService = app(PesananService::class);
    $pesanan = $pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], test()->sales);
    $pesananService->setujui($pesanan, test()->admin);

    $batch = app(RoutingService::class)->generate(test()->admin);
    app(RoutingService::class)->setujui($batch, test()->admin);

    return $batch->fresh()->kendaraans->first();
}

it('mengunci driver_id ke akun driver sungguhan yang mengambilnya', function () {
    $kendaraan = kendaraanSiapDiambil();

    Livewire::actingAs($this->driver)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.kunjungan', $kendaraan));

    $segar = $kendaraan->fresh();
    expect($segar->driver_id)->toBe($this->driver->id)
        ->and($segar->diambil_at)->not->toBeNull();
});

it('superadmin membuka mobil kosong tanpa mengunci driver_id-nya', function () {
    $kendaraan = kendaraanSiapDiambil();

    Livewire::actingAs($this->superadmin)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.kunjungan', $kendaraan));

    expect($kendaraan->fresh()->driver_id)->toBeNull();
});

it('admin membuka mobil kosong tanpa mengunci driver_id-nya', function () {
    $kendaraan = kendaraanSiapDiambil();

    Livewire::actingAs($this->admin)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.kunjungan', $kendaraan));

    expect($kendaraan->fresh()->driver_id)->toBeNull();
});

it('driver sungguhan tetap bisa mengambil mobil setelah superadmin membukanya lebih dulu', function () {
    $kendaraan = kendaraanSiapDiambil();

    Livewire::actingAs($this->superadmin)->test(PilihMobil::class)->call('ambil', $kendaraan->id);

    Livewire::actingAs($this->driver)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.kunjungan', $kendaraan));

    expect($kendaraan->fresh()->driver_id)->toBe($this->driver->id);
});

it('mobil yang sudah diambil superadmin masih muncul di daftar untuk driver lain', function () {
    $kendaraan = kendaraanSiapDiambil();

    Livewire::actingAs($this->superadmin)->test(PilihMobil::class)->call('ambil', $kendaraan->id);

    Livewire::actingAs($this->driver)
        ->test(PilihMobil::class)
        ->assertSee($kendaraan->nama);
});

it('tetap menolak driver kedua kalau mobil sudah benar-benar diambil driver pertama', function () {
    $kendaraan = kendaraanSiapDiambil();

    Livewire::actingAs($this->driver)->test(PilihMobil::class)->call('ambil', $kendaraan->id);

    Livewire::actingAs($this->driverLain)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertDispatched('notifikasi');

    expect($kendaraan->fresh()->driver_id)->toBe($this->driver->id);
});

it('superadmin bisa membuka layar kunjungan mobil yang masih kosong lewat URL langsung', function () {
    $kendaraan = kendaraanSiapDiambil();

    $this->actingAs($this->superadmin)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk();

    expect($kendaraan->fresh()->driver_id)->toBeNull();
});

it('mencatat diambil_at saat driver membuka mobil yang sudah ditetapkan admin lebih dulu', function () {
    $kendaraan = kendaraanSiapDiambil();

    app(RoutingService::class)->ubahDriver($kendaraan, $this->driver);
    expect($kendaraan->fresh()->diambil_at)->toBeNull();

    Livewire::actingAs($this->driver)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.kunjungan', $kendaraan));

    $segar = $kendaraan->fresh();
    expect($segar->driver_id)->toBe($this->driver->id)
        ->and($segar->diambil_at)->not->toBeNull();
});
