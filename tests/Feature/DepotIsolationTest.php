<?php

use App\Enums\PeranPengguna;
use App\Exceptions\DepotTidakDiketahui;
use App\Livewire\Auth\Login;
use App\Livewire\Driver\DaftarKunjungan;
use App\Livewire\Kunjungan\Penugasan;
use App\Livewire\Master\DaftarProduk;
use App\Livewire\Master\DaftarPromo;
use App\Livewire\Master\DaftarToko;
use App\Livewire\Master\DaftarWilayah;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Livewire\Pos\Kasir;
use App\Models\Depot;
use App\Models\Kendaraan;
use App\Models\RoutingBatch;
use App\Models\Toko;
use App\Models\User;
use App\Support\DepotContext;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/**
 * Membuktikan langsung requirement inti multi-depot: isolasi antar-depot,
 * superadmin yang bisa terkunci ke satu depot / lihat semua depot, dan
 * kegagalan-keras saat konteks depot belum ditentukan.
 *
 * tests/TestCase.php hanya pernah menyiapkan SATU depot ambient untuk
 * seluruh test lain di aplikasi (lewat DepotContext::pakai() di setUp()) —
 * itu membuat 41+ test lama tetap jalan tanpa diedit, tapi juga berarti
 * test-test itu TIDAK PERNAH benar-benar menguji isolasi lintas-depot.
 * File inilah yang mengisi celah itu, dengan sengaja membuat depot kedua.
 */
it('mengisolasi toko antar depot lewat query biasa, tanpa saling terlihat', function () {
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);

    $tokoA = Toko::create(['kode' => 'TK-A0001', 'nama' => 'Toko Milik A', 'alamat' => 'Jl. A']);

    DepotContext::jalankanSebagai($depotB, function () {
        Toko::create(['kode' => 'TK-B0001', 'nama' => 'Toko Milik B', 'alamat' => 'Jl. B']);
    });

    // Konteks saat ini (dari TestCase::setUp()) masih depot A.
    expect(Toko::count())->toBe(1)
        ->and(Toko::first()->id)->toBe($tokoA->id);

    DepotContext::jalankanSebagai($depotB, function () use ($tokoA) {
        $toko = Toko::first();

        expect(Toko::count())->toBe(1)
            ->and($toko->id)->not->toBe($tokoA->id)
            ->and($toko->nama)->toBe('Toko Milik B');
    });

    // Baris keduanya benar-benar ada di database — hanya scope yang
    // membatasi tampilannya, bukan salah satu gagal tersimpan.
    DepotContext::jalankanUntukSemuaDepot(function () {
        expect(Toko::count())->toBe(2);
    });
});

/**
 * "TK-0001 boleh sama di 2 depot" — persis requirement yang diminta di
 * awal — baru benar-benar berlaku di level DATABASE setelah migrasi
 * tighten Stage 4 (unique(depot_id, kode) menggantikan unique(kode) yang
 * global). Sebelumnya sengaja di-skip menunggu Stage 4 selesai — lihat
 * ~/.claude/plans/tranquil-wondering-mango.md §5 Stage 4.
 */
it('mengizinkan dua depot punya toko dengan kode yang SAMA persis', function () {
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);

    Toko::create(['kode' => 'TK-0001', 'nama' => 'Toko Milik A', 'alamat' => 'Jl. A']);

    DepotContext::jalankanSebagai($depotB, function () {
        Toko::create(['kode' => 'TK-0001', 'nama' => 'Toko Milik B', 'alamat' => 'Jl. B']);
    });

    DepotContext::jalankanUntukSemuaDepot(function () {
        expect(Toko::where('kode', 'TK-0001')->count())->toBe(2);
    });
});

/**
 * Kasus khusus dari §3 rencana Stage 4: kolom generated `depot_kunci_unik`
 * (COALESCE(depot_id, 0)) membuat email unik PER DEPOT untuk user biasa,
 * tapi tetap unik GLOBAL khusus di antara sesama superadmin (yang
 * depot_id-nya sama-sama NULL, jadi "berkumpul" di satu kunci yang sama).
 */
