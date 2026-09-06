<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promo apa (kalau ada) yang menyumbang item bonus pada pesanan ini —
     * dicatat di level pesanan, bukan per item, karena V1 cuma mendukung
     * satu promo aktif dalam satu waktu. Dipakai untuk pelaporan ("berapa
     * pesanan yang memakai promo ini") dan supaya PesananService::buat()
     * bisa mencatat promo yang SUNGGUH divalidasi di server, bukan sekadar
     * dipercaya dari input klien.
     */
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->foreignId('promo_id')->nullable()->after('sales_id')
                ->constrained('promos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropForeign(['promo_id']);
            $table->dropColumn('promo_id');
        });
    }
};
