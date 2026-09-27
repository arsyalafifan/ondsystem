<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jalur routing ketiga: pengambilan freezer untuk Tarik Freezer — pasangan
 * dari add_noo_to_routing_tables, arah kebalikannya. `kendaraan_stops.jenis`
 * bertambah nilai `tarik`; `pesanan_id` dan `noo_id` sudah nullable dari
 * migrasi sebelumnya jadi tetap begitu apa adanya untuk stop jenis ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraan_stops', function (Blueprint $table) {
            $table->foreignId('tarik_freezer_id')->nullable()->after('noo_id')
                ->constrained('tarik_freezers')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kendaraan_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarik_freezer_id');
        });
    }
};
