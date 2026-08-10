<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Driver\DaftarKunjungan;
use App\Livewire\Driver\PilihMobil;
use App\Livewire\Kunjungan\DaftarPeriode;
use App\Livewire\Kunjungan\DetailPeriode;
use App\Livewire\Kunjungan\Kunjungi;
use App\Livewire\Kunjungan\Penugasan;
use App\Livewire\Kunjungan\TugasSaya;
use App\Livewire\Master\DaftarProduk;
use App\Livewire\Master\DaftarToko;
use App\Livewire\Master\DaftarWilayah;
use App\Livewire\Pesanan\BuatPesanan;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Livewire\Routing\GenerateRouting;
use App\Livewire\Routing\RiwayatRouting;
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

Route::middleware('auth')->group(function () {

    // --- Admin ---
    Route::middleware('peran:admin')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        Route::get('/routing/generate', GenerateRouting::class)->name('routing.generate');
        Route::get('/routing/riwayat', RiwayatRouting::class)->name('routing.riwayat');
        Route::get('/routing/{batch}', GenerateRouting::class)->name('routing.lihat');

        Route::get('/kunjungan/periode', DaftarPeriode::class)->name('kunjungan.periode');
        Route::get('/kunjungan/periode/{periode}', DetailPeriode::class)->name('kunjungan.periode.lihat');
        Route::get('/kunjungan/penugasan', Penugasan::class)->name('kunjungan.penugasan');

        Route::get('/master/toko', DaftarToko::class)->name('master.toko');
        Route::get('/master/produk', DaftarProduk::class)->name('master.produk');
        Route::get('/master/wilayah', DaftarWilayah::class)->name('master.wilayah');
    });

    // --- Sales dan Admin ---
    Route::middleware('peran:admin,sales')->group(function () {
        Route::get('/pesanan/buat', BuatPesanan::class)->name('pesanan.buat');
        Route::get('/pesanan', DaftarPesanan::class)->name('pesanan.daftar');
    });

    // --- Sales ---
    Route::middleware('peran:sales')->group(function () {
        Route::get('/kunjungan/tugas', TugasSaya::class)->name('kunjungan.tugas');
        Route::get('/kunjungan', Kunjungi::class)->name('kunjungan.kunjungi');
    });

    // --- Driver ---
    Route::middleware('peran:driver')->group(function () {
        Route::get('/driver', PilihMobil::class)->name('driver.pilih-mobil');
        Route::get('/driver/mobil/{kendaraan}', DaftarKunjungan::class)->name('driver.kunjungan');
    });
});
