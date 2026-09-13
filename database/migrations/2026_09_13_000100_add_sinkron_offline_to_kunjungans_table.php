<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak kunjungan yang dikerjakan sales saat tidak ada jaringan, lalu
 * dikirim menyusul begitu sinyal kembali.
 *
 * `uuid_klien` dibuat di perangkat sebelum kunjungan terkirim, dan unik
 * di sini supaya pengiriman ulang (sinyal putus di tengah unggahan) tidak
 * pernah menghasilkan kunjungan kembar — basis data sendiri yang
 * menahannya, bukan hanya pemeriksaan aplikasi.
 *
 * `disinkronkan_at` sekaligus menjadi PENANDA offline: kunjungan yang
 * dikerjakan daring tidak pernah mengisinya. Waktu ini berasal dari jam
 * SERVER (kapan datanya benar-benar sampai), sementara `mulai_at`,
 * `selesai_at`, dan `kunjungan_fotos.diambil_at` berasal dari jam PONSEL
 * sales. Keduanya sengaja disimpan terpisah supaya admin bisa melihat
 * selisihnya — jam ponsel bisa diubah sendiri oleh pemakainya, jadi
 * kunjungan offline memang perlu diperiksa berbeda dari yang daring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungans', function (Blueprint $table) {
            $table->uuid('uuid_klien')->nullable()->unique()->after('asset_id_terpindai');
            $table->timestamp('disinkronkan_at')->nullable()->after('selesai_at');
        });

        Schema::table('kunjungan_fotos', function (Blueprint $table) {
            $table->timestamp('disinkronkan_at')->nullable()->after('diambil_at');
        });
    }

    public function down(): void
    {
        Schema::table('kunjungans', function (Blueprint $table) {
            $table->dropUnique(['uuid_klien']);
            $table->dropColumn(['uuid_klien', 'disinkronkan_at']);
        });

        Schema::table('kunjungan_fotos', function (Blueprint $table) {
            $table->dropColumn('disinkronkan_at');
        });
    }
};
