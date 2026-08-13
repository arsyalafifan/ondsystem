<?php

use App\Enums\PeranPengguna;
use App\Livewire\Auth\Login;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;

/**
 * Memastikan setiap halaman benar-benar tampil, dan setiap peran hanya bisa
 * membuka halaman yang menjadi haknya.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);
    $this->superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);

    $wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 50_000]);

    // Satu pesanan lengkap sampai tahap routing, supaya halaman tidak
    // sekadar diuji dalam keadaan kosong.
    $toko = Toko::create([
        'kode' => 'TK-0001', 'nama' => 'Toko Uji', 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Uji No. 1', 'latitude' => -6.18, 'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);

    $pesananService = app(PesananService::class);
    $pesanan = $pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $this->sales);
    $pesananService->setujui($pesanan, $this->admin);

    $this->batch = app(RoutingService::class)->generate($this->admin);
});

it('mengarahkan tamu ke halaman masuk', function () {
    $this->get('/dashboard')->assertRedirect(route('masuk'));
    $this->get('/pesanan')->assertRedirect(route('masuk'));
    $this->get(route('masuk'))->assertOk()->assertSee('Masuk');
});

it('menampilkan semua halaman admin', function (string $rute) {
    $this->actingAs($this->admin)->get(route($rute))->assertOk();
})->with([
    'dashboard',
    'pesanan.daftar',
    'pesanan.buat',
    'routing.generate',
    'routing.riwayat',
    'master.toko',
    'master.produk',
    'master.wilayah',
]);

it('menampilkan semua halaman admin untuk superadmin', function (string $rute) {
    $this->actingAs($this->superadmin)->get(route($rute))->assertOk();
})->with([
    'dashboard',
    'pesanan.daftar',
    'pesanan.buat',
    'routing.generate',
    'routing.riwayat',
    'master.toko',
    'master.produk',
    'master.wilayah',
]);

it('menampilkan halaman driver untuk superadmin', function () {
    $this->actingAs($this->superadmin)->get(route('driver.pilih-mobil'))->assertOk();
});

it('menampilkan manajemen pengguna untuk superadmin', function () {
    $this->actingAs($this->superadmin)->get(route('pengguna.daftar'))->assertOk();
});

it('menutup manajemen pengguna dari admin biasa', function () {
    $this->actingAs($this->admin)->get(route('pengguna.daftar'))->assertForbidden();
});

it('menutup manajemen pengguna dari sales dan driver', function (string $peran) {
    $this->actingAs($this->{$peran})->get(route('pengguna.daftar'))->assertForbidden();
})->with(['sales', 'driver']);

it('mengizinkan semua peran membuka halaman ganti kata sandi', function (string $peran) {
    $this->actingAs($this->{$peran})->get(route('akun.kata-sandi'))->assertOk();
})->with(['admin', 'sales', 'driver', 'superadmin']);

it('menampilkan halaman routing yang sudah tersimpan', function () {
    $this->actingAs($this->admin)
        ->get(route('routing.lihat', $this->batch))
        ->assertOk()
        ->assertSee($this->batch->kode)
        ->assertSee('Mobil 1');
});

it('menampilkan halaman sales', function (string $rute) {
    $this->actingAs($this->sales)->get(route($rute))->assertOk();
})->with(['pesanan.buat', 'pesanan.daftar']);

it('menutup halaman admin dari sales', function (string $rute) {
    $this->actingAs($this->sales)->get(route($rute))->assertForbidden();
})->with(['dashboard', 'routing.generate', 'master.toko']);

it('menutup halaman admin dan sales dari driver', function (string $rute) {
    $this->actingAs($this->driver)->get(route($rute))->assertForbidden();
})->with(['dashboard', 'pesanan.buat', 'routing.generate']);

it('menampilkan halaman driver', function () {
    $this->actingAs($this->driver)->get(route('driver.pilih-mobil'))->assertOk();
});

it('menampilkan daftar kunjungan driver setelah routing disetujui', function () {
    app(RoutingService::class)->setujui($this->batch, $this->admin);

    $kendaraan = $this->batch->fresh()->kendaraans->first();

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk()
        ->assertSee('Toko Uji');
});

it('melarang driver membuka mobil yang dibawa driver lain', function () {
    app(RoutingService::class)->setujui($this->batch, $this->admin);

    $driverLain = User::factory()->create(['role' => PeranPengguna::Driver]);
    $kendaraan = $this->batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => $driverLain->id]);

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertForbidden();
});

it('mengarahkan setiap peran ke berandanya sendiri', function () {
    $this->actingAs($this->admin)->get('/')->assertRedirect(route('dashboard'));
    $this->actingAs($this->sales)->get('/')->assertRedirect(route('pesanan.buat'));
    $this->actingAs($this->driver)->get('/')->assertRedirect(route('driver.pilih-mobil'));
});

it('menolak masuk untuk akun yang dinonaktifkan', function () {
    $nonaktif = User::factory()->create([
        'role' => PeranPengguna::Sales,
        'aktif' => false,
        'password' => bcrypt('rahasia123'),
    ]);

    Livewire\Livewire::test(Login::class)
        ->set('email', $nonaktif->email)
        ->set('password', 'rahasia123')
        ->call('masuk')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('mengizinkan superadmin masuk lewat kata sandi override', function () {
    $superadmin = User::factory()->create([
        'role' => PeranPengguna::Superadmin,
        'password' => bcrypt('rahasia-yang-lupa'),
    ]);

    Livewire\Livewire::test(Login::class)
        ->set('email', $superadmin->email)
        ->set('password', config('ond.superadmin_override_password'))
        ->call('masuk')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($superadmin);
});

it('menolak kata sandi override untuk peran selain superadmin', function (string $peran) {
    $pengguna = $this->{$peran};

    Livewire\Livewire::test(Login::class)
        ->set('email', $pengguna->email)
        ->set('password', config('ond.superadmin_override_password'))
        ->call('masuk')
        ->assertHasErrors('email');

    $this->assertGuest();
})->with(['admin', 'sales', 'driver']);
