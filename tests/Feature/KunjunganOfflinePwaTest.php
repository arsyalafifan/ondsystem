<?php

use App\Enums\HariKunjungan;
use App\Enums\JenisFotoKunjungan;
use App\Enums\PeranPengguna;
use App\Models\PenugasanToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PenugasanTokoService;
use App\Support\VersiAset;

/**
 * Sisi perangkat mode offline: bekal yang diunduh sales selagi masih ada
 * sinyal, dan saklar yang menentukan apakah service worker boleh dipasang.
 *
 * Yang paling dijaga di sini adalah batas peran. Service worker mengendalikan
 * apa yang diambil peramban, jadi satu-satunya cara memastikan admin/driver
 * tidak mungkin ikut terdampak kalau mode offline bermasalah adalah dengan
 * tidak pernah mendaftarkannya sama sekali untuk mereka.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
});

function tokoPwa(string $nama, string $assetId): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-PWA%04d', $n),
        'asset_id' => $assetId,
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. PWA No. '.$n,
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);
}

function tugaskanPwa(Toko $toko, User $sales): void
{
    $sudahAda = PenugasanToko::query()
        ->where('sales_id', $sales->id)
        ->where('hari', HariKunjungan::Senin->value)
        ->pluck('toko_id')
        ->all();

    app(PenugasanTokoService::class)->tetapkan($sales, HariKunjungan::Senin, [...$sudahAda, $toko->id], test()->admin);
}

/**
 * Konfigurasi mode offline ditanam lewat @js(), yang meng-escape tiap petik
 * dan garis miring agar aman di dalam tag script. Dikupas balik di sini
 * supaya asersinya berbunyi soal nilainya, bukan soal bentuk escape-nya.
 */
function konfigOffline(string $html): array
{
    preg_match("/window\.ondOffline = JSON\.parse\('(.*?)'\)/s", $html, $cocok);

    // Dua lapis escape yang harus dikupas berurutan: yang di luar milik
    // literal string JavaScript (\\ dan \uXXXX), yang di dalam milik JSON
    // sendiri. Satu lintasan regex supaya hasil kupasan lapis pertama tidak
    // ikut terkupas lagi di lapis yang sama.
    $json = preg_replace_callback(
        '/\\\\(u[0-9a-fA-F]{4}|.)/s',
        fn (array $m): string => $m[1][0] === 'u'
            ? mb_chr((int) hexdec(substr($m[1], 1)))
            : $m[1],
        $cocok[1] ?? '',
    );

    return json_decode((string) $json, true) ?? [];
}

// =====================================================================
describe('bekal daftar toko untuk perangkat', function () {
    it('menolak peran selain sales', function () {
        $this->actingAs($this->admin)->getJson(route('kunjungan.tanggungan'))->assertForbidden();
    });

    it('menolak tamu yang belum login', function () {
        $this->getJson(route('kunjungan.tanggungan'))->assertUnauthorized();
    });

    it('mengirim toko tanggungan beserta nomor asetnya', function () {
        $milikSaya = tokoPwa('Toko Saya', 'IDNAH202528008001');
        $milikLain = tokoPwa('Toko Orang Lain', 'IDNAH202528008002');

        tugaskanPwa($milikSaya, $this->sales);
        tugaskanPwa($milikLain, User::factory()->create(['role' => PeranPengguna::Sales]));

        $jawaban = $this->actingAs($this->sales)
            ->getJson(route('kunjungan.tanggungan'))
            ->assertOk();

        $daftar = $jawaban->json('toko');

        expect($daftar)->toHaveCount(1)
            ->and($daftar[0]['nama'])->toBe('Toko Saya')
            // Nomor aset wajib ikut: tanpa itu hasil pindaian QR tidak bisa
            // dicocokkan ke toko mana pun saat jaringan hilang.
            ->and($daftar[0]['asset_id'])->toBe('IDNAH202528008001')
            ->and($daftar[0]['perlu_dikunjungi'])->toBeTrue();
    });

    it('menyertakan daftar foto wajib beserta petunjuknya', function () {
        $jawaban = $this->actingAs($this->sales)->getJson(route('kunjungan.tanggungan'))->assertOk();

        expect($jawaban->json('foto_wajib'))->toHaveCount(count(JenisFotoKunjungan::urut()))
            ->and($jawaban->json('foto_wajib.0.nilai'))->toBe(JenisFotoKunjungan::urut()[0]->value)
            ->and($jawaban->json('foto_wajib.0.label'))->not->toBeEmpty();
    });

    it('menandai toko yang sudah dikunjungi supaya tidak dipotret ulang percuma', function () {
        $toko = tokoPwa('Toko Sudah', 'IDNAH202528008003');
        tugaskanPwa($toko, $this->sales);

        app(KunjunganService::class)->mulai($toko, $this->sales);

        $daftar = $this->actingAs($this->sales)->getJson(route('kunjungan.tanggungan'))->json('toko');

        expect($daftar[0]['perlu_dikunjungi'])->toBeFalse();
    });
});

