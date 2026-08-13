<?php

namespace App\Services;

use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Models\Kendaraan;
use App\Models\Pesanan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rekonsiliasi pembayaran: menandai pesanan SELESAI sudah/belum dibayar
 * tokonya, per pesanan maupun secara massal per kendaraan.
 */
class PelunasanService
{
    public function tandaiLunas(Pesanan $pesanan, User $admin): void
    {
        $this->pastikanSelesai($pesanan);

        $pesanan->update([
            'status_bayar' => StatusBayar::Lunas,
            'tanggal_lunas' => today(),
            'dilunasi_oleh' => $admin->id,
        ]);
    }

    public function tandaiBelumLunas(Pesanan $pesanan, User $admin): void
    {
        $this->pastikanSelesai($pesanan);

        $pesanan->update([
            'status_bayar' => StatusBayar::BelumLunas,
            'tanggal_lunas' => null,
            'dilunasi_oleh' => null,
        ]);
    }

    /**
     * Melunasi semua pesanan yang masih PENDING di sebuah kendaraan.
     *
     * Yang sudah eksplisit ditandai BELUM LUNAS sengaja tidak disentuh: ini
     * cuma jalan pintas untuk toko yang belum diputuskan sama sekali, bukan
     * cara membatalkan keputusan "belum lunas" yang sudah diambil.
     */
    public function lunasiSisaKendaraan(Kendaraan $kendaraan, User $admin): int
    {
        return DB::transaction(function () use ($kendaraan, $admin): int {
            $pesanans = Pesanan::query()
                ->whereHas('stop', fn ($q) => $q->where('kendaraan_id', $kendaraan->id))
                ->where('status', StatusPesanan::Selesai)
                ->where('status_bayar', StatusBayar::Pending)
                ->lockForUpdate()
                ->get();

            foreach ($pesanans as $pesanan) {
                $this->tandaiLunas($pesanan, $admin);
            }

            return $pesanans->count();
        });
    }

    private function pastikanSelesai(Pesanan $pesanan): void
    {
        if ($pesanan->status !== StatusPesanan::Selesai) {
            throw new RuntimeException(__('pembayaran.galat_bukan_selesai', ['kode' => $pesanan->kode]));
        }
    }
}
