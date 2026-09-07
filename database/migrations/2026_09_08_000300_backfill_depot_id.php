<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 2 dari rollout multi-depot (lihat ~/.claude/plans/tranquil-wondering-mango.md).
 *
 * Mengisi depot_id semua baris yang sudah ada dengan depot pertama (satu-
 * satunya depot yang ada di titik ini, dibuat migrasi sebelumnya). Dipecah
 * per-batch lewat chunkById — bukan satu UPDATE raksasa tanpa batas, dan
 * bukan UPDATE...LIMIT (tidak portabel ke SQLite yang dipakai pengujian) —
 * supaya aman dijalankan di tabel produksi berapapun besarnya tanpa
 * mengunci tabel dalam waktu lama sekaligus.
 *
 * Masih nol perubahan kode aplikasi: belum ada satupun query yang membaca
 * kolom ini, jadi tahap ini pun aman di-deploy ke Perawang tanpa downtime.
 */
return new class extends Migration
{
    /** Semua tabel kecuali `users`, yang punya aturan khusus (lihat bawah). */
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
            // Tidak ada depot sama sekali — tidak mungkin terjadi lewat alur
            // normal (migrasi sebelumnya selalu membuat satu), tapi dijaga
            // eksplisit daripada diam-diam melewati backfill.
            throw new RuntimeException('Tidak ada baris di tabel depots — jalankan migrasi create_depots_table dulu.');
        }

        foreach ($this->tabel as $nama) {
            $this->isiSatuTabel($nama, $depotId);
        }

        // users: superadmin sengaja dibiarkan depot_id = NULL (bukan bug —
        // itulah yang membuatnya "milik semua depot" sejak awal). Hanya
        // admin/sales/driver yang di-backfill ke depot pertama.
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

    public function down(): void
    {
        // Balik ke NULL untuk semua tabel yang di-backfill up() — termasuk
        // users, walau baris superadmin di sana memang sudah NULL sejak awal.
        foreach ([...$this->tabel, 'users'] as $nama) {
            DB::table($nama)->update(['depot_id' => null]);
        }
    }
};
