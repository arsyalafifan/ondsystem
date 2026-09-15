<?php

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Snapshot hak akses SEBELUM Hak Akses Management dibangun: siapa boleh
 * membuka rute mana, menu sidebar apa (label + urutan) dan pemilih aplikasi
 * apa yang dilihat tiap peran. Selama tidak ada setting tersimpan, semua ini
 * harus tetap PERSIS sama — itu janji "tidak ada akses yang berubah".
 *
 * Sengaja hanya memakai rute & HTML yang dirender (bukan kelas Hak Akses
 * sendiri), supaya tes yang sama bisa dijalankan sebelum dan sesudah refaktor.
 */
beforeEach(function () {
    foreach (PeranPengguna::cases() as $peran) {
        $this->{$peran->value} = User::factory()->create(['role' => $peran]);
    }
});

/** Rute → peran (selain superadmin, yang selalu boleh) yang boleh membukanya. */
const PARITAS_RUTE = [
    'dashboard' => ['admin'],
    'routing.generate' => ['admin'],
    'routing.riwayat' => ['admin'],
    'routing.lihat' => ['admin'],
    'routing.packing-list' => ['admin'],
    'routing.packing-list.pdf' => ['admin'],
    'routing.packing-list.escp' => ['admin'],
    'kunjungan.periode' => ['admin'],
    'kunjungan.periode.lihat' => ['admin'],
    'kunjungan.penugasan' => ['admin'],
    'pembayaran.pelunasan' => ['admin'],
    'pembayaran.belum-lunas' => ['admin'],
    'pembayaran.pendapatan' => ['admin'],
    'insentif.sales' => ['admin'],
    'penjualan.barang-terjual' => ['admin'],
    'statistik.repeat-order-sales' => ['admin'],
    'statistik.dus-pulang-driver' => ['admin'],
    'statistik.dus-terjual-driver' => ['admin'],
    'statistik.form-pembelian-produk' => ['admin'],
    'master.toko' => ['admin'],
    'master.produk' => ['admin'],
    'master.wilayah' => ['admin'],
    'master.promo' => ['admin'],
    'pos.kasir' => ['admin'],
    'pesanan.buat' => ['admin', 'sales'],
    'pesanan.daftar' => ['admin', 'sales', 'supervisor'],
    'pesanan.nota' => ['admin', 'sales', 'supervisor'],
    'pesanan.nota.pdf' => ['admin', 'sales', 'supervisor'],
    'pesanan.nota.escp' => ['admin', 'sales', 'supervisor'],
    'toko.lengkapi-data' => ['admin', 'sales', 'supervisor'],
    'kunjungan.tugas' => ['sales'],
    'kunjungan.kunjungi' => ['sales'],
    'kunjungan.sinkron' => ['sales'],
    'kunjungan.tanggungan' => ['sales'],
    'driver.pilih-mobil' => ['driver', 'admin'],
    'driver.kunjungan' => ['driver', 'admin'],
    'hr.dashboard' => ['admin', 'hr'],
    'hr.karyawan' => ['admin', 'hr'],
    'hr.department' => ['admin', 'hr'],
    'hr.jabatan' => ['admin', 'hr'],
    'pengguna.daftar' => [],
    'depot.daftar' => [],
    'akun.kata-sandi' => ['admin', 'sales', 'driver', 'hr', 'supervisor'],
];

/**
 * Menjalankan middleware pembatas akses rute (apa pun namanya, `peran:` atau
 * `akses:`) langsung — tanpa route-model binding, jadi rute berparameter
 * tidak butuh data contoh.
 */
function paritasBolehRute(User $pengguna, string $nama): bool
{
    $rute = Route::getRoutes()->getByName($nama);
    $alias = app('router')->getMiddleware();

    foreach ($rute->gatherMiddleware() as $middleware) {
        if (! is_string($middleware) || ! str_contains($middleware, ':')) {
            continue;
        }

        [$nama, $parameter] = explode(':', $middleware, 2);

        if (! in_array($nama, ['peran', 'akses'], true)) {
            continue;
        }

        $permintaan = Request::create('/'.$rute->uri(), $rute->methods()[0]);
        $permintaan->setUserResolver(fn () => $pengguna);

        try {
            app($alias[$nama])->handle($permintaan, fn () => response('ok'), ...explode(',', $parameter));
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 403) {
                return false;
            }

            throw $e;
        }
    }

    return true;
}

