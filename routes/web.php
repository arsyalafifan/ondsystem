<?php

use App\Http\Controllers\NotaPesananController;
use App\Http\Controllers\PackingListController;
use App\Http\Controllers\SinkronKunjunganController;
use App\Http\Controllers\TanggunganSalesController;
use App\Livewire\Akun\GantiKataSandi;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Depot\DaftarDepot;
use App\Livewire\Driver\DaftarKunjungan;
use App\Livewire\Driver\PilihMobil;
use App\Livewire\Hr\DaftarDepartment;
use App\Livewire\Hr\DaftarJabatan;
use App\Livewire\Hr\DaftarKaryawan;
use App\Livewire\Hr\Dashboard as HrDashboard;
use App\Livewire\Insentif\InsentifSales;
use App\Livewire\Kunjungan\DaftarPeriode;
use App\Livewire\Kunjungan\DetailPeriode;
use App\Livewire\Kunjungan\Kunjungi;
use App\Livewire\Kunjungan\Penugasan;
use App\Livewire\Kunjungan\TugasSaya;
use App\Livewire\Master\DaftarProduk;
use App\Livewire\Master\DaftarPromo;
use App\Livewire\Master\DaftarToko;
use App\Livewire\Master\DaftarWilayah;
use App\Livewire\Pembayaran\BelumLunas;
use App\Livewire\Pembayaran\Pelunasan;
use App\Livewire\Pembayaran\Pendapatan;
use App\Livewire\Pengguna\DaftarPengguna;
use App\Livewire\Penjualan\BarangTerjual;
use App\Livewire\Pesanan\BuatPesanan;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Livewire\Pos\Kasir;
use App\Livewire\Routing\GenerateRouting;
use App\Livewire\Routing\RiwayatRouting;
use App\Livewire\Statistik\DusPulangDriver;
use App\Livewire\Statistik\DusTerjualDriver;
use App\Livewire\Statistik\RepeatOrderSales;
use App\Livewire\Toko\LengkapiData;
use App\Models\Depot;
use App\Support\Bahasa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route(Auth::user()->role->beranda())
        : redirect()->route('masuk');
});

Route::middleware('guest')->group(function () {
    Route::get('/masuk', Login::class)->name('masuk');
});

// Terbuka untuk tamu maupun pengguna yang sudah masuk, karena pemilih bahasa
// juga ada di halaman masuk.
Route::post('/bahasa', function (Request $request) {
    Bahasa::pakai((string) $request->input('kode'), $request->user());

    return back()->with('sukses', __('umum.bahasa_diubah', [
        'bahasa' => Bahasa::info()['nama'],
    ]));
})->name('bahasa.ubah');

Route::post('/keluar', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('masuk');
})->name('logout');

// Dipanggil OND Print Helper (.exe Windows), bukan dari sesi browser — jadi
// di luar middleware auth, dan diamankan lewat tanda tangan sementara
// (URL::temporarySignedRoute) alih-alih login/cookie sama sekali.
Route::get('/pesanan/{pesanan}/nota/escp/signed', [NotaPesananController::class, 'escpUntukAgenCetak'])
    ->name('pesanan.nota.escp.signed')
    ->middleware(['signed', 'depot.semua']);

Route::get('/routing/{kendaraan}/packing-list/escp/signed', [PackingListController::class, 'escpUntukAgenCetak'])
    ->name('routing.packing-list.escp.signed')
    ->middleware(['signed', 'depot.semua']);

