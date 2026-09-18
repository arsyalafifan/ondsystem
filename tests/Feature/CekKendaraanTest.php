<?php

use App\Enums\JenisCatatanBbm;
use App\Enums\LevelBahanBakar;
use App\Enums\PeranPengguna;
use App\Enums\StatusStop;
use App\Livewire\Driver\CekKendaraan;
use App\Livewire\Driver\DaftarKunjungan;
use App\Livewire\Driver\PilihMobil;
use App\Models\CatatanBbm;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kendaraan\CatatanBbmService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Foto KM + bahan bakar sebelum berangkat & saat kembali, dan pengisian
 * bahan bakar opsional di jalan — lihat App\Livewire\Driver\CekKendaraan
 * dan App\Services\Kendaraan\CatatanBbmService.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);
    $this->driverLain = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 10_000]);
});

/** Satu kendaraan siap jalan, sudah diambil test()->driver. */
function kendaraanCekBbm(): Kendaraan
{
    static $n = 0;
    $n++;

    $toko = Toko::create([
        'kode' => "TK-CB{$n}", 'nama' => "Toko Cek BBM {$n}", 'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Cek BBM', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $pesananService = app(PesananService::class);
    $pesanan = $pesananService->buat($toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => 10]], test()->sales);
    $pesananService->setujui($pesanan, test()->admin);

    $batch = app(RoutingService::class)->generate(test()->admin);
    app(RoutingService::class)->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id, 'diambil_at' => now()]);

    return $kendaraan->fresh();
}

function gambarBbm(): string
{
    $gambar = imagecreatetruecolor(240, 320);
    imagefilledrectangle($gambar, 0, 0, 240, 320, imagecolorallocate($gambar, 80, 90, 100));

    ob_start();
    imagejpeg($gambar, null, 80);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

// =====================================================================
// Gerbang wajib sebelum berangkat
// =====================================================================

it('driver diarahkan ke Cek Kendaraan saat mengambil mobil, bukan langsung ke layar pengiriman', function () {
    $kendaraan = kendaraanCekBbm();
    $kendaraan->update(['driver_id' => null, 'diambil_at' => null]);

    Livewire::actingAs($this->driver)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.cek-kendaraan', $kendaraan));
});

it('driver ditolak masuk layar pengiriman lewat URL langsung sebelum berangkat dicatat', function () {
    $kendaraan = kendaraanCekBbm();

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertRedirect(route('driver.cek-kendaraan', $kendaraan));
});

it('driver bisa masuk layar pengiriman setelah berangkat dicatat', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk();
});

it('admin tidak pernah terkena gerbang ini, cukup memantau', function () {
    $kendaraan = kendaraanCekBbm();

    Livewire::actingAs($this->admin)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->assertOk()
        ->assertSet('melihatSebagaiAdmin', true);
});

it('driver lain tetap ditolak 403 di Cek Kendaraan, bukan diarahkan ke gerbang', function () {
    $kendaraan = kendaraanCekBbm();

    $this->actingAs($this->driverLain)
        ->get(route('driver.cek-kendaraan', $kendaraan))
        ->assertForbidden();
});

// =====================================================================
// Kendaraan yang sudah berjalan sebelum fitur ini ada (bebas_cek_bbm)
// =====================================================================

it('kendaraan yang sudah jalan sebelum fitur ini ada tidak kena gerbang berangkat', function () {
    $kendaraan = kendaraanCekBbm();
    $kendaraan->update(['status' => 'jalan', 'bebas_cek_bbm' => true]);

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk();

    Livewire::actingAs($this->driver)
        ->test(PilihMobil::class)
        ->call('ambil', $kendaraan->id)
        ->assertRedirect(route('driver.kunjungan', $kendaraan));
});

it('kendaraan baru (bukan bawaan migrasi) tetap kena gerbang meski suatu saat berstatus jalan', function () {
    $kendaraan = kendaraanCekBbm();
    $kendaraan->update(['status' => 'jalan']);

    expect($kendaraan->bebas_cek_bbm)->toBeFalse();

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertRedirect(route('driver.cek-kendaraan', $kendaraan));
});

it('kendaraan bebas_cek_bbm tetap boleh mencatat berangkat secara sukarela lewat Cek Kendaraan', function () {
    $kendaraan = kendaraanCekBbm();
    $kendaraan->update(['status' => 'jalan', 'bebas_cek_bbm' => true]);

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'berangkat')
        ->set('km', '999')
        ->set('levelBbm', LevelBahanBakar::Setengah->value)
        ->call('terimaFoto', 'bbm', gambarBbm())
        ->call('simpanBerangkat')
        ->assertRedirect(route('driver.kunjungan', $kendaraan));

    expect(CatatanBbm::where('kendaraan_id', $kendaraan->id)->where('jenis', JenisCatatanBbm::Berangkat)->count())->toBe(1);
});

