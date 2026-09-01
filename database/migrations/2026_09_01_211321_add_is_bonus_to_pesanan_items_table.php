<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai baris item sebagai bonus — harganya SELALU 0 berapa pun
     * jumlah dus-nya, dan tidak pernah digabung dengan baris biasa untuk
     * produk yang sama (lihat PesananService::buat()). Stoknya tetap
     * dikunci dan dipotong seperti item biasa; cuma harganya yang beda.
     */
    public function up(): void
    {
        Schema::table('pesanan_items', function (Blueprint $table) {
            $table->boolean('is_bonus')->default(false)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('pesanan_items', function (Blueprint $table) {
            $table->dropColumn('is_bonus');
        });
    }
};
