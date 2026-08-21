<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua kelompok tabel yang sebelumnya memakai ->delete() sungguhan.
     *
     * Kelompok pertama aman langsung dipindah ke soft delete: setiap
     * kueri yang membacanya lewat Eloquent, jadi baris yang ditandai
     * terhapus otomatis tersembunyi di semua tempat tanpa perubahan lain
     * — lihat App\Models\{Wilayah,RoutingBatch,Kendaraan,KendaraanStop}.
     *
     * Kelompok kedua kolomnya disiapkan lebih dulu, tapi trait
     * SoftDeletes sengaja belum dipasang: `penugasan_sales` memakai
     * batasan unik (toko_id, bulan) sebagai satu-satunya cara mendeteksi
     * "toko sudah dipegang sales lain" (lihat PenugasanService::tetapkan
     * — tangkapan QueryException-nya bergantung pada baris lama itu
     * masih menghuni slot uniknya), dan `kunjungan_fotos` membuang berkas
     * fisiknya begitu foto diulang (lihat KunjunganService::simpanFoto) —
     * keduanya perlu keputusan produk (retensi bukti foto, alur
     * pulihkan-atau-buat-baru) sebelum baris lama boleh dianggap "hidup
     * kembali" begitu saja.
     */
    public function up(): void
    {
        foreach (['wilayahs', 'routing_batches', 'kendaraans', 'kendaraan_stops'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->softDeletes()->index();
            });
        }

        foreach (['penugasan_sales', 'kunjungan_fotos'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['wilayahs', 'routing_batches', 'kendaraans', 'kendaraan_stops', 'penugasan_sales', 'kunjungan_fotos'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }
    }
};
