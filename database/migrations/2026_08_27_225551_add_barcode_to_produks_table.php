<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            // Nullable dan unik: belum semua produk perlu ditempeli barcode
            // sekaligus, tapi begitu diisi tidak boleh dua produk berbagi
            // kode yang sama. Dipakai pencarian cepat di layar POS — lihat
            // Kasir::produkDariBarcode().
            $table->string('barcode', 64)->nullable()->unique()->after('kode');
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }
};