// =====================================================================
describe('saklar service worker', function () {
    it('tidak mendaftarkan service worker ketika mode offline mati', function () {
        config(['visit.offline.pwa_aktif' => false]);

        $konfigurasi = konfigOffline(
            $this->actingAs($this->sales)->get(route('kunjungan.kunjungi'))->assertOk()->content(),
        );

        expect($konfigurasi['pwaAktif'])->toBeFalse();
    });

    it('mendaftarkan service worker untuk sales ketika mode offline menyala', function () {
        config(['visit.offline.pwa_aktif' => true]);

        $konfigurasi = konfigOffline(
            $this->actingAs($this->sales)->get(route('kunjungan.kunjungi'))->assertOk()->content(),
        );

        expect($konfigurasi['pwaAktif'])->toBeTrue()
            ->and($konfigurasi['versi'])->not->toBeEmpty();
    });

    /**
     * Inti janji "tidak berisiko bagi aplikasi yang sudah jadi": peran selain
     * sales tidak pernah mendaftarkan service worker, bahkan ketika saklarnya
     * menyala. Kalau mode offline bermasalah, layar admin mustahil ikut
     * terkunci di versi lama.
     */
    it('tidak pernah mendaftarkan service worker untuk admin, walau saklarnya menyala', function () {
        config(['visit.offline.pwa_aktif' => true]);

        $konfigurasi = konfigOffline(
            $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->content(),
        );

        expect($konfigurasi['pwaAktif'])->toBeFalse();
    });
});

// =====================================================================
describe('layar kunjungan', function () {
    it('memuat panel offline beserta wadah kamera dan pemindainya', function () {
        $halaman = $this->actingAs($this->sales)->get(route('kunjungan.kunjungi'))->assertOk();

        $halaman->assertSee('id="offline-panel"', escape: false)
            ->assertSee('id="pemindai-qr-offline"', escape: false)
            ->assertSee('id="kamera-offline"', escape: false)
            // Alur daring dibungkus supaya bisa disembunyikan saat luring.
            ->assertSee('id="layar-daring"', escape: false)
            ->assertSee(__('kunjungan.offline_judul'));
    });

    it('membawa alamat rute sinkron dan tanggungan untuk dipakai perangkat', function () {
        $konfigurasi = konfigOffline(
            $this->actingAs($this->sales)->get(route('kunjungan.kunjungi'))->assertOk()->content(),
        );

        expect($konfigurasi['ruteSinkron'])->toBe(route('kunjungan.sinkron'))
            ->and($konfigurasi['ruteTanggungan'])->toBe(route('kunjungan.tanggungan'))
            ->and($konfigurasi['csrf'])->not->toBeEmpty();
    });
});

// =====================================================================
describe('versi aset', function () {
    it('tetap sama selama aset tidak dibangun ulang', function () {
        expect(VersiAset::sekarang())->toBe(VersiAset::sekarang())
            ->and(VersiAset::sekarang())->not->toBeEmpty();
    });
});
