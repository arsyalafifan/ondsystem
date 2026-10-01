<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jarak maksimal (km) dari gudang supaya sebuah toko layak diantar rider
 * (bukan rute kendaraan) — lihat App\Services\PengantaranRider\PengantaranRiderService::dalamRadius().
 * Default 13km sesuai aturan operasional awal, tapi bisa diatur beda tiap
 * gudang karena kondisi lapangan (kepadatan toko, kondisi jalan) berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->unsignedSmallInteger('radius_rider_km')->default(13)->after('min_dus_per_toko');
        });
    }

    public function down(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->dropColumn('radius_rider_km');
        });
    }
};