it('dua depot boleh punya user dengan email yang SAMA persis', function () {
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);

    User::factory()->create(['role' => PeranPengguna::Admin, 'email' => 'admin@sama.com']);

    DepotContext::jalankanSebagai($depotB, function () {
        User::factory()->create(['role' => PeranPengguna::Admin, 'email' => 'admin@sama.com']);
    });

    DepotContext::jalankanUntukSemuaDepot(function () {
        expect(User::where('email', 'admin@sama.com')->count())->toBe(2);
    });
});

it('dua superadmin tidak boleh punya email yang sama, walau depot_id sama-sama NULL', function () {
    User::factory()->superadmin()->create(['email' => 'super@sama.com']);

    expect(fn () => User::factory()->superadmin()->create(['email' => 'super@sama.com']))
        ->toThrow(QueryException::class);
});

it('user depot A tidak pernah melihat toko depot B lewat halaman master toko', function () {
    $adminA = User::factory()->create(['role' => PeranPengguna::Admin]);
    Toko::create(['kode' => 'TK-A1', 'nama' => 'Toko Alpha', 'alamat' => 'Jl. A']);

    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);
    DepotContext::jalankanSebagai($depotB, function () {
        Toko::create(['kode' => 'TK-B1', 'nama' => 'Toko Beta', 'alamat' => 'Jl. B']);
    });

    $this->actingAs($adminA)->get(route('master.toko'))
        ->assertOk()
        ->assertSee('Toko Alpha')
        ->assertDontSee('Toko Beta');
});

it('superadmin login terkunci ke satu depot cuma melihat depot itu', function () {
    $superadmin = User::factory()->superadmin()->create(['password' => bcrypt('rahasia123')]);

    Toko::create(['kode' => 'TK-A1', 'nama' => 'Toko Alpha', 'alamat' => 'Jl. A']);
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);
    DepotContext::jalankanSebagai($depotB, function () {
        Toko::create(['kode' => 'TK-B1', 'nama' => 'Toko Beta', 'alamat' => 'Jl. B']);
    });

    Livewire::test(Login::class)
        ->set('depotId', (string) $this->depot->id)
        ->set('email', $superadmin->email)
        ->set('password', 'rahasia123')
        ->call('masuk');

    $this->get(route('master.toko'))
        ->assertOk()
        ->assertSee('Toko Alpha')
        ->assertDontSee('Toko Beta');
});

it('superadmin login pilih semua depot melihat toko dari kedua depot', function () {
    $superadmin = User::factory()->superadmin()->create(['password' => bcrypt('rahasia123')]);

    Toko::create(['kode' => 'TK-A1', 'nama' => 'Toko Alpha', 'alamat' => 'Jl. A']);
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);
    DepotContext::jalankanSebagai($depotB, function () {
        Toko::create(['kode' => 'TK-B1', 'nama' => 'Toko Beta', 'alamat' => 'Jl. B']);
    });

    Livewire::test(Login::class)
        ->set('depotId', 'semua')
        ->set('email', $superadmin->email)
        ->set('password', 'rahasia123')
        ->call('masuk');

    $this->get(route('master.toko'))
        ->assertOk()
        ->assertSee('Toko Alpha')
        ->assertSee('Toko Beta');
});

it('superadmin bisa berpindah depot lewat switcher dan halaman sepenuhnya ganti isi', function () {
    $superadmin = User::factory()->superadmin()->create(['password' => bcrypt('rahasia123')]);

    Toko::create(['kode' => 'TK-A1', 'nama' => 'Toko Alpha', 'alamat' => 'Jl. A']);
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);
    DepotContext::jalankanSebagai($depotB, function () {
        Toko::create(['kode' => 'TK-B1', 'nama' => 'Toko Beta', 'alamat' => 'Jl. B']);
    });

    Livewire::test(Login::class)
        ->set('depotId', (string) $this->depot->id)
        ->set('email', $superadmin->email)
        ->set('password', 'rahasia123')
        ->call('masuk');

    $this->get(route('master.toko'))->assertSee('Toko Alpha')->assertDontSee('Toko Beta');

    $this->post(route('depot.ganti'), ['depot_id' => $depotB->id]);

    $this->get(route('master.toko'))->assertSee('Toko Beta')->assertDontSee('Toko Alpha');
});

