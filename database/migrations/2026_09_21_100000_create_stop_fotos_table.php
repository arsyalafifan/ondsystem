<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti pengiriman tambahan per kunjungan pengiriman (foto QR code freezer,
 * suhu freezer, dus pesanan, depan toko, es krim disusun di freezer — atau
 * tanda tangan digital toko kalau mereka memilih menyusun sendiri). Lihat
 * App\Enums\JenisBuktiPengiriman.
 *
 * Satu baris per KEJADIAN (bukan satu kolom per jenis di `kendaraan_stops`),
 * mengikuti pola `kunjungan_fotos`: jenis foto bertambah dari waktu ke
 * waktu tanpa perlu migrasi skema lagi, dan setiap bukti punya barisnya
 * sendiri yang bisa ditelusuri terpisah.
 *
 * `catatan` cuma terisi untuk tanda tangan (nama penanggung jawab toko yang
 * menandatangani) — kosong untuk semua jenis foto biasa.
 *
 * Foto nota (`kendaraan_stops.foto_nota`) TIDAK dipindah ke sini — itu bukti
 * serah terima utama yang sudah ada jauh sebelum fitur ini, tetap di kolom
 * lamanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stop_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kendaraan_stop_id')->constrained('kendaraan_stops')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('jenis', 50);
            $table->string('path');
            $table->text('catatan')->nullable();

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            // Satu jenis foto satu kali per kunjungan pengiriman — konfirmasi
            // pengiriman sekali jalan (stop langsung berpindah dari Pending
            // begitu tersimpan, tidak bisa dibuka ulang), jadi ini murni
            // penjaga terakhir supaya tidak pernah dobel, bukan mekanisme
            // "ambil ulang" yang sungguh dipakai.
            $table->unique(['kendaraan_stop_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stop_fotos');
    }
};
