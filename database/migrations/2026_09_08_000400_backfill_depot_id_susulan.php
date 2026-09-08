<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susulan dari 2026_09_08_000300_backfill_depot_id.php.
 *
 * Ditemukan di produksi Perawang: ada jeda waktu antara migrasi backfill
 * Stage 2 dijalankan dan kode Stage 3 (yang mulai menulis depot_id lewat
 * trait BerDepot) benar-benar di-deploy — selama jeda itu operasional
 * jalan terus, jadi baris BARU (bukan baris lama yang sudah dibackfill)
 * ikut tersimpan dengan depot_id NULL. Simtomnya: dashboard superadmin
 * yang terkunci ke satu depot tidak melihat kendaraan hari ini yang
 * sebenarnya ada, karena baris itu tidak match filter depot_id manapun.
 *
 * Logikanya identik dengan migrasi Stage 2 (idempotent, whereNull, chunked)
 * — sengaja migrasi terpisah, bukan mengedit yang lama, supaya riwayat apa
 * yang terjadi tetap jujur di git log. Aman dijalankan berkali-kali di
 * lingkungan manapun: pada instalasi yang tidak pernah punya jeda ini,
 * seluruh whereNull() di bawah tidak menemukan baris apapun.
 */
return new class extends Migration
{
    private array $tabel = [
        'wilayahs',
        'tokos',
        'produks',
        'pesanans',
        'pesanan_items',
        'routing_batches',
        'kendaraans',
        'kendaraan_stops',
        'stok_mutasis',
        'penugasan_sales',
        'penugasan_tokos',
        'penugasan_toko_defaults',
        'periode_kunjungans',
        'periode_sales',
        'kunjungans',
        'kunjungan_fotos',
        'promos',
        'promo_produk',
        'pengaturan_kunjungans',
    ];

    public function up(): void
    {
        $depotId = DB::table('depots')->orderBy('id')->value('id');

        if ($depotId === null) {
            return;
        }

        foreach ($this->tabel as $nama) {
            $this->isiSatuTabel($nama, $depotId);
        }

        // users: superadmin tetap harus NULL — hanya admin/sales/driver
        // yang disusulkan.
        DB::table('users')->select('id')->whereNull('depot_id')
            ->where('role', '!=', 'superadmin')
            ->orderBy('id')
            ->chunkById(500, function ($baris) use ($depotId) {
                DB::table('users')->whereIn('id', $baris->pluck('id'))
                    ->update(['depot_id' => $depotId]);
            });
    }

    private function isiSatuTabel(string $tabel, int $depotId): void
    {
        DB::table($tabel)->select('id')->whereNull('depot_id')
            ->orderBy('id')
            ->chunkById(500, function ($baris) use ($tabel, $depotId) {
                DB::table($tabel)->whereIn('id', $baris->pluck('id'))
                    ->update(['depot_id' => $depotId]);
            });
    }

    /**
     * Tidak ada down() yang membalik ke NULL: berbeda dari migrasi Stage 2,
     * kita tidak bisa membedakan lagi mana baris yang "memang sudah lama"
     * dari mana yang "baru disusulkan" begitu keduanya sama-sama terisi
     * depot_id — membalikkannya akan menghapus informasi baris asli dari
     * migrasi Stage 2 juga.
     */
    public function down(): void
    {
        //
    }
};