it('user biasa tidak bisa mengganti depot aktif lewat switcher', function () {
    $adminA = User::factory()->create(['role' => PeranPengguna::Admin]);
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);

    $this->actingAs($adminA)
        ->post(route('depot.ganti'), ['depot_id' => $depotB->id])
        ->assertForbidden();
});

it('query tanpa konteks depot gagal keras, bukan diam-diam tanpa filter', function () {
    // Membuang instance DepotContext yang sudah disiapkan TestCase::setUp()
    // — meniru kondisi kode yang lupa menyiapkan konteks depot sama sekali.
    app()->forgetInstance(DepotContext::class);

    expect(fn () => Toko::create(['kode' => 'X', 'nama' => 'X', 'alamat' => 'X']))
        ->toThrow(DepotTidakDiketahui::class);

    expect(fn () => Toko::query()->count())
        ->toThrow(DepotTidakDiketahui::class);
});

/**
 * Menirukan kondisi yang sungguh terjadi di localhost (bukan cuma teori):
 * proses yang baru mulai, DepotContext belum pernah disentuh sama sekali
 * oleh apapun — beda dari test lain di file ini yang selalu mewarisi
 * konteks yang sudah disiapkan TestCase::setUp(). Di sinilah celah
 * ayam-dan-telur ketemu: SessionGuard perlu menemukan User pemilik sesi
 * SEBELUM konteks depot bisa ditentukan (karena depot itu sendiri
 * ditentukan DARI User yang ditemukan) — makanya provider auth-nya
 * (App\Auth\DepotAwareUserProvider) sengaja melewati DepotScope.
 */
/**
 * Sengaja TIDAK pakai actingAs(): itu men-set user langsung ke guard
 * (setUser()) tanpa lewat SessionGuard->user()/provider sama sekali,
 * jadi tidak pernah benar-benar melewati jalur yang meledak di
 * localhost. Sesi diisi manual meniru cookie sungguhan, supaya
 * SessionGuard terpaksa menemukan usernya lewat provider — persis jalur
 * yang dipakai DatabaseSessionHandler di akhir permintaan nyata.
 */
function kunciSesiAuth(): string
{
    return 'login_web_'.sha1(SessionGuard::class);
}

it('permintaan HTTP dengan sesi lama tidak meledak walau DepotContext belum pernah disentuh', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin]);

    app()->forgetInstance(DepotContext::class);

    $this->withSession([kunciSesiAuth() => $admin->id])
        ->get(route('master.toko'))
        ->assertOk();
});

/**
 * Bukti perbaikan untuk laporan pengguna: superadmin yang memilih "Semua
 * Depot" dulu meledak DepotTidakDiketahui begitu membuka menu Pesanan
 * atau Generate Routing — kedua halaman itu memang tidak bisa berfungsi
 * tanpa satu depot spesifik (menyusun/menyimpan data untuk depot
 * tertentu), jadi solusinya bukan membuatnya "bisa," tapi menampilkan
 * pesan yang mudah dipahami alih-alih galat mentah.
 */
it('superadmin di mode semua depot melihat pesan ramah, bukan galat, di menu buat pesanan', function () {
    $superadmin = User::factory()->superadmin()->create(['password' => bcrypt('rahasia123')]);

    Livewire::test(Login::class)
        ->set('depotId', 'semua')
        ->set('email', $superadmin->email)
        ->set('password', 'rahasia123')
        ->call('masuk');

    $this->get(route('pesanan.buat'))
        ->assertOk()
        ->assertSee(__('umum.butuh_depot_judul'));
});

