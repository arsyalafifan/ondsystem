<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai pesanan yang lahir otomatis dari NOO.
 *
 * Dipakai untuk dua hal yang sama pentingnya: memberi lencana "NOO" di menu
 * Pesanan supaya admin tahu asal-usulnya, dan MENGECUALIKANNYA dari
 * persetujuan massal — tanggal berangkat pesanan perdana belum tentu sama
 * dengan gelombang pesanan reguler hari itu, jadi ia harus disetujui satu
 * per satu dengan sadar.
 *
 * Sengaja berupa kolom relasi, bukan case baru di App\Enums\JenisPesanan:
 * perilaku pesanannya memang pesanan normal (dirutekan, dikirim, ditagih
 * seperti biasa), dan `JenisPesanan` punya `match` di banyak tempat yang
 * semuanya harus ikut diubah kalau ditambah case. Sebagai kolom relasi ia
 * juga sekalian menjadi tautan balik ke NOO-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->foreignId('noo_id')->nullable()->after('promo_id')
                ->constrained('noos')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('noo_id');
        });
    }
};
