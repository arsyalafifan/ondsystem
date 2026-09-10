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
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Admin dan superadmin bisa membuka /driver untuk memantau, bukan cuma
 * superadmin — PastikanPeran HANYA membebaskan superadmin secara otomatis;
 * peran admin biasa harus disebut eksplisit di rute
 * (`peran:driver,admin`), sama seperti /driver/mobil/{kendaraan}.
 *
 * Dua bug nyata yang diperbaiki di sini, keduanya baru kelihatan lewat
 * rute HTTP sungguhan (`->get(route(...))`), bukan lewat
 * `Livewire::test()` yang memanggil komponennya langsung — jalur itu tidak
 * pernah melewati middleware rute sama sekali, jadi galat 403 pada rute
 * tidak pernah tertangkap tes yang cuma memanggil method komponen:
 *
 *  - Admin (bukan superadmin) ditolak 403 membuka /driver sama sekali,
 *    karena rutenya dulu hanya `peran:driver`.
 *  - kendaraans() menyaring mobil yang sudah dibawa driver lain dari
 *    daftar, jadi admin/superadmin tidak pernah melihatnya — padahal
 *    justru itulah yang perlu mereka pantau. Mengklik "Ambil" pada mobil
 *    begitu (lewat tautan langsung atau URL) juga ditolak dengan pesan
 *    "sudah diambil driver lain", padahal admin cuma ingin memantau, tidak
 *    ikut mengklaim.
 *
 * Ditambah, dari sebelumnya: mengklik "Ambil mobil ini" pada mobil KOSONG
 * tidak boleh mengunci driver_id ke akun admin/superadmin — driver
 * aslinya harus tetap bisa mengambil mobil itu sendiri belakangan.
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
    return kendaraanSiapDiambilTanggal(null);
}

