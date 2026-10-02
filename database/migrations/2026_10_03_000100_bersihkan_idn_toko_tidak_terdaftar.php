<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sekali jalan: IDN (tokos.asset_id) yang tidak terdaftar di Master Freezer
 * dihilangkan dari toko, supaya tidak ada lagi data rancu (IDN menempel di
 * toko tapi di Master Freezer tidak ada, atau sudah dipakai freezer/toko
 * lain). Sumber IDN yang sah hanya Master Freezer.
 *
 * Urutannya hati-hati karena ini menghapus data:
 * - IDN yang cuma beda format (huruf kecil / spasi) dari freezer yang sah
 *   DAN belum dipegang toko lain dirapikan ke bentuk freezernya, bukan
 *   dihapus.
 * - Sisanya dikosongkan (asset_id dan freezer_tipe — tipe mengikuti
 *   freezer, jadi tak bermakna lagi tanpa IDN).
 * - Semua yang dikosongkan dicatat ke berkas CSV di storage/app/cadangan
 *   supaya bisa dipulihkan manual kalau ternyata keliru.
 *
 * Query builder mentah (bukan model) agar tak bergantung DepotContext —
 * baris dari SEMUA gudang diproses. Tidak bisa dibalik otomatis.
 */
return new class extends Migration
{
    private function normal(?string $idn): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/', '', (string) $idn));
    }

    public function up(): void
    {
        $idnFreezer = DB::table('freezers')->pluck('idn');

        // Pencocokan memakai bentuk ternormalisasi, bukan perbandingan
        // collation database, supaya hasilnya sama di MySQL maupun SQLite.
        $freezerPerNormal = $idnFreezer->mapWithKeys(fn ($idn) => [$this->normal($idn) => $idn]);
        $idnSah = $idnFreezer->flip();

        $yatim = DB::table('tokos')
            ->whereNotNull('asset_id')
            ->get(['id', 'kode', 'nama', 'depot_id', 'asset_id', 'freezer_tipe'])
            ->reject(fn ($t) => isset($idnSah[$t->asset_id]));

        if ($yatim->isEmpty()) {
            return;
        }

        // IDN freezer yang SUDAH dipegang toko yang valid.
        $dipegang = DB::table('tokos')->whereIn('asset_id', $idnFreezer)->pluck('id', 'asset_id')->all();

        $dikosongkan = [];

        foreach ($yatim as $toko) {
            $kanonik = $freezerPerNormal[$this->normal($toko->asset_id)] ?? null;

            if ($kanonik !== null && ! isset($dipegang[$kanonik])) {
                DB::table('tokos')->where('id', $toko->id)->update(['asset_id' => $kanonik, 'updated_at' => now()]);
                $dipegang[$kanonik] = $toko->id;

                continue;
            }

            DB::table('tokos')->where('id', $toko->id)->update([
                'asset_id' => null,
                'freezer_tipe' => null,
                'updated_at' => now(),
            ]);

            $dikosongkan[] = $toko;
        }

        if ($dikosongkan === []) {
            return;
        }

        $csv = "toko_id,kode,nama,depot_id,asset_id_lama,freezer_tipe_lama\n";
        foreach ($dikosongkan as $t) {
            $csv .= implode(',', array_map(
                fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
                [$t->id, $t->kode, $t->nama, $t->depot_id, $t->asset_id, $t->freezer_tipe],
            ))."\n";
        }

        Storage::disk('local')->put('cadangan/idn-toko-dikosongkan-'.now()->format('Ymd-His').'.csv', $csv);
    }

    public function down(): void
    {
        // Sengaja kosong — pemulihan manual lewat berkas CSV cadangan.
    }
};
