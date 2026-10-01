<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sekali jalan: melepas jadwal kunjungan toko yang SUDAH nonaktif sebelum
 * aturan "toko nonaktif otomatis keluar dari rute sales" ada (lihat
 * App\Services\Kunjungan\PenugasanTokoService::lepasToko(), yang sejak
 * sekarang dipanggil otomatis setiap kali toko dinonaktifkan).
 *
 * Query builder mentah (bukan model) supaya tidak bergantung pada
 * DepotContext — migrasi berjalan tanpa konteks gudang, dan baris dari
 * SEMUA gudang memang perlu dibersihkan.
 *
 * Tidak bisa dibalik: jadwal yang dilepas tidak disimpan (sesuai keputusan
 * — toko yang diaktifkan lagi ditugaskan ulang oleh admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tokoNonaktif = DB::table('tokos')->where('aktif', false)->select('id');

        $dilepas = DB::table('penugasan_tokos')->whereIn('toko_id', $tokoNonaktif)->delete();
        DB::table('penugasan_toko_defaults')->whereIn('toko_id', $tokoNonaktif)->delete();

        if ($dilepas === 0) {
            return;
        }

        // Target minggu berjalan disamakan dengan jadwal terbaru — persis
        // yang dilakukan PeriodeKunjunganService::segarkanTarget() saat
        // admin mengubah penugasan. Minggu yang sudah lewat tidak disentuh.
        DB::table('periode_sales')
            ->whereIn('periode_kunjungan_id', DB::table('periode_kunjungans')->where('status', 'berjalan')->select('id'))
            ->update([
                'target_toko' => DB::raw('(SELECT COUNT(*) FROM penugasan_tokos pt
                    WHERE pt.sales_id = periode_sales.sales_id AND pt.depot_id = periode_sales.depot_id)'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Sengaja kosong — lihat docblock.
    }
};
