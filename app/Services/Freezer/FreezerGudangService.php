<?php

namespace App\Services\Freezer;

use App\Models\Depot;
use App\Models\Freezer;
use App\Models\User;
use RuntimeException;

/**
 * Mencatat di gudang mana sebuah freezer disimpan selagi belum terpasang di
 * toko — dipakai menu "Freezer ke Gudang" (dipindai manusia) dan Tarik
 * Freezer (otomatis, begitu freezernya sampai kembali).
 *
 * Sengaja hanya untuk freezer TANPA toko: gudang freezer yang terpasang
 * ikut gudang tokonya (lihat Freezer::gudangSaatIni()), jadi mencatat
 * gudang lain di sini cuma menghasilkan data yang tidak pernah dibaca.
 */
class FreezerGudangService
{
    /** @throws RuntimeException bila freezernya masih terpasang di toko atau gudangnya tidak aktif */
    public function catat(Freezer $freezer, Depot $depot, User $pencatat): void
    {
        $toko = $freezer->toko()->first(['id', 'nama']);

        if ($toko !== null) {
            throw new RuntimeException(__('freezer_gudang.galat_masih_di_toko', [
                'idn' => $freezer->idn,
                'toko' => $toko->nama,
            ]));
        }

        if (! $depot->aktif) {
            throw new RuntimeException(__('freezer_gudang.galat_gudang_tidak_aktif'));
        }

        $freezer->update([
            'depot_simpan_id' => $depot->id,
            'gudang_dicatat_oleh' => $pencatat->id,
            'gudang_dicatat_at' => now(),
        ]);
    }
}
