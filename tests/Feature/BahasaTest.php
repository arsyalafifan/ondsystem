<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Auth\Login;
use App\Models\Kendaraan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;
use App\Support\Bahasa;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Livewire\Livewire;

/**
 * Menjaga agar dukungan empat bahasa tetap utuh.
 *
 * Yang paling sering rusak pada aplikasi multibahasa adalah kunci yang lupa
 * diterjemahkan — akibatnya pengguna melihat teks mentah seperti
 * "pesanan.judul" di layar. Karena itu kelengkapan kunci diperiksa sebagai
 * bagian dari rangkaian tes, bukan diserahkan pada ketelitian saat menyunting.
 */
function bahasaTersedia(): array
{
    return array_keys(config('bahasa.tersedia'));
}

/** @return array<int, string> daftar kunci bertingkat, misal "pesanan.judul_buat" */
function kunciDatar(array $data, string $awalan = ''): array
{
    $kunci = [];

    foreach ($data as $nama => $nilai) {
        $penuh = $awalan === '' ? (string) $nama : "{$awalan}.{$nama}";

        if (is_array($nilai)) {
            $kunci = array_merge($kunci, kunciDatar($nilai, $penuh));
        } else {
            $kunci[] = $penuh;
        }
    }

    return $kunci;
}

/** @return array<int, string> */
function kunciBahasa(string $kode): array
{
    $kunci = [];

    foreach (File::glob(lang_path($kode.'/*.php')) as $berkas) {
        // validation.php sengaja dibiarkan sebagian di luar bahasa Inggris:
        // aturan yang tidak dipakai aplikasi ini mengandalkan cadangan
        // bahasa Inggris, jadi tidak ikut diperiksa kelengkapannya.
        if (basename($berkas) === 'validation.php') {
            continue;
        }

        $kunci = array_merge($kunci, kunciDatar(require $berkas, pathinfo($berkas, PATHINFO_FILENAME)));
    }

    sort($kunci);

    return $kunci;
}

it('menyediakan empat bahasa dengan bahasa Indonesia sebagai bawaan', function () {
    expect(bahasaTersedia())->toBe(['id', 'en', 'zh_CN', 'zh_TW'])
        ->and(config('app.locale'))->toBe('id')
        ->and(Bahasa::bawaan())->toBe('id')
        // Cadangan wajib bahasa Inggris, karena berkas validation.php yang
        // lengkap hanya ada di sana.
        ->and(config('app.fallback_locale'))->toBe('en');
});

it('punya kunci terjemahan yang sama persis di setiap bahasa', function () {
    $acuan = kunciBahasa('id');

    expect($acuan)->not->toBeEmpty();

    foreach (['en', 'zh_CN', 'zh_TW'] as $kode) {
        $milik = kunciBahasa($kode);

        $kurang = array_diff($acuan, $milik);
        $lebih = array_diff($milik, $acuan);

        expect($kurang)->toBe([], "Bahasa {$kode} kekurangan kunci: ".implode(', ', $kurang))
            ->and($lebih)->toBe([], "Bahasa {$kode} punya kunci berlebih: ".implode(', ', $lebih));
    }
});

it('tidak meninggalkan terjemahan yang kosong', function (string $kode) {
    foreach (File::glob(lang_path($kode.'/*.php')) as $berkas) {
        if (basename($berkas) === 'validation.php') {
            continue;
        }

        foreach (kunciDatar(require $berkas, pathinfo($berkas, PATHINFO_FILENAME)) as $kunci) {
            app()->setLocale($kode);

            expect(trim(__($kunci)))->not->toBe('', "Kunci {$kunci} kosong pada bahasa {$kode}.");
        }
    }
})->with(['id', 'en', 'zh_CN', 'zh_TW']);

it('menerjemahkan setiap status pesanan dan peran ke semua bahasa', function () {
    foreach (bahasaTersedia() as $kode) {
        app()->setLocale($kode);

        foreach (StatusPesanan::cases() as $status) {
            // Kunci yang belum diterjemahkan akan mengembalikan kuncinya
            // sendiri, jadi itulah yang diperiksa.
            expect($status->label())->not->toContain('status.')
                ->and($status->keterangan())->not->toContain('status.');
        }

        foreach (PeranPengguna::cases() as $peran) {
            expect($peran->label())->not->toContain('status.');
        }
    }
});

it('menulis angka dan tanggal menurut kebiasaan tiap bahasa', function () {
    $harapan = [
        'id' => ['2.110', 'Rp 1.450.000'],
        'en' => ['2,110', 'Rp 1,450,000'],
        'zh_CN' => ['2,110', 'Rp 1,450,000'],
        'zh_TW' => ['2,110', 'Rp 1,450,000'],
    ];

    foreach ($harapan as $kode => [$angka, $rupiah]) {
        Bahasa::pakai($kode);
        Number::useLocale(Bahasa::htmlLocale());

        expect(Bahasa::angka(2110))->toBe($angka)
            ->and(Bahasa::rupiah(1450000))->toBe($rupiah);
    }
});

