<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadangan "default" jadwal mingguan SATU sales, dipakai sebagai
     * checkpoint yang bisa dipulihkan kapan saja lewat "Restore ke
     * Default" — tanpa harus mengetik ulang satu per satu kalau kondisi
     * visit toko berubah sementara lalu perlu dikembalikan seperti semula.
     *
     * Sengaja terpisah dari penugasan_tokos (jadwal yang SUNGGUH berlaku):
     * "Jadikan Default" menyalin dari sana ke sini, "Restore" menyalin
     * balik dari sini ke sana — snapshot ini sendiri tidak pernah dibaca
     * KunjunganService/PeriodeKunjunganService, murni tempat penyimpanan.
     *
     * Unique-nya (sales_id, toko_id) BUKAN toko_id sendirian seperti
     * penugasan_tokos: ini snapshot pasif milik satu sales, bukan data
     * hidup yang harus eksklusif lintas sales — potensi bentrok (toko yang
     * tersimpan di sini sudah dipegang sales lain di kondisi terkini)
     * baru diperiksa saat restore, bukan saat menyimpan default.
     */
    public function up(): void
    {
        Schema::create('penugasan_toko_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('toko_id')->constrained('tokos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('hari');
            $table->timestamps();

            $table->unique(['sales_id', 'toko_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_toko_defaults');
    }
};
