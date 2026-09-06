<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal kunjungan MINGGUAN yang berdiri sendiri (bukan disusun ulang
     * tiap bulan seperti penugasan_sales lama) — satu toko selamanya
     * berada di satu slot hari untuk satu sales, sampai admin sendiri yang
     * memindahkannya. Karena itu unique-nya cuma toko_id (bukan
     * (toko_id, hari) atau (toko_id, bulan) seperti pendahulunya): begitu
     * toko sudah dijadwalkan di satu hari, ia tidak bisa masuk hari lain
     * ataupun sales lain sampai baris ini diubah/dihapus.
     *
     * penugasan_sales (bulanan) SENGAJA tidak disentuh sama sekali oleh
     * migrasi ini — riwayat penugasan lama tetap ada di sana, cuma tidak
     * lagi dipakai fitur manapun setelah ini. Tidak ada cara otomatis
     * memindahkan datanya ke sini karena tidak pernah ada informasi hari
     * di data lama; jadwal baru ini sengaja dimulai kosong.
     */
    public function up(): void
    {
        Schema::create('penugasan_tokos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toko_id')->constrained('tokos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            // HariKunjungan: 1=Senin .. 6=Sabtu, 7=Minggu (opsional, bukan
            // hari libur yang diblokir sistem — cuma biasanya dibiarkan
            // kosong).
            $table->unsignedTinyInteger('hari');
            $table->foreignId('ditugaskan_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique('toko_id');
            $table->index(['sales_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_tokos');
    }
};
