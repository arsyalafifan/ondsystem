<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori toko (Sekolah, Perusahaan, Pemerintah, Toko, Lainnya) — lihat
 * App\Enums\KategoriToko.
 *
 * VARCHAR biasa, bukan ENUM basis data: menambah/mengubah daftar kategori
 * kelak cukup di enum PHP-nya, tanpa migrasi pengubah kolom — pelajaran
 * yang sama seperti `kunjungan_fotos.jenis` (lihat migrasi
 * `ubah_jenis_kunjungan_fotos_jadi_string`). Nullable: toko lama belum
 * punya kategori, dan itu ditampilkan kosong apa adanya, bukan dipaksa
 * bernilai "Lainnya".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->string('kategori', 20)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
