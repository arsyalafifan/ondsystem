<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_kunjungan_id')->constrained('periode_kunjungans')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('periode_sales_id')->constrained('periode_sales')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('toko_id')->constrained('tokos')->cascadeOnUpdate()->restrictOnDelete();

            $table->enum('status', [
                'berjalan',          // sales sedang mengambil foto
                'selesai',           // enam foto lengkap
                'tutup_diajukan',    // sales melapor toko tutup, menunggu admin
                'tutup_disetujui',   // admin membenarkan, toko keluar dari target
                'tutup_ditolak',     // admin menolak, toko wajib dikunjungi lagi
            ])->default('berjalan')->index();

            // Nomor aset yang benar-benar terbaca dari QR, disimpan apa adanya
            // sebagai jejak bahwa sales memang berdiri di depan freezer itu.
            $table->string('asset_id_terpindai', 40)->nullable();

            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('selesai_at')->nullable();

            // Titik GPS saat kunjungan diselesaikan.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('akurasi_m')->nullable();
            // Jarak ke koordinat toko. Selisih yang jauh jadi bahan pemeriksaan.
            $table->unsignedInteger('jarak_dari_toko_m')->nullable();

            $table->text('catatan_sales')->nullable();
            $table->text('catatan_admin')->nullable();

            $table->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditinjau_at')->nullable();

            $table->timestamps();

            // Inti aturan: dalam satu periode, satu toko hanya boleh
            // dikunjungi satu kali. Sales kedua yang memindai QR toko yang
            // sama akan tertahan di sini, bukan hanya di pemeriksaan aplikasi.
            $table->unique(['periode_kunjungan_id', 'toko_id']);
            $table->index(['periode_sales_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungans');
    }
};
