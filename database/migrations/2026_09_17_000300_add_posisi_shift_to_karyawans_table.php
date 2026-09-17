<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posisi (aturan absensi) dan shift karyawan.
 *
 * Keduanya nullable di basis data — karyawan yang sudah tersimpan sebelum
 * fitur absensi ada tidak perlu di-backfill dulu, dan `shift_id` kosong
 * memang punya arti tersendiri: "Normal", ikut jam kerja posisinya.
 * Formulir Master Karyawan sendiri yang mewajibkan posisi diisi untuk
 * karyawan baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->foreignId('posisi_id')->nullable()->constrained('posisis')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posisi_id');
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
