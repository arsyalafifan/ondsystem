<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per KEJADIAN absen (masuk / kembali istirahat / pulang), bukan
 * satu baris per hari: tiap kejadian membawa foto selfie dan titik GPS-nya
 * sendiri. Status harian di layar Monitoring dihitung dari pengelompokan
 * baris-baris satu tanggal — tidak ada tabel harian terpisah yang harus
 * dijaga tetap sinkron.
 *
 * `posisi_id`, `shift_id`, dan `jam_acuan` adalah SNAPSHOT saat absen
 * terjadi: setting jam kerja boleh berubah kapan saja, dan riwayat "hari itu
 * dinilai terlambat terhadap jam berapa" tidak boleh ikut berubah.
 *
 * Waktunya selalu jam SERVER (lihat App\Services\Absensi\AturanAbsensi),
 * bukan jam ponsel yang bisa diubah sendiri pemakainya.
 *
 * Tidak memakai BerDepot — mengikuti `karyawans` yang memang lintas gudang;
 * `depot_id`/`toko_id` di sini adalah TEMPAT absen, bukan penanda tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained()->cascadeOnDelete();

            $table->date('tanggal');
            $table->string('jenis', 20);
            $table->timestamp('waktu');

            $table->string('status', 20);
            $table->unsignedSmallInteger('telat_menit')->default(0);
            $table->time('jam_acuan')->nullable();

            $table->foreignId('posisi_id')->nullable()->constrained('posisis')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            $table->string('foto');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('akurasi_m')->nullable();
            $table->unsignedInteger('jarak_m')->nullable();

            $table->string('lokasi_jenis', 20);
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('toko_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // Satu kali per jenis per hari — absen ulang ditolak, bukan
            // menimpa, supaya jam masuk yang sudah tercatat tidak bisa
            // "diperbaiki" sendiri oleh karyawannya.
            $table->unique(['karyawan_id', 'tanggal', 'jenis']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensis');
    }
};
