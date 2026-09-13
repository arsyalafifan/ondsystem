<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal-usul tiap foto bukti kunjungan.
 *
 * Sampai sekarang hanya ada satu jalan masuk — bidikan langsung dari aliran
 * kamera — sehingga tidak perlu dicatat. Begitu unggahan berkas dibuka
 * sebagai jalan keluar untuk kamera yang bermasalah, dua foto yang tampak
 * sama di layar admin bisa punya kekuatan bukti yang jauh berbeda, dan
 * bedanya harus tersimpan, bukan cuma tersirat.
 *
 * `sumber` bawaan 'kamera' supaya seluruh foto lama tetap tercatat sebagai
 * bidikan langsung — dan itu memang benar, karena saat itu tidak ada jalan
 * lain untuk memasukkan foto.
 *
 * `exif_diambil_at` menyimpan klaim waktu dari berkasnya sendiri. Sengaja
 * terpisah dari `diambil_at` (yang selalu terisi dan dipakai watermark):
 * kolom ini KOSONG berarti berkasnya tidak membawa keterangan waktu sama
 * sekali — hal yang wajib diketahui admin, dan akan hilang kalau keduanya
 * digabung jadi satu kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan_fotos', function (Blueprint $table) {
            $table->enum('sumber', ['kamera', 'unggah'])->default('kamera')->after('jenis');
            $table->timestamp('exif_diambil_at')->nullable()->after('diambil_at');
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan_fotos', function (Blueprint $table) {
            $table->dropColumn(['sumber', 'exif_diambil_at']);
        });
    }
};
