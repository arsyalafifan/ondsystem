<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produk yang berhak dijadikan item bonus untuk satu promo. cascadeOnDelete
     * di kedua sisi aman: Produk tidak pernah benar-benar dihapus lewat UI
     * (Master Produk cuma mengubah `aktif`), dan Promo sendiri hanya boleh
     * dihapus kalau belum pernah dipakai pesanan mana pun (lihat
     * DaftarPromo::hapus()) — jadi cascade di sini tidak pernah meruntuhkan
     * data yang masih berarti.
     */
    public function up(): void
    {
        Schema::create('promo_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promo_id', 'produk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_produk');
    }
};