it('superadmin di mode semua depot melihat pesan ramah, bukan galat, di menu generate routing', function () {
    $superadmin = User::factory()->superadmin()->create(['password' => bcrypt('rahasia123')]);

    Livewire::test(Login::class)
        ->set('depotId', 'semua')
        ->set('email', $superadmin->email)
        ->set('password', 'rahasia123')
        ->call('masuk');

    $this->get(route('routing.generate'))
        ->assertOk()
        ->assertSee(__('umum.butuh_depot_judul'));
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat menyimpan penugasan toko, bukan galat', function () {
    $superadmin = User::factory()->superadmin()->create(['password' => bcrypt('rahasia123')]);
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = Toko::create(['kode' => 'TK-A1', 'nama' => 'Toko Alpha', 'alamat' => 'Jl. A']);

    Livewire::test(Login::class)
        ->set('depotId', 'semua')
        ->set('email', $superadmin->email)
        ->set('password', 'rahasia123')
        ->call('masuk');

    Livewire::test(Penugasan::class)
        ->set('salesDipilih', $sales->id)
        ->set('terpilih', [$toko->id])
        ->call('simpan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

/**
 * Audit menyeluruh: superadmin bisa membuka HAMPIR semua halaman (lihat
 * App\Http\Middleware\PastikanPeran — superadmin lolos dari setiap
 * pembatasan peran), termasuk yang punya aksi tulis (buat/ubah data)
 * yang tadinya cuma diuji lewat menu Pesanan & Generate Routing. Setiap
 * titik di bawah ini ditemukan lewat audit manual seluruh pemanggilan
 * ::create()/updateOrCreate()/firstOrCreate() pada model yang pakai
 * trait BerDepot, lalu dicek satu per satu apakah superadmin benar-benar
 * bisa mencapainya dalam mode "Semua Depot".
 */
it('superadmin di mode semua depot mendapat notifikasi ramah saat simpan produk baru', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarProduk::class)
        ->call('simpan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat penyesuaian stok', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarProduk::class)
        ->call('simpanPenyesuaian')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat simpan toko baru', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarToko::class)
        ->call('simpan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat mulai impor CSV toko', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarToko::class)
        ->call('mulaiImporCsv')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat simpan wilayah baru', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarWilayah::class)
        ->call('simpan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat simpan promo baru', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarPromo::class)
        ->call('simpan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat checkout POS', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(Kasir::class)
        ->call('simpan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat membatalkan pesanan', function () {
    $superadmin = User::factory()->superadmin()->create();
    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarPesanan::class)
        ->set('alasanCancel', 'Toko tutup')
        ->call('batalkan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('superadmin di mode semua depot mendapat notifikasi ramah saat menyelesaikan kendaraan', function () {
    $superadmin = User::factory()->superadmin()->create();

    $batch = DepotContext::jalankanSebagai($this->depot, fn () => RoutingBatch::create([
        'kode' => 'RB-DEPOT-TEST',
        'tanggal' => now()->toDateString(),
        'status' => 'disetujui',
        'total_kendaraan' => 1,
        'total_toko' => 0,
        'total_dus' => 0,
        'dibuat_oleh' => $superadmin->id,
    ]));

    $kendaraan = DepotContext::jalankanSebagai($this->depot, fn () => Kendaraan::create([
        'routing_batch_id' => $batch->id,
        'nomor' => 1,
        'nama' => 'Mobil Uji',
        'total_toko' => 0,
        'total_dus' => 0,
        'target_dus' => 0,
        'status' => 'jalan',
        'tanggal' => now()->toDateString(),
    ]));

    DepotContext::pakaiSemuaDepot();

    Livewire::actingAs($superadmin)
        ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->call('selesaikanKendaraan')
        ->assertDispatched('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
});

it('rute tamu tidak meledak walau ada sesi lama yang masih tersimpan', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin]);

    app()->forgetInstance(DepotContext::class);

    // /bahasa terbuka untuk tamu MAUPUN yang sudah masuk — persis skenario
    // yang meledak di localhost: DatabaseSessionHandler mencatat user_id
    // pemilik sesi di akhir permintaan ini, walau rute itu sendiri tidak
    // butuh data yang di-scope sama sekali.
    $this->withSession([kunciSesiAuth() => $admin->id])
        ->post(route('bahasa.ubah'), ['kode' => 'id'])
        ->assertRedirect();
});
