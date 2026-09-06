<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promo periode "beli N dus gratis M dus". Aktif/tidaknya murni
     * ditentukan tanggal_mulai/tanggal_selesai — tidak ada kolom aktif
     * terpisah, supaya promo otomatis muncul dan hilang sesuai periodenya
     * tanpa admin perlu menyalakan/mematikan manual. Produk yang berhak
     * (checklist dari Master Produk) ada di tabel pivot promo_produk.
     */
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            // Ambang total dus REGULER (bukan bonus) seluruh pesanan, dari
            // produk apa pun — bukan cuma produk yang berhak bonus.
            $table->unsignedInteger('minimal_dus');
            // Dus bonus FLAT yang terbuka begitu ambang tercapai, berapa
            // pun kelebihan pesanannya dari ambang.
            $table->unsignedInteger('bonus_dus');
            $table->timestamps();

            // Dipakai baik oleh pencarian promo aktif (aktifPada()) maupun
            // pemeriksaan bentrok periode saat promo baru dibuat/disunting.
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
