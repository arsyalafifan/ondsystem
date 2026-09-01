<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produk yang sama sekarang boleh muncul dua kali pada satu pesanan: sekali
 * di baris biasa, sekali lagi di baris bonus (harga_satuan selalu 0) — dua
 * baris terpisah yang sengaja TIDAK digabung, supaya faktur menampilkan
 * keduanya sebagai item berbeda. Batasan unik lama (pesanan_id, produk_id)
 * menolak kombinasi itu; diganti supaya is_bonus ikut jadi pembeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Urutan tambah-dulu-baru-hapus disengaja: kolom produk_id juga
        // dipakai foreign key-nya, dan MySQL/InnoDB butuh SATU index yang
        // menutupinya setiap saat untuk menjaga constraint itu — melepas
        // index unik lama sebelum index baru ada membuat MySQL menolak
        // (error 1553, "needed in a foreign key constraint").
        Schema::table('pesanan_items', function (Blueprint $table) {
            $table->unique(['pesanan_id', 'produk_id', 'is_bonus']);
        });

        Schema::table('pesanan_items', function (Blueprint $table) {
            $table->dropUnique(['pesanan_id', 'produk_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pesanan_items', function (Blueprint $table) {
            $table->unique(['pesanan_id', 'produk_id']);
        });

        Schema::table('pesanan_items', function (Blueprint $table) {
            $table->dropUnique(['pesanan_id', 'produk_id', 'is_bonus']);
        });
    }
};
