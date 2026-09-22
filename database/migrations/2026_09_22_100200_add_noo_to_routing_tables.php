<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membuka jalur routing kedua: pengantaran freezer untuk NOO.
 *
 * Mesin routingnya (App\Services\Routing\*) sudah tidak peduli isi muatan —
 * ia hanya menyusun titik-titik koordinat berkapasitas. Yang masih terikat
 * pesanan adalah penyimpanannya, dan itulah yang dilonggarkan di sini:
 *
 * - `routing_batches.jenis` memisahkan dua antrean routing supaya rute
 *   freezer tidak pernah tercampur di layar rute reguler, dan sebaliknya.
 * - `kendaraan_stops.noo_id` menggantikan peran `pesanan_id` untuk stop
 *   freezer, yang memang belum punya pesanan apa pun — pesanan perdananya
 *   baru lahir SETELAH freezernya terpasang.
 *
 * `pesanan_id` karena itu jadi nullable. Indeks uniknya SENGAJA tidak
 * diubah: MySQL maupun SQLite memperlakukan NULL sebagai nilai yang selalu
 * berbeda, jadi berapa pun banyaknya stop freezer tanpa pesanan, batasan
 * "satu pesanan hanya boleh ada di satu rute" tetap utuh untuk stop reguler.
 *
 * Pembedanya tetap `kendaraan_stops.jenis` yang sudah ada sejak pengiriman
 * lapangan (`rute`/`kampas`), kini bertambah `noo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routing_batches', function (Blueprint $table) {
            $table->string('jenis', 20)->default('reguler')->index()->after('kode');
        });

        Schema::table('kendaraan_stops', function (Blueprint $table) {
            $table->foreignId('noo_id')->nullable()->after('pesanan_id')
                ->constrained('noos')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('kendaraan_stops', function (Blueprint $table) {
            $table->foreignId('pesanan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kendaraan_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('noo_id');
        });

        Schema::table('routing_batches', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
