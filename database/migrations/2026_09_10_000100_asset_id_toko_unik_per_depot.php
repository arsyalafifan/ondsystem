<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koreksi keputusan Stage 4 (lihat 2026_09_08_000500_tighten_depot_id_constraints.php):
 * `tokos.asset_id` awalnya sengaja dibiarkan unik GLOBAL dengan alasan "satu
 * barang fisik (stiker QR freezer) tidak mungkin ada di 2 depot sekaligus".
 *
 * Ternyata premis itu salah di lapangan: toko yang sama secara operasional
 * BOLEH tercatat di dua depot (mis. wilayah perbatasan yang dilayani dua
 * gudang) — freezer fisiknya pun ikut sama, jadi nomor stiker yang sama
 * SAH muncul di baris toko kedua depot itu. Diperbaiki supaya konsisten
 * dengan kode/NIK/nomor HP yang sudah dibuat unik per-depot: nomor stiker
 * yang sama tetap ditolak DALAM satu depot (mencegah salah pindai antar
 * toko di depot yang sama), tapi boleh berulang lintas depot.
 *
 * Aman dijalankan terhadap data yang ada: ini pelonggaran (global -> per
 * depot), bukan pengetatan — apapun yang sudah unik global otomatis tetap
 * unik per depot, tidak ada baris yang bisa bentrok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropUnique('tokos_asset_id_unique');
            $table->unique(['depot_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'asset_id']);
            $table->unique('asset_id');
        });
    }
};