it('logika backfill migrasi hanya membebaskan kendaraan yang benar-benar sudah diambil driver', function () {
    $sudahJalan = kendaraanCekBbm();
    $sudahJalan->update(['status' => 'jalan', 'diambil_at' => now()]);

    $sudahSelesai = kendaraanCekBbm();
    $sudahSelesai->update(['status' => 'selesai', 'diambil_at' => now()]);

    $siapBelumDiambil = kendaraanCekBbm();
    $siapBelumDiambil->update(['status' => 'siap', 'driver_id' => null, 'diambil_at' => null]);

    $siapSudahDitetapkanTapiBelumDiambil = kendaraanCekBbm();
    $siapSudahDitetapkanTapiBelumDiambil->update(['status' => 'siap', 'diambil_at' => null]);

    // Query persis seperti migrasi bebaskan_kendaraan_berjalan_dari_cek_bbm.
    DB::table('kendaraans')
        ->whereIn('status', ['jalan', 'selesai'])
        ->whereNotNull('driver_id')
        ->whereNotNull('diambil_at')
        ->update(['bebas_cek_bbm' => true]);

    expect($sudahJalan->fresh()->bebas_cek_bbm)->toBeTrue()
        ->and($sudahSelesai->fresh()->bebas_cek_bbm)->toBeTrue()
        ->and($siapBelumDiambil->fresh()->bebas_cek_bbm)->toBeFalse()
        ->and($siapSudahDitetapkanTapiBelumDiambil->fresh()->bebas_cek_bbm)->toBeFalse();
});

// =====================================================================
// Mencatat berangkat / kembali
// =====================================================================

it('mencatat berangkat lewat kamera lalu langsung diarahkan ke layar pengiriman', function () {
    $kendaraan = kendaraanCekBbm();

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'berangkat')
        ->set('km', '54321')
        ->set('levelBbm', LevelBahanBakar::Penuh->value)
        ->call('terimaFoto', 'bbm', gambarBbm())
        ->call('simpanBerangkat')
        ->assertRedirect(route('driver.kunjungan', $kendaraan));

    $catatan = CatatanBbm::where('kendaraan_id', $kendaraan->id)->where('jenis', JenisCatatanBbm::Berangkat)->first();

    expect($catatan)->not->toBeNull()
        ->and($catatan->km)->toBe(54321)
        ->and($catatan->level_bbm)->toBe(LevelBahanBakar::Penuh)
        ->and($catatan->dicatat_oleh)->toBe($this->driver->id)
        ->and($catatan->foto)->not->toBeNull();

    Storage::disk('public')->assertExists($catatan->foto);
});

it('menolak simpan berangkat tanpa km, level bbm, atau foto', function () {
    $kendaraan = kendaraanCekBbm();

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'berangkat')
        ->call('simpanBerangkat')
        ->assertHasErrors(['km', 'levelBbm', 'fotoBbm']);

    expect(CatatanBbm::count())->toBe(0);
});

it('tidak bisa mencatat berangkat dua kali untuk kendaraan yang sama', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'berangkat')
        ->set('km', '1')
        ->set('levelBbm', LevelBahanBakar::Kosong->value)
        ->call('terimaFoto', 'bbm', gambarBbm())
        ->call('simpanBerangkat')
        ->assertDispatched('notifikasi');

    expect(CatatanBbm::where('kendaraan_id', $kendaraan->id)->where('jenis', JenisCatatanBbm::Berangkat)->count())->toBe(1);
});

it('mencatat kembali tidak mengarahkan ke mana pun, tetap di layar Cek Kendaraan', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'kembali')
        ->set('km', '54999')
        ->set('levelBbm', LevelBahanBakar::Seperempat->value)
        ->call('terimaFoto', 'bbm', gambarBbm())
        ->call('simpanKembali')
        ->assertNoRedirect();

    $catatan = CatatanBbm::where('kendaraan_id', $kendaraan->id)->where('jenis', JenisCatatanBbm::Kembali)->first();

    expect($catatan)->not->toBeNull()->and($catatan->km)->toBe(54999);
});

it('pengingat foto kembali muncul begitu seluruh kunjungan tuntas, dan hilang setelah dicatat', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    KendaraanStop::where('kendaraan_id', $kendaraan->id)->update(['status' => StatusStop::Selesai]);
    $kendaraan->update(['status' => 'selesai']);

    $komponen = Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan]);
    $komponen->assertSet('butuhFotoKembali', true);

    app(CatatanBbmService::class)->kembali($kendaraan, $this->driver, gambarBbm(), 100, LevelBahanBakar::Penuh);

    // Kendaraan baru (bukan objek PHP yang sama seperti di atas) — mount()
    // pada permintaan HTTP sungguhan pun selalu mendapat model segar lewat
    // route-model binding, jadi relasi yang sudah dimuat sebelumnya tidak
    // pernah ikut terbawa basi seperti kalau objeknya dipakai ulang di sini.
    Livewire::actingAs($this->driver)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
        ->assertSet('butuhFotoKembali', false);
});

