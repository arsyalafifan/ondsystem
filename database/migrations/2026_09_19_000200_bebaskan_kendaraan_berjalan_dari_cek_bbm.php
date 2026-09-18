<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollout gerbang "Cek Kendaraan" (foto KM+BBM wajib sebelum berangkat,
 * lihat catatan_bbms) tidak boleh mengganggu kendaraan yang SUDAH di jalan
 * sebelum fitur ini ada — driver-nya sudah benar-benar berangkat, jadi
 * "sebelum berangkat" tidak lagi berarti apa-apa untuk mereka, dan memaksa
 * mengisi foto retroaktif di tengah rute cuma membingungkan.
 *
 * `bebas_cek_bbm` menandai kendaraan begitu: App\Livewire\Driver\PilihMobil
 * dan App\Livewire\Driver\DaftarKunjungan melewati gerbang berangkat untuk
 * kendaraan bertanda ini. Kendaraan BARU (dibuat setelah migrasi ini)
 * selalu mulai `false` — hanya kendaraan yang sudah diambil driver
 * (diambil_at terisi) SEBELUM migrasi ini berjalan yang dibebaskan.
 *
 * Pengingat foto "Saat Kembali" (bukan gerbang, cuma banner) TIDAK ikut
 * dibebaskan — tetap muncul begitu kendaraan ini selesai, karena itu murni
 * pengingat maju ke depan, tidak pernah memblokir apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraans', function (Blueprint $table) {
            $table->boolean('bebas_cek_bbm')->default(false)->after('status');
        });

        DB::table('kendaraans')
            ->whereIn('status', ['jalan', 'selesai'])
            ->whereNotNull('driver_id')
            ->whereNotNull('diambil_at')
            ->update(['bebas_cek_bbm' => true]);
    }

    public function down(): void
    {
        Schema::table('kendaraans', function (Blueprint $table) {
            $table->dropColumn('bebas_cek_bbm');
        });
    }
};
