<?php

use App\Akses\HakAkses;
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
use App\Livewire\HakAkses\KelolaHakAkses;
use App\Livewire\Hr\Absensi as HrAbsensi;
use App\Livewire\Hr\DaftarDepartment;
use App\Livewire\Hr\DaftarJabatan;
use App\Livewire\Hr\DaftarKaryawan;
use App\Livewire\Hr\DaftarPosisi;
use App\Livewire\Hr\DaftarShift;
use App\Livewire\Hr\Dashboard as HrDashboard;
use App\Livewire\Hr\MonitoringAbsensi;
use App\Livewire\Hr\SettingJamKerja;
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
use App\Livewire\Statistik\FormPembelianProduk;
use App\Livewire\Statistik\RepeatOrderSales;
use App\Livewire\Toko\LengkapiData;
use App\Models\Depot;
use App\Support\Bahasa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route(app(HakAkses::class)->beranda(Auth::user()))
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

    // Ganti gudang aktif — superadmin ke gudang mana pun (atau "semua"),
    // pengguna lain hanya ke gudang yang diizinkan untuknya di User Admin.
    // Form POST biasa (bukan Livewire) supaya halaman dimuat ulang
    // seluruhnya — sama seperti alasan /bahasa di atas: tanpa muat ulang,
    // data gudang lama yang sudah tergambar di layar tertinggal.
    Route::post('/depot/ganti', function (Request $request) {
        $pengguna = $request->user();
        $pilihan = $request->input('depot_id');

        if ($pilihan === 'semua') {
            abort_unless($pengguna->isSuperadmin(), 403);
        } else {
            abort_unless(is_numeric($pilihan), 422);
            abort_unless($pengguna->depotYangBisaDiakses()->contains('id', (int) $pilihan), 403);
        }

        $request->session()->put('depot_aktif', $pilihan === 'semua' ? 'semua' : (int) $pilihan);

        return back();
    })->name('depot.ganti');

    // Setiap rute di bawah dijaga lewat kunci menu di App\Akses\DaftarAkses
    // (`akses:`), bukan nama peran — siapa yang boleh diatur di sana (bawaan)
    // dan di layar Hak Akses Management (pengecualian). Rute yang dipakai
    // lebih dari satu menu menyebut semuanya: cukup salah satu yang boleh.

    // --- O&D System ---
    Route::get('/dashboard', Dashboard::class)->name('dashboard')->middleware('akses:ond.dashboard');

    Route::get('/routing/generate', GenerateRouting::class)->name('routing.generate')->middleware('akses:ond.generate_routing');
    Route::get('/routing/riwayat', RiwayatRouting::class)->name('routing.riwayat')->middleware('akses:ond.riwayat_routing');

    Route::middleware('akses:ond.generate_routing,ond.riwayat_routing')->group(function () {
        Route::get('/routing/{batch}', GenerateRouting::class)->name('routing.lihat');
        Route::get('/routing/{kendaraan}/packing-list', [PackingListController::class, 'cetak'])->name('routing.packing-list');
        Route::get('/routing/{kendaraan}/packing-list/pdf', [PackingListController::class, 'unduhPdf'])->name('routing.packing-list.pdf');
        Route::get('/routing/{kendaraan}/packing-list/escp', [PackingListController::class, 'unduhEscp'])->name('routing.packing-list.escp');
    });

    Route::middleware('akses:ond.visit_sales')->group(function () {
        Route::get('/kunjungan/periode', DaftarPeriode::class)->name('kunjungan.periode');
        Route::get('/kunjungan/periode/{periode}', DetailPeriode::class)->name('kunjungan.periode.lihat');
    });
    Route::get('/kunjungan/penugasan', Penugasan::class)->name('kunjungan.penugasan')->middleware('akses:ond.penugasan');

    Route::get('/pembayaran/pelunasan', Pelunasan::class)->name('pembayaran.pelunasan')->middleware('akses:ond.pelunasan');
    Route::get('/pembayaran/belum-lunas', BelumLunas::class)->name('pembayaran.belum-lunas')->middleware('akses:ond.belum_lunas');
    Route::get('/pembayaran/pendapatan', Pendapatan::class)->name('pembayaran.pendapatan')->middleware('akses:ond.pendapatan');

    Route::get('/insentif/sales', InsentifSales::class)->name('insentif.sales')->middleware('akses:ond.insentif_sales');
    Route::get('/penjualan/barang-terjual', BarangTerjual::class)->name('penjualan.barang-terjual')->middleware('akses:ond.barang_terjual');
    Route::get('/statistik/repeat-order-sales', RepeatOrderSales::class)->name('statistik.repeat-order-sales')->middleware('akses:ond.repeat_order_sales');
    Route::get('/statistik/dus-pulang-driver', DusPulangDriver::class)->name('statistik.dus-pulang-driver')->middleware('akses:ond.dus_pulang_driver');
    Route::get('/statistik/dus-terjual-driver', DusTerjualDriver::class)->name('statistik.dus-terjual-driver')->middleware('akses:ond.dus_terjual_driver');
    Route::get('/statistik/form-pembelian-produk', FormPembelianProduk::class)->name('statistik.form-pembelian-produk')->middleware('akses:ond.form_pembelian_produk');

    Route::get('/master/toko', DaftarToko::class)->name('master.toko')->middleware('akses:ond.master_toko');
    Route::get('/master/produk', DaftarProduk::class)->name('master.produk')->middleware('akses:ond.master_produk');
    Route::get('/master/wilayah', DaftarWilayah::class)->name('master.wilayah')->middleware('akses:ond.master_wilayah');
    Route::get('/master/promo', DaftarPromo::class)->name('master.promo')->middleware('akses:ond.master_promo');

    Route::get('/pos', Kasir::class)->name('pos.kasir')->middleware('akses:ond.pos');

    Route::get('/pesanan/buat', BuatPesanan::class)->name('pesanan.buat')->middleware('akses:ond.input_pesanan');
    Route::get('/pesanan', DaftarPesanan::class)->name('pesanan.daftar')->middleware('akses:ond.pesanan');
    Route::middleware('akses:ond.pesanan,ond.input_pesanan')->group(function () {
        Route::get('/pesanan/{pesanan}/nota', [NotaPesananController::class, 'cetak'])->name('pesanan.nota');
        Route::get('/pesanan/{pesanan}/nota/pdf', [NotaPesananController::class, 'unduhPdf'])->name('pesanan.nota.pdf');
        Route::get('/pesanan/{pesanan}/nota/escp', [NotaPesananController::class, 'unduhEscp'])->name('pesanan.nota.escp');
    });
    Route::get('/toko/lengkapi-data', LengkapiData::class)->name('toko.lengkapi-data')->middleware('akses:ond.lengkapi_data_toko');

    Route::get('/kunjungan/tugas', TugasSaya::class)->name('kunjungan.tugas')->middleware('akses:ond.tugas');

    Route::middleware('akses:ond.kunjungi')->group(function () {
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
    Route::middleware('akses:ond.pengiriman_driver')->group(function () {
        Route::get('/driver', PilihMobil::class)->name('driver.pilih-mobil');
        Route::get('/driver/mobil/{kendaraan}', DaftarKunjungan::class)->name('driver.kunjungan');
    });

    // --- HR System ---
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('/dashboard', HrDashboard::class)->name('dashboard')->middleware('akses:hr.dashboard');
        Route::get('/karyawan', DaftarKaryawan::class)->name('karyawan')->middleware('akses:hr.karyawan');
        Route::get('/department', DaftarDepartment::class)->name('department')->middleware('akses:hr.department');
        Route::get('/jabatan', DaftarJabatan::class)->name('jabatan')->middleware('akses:hr.jabatan');
        Route::get('/posisi', DaftarPosisi::class)->name('posisi')->middleware('akses:hr.posisi');
        Route::get('/shift', DaftarShift::class)->name('shift')->middleware('akses:hr.shift');
        Route::get('/jam-kerja', SettingJamKerja::class)->name('jam-kerja')->middleware('akses:hr.jam_kerja');

        // Absensi dibuka semua peran yang punya data karyawan — merekalah
        // yang absen. Monitoring-nya tetap khusus admin/HR.
        Route::get('/absensi', HrAbsensi::class)->name('absensi')->middleware('akses:hr.absensi');
        Route::get('/monitoring-absensi', MonitoringAbsensi::class)->name('monitoring-absensi')->middleware('akses:hr.monitoring_absensi');
    });

    // --- User Admin ---
    Route::get('/pengguna', DaftarPengguna::class)->name('pengguna.daftar')->middleware('akses:user_admin.pengguna');
    Route::get('/depot', DaftarDepot::class)->name('depot.daftar')->middleware('akses:user_admin.depot');
    Route::get('/hak-akses', KelolaHakAkses::class)->name('hak-akses.kelola')->middleware('akses:user_admin.hak_akses');

    // --- Semua peran yang sudah masuk ---
    Route::get('/akun/kata-sandi', GantiKataSandi::class)->name('akun.kata-sandi');
});