// =====================================================================
// Pengisian bahan bakar (opsional, boleh berkali-kali)
// =====================================================================

it('mencatat pengisian bahan bakar dengan struk wajib, sebelum/sesudah opsional', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'pengisian')
        ->set('liter', '15.5')
        ->set('biaya', '155000')
        ->call('terimaFoto', 'struk', gambarBbm())
        ->call('simpanPengisian')
        ->assertHasNoErrors();

    $catatan = CatatanBbm::where('kendaraan_id', $kendaraan->id)->where('jenis', JenisCatatanBbm::Pengisian)->first();

    expect($catatan)->not->toBeNull()
        ->and((float) $catatan->liter)->toBe(15.5)
        ->and((float) $catatan->biaya)->toBe(155000.0)
        ->and($catatan->foto_sebelum)->toBeNull()
        ->and($catatan->foto_sesudah)->toBeNull();
});

it('menolak pengisian tanpa foto struk', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'pengisian')
        ->call('simpanPengisian')
        ->assertHasErrors('fotoStruk');

    expect(CatatanBbm::where('jenis', JenisCatatanBbm::Pengisian)->count())->toBe(0);
});

it('boleh mencatat pengisian bahan bakar berkali-kali untuk kendaraan yang sama', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    $service = app(CatatanBbmService::class);
    $service->pengisian($kendaraan, $this->driver, gambarBbm());
    $service->pengisian($kendaraan, $this->driver, gambarBbm());

    expect($kendaraan->pengisianBbms()->count())->toBe(2);
});

it('foto sebelum dan sesudah pengisian tersimpan bila diisi', function () {
    $kendaraan = kendaraanCekBbm();
    catatBerangkatKendaraan($kendaraan, $this->driver);

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'pengisian')
        ->call('terimaFoto', 'struk', gambarBbm())
        ->call('terimaFoto', 'sebelum', gambarBbm())
        ->call('terimaFoto', 'sesudah', gambarBbm())
        ->call('simpanPengisian')
        ->assertHasNoErrors();

    $catatan = CatatanBbm::where('jenis', JenisCatatanBbm::Pengisian)->first();

    expect($catatan->foto_sebelum)->not->toBeNull()
        ->and($catatan->foto_sesudah)->not->toBeNull();

    Storage::disk('public')->assertExists($catatan->foto_sebelum);
    Storage::disk('public')->assertExists($catatan->foto_sesudah);
});

it('hapusFoto mengosongkan slot supaya bisa diambil ulang', function () {
    $kendaraan = kendaraanCekBbm();

    Livewire::actingAs($this->driver)
        ->test(CekKendaraan::class, ['kendaraan' => $kendaraan])
        ->call('bukaModal', 'berangkat')
        ->call('terimaFoto', 'bbm', gambarBbm())
        ->assertSet('fotoBbm', fn ($v) => $v !== null)
        ->call('hapusFoto', 'bbm')
        ->assertSet('fotoBbm', null);
});

// =====================================================================
// Model & service
// =====================================================================

it('CatatanBbmService menolak gambar yang bukan data URL gambar valid', function () {
    $kendaraan = kendaraanCekBbm();

    expect(fn () => app(CatatanBbmService::class)
        ->berangkat($kendaraan, $this->driver, 'bukan-data-url', 100, LevelBahanBakar::Penuh))
        ->toThrow(RuntimeException::class);
});

it('relasi Kendaraan::catatanBerangkat dan catatanKembali hanya mengembalikan jenisnya masing-masing', function () {
    $kendaraan = kendaraanCekBbm();
    $service = app(CatatanBbmService::class);

    $service->berangkat($kendaraan, $this->driver, gambarBbm(), 100, LevelBahanBakar::Penuh);
    $service->pengisian($kendaraan, $this->driver, gambarBbm());
    $service->kembali($kendaraan, $this->driver, gambarBbm(), 200, LevelBahanBakar::Kosong);

    $segar = $kendaraan->fresh(['catatanBerangkat', 'catatanKembali', 'pengisianBbms']);

    expect($segar->catatanBerangkat->jenis)->toBe(JenisCatatanBbm::Berangkat)
        ->and($segar->catatanKembali->jenis)->toBe(JenisCatatanBbm::Kembali)
        ->and($segar->pengisianBbms)->toHaveCount(1)
        ->and($segar->catatanBbms)->toHaveCount(3);
});
