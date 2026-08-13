<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peran bertambah satu kemungkinan: 'superadmin'. Kolomnya diubah
        // menjadi teks biasa, bukan enum basis data, supaya penambahan nilai
        // berikutnya tidak menuntut pernyataan ALTER yang berbeda-beda di
        // tiap mesin basis data. Nilai yang sah dijaga oleh enum PHP
        // PeranPengguna.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('sales')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'sales', 'driver'])->default('sales')->change();
        });
    }
};