/** @return list<string> "# Grup" atau "Label @ url", berurutan seperti di sidebar. */
function paritasMenu(TestResponse $halaman): array
{
    $html = $halaman->getContent();
    $awal = strpos($html, '<div class="lg:flex-1 space-y-1">');
    $akhir = strpos($html, '<div class="mt-8 space-y-2 border-t', $awal);

    preg_match_all(
        '#<span class="flex-1 text-left">(.*?)</span>|<a href="([^"]+)" wire:navigate[^>]*>(.*?)</a>#s',
        substr($html, $awal, $akhir - $awal),
        $cocok,
        PREG_SET_ORDER,
    );

    return array_map(fn (array $c) => ($c[2] ?? '') !== ''
        ? html_entity_decode(trim(strip_tags($c[3]))).' @ '.$c[2]
        : '# '.html_entity_decode(trim($c[1])), $cocok);
}

/** @return ?list<string> label pemilih aplikasi berurutan, null bila tidak tampil. */
function paritasAplikasi(TestResponse $halaman): ?array
{
    $html = $halaman->getContent();
    $bagian = substr($html, strpos($html, '<nav'), strpos($html, '<div class="lg:flex-1 space-y-1">') - strpos($html, '<nav'));

    if (! str_contains($bagian, 'role="listbox"')) {
        return null;
    }

    preg_match_all('#role="option".*?<span class="flex-1 truncate">(.*?)</span>#s', $bagian, $cocok);

    return array_map(fn ($l) => html_entity_decode(trim($l)), $cocok[1]);
}

function paritasTautan(string $label, string $rute): string
{
    return __($label).' @ '.route($rute);
}

function paritasMenuAdmin(): array
{
    return [
        paritasTautan('nav.dashboard', 'dashboard'),
        paritasTautan('nav.pesanan', 'pesanan.daftar'),
        paritasTautan('nav.input_pesanan', 'pesanan.buat'),
        paritasTautan('nav.pos', 'pos.kasir'),
        paritasTautan('nav.lengkapi_data_toko', 'toko.lengkapi-data'),
        paritasTautan('nav.generate_routing', 'routing.generate'),
        paritasTautan('nav.riwayat_routing', 'routing.riwayat'),
        paritasTautan('nav.visit_sales', 'kunjungan.periode'),
        paritasTautan('nav.penugasan', 'kunjungan.penugasan'),
        '# '.__('nav.pembayaran'),
        paritasTautan('nav.pelunasan', 'pembayaran.pelunasan'),
        paritasTautan('nav.belum_lunas', 'pembayaran.belum-lunas'),
        paritasTautan('nav.pendapatan', 'pembayaran.pendapatan'),
        '# '.__('nav.statistik'),
        paritasTautan('nav.insentif_sales', 'insentif.sales'),
        paritasTautan('nav.barang_terjual', 'penjualan.barang-terjual'),
        paritasTautan('nav.repeat_order_sales', 'statistik.repeat-order-sales'),
        paritasTautan('nav.dus_terjual_driver', 'statistik.dus-terjual-driver'),
        paritasTautan('nav.dus_pulang_driver', 'statistik.dus-pulang-driver'),
        paritasTautan('nav.form_pembelian_produk', 'statistik.form-pembelian-produk'),
        '# '.__('nav.master'),
        paritasTautan('nav.master_toko', 'master.toko'),
        paritasTautan('nav.master_produk', 'master.produk'),
        paritasTautan('nav.master_wilayah', 'master.wilayah'),
        paritasTautan('nav.master_promo', 'master.promo'),
        '# '.__('nav.pengiriman'),
        paritasTautan('nav.pengiriman_driver', 'driver.pilih-mobil'),
    ];
}

function paritasMenuHr(): array
{
    return [
        paritasTautan('nav.dashboard', 'hr.dashboard'),
        '# '.__('nav.master'),
        paritasTautan('nav.hr_karyawan', 'hr.karyawan'),
        paritasTautan('nav.hr_department', 'hr.department'),
        paritasTautan('nav.hr_jabatan', 'hr.jabatan'),
    ];
}

