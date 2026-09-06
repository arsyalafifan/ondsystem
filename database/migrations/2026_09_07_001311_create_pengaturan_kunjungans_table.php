<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris tunggal (singleton) berisi batas jumlah toko yang boleh
     * dijadwalkan per hari untuk satu sales — pengganti config
     * visit.maks_toko_per_sales (env, cuma bisa diubah lewat deploy) yang
     * dulu jadi target/batas PER BULAN. Sekarang jadi setting yang
     * sungguh bisa diubah admin dari layar Penugasan Toko, bukan lagi
     * angka tetap 120.
     *
     * Baris awalnya langsung disisipkan di sini (bukan firstOrCreate()
     * saat runtime) supaya tidak ada kondisi balapan antara dua permintaan
     * yang sama-sama mencoba membuat baris pertama — aplikasi selalu
     * tinggal membaca/mengubah SATU baris yang sudah pasti ada sejak
     * migrasi ini jalan.
     */
    public function up(): void
    {
        Schema::create('pengaturan_kunjungans', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('maks_toko_per_hari')->default(20);
            $table->timestamps();
        });

        DB::table('pengaturan_kunjungans')->insert([
            'maks_toko_per_hari' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_kunjungans');
    }
};
