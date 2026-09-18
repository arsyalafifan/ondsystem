<?php

use App\Services\Pengguna\GabungAkunGanda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Email pengguna jadi unik untuk seluruh aplikasi (sebelumnya unik per
 * gudang), karena satu orang sekarang cukup punya satu akun dengan akses ke
 * beberapa gudang, dan login tidak lagi memilih gudang.
 *
 * Akun ganda yang sudah ada digabungkan lebih dulu (lihat
 * App\Services\Pengguna\GabungAkunGanda). Rencananya bisa dilihat sebelum
 * deploy lewat `php artisan pengguna:gabung-akun-ganda`. Kalau ada kelompok
 * yang gagal digabung, migrasi berhenti sebelum mengubah indeks — tidak ada
 * yang setengah jadi.
 *
 * Kolom `depot_kunci_unik` (hanya ada untuk indeks lama) ikut dibuang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasil = app(GabungAkunGanda::class)->jalankan();

        if ($hasil['digabung'] > 0) {
            Log::info('Akun ganda digabungkan', $hasil);
        }

        if ($hasil['gagal'] !== []) {
            throw new RuntimeException('Sebagian akun ganda gagal digabung: '.json_encode($hasil['gagal']));
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['depot_kunci_unik', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('depot_kunci_unik');
            $table->unique('email');
        });
    }

    public function down(): void
    {
        // Akun yang sudah digabung tidak bisa dipisah kembali; turun balik
        // hanya mengembalikan bentuk indeks lamanya.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        $tipe = Schema::getConnection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'BIGINT UNSIGNED';
        Schema::getConnection()->statement("ALTER TABLE users ADD COLUMN depot_kunci_unik {$tipe} GENERATED ALWAYS AS (COALESCE(depot_id, 0)) STORED");

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['depot_kunci_unik', 'email']);
        });
    }
};