function paritasMenuSales(): array
{
    return [
        paritasTautan('nav.mulai_kunjungan', 'kunjungan.kunjungi'),
        paritasTautan('nav.tugas_saya', 'kunjungan.tugas'),
        paritasTautan('nav.lengkapi_data_toko', 'toko.lengkapi-data'),
        paritasTautan('nav.input_pesanan', 'pesanan.buat'),
        paritasTautan('nav.riwayat_pesanan', 'pesanan.daftar'),
    ];
}

it('setiap peran boleh/ditolak di setiap rute persis seperti sebelumnya', function () {
    foreach (PARITAS_RUTE as $rute => $bolehUntuk) {
        foreach (PeranPengguna::cases() as $peran) {
            $harapan = $peran === PeranPengguna::Superadmin || in_array($peran->value, $bolehUntuk, true);

            expect(paritasBolehRute($this->{$peran->value}, $rute))
                ->toBe($harapan, "{$peran->value} @ {$rute}");
        }
    }
});

it('menu sidebar dan pemilih aplikasi tiap peran tidak berubah', function () {
    $o = [__('nav.aplikasi_ond'), __('nav.aplikasi_hr'), __('nav.aplikasi_accounting')];

    $kasus = [
        ['admin', 'dashboard', paritasMenuAdmin(), $o],
        ['admin', 'hr.dashboard', paritasMenuHr(), $o],
        ['admin', 'akun.kata-sandi', paritasMenuAdmin(), $o],
        ['superadmin', 'dashboard', paritasMenuAdmin(), [...$o, __('nav.aplikasi_user_admin')]],
        ['superadmin', 'hr.dashboard', paritasMenuHr(), [...$o, __('nav.aplikasi_user_admin')]],
        ['superadmin', 'akun.kata-sandi', paritasMenuAdmin(), [...$o, __('nav.aplikasi_user_admin')]],
        ['sales', 'pesanan.buat', paritasMenuSales(), null],
        ['sales', 'akun.kata-sandi', paritasMenuSales(), null],
        ['driver', 'driver.pilih-mobil', ['# '.__('nav.pengiriman'), paritasTautan('nav.pengiriman_driver', 'driver.pilih-mobil')], null],
        ['hr', 'hr.dashboard', paritasMenuHr(), null],
        ['hr', 'akun.kata-sandi', paritasMenuHr(), null],
        ['supervisor', 'pesanan.daftar', [paritasTautan('nav.pesanan', 'pesanan.daftar'), paritasTautan('nav.lengkapi_data_toko', 'toko.lengkapi-data')], null],
    ];

    foreach ($kasus as [$peran, $rute, $menu, $aplikasi]) {
        $halaman = $this->actingAs($this->{$peran})->get(route($rute))->assertOk();

        expect(paritasMenu($halaman))->toBe($menu, "menu {$peran} @ {$rute}")
            ->and(paritasAplikasi($halaman))->toBe($aplikasi, "aplikasi {$peran} @ {$rute}");
    }
});

it('menu User Admin superadmin diawali Kelola Pengguna dan Kelola Depot', function () {
    $menu = paritasMenu($this->actingAs($this->superadmin)->get(route('pengguna.daftar'))->assertOk());

    // Hanya dua entri pertama yang dikunci: menu Hak Akses Management
    // memang ditambahkan SESUDAHNYA di aplikasi yang sama.
    expect(array_slice($menu, 0, 2))->toBe([
        paritasTautan('nav.manage_pengguna', 'pengguna.daftar'),
        paritasTautan('nav.manage_depot', 'depot.daftar'),
    ])->and(collect($menu)->contains(fn ($m) => str_contains($m, route('master.toko'))))->toBeFalse();
});

it('halaman awal setelah masuk tiap peran tidak berubah', function () {
    $beranda = ['admin' => 'dashboard', 'superadmin' => 'dashboard', 'sales' => 'pesanan.buat', 'driver' => 'driver.pilih-mobil', 'hr' => 'hr.dashboard', 'supervisor' => 'pesanan.daftar'];

    foreach ($beranda as $peran => $rute) {
        $this->actingAs($this->{$peran})->get('/')->assertRedirect(route($rute));
    }
});
