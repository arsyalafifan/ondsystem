<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data departemen perusahaan — dipakai mengklasifikasikan karyawan di modul
 * HR. Sengaja TIDAK di-scope per depot (tidak ada kolom depot_id, model-nya
 * tidak memakai trait BerDepot): departemen adalah struktur organisasi
 * lintas-gudang ("Finance", "Gudang", dst berlaku company-wide), bukan data
 * milik satu tenant/depot tertentu — beda dari mayoritas tabel master lain
 * di aplikasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
