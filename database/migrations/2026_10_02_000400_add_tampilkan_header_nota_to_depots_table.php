<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sebagian gudang sudah punya kertas kontinu dengan letterhead perusahaan
 * tercetak duluan di kertas fisiknya — mencetak blok nama/alamat/bank
 * perusahaan lagi lewat sistem jadi dobel. Penanda ini mengizinkan nota
 * gudang itu melewati blok perusahaan saja (Kepada/No. Faktur/Tanggal/Sales
 * tetap tercetak seperti biasa, itu data per transaksi yang memang harus
 * dicetak dinamis) — lihat resources/views/cetak/nota-pesanan.blade.php
 * dan App\Support\EscpNotaBuilder.
 *
 * Default AKTIF (bukan "mati dulu, nyalakan manual" seperti gudang_penyimpanan):
 * ini bukan kapabilitas baru, melainkan opsi untuk MENGHILANGKAN perilaku
 * yang sudah berjalan selama ini — gudang yang sudah ada tidak boleh
 * tiba-tiba kehilangan header di notanya begitu migrasi ini jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->boolean('tampilkan_header_nota')->default(true)->after('radius_rider_km');
        });
    }

    public function down(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->dropColumn('tampilkan_header_nota');
        });
    }
};
