<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengecualian hak akses per peran dari layar Hak Akses Management.
 *
 * Hanya MENAMBAH tabel baru (tanpa ALTER tabel lama, tanpa data awal):
 * tabel kosong berarti setiap menu memakai `peran` bawaannya di
 * App\Akses\DaftarAkses — persis akses sebelum fitur ini ada. Karena itu
 * migrasi ini aman dijalankan kapan pun tanpa downtime, sebelum ataupun
 * sesudah kode barunya terpasang.
 *
 * Tidak per depot: satu peran punya hak akses yang sama di semua depot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hak_akses_perans', function (Blueprint $table) {
            $table->id();
            $table->string('peran', 30);
            $table->string('menu', 80);
            $table->boolean('boleh');
            $table->string('cakupan', 10)->default('semua');
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['peran', 'menu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hak_akses_perans');
    }
};