Route::middleware('auth')->group(function () {

    // Ganti depot aktif — khusus superadmin, satu-satunya peran yang bisa
    // berpindah-pindah. Form POST biasa (bukan Livewire) supaya halaman
    // dimuat ulang seluruhnya — sama seperti alasan /bahasa di atas: tanpa
    // muat ulang, data depot lama yang sudah tergambar di layar tertinggal.
    Route::post('/depot/ganti', function (Request $request) {
        abort_unless($request->user()->isSuperadmin(), 403);

        $pilihan = $request->input('depot_id');
        $valid = $pilihan === 'semua' || Depot::query()->aktif()->whereKey($pilihan)->exists();

        abort_unless($valid, 422);

        $request->session()->put('depot_aktif', $pilihan === 'semua' ? 'semua' : (int) $pilihan);

        return back();
    })->name('depot.ganti');

    // --- Admin ---
    Route::middleware('peran:admin')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        Route::get('/routing/generate', GenerateRouting::class)->name('routing.generate');
        Route::get('/routing/riwayat', RiwayatRouting::class)->name('routing.riwayat');
        Route::get('/routing/{batch}', GenerateRouting::class)->name('routing.lihat');

        Route::get('/routing/{kendaraan}/packing-list', [PackingListController::class, 'cetak'])->name('routing.packing-list');
        Route::get('/routing/{kendaraan}/packing-list/pdf', [PackingListController::class, 'unduhPdf'])->name('routing.packing-list.pdf');
        Route::get('/routing/{kendaraan}/packing-list/escp', [PackingListController::class, 'unduhEscp'])->name('routing.packing-list.escp');

        Route::get('/kunjungan/periode', DaftarPeriode::class)->name('kunjungan.periode');
        Route::get('/kunjungan/periode/{periode}', DetailPeriode::class)->name('kunjungan.periode.lihat');
        Route::get('/kunjungan/penugasan', Penugasan::class)->name('kunjungan.penugasan');

        Route::get('/pembayaran/pelunasan', Pelunasan::class)->name('pembayaran.pelunasan');
        Route::get('/pembayaran/belum-lunas', BelumLunas::class)->name('pembayaran.belum-lunas');
        Route::get('/pembayaran/pendapatan', Pendapatan::class)->name('pembayaran.pendapatan');

        Route::get('/insentif/sales', InsentifSales::class)->name('insentif.sales');

        Route::get('/penjualan/barang-terjual', BarangTerjual::class)->name('penjualan.barang-terjual');

        Route::get('/statistik/repeat-order-sales', RepeatOrderSales::class)->name('statistik.repeat-order-sales');
        Route::get('/statistik/dus-pulang-driver', DusPulangDriver::class)->name('statistik.dus-pulang-driver');
        Route::get('/statistik/dus-terjual-driver', DusTerjualDriver::class)->name('statistik.dus-terjual-driver');

        Route::get('/master/toko', DaftarToko::class)->name('master.toko');
        Route::get('/master/produk', DaftarProduk::class)->name('master.produk');
        Route::get('/master/wilayah', DaftarWilayah::class)->name('master.wilayah');
        Route::get('/master/promo', DaftarPromo::class)->name('master.promo');

        Route::get('/pos', Kasir::class)->name('pos.kasir');
    });

    // --- Sales dan Admin ---
    Route::middleware('peran:admin,sales')->group(function () {
        Route::get('/pesanan/buat', BuatPesanan::class)->name('pesanan.buat');
        Route::get('/pesanan', DaftarPesanan::class)->name('pesanan.daftar');
        Route::get('/pesanan/{pesanan}/nota', [NotaPesananController::class, 'cetak'])->name('pesanan.nota');
        Route::get('/pesanan/{pesanan}/nota/pdf', [NotaPesananController::class, 'unduhPdf'])->name('pesanan.nota.pdf');
        Route::get('/pesanan/{pesanan}/nota/escp', [NotaPesananController::class, 'unduhEscp'])->name('pesanan.nota.escp');
        Route::get('/toko/lengkapi-data', LengkapiData::class)->name('toko.lengkapi-data');
    });

    // --- Sales ---
    Route::middleware('peran:sales')->group(function () {
        Route::get('/kunjungan/tugas', TugasSaya::class)->name('kunjungan.tugas');
        Route::get('/kunjungan', Kunjungi::class)->name('kunjungan.kunjungi');

        // Kiriman susulan dari perangkat yang tadi tidak punya sinyal.
        //
        // Berawalan /api/ bukan karena hidup di luar sesi — ia tetap memakai
        // middleware web yang sama persis (sesi, CSRF, bahasa, depot) — tapi
        // karena bootstrap/app.php menetapkan `api/*` sebagai penanda
        // permintaan yang galatnya dijawab JSON, bukan dialihkan ke halaman.
        // Perangkat yang mengirim antrean butuh 422/401 yang bisa dibaca
        // mesin, bukan pengalihan ke layar masuk.
        Route::post('/api/kunjungan/sinkron', SinkronKunjunganController::class)->name('kunjungan.sinkron');

        // Daftar toko tanggungan untuk disimpan di perangkat selagi masih
        // ada sinyal — tanpa ini, hasil pindaian QR tidak bisa dicocokkan
        // ke toko mana pun saat jaringan hilang.
        Route::get('/api/kunjungan/tanggungan', TanggunganSalesController::class)->name('kunjungan.tanggungan');
    });

    // --- Driver ---
    // Admin/superadmin boleh memantau kendaraan mana pun di kedua rute ini
    // (hanya memantau — PilihMobil::ambil() dan
    // DaftarKunjungan::pastikanBisaBertindak() sendiri yang mengunci semua
    // tindakan driver untuk peran selain driver), jadi keduanya perlu
    // peran admin juga, bukan cuma driver.
    Route::middleware('peran:driver,admin')->group(function () {
        Route::get('/driver', PilihMobil::class)->name('driver.pilih-mobil');
        Route::get('/driver/mobil/{kendaraan}', DaftarKunjungan::class)->name('driver.kunjungan');
    });

    // --- HR ---
    // 'peran:admin,hr' otomatis meloloskan superadmin juga lewat bypass di
    // PastikanPeran, tidak perlu disebut eksplisit di daftar peran.
    Route::middleware('peran:admin,hr')->prefix('hr')->name('hr.')->group(function () {
        Route::get('/dashboard', HrDashboard::class)->name('dashboard');
        Route::get('/karyawan', DaftarKaryawan::class)->name('karyawan');
        Route::get('/department', DaftarDepartment::class)->name('department');
        Route::get('/jabatan', DaftarJabatan::class)->name('jabatan');
    });

    // --- Superadmin ---
    Route::middleware('peran:superadmin')->group(function () {
        Route::get('/pengguna', DaftarPengguna::class)->name('pengguna.daftar');
        Route::get('/depot', DaftarDepot::class)->name('depot.daftar');
    });

    // --- Semua peran yang sudah masuk ---
    Route::get('/akun/kata-sandi', GantiKataSandi::class)->name('akun.kata-sandi');
});
