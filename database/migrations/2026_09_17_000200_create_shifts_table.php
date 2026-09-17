<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift kerja khusus, menempel ke KARYAWAN (bukan posisi): dalam satu posisi
 * pun karyawannya bisa berbeda shift. Karyawan tanpa shift = "Normal", yaitu
 * mengikuti jam kerja posisinya; begitu HR menyetel sebuah shift, jam masuk
 * dan pulang karyawan itu mengikuti shift tersebut.
 *
 * `lintas_hari` untuk shift yang pulangnya melewati tengah malam (mis.
 * 22:00–06:00) — dipakai saat mencocokkan absen pulang dengan absen masuk
 * hari sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->boolean('lintas_hari')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