describe('penggantian bahasa', function () {
    beforeEach(function () {
        $this->pengguna = User::factory()->create([
            'role' => PeranPengguna::Admin,
            'locale' => null,
        ]);
    });

    it('menyimpan pilihan ke akun pengguna', function () {
        $this->actingAs($this->pengguna)
            ->post(route('bahasa.ubah'), ['kode' => 'zh_TW'])
            ->assertRedirect();

        expect($this->pengguna->fresh()->locale)->toBe('zh_TW')
            ->and(session(config('bahasa.kunci_sesi')))->toBe('zh_TW');
    });

    it('memakai pilihan yang tersimpan di akun pada permintaan berikutnya', function () {
        $this->pengguna->update(['locale' => 'en']);

        $this->actingAs($this->pengguna)->get(route('dashboard'))->assertOk();

        expect(app()->getLocale())->toBe('en');
    });

    it('mengingat pilihan tamu lewat sesi', function () {
        $this->post(route('bahasa.ubah'), ['kode' => 'zh_CN'])->assertRedirect();

        $this->get(route('masuk'))->assertOk();

        expect(app()->getLocale())->toBe('zh_CN');
    });

    it('mengabaikan kode bahasa yang tidak dikenal', function () {
        $this->actingAs($this->pengguna)
            ->post(route('bahasa.ubah'), ['kode' => 'de'])
            ->assertRedirect();

        expect($this->pengguna->fresh()->locale)->toBeNull()
            ->and(Bahasa::didukung('de'))->toBeFalse();
    });

    it('memindahkan pilihan bahasa dari halaman masuk ke akun', function () {
        $pengguna = User::factory()->create([
            'role' => PeranPengguna::Sales,
            'locale' => null,
            'password' => bcrypt('rahasia123'),
        ]);

        session([config('bahasa.kunci_sesi') => 'zh_CN']);

        Livewire::test(Login::class)
            ->set('email', $pengguna->email)
            ->set('password', 'rahasia123')
            ->call('masuk');

        expect($pengguna->fresh()->locale)->toBe('zh_CN');
    });
});

describe('halaman dalam semua bahasa', function () {
    beforeEach(function () {
        $wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
        $produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 50_000]);

        $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
        $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
        $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

        $toko = Toko::create([
            'kode' => 'TK-0001', 'nama' => 'Toko Uji', 'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. Uji No. 1', 'latitude' => -6.18, 'longitude' => 106.83,
            'sumber_koordinat' => 'manual',
        ]);

        $service = app(PesananService::class);
        $pesanan = $service->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $this->sales);
        $service->setujui($pesanan, $this->admin);

        $this->batch = app(RoutingService::class)->generate($this->admin);
    });

    it('menampilkan semua halaman admin tanpa kunci terjemahan yang bocor', function (string $kode) {
        $this->admin->update(['locale' => $kode]);

        $rute = [
            'dashboard', 'pesanan.daftar', 'pesanan.buat', 'routing.generate',
            'routing.riwayat', 'master.toko', 'master.produk', 'master.wilayah',
        ];

        foreach ($rute as $nama) {
            $respons = $this->actingAs($this->admin)->get(route($nama));

            $respons->assertOk();

            // Kunci yang lupa diterjemahkan akan tampil apa adanya sebagai
            // teks di halaman. Yang diperiksa hanya teks tampak: atribut HTML
            // ikut memuat nama komponen Livewire seperti "pesanan.daftar-pesanan"
            // yang bentuknya mirip kunci tapi bukan.
            $teks = strip_tags($respons->getContent());

            preg_match_all(
                '/\b(umum|nav|pesanan|routing|driver|dashboard|master|auth|status|validation)\.[a-z_]+\b/',
                $teks,
                $cocok,
            );

            expect(array_unique($cocok[0]))->toBe(
                [],
                "Halaman {$nama} bahasa {$kode} menampilkan kunci mentah: ".implode(', ', array_unique($cocok[0])),
            );
        }
    })->with(['id', 'en', 'zh_CN', 'zh_TW']);

    it('menampilkan halaman driver dalam semua bahasa', function (string $kode) {
        $this->driver->update(['locale' => $kode]);
        app(RoutingService::class)->setujui($this->batch, $this->admin);

        $kendaraan = $this->batch->fresh()->kendaraans->first();

        $this->actingAs($this->driver)->get(route('driver.pilih-mobil'))->assertOk();
        $this->actingAs($this->driver)->get(route('driver.kunjungan', $kendaraan))->assertOk();
    })->with(['id', 'en', 'zh_CN', 'zh_TW']);

    it('menampilkan halaman masuk dalam semua bahasa', function (string $kode) {
        session([config('bahasa.kunci_sesi') => $kode]);

        $this->get(route('masuk'))
            ->assertOk()
            ->assertSee(config('bahasa.tersedia')[$kode]['nama'], escape: false);
    })->with(['id', 'en', 'zh_CN', 'zh_TW']);

    it('menamai kendaraan menurut bahasa pembaca, bukan bahasa pembuatnya', function () {
        $kendaraan = Kendaraan::first();

        $harapan = ['id' => 'Mobil 1', 'en' => 'Vehicle 1', 'zh_CN' => '车辆 1', 'zh_TW' => '車輛 1'];

        foreach ($harapan as $kode => $nama) {
            app()->setLocale($kode);

            expect($kendaraan->nama)->toBe($nama);
        }

        // Kolom di basis data tetap dalam bentuk kanonis, apa pun bahasa
        // yang aktif saat kendaraan dibuat.
        expect($kendaraan->getRawOriginal('nama'))->toBe('Mobil 1');
    });
});
