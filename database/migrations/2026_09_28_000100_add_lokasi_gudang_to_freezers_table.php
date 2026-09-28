<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokasi freezer yang BELUM terpasang di toko: gudang tempat ia disimpan,
 * dicatat lewat menu "Freezer ke Gudang" (atau otomatis saat Tarik Freezer
 * selesai).
 *
 * Gudang sebuah freezer di Master Freezer dihitung seperti COALESCE: kalau
 * freezernya terpasang di toko, gudang mengikuti gudang toko itu; kalau
 * tidak, dipakai kolom ini. Karena itu nilainya SENGAJA dibiarkan tersisa
 * (bukan dikosongkan) saat freezer dipasang ke toko — selama ada toko,
 * kolom ini diabaikan.
 *
 * Bukan `depot_id`: kolom itu di aplikasi ini berarti "baris ini milik
 * depot X" (tenant), sedangkan freezer sengaja tidak terikat depot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freezers', function (Blueprint $table) {
            $table->foreignId('depot_simpan_id')->nullable()->after('aktif')
                ->constrained('depots')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('gudang_dicatat_oleh')->nullable()->after('depot_simpan_id')
                ->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('gudang_dicatat_at')->nullable()->after('gudang_dicatat_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('freezers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gudang_dicatat_oleh');
            $table->dropConstrainedForeignId('depot_simpan_id');
            $table->dropColumn('gudang_dicatat_at');
        });
    }
};