/** Sama seperti kendaraanSiapDiambil(), tapi tanggal keberangkatannya bisa diatur. */
function kendaraanSiapDiambilTanggal(?CarbonImmutable $tanggal): Kendaraan
{
    static $n = 0;
    $n++;

    $wilayah = Wilayah::create(['kode' => "W-PM{$n}", 'nama' => "Wilayah Pilih Mobil {$n}"]);
    $produk = Produk::create(['kode' => "PM{$n}", 'nama' => "Produk Uji {$n}", 'stok' => 1000, 'harga' => 50_000]);
    $toko = Toko::create([
        'kode' => "TK-PM{$n}", 'nama' => "Toko Pilih Mobil {$n}", 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Pilih Mobil', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $pesananService = app(PesananService::class);
    $pesanan = $pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], test()->sales);
    $pesananService->setujui($pesanan, test()->admin);

    $batch = app(RoutingService::class)->generate(test()->admin, tanggalKeberangkatan: $tanggal);
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

// =====================================================================
// Bug nyata: admin ditolak 403 di rute /driver, dan admin/superadmin
// tidak bisa melihat/membuka mobil yang sudah dibawa driver lain.
// =====================================================================

/** Kendaraan yang BENAR-BENAR sudah dibawa driver (driver_id + diambil_at terisi). */
function kendaraanSudahDiambilDriver(User $driver): Kendaraan
{
    $kendaraan = kendaraanSiapDiambil();

    Livewire::actingAs($driver)->test(PilihMobil::class)->call('ambil', $kendaraan->id);

    return $kendaraan->fresh();
}

it('admin (bukan superadmin) bisa membuka rute /driver, tidak ditolak 403', function () {
    $this->actingAs($this->admin)->get(route('driver.pilih-mobil'))->assertOk();
});

it('superadmin bisa membuka rute /driver', function () {
    $this->actingAs($this->superadmin)->get(route('driver.pilih-mobil'))->assertOk();
});

it('driver biasa tetap bisa membuka rute /driver seperti biasa', function () {
    $this->actingAs($this->driver)->get(route('driver.pilih-mobil'))->assertOk();
});

it('sales tetap ditolak 403 di rute /driver', function () {
    $this->actingAs($this->sales)->get(route('driver.pilih-mobil'))->assertForbidden();
});

it('admin melihat mobil yang sudah dibawa driver lain di daftar, bukan cuma yang masih kosong', function () {
    $kendaraan = kendaraanSudahDiambilDriver($this->driver);

    Livewire::actingAs($this->admin)
        ->test(PilihMobil::class)
        ->assertSee($kendaraan->nama)
        ->assertSee($this->driver->name);
});

it('superadmin melihat mobil yang sudah dibawa driver lain di daftar', function () {
    $kendaraan = kendaraanSudahDiambilDriver($this->driver);

    Livewire::actingAs($this->superadmin)
        ->test(PilihMobil::class)
        ->assertSee($kendaraan->nama);
});

it('driver TETAP tidak melihat mobil yang sudah dibawa driver lain (bukan admin)', function () {
    $kendaraan = kendaraanSudahDiambilDriver($this->driver);

    Livewire::actingAs($this->driverLain)
        ->test(PilihMobil::class)
        ->assertDontSee($kendaraan->nama);
});

it('admin bisa membuka layar kunjungan mobil yang sudah dibawa driver lain, tidak ditolak', function () {
    $kendaraan = kendaraanSudahDiambilDriver($this->driver);

    Livewire::actingAs($this->admin)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.kunjungan', $kendaraan))
        ->assertNotDispatched('notifikasi');

    // Kepemilikan mobil tidak berubah — admin cuma memantau.
    expect($kendaraan->fresh()->driver_id)->toBe($this->driver->id);
});

it('admin bisa membuka layar kunjungan mobil siapa pun lewat URL langsung, tidak ditolak 403', function () {
    $kendaraan = kendaraanSudahDiambilDriver($this->driver);

    $this->actingAs($this->admin)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk();
});

it('driver kedua tetap ditolak "sudah diambil" saat mencoba mengambil mobil driver lain', function () {
    $kendaraan = kendaraanSudahDiambilDriver($this->driver);

    Livewire::actingAs($this->driverLain)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertDispatched('notifikasi')
        ->assertNoRedirect();

    expect($kendaraan->fresh()->driver_id)->toBe($this->driver->id);
});

/**
 * Daftar mobil sekarang mengikuti tanggal KEBERANGKATAN (`Kendaraan::tanggal`),
 * bawaannya hari ini — sebelumnya semua mobil berstatus siap/jalan
 * "berkumpul" jadi satu daftar tanpa peduli tanggal berangkatnya, jadi
 * mobil kemarin yang belum tuntas dikirim tetap nyangkut di daftar hari
 * ini bersama mobil yang baru berangkat.
 */
describe('daftar mobil menyaring per tanggal keberangkatan', function () {
    it('mobil yang belum berangkat hari ini tidak nyangkut di daftar kemarin yang belum tuntas', function () {
        $kemarin = kendaraanSiapDiambilTanggal(CarbonImmutable::yesterday());
        $hariIni = kendaraanSiapDiambilTanggal(CarbonImmutable::today());

        $daftar = Livewire::actingAs($this->driver)->test(PilihMobil::class)->instance()->kendaraans;

        expect($daftar->pluck('id'))->toContain($hariIni->id)
            ->and($daftar->pluck('id'))->not->toContain($kemarin->id);
    });

    it('mobil kemarin tetap bisa dilihat lewat filter tanggal', function () {
        $kemarin = kendaraanSiapDiambilTanggal(CarbonImmutable::yesterday());

        $daftar = Livewire::actingAs($this->admin)
            ->test(PilihMobil::class)
            ->set('tanggal', CarbonImmutable::yesterday()->toDateString())
            ->instance()->kendaraans;

        expect($daftar->pluck('id'))->toContain($kemarin->id);
    });

    it('bawaannya tanggal hari ini begitu halaman dibuka', function () {
        Livewire::actingAs($this->driver)
            ->test(PilihMobil::class)
            ->assertSet('tanggal', CarbonImmutable::today()->toDateString());
    });
});
