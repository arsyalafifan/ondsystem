<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto produk (diunggah di Master Produk) — ditampilkan di kartu pemilihan
 * produk Input Pesanan & POS. Nullable: produk tanpa foto tetap tampil
 * dengan ikon pengganti, tidak ada yang wajib diisi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
