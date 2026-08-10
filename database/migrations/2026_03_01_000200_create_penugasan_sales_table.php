<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar toko yang menjadi tanggungan seorang sales pada satu bulan.
        // Admin memperbaruinya tiap bulan, sementara kunjungannya berulang
        // setiap minggu memakai daftar yang sama.
        Schema::create('penugasan_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('toko_id')->constrained('tokos')->cascadeOnUpdate()->cascadeOnDelete();

            // Selalu tanggal 1, sebagai penanda bulan penugasan.
            $table->date('bulan');

            $table->foreignId('ditugaskan_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            // Satu toko hanya boleh jadi tanggungan satu sales dalam satu
            // bulan. Dijaga di tingkat basis data, bukan hanya di formulir,
            // supaya dua admin yang menugaskan bersamaan tidak bisa lolos.
            $table->unique(['toko_id', 'bulan']);
            $table->index(['sales_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_sales');
    }
};
