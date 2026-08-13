<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            // Teks biasa, bukan enum basis data, supaya nilai baru nanti tidak
            // menuntut ALTER — keabsahannya dijaga oleh enum StatusBayar di PHP.
            $table->string('status_bayar', 20)->default('pending')->after('kurang_kirim')->index();
            $table->date('tanggal_lunas')->nullable()->after('status_bayar');
            $table->foreignId('dilunasi_oleh')->nullable()->after('tanggal_lunas')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropForeign(['dilunasi_oleh']);
            $table->dropColumn(['status_bayar', 'tanggal_lunas', 'dilunasi_oleh']);
        });
    }
};
