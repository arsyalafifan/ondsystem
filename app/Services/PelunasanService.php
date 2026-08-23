<?php

namespace App\Services;

use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\User;
use App\Support\Bahasa;
use RuntimeException;

/**
 * Rekonsiliasi pembayaran: menandai pesanan SELESAI sudah/belum dibayar
 * tokonya, sekaligus mencatat dari mana uangnya berasal.
 */
class PelunasanService
{
    /**
     * Toleransi pembulatan saat membandingkan jumlah cash+transfer dengan
     * tagihan. Rupiah tidak punya sen, tapi tagihan() bisa jadi pecahan kalau
     * harga satuan sendiri pecahan — 1 rupiah cukup longgar untuk pembulatan
     * wajar, tapi tetap menolak selisih yang berarti.
     */
    private const TOLERANSI_RUPIAH = 1.0;

    /**
     * Menandai pesanan lunas, dengan rincian sumber pembayarannya.
     *
     * Nominal cash dan transfer wajib diisi manual oleh admin — tidak ada
     * pembagian otomatis atau tebakan. Salah satu boleh nol asalkan
     * jumlahnya persis sama dengan tagihan; kalau tidak, ditolak sebelum
     * status pesanan berubah sama sekali.
     *
     * @throws RuntimeException bila pesanan belum SELESAI, nominal negatif,
     *                          atau jumlahnya tidak sama dengan tagihan
     */
    public function tandaiLunas(Pesanan $pesanan, User $admin, float $nominalCash, float $nominalTransfer): void
    {
        $this->pastikanSelesai($pesanan);

        if ($nominalCash < 0 || $nominalTransfer < 0) {
            throw new RuntimeException(__('pembayaran.galat_nominal_negatif'));
        }

        $total = $nominalCash + $nominalTransfer;
        $tagihan = (float) $pesanan->tagihan;

        if (abs($total - $tagihan) > self::TOLERANSI_RUPIAH) {
            throw new RuntimeException(__('pembayaran.galat_nominal_tidak_sesuai', [
                'total' => Bahasa::rupiah($total),
                'tagihan' => Bahasa::rupiah($tagihan),
            ]));
        }

        $pesanan->update([
            'status_bayar' => StatusBayar::Lunas,
            'tanggal_lunas' => today(),
            'dilunasi_oleh' => $admin->id,
            'nominal_cash' => $nominalCash,
            'nominal_transfer' => $nominalTransfer,
        ]);
    }

    public function tandaiBelumLunas(Pesanan $pesanan, User $admin): void
    {
        $this->pastikanSelesai($pesanan);

        $pesanan->update([
            'status_bayar' => StatusBayar::BelumLunas,
            'tanggal_lunas' => null,
            'dilunasi_oleh' => null,
            'nominal_cash' => null,
            'nominal_transfer' => null,
        ]);
    }

    private function pastikanSelesai(Pesanan $pesanan): void
    {
        if ($pesanan->status !== StatusPesanan::Selesai) {
            throw new RuntimeException(__('pembayaran.galat_bukan_selesai', ['kode' => $pesanan->kode]));
        }
    }
}
