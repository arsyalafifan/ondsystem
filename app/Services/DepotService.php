<?php

namespace App\Services;

use App\Models\Depot;
use App\Models\PengaturanKunjungan;
use Illuminate\Support\Facades\DB;

/**
 * Membuka depot baru — satu-satunya jalan resmi selain migrasi Stage 1.
 * Depot baru mulai kosong sepenuhnya (nol toko, produk, user, dst) kecuali
 * baris `pengaturan_kunjungans` awal, yang wajib ada sebelum layar
 * Penugasan Toko/Kunjungan bisa dibuka untuk depot itu (lihat
 * PengaturanKunjungan::ambil() — firstOrFail(), bukan firstOrCreate()).
 */
class DepotService
{
    public function buat(array $data): Depot
    {
        return DB::transaction(function () use ($data): Depot {
            $depot = Depot::create($data);

            // creating() BerDepot tidak berlaku di sini: PengaturanKunjungan
            // dibuat SEBELUM DepotContext pindah ke depot baru ini (kalau
            // sama sekali belum ada konteks aktif, mis. dipanggil dari
            // command, auto-stamp-nya malah akan gagal). depot_id disebut
            // eksplisit, bukan mengandalkan konteks yang sedang aktif.
            PengaturanKunjungan::create([
                'depot_id' => $depot->id,
                'maks_toko_per_hari' => 20,
            ]);

            return $depot;
        });
    }

    public function ubah(Depot $depot, array $data): Depot
    {
        $depot->update($data);

        return $depot;
    }
}
