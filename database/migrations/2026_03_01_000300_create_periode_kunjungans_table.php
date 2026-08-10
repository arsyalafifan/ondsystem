<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transaksi utama: satu baris untuk satu minggu kerja (Senin–Sabtu).
        // Periode lama tidak pernah dihapus, sehingga riwayat kunjungan
        // minggu-minggu sebelumnya tetap bisa dilihat.
        Schema::create('periode_kunjungans', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();      // VST-2026-W05
            $table->date('tanggal_mulai');             // Senin
            $table->date('tanggal_selesai');           // Sabtu
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('minggu');     // nomor minggu ISO
            $table->enum('status', ['berjalan', 'selesai'])->default('berjalan')->index();
            $table->timestamps();

            $table->unique(['tahun', 'minggu']);
            $table->index('tanggal_mulai');
        });

        // Rekap per sales dalam satu periode. Targetnya disalin saat periode
        // dibuka, jadi perubahan penugasan di tengah bulan tidak mengubah
        // angka target minggu yang sudah berjalan.
        Schema::create('periode_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_kunjungan_id')->constrained('periode_kunjungans')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('target_toko')->default(0);
            $table->timestamps();

            $table->unique(['periode_kunjungan_id', 'sales_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_sales');
        Schema::dropIfExists('periode_kunjungans');
    }
};
