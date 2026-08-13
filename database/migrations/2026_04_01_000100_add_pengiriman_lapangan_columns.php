<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom-kolom untuk tiga tindakan driver di lapangan: membatalkan toko,
 * mencoret nota, dan mengampaskan sisa muatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraan_stops', function (Blueprint $table) {
            // Jumlah yang benar-benar diterima toko. Berbeda dari total_dus
            // ketika notanya dicoret, dan nol ketika tokonya dibatalkan.
            $table->unsignedInteger('total_dus_terkirim')->default(0)->after('total_dus');

            // Kunjungan biasa berasal dari hasil routing; kunjungan kampas
            // ditambahkan driver di jalan untuk menghabiskan sisa muatan.
            $table->string('jenis', 20)->default('rute')->after('urutan')->index();

            $table->string('alasan_batal')->nullable()->after('catatan_driver');
            $table->text('catatan_batal')->nullable()->after('alasan_batal');
            $table->timestamp('dibatalkan_at')->nullable()->after('catatan_batal');
        });

        // Status bertambah satu kemungkinan: 'dibatalkan'. Kolomnya diubah
        // menjadi teks biasa, bukan enum basis data, supaya penambahan nilai
        // berikutnya tidak menuntut pernyataan ALTER yang berbeda-beda di tiap
        // mesin basis data. Nilai yang sah dijaga oleh enum PHP.
        Schema::table('kendaraan_stops', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        // Kunjungan yang sudah selesai sebelum kolom ini ada dianggap
        // terkirim penuh, sesuai keadaannya waktu itu.
        DB::table('kendaraan_stops')
            ->where('status', 'selesai')
            ->update(['total_dus_terkirim' => DB::raw('total_dus')]);

        Schema::table('pesanans', function (Blueprint $table) {
            $table->string('jenis', 20)->default('normal')->after('status')->index();

            // Ditandai ketika notanya dicoret, supaya admin bisa menyaring
            // pesanan yang tidak terkirim penuh.
            $table->boolean('kurang_kirim')->default(false)->after('jenis')->index();
        });

        Schema::table('pesanan_items', function (Blueprint $table) {
            // Kosong berarti terkirim sesuai jumlah yang dipesan.
            $table->unsignedInteger('jumlah_dus_terkirim')->nullable()->after('jumlah_dus');
        });

        Schema::table('kendaraans', function (Blueprint $table) {
            // Salinan muatan saat routing disetujui, dipakai sebagai penyebut
            // persentase pengiriman. Disimpan terpisah karena total_dus ikut
            // berubah ketika driver menambah kunjungan kampas, sedangkan
            // targetnya harus tetap: 100% berarti seluruh dus yang dibawa
            // benar-benar keluar dari mobil.
            $table->unsignedInteger('target_dus')->default(0)->after('total_dus');
        });

        DB::table('kendaraans')->update(['target_dus' => DB::raw('total_dus')]);
    }

    public function down(): void
    {
        Schema::table('kendaraan_stops', function (Blueprint $table) {
            $table->dropColumn(['total_dus_terkirim', 'jenis', 'alasan_batal', 'catatan_batal', 'dibatalkan_at']);
        });

        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropColumn(['jenis', 'kurang_kirim']);
        });

        Schema::table('pesanan_items', function (Blueprint $table) {
            $table->dropColumn('jumlah_dus_terkirim');
        });

        Schema::table('kendaraans', function (Blueprint $table) {
            $table->dropColumn('target_dus');
        });
    }
};
