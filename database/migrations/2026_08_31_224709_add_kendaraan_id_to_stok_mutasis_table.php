<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai mutasi 'release' yang berasal dari admin mengembalikan sisa
     * kampas satu kendaraan ke gudang (bukan dari pesanan mana pun — sisa
     * satu kendaraan biasanya berasal dari beberapa toko yang batal/dicoret
     * sekaligus, jadi tidak masuk akal dikaitkan ke satu pesanan tunggal).
     * PengirimanService::jatahKampas() menjumlahkan mutasi ini per kendaraan
     * supaya sisa yang sudah dikembalikan tidak dihitung tersedia lagi.
     */
    public function up(): void
    {
        Schema::table('stok_mutasis', function (Blueprint $table) {
            $table->foreignId('kendaraan_id')->nullable()->after('pesanan_id')
                ->constrained('kendaraans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stok_mutasis', function (Blueprint $table) {
            $table->dropForeign(['kendaraan_id']);
            $table->dropColumn('kendaraan_id');
        });
    }
};
