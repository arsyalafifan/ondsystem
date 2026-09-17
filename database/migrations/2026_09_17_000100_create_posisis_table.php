<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posisi kerja — pengelompokan CARA KERJA untuk absensi, berbeda dari
 * `jabatans` yang merupakan jabatan struktural. Beberapa jabatan berbeda
 * bisa memakai satu posisi yang sama (mis. "Staff Kantor").
 *
 * Aturan jam kerja & kondisi absen ditaruh sebagai kolom di tabel yang sama
 * (bukan tabel setting 1:1 terpisah): isinya memang milik posisi itu, dan
 * dua menu yang mengelolanya (Master Posisi untuk identitas, Setting Jam
 * Kerja untuk aturan) tinggal menyunting kolom yang berbeda pada baris yang
 * sama — tanpa join dan tanpa kemungkinan baris setting yatim.
 *
 * Kolom enum ditulis sebagai VARCHAR, bukan ENUM basis data: menambah
 * pilihan baru (mis. kondisi absen lain) cukup di App\Enums, tanpa migrasi
 * pengubah kolom — pelajaran dari `kunjungan_fotos.jenis`.
 *
 * Tidak memakai trait BerDepot, sama seperti Karyawan/Department/Jabatan:
 * ini master HR lintas gudang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posisis', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);

            // --- Setting jam kerja ---
            $table->time('jam_masuk')->default('08:00:00');
            $table->time('jam_pulang')->default('17:00:00');
            $table->unsignedSmallInteger('toleransi_telat_menit')->default(0);

            // Hari kerja ISO-8601 (1 = Senin ... 7 = Minggu). Dipakai
            // Monitoring untuk tahu hari ini wajib absen atau tidak, supaya
            // hari libur tidak terhitung alfa.
            $table->json('hari_kerja')->nullable();

            // --- Kondisi absen ---
            $table->string('lokasi_jenis', 20)->default('depot');
            $table->unsignedInteger('radius_meter')->default(100);

            $table->boolean('pakai_absen_istirahat')->default(false);
            $table->time('istirahat_paling_lambat')->nullable();

            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posisis');
    }
};
