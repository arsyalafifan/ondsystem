<?php

namespace App\Enums;

enum JenisPesanan: string
{
    case Normal = 'normal';

    /**
     * Pesanan yang dibuat driver di jalan untuk menghabiskan sisa muatan
     * akibat toko yang dibatalkan atau nota yang dicoret. Barangnya sudah ada
     * di mobil, jadi pesanan ini langsung selesai begitu dibuat.
     */
    case Kampas = 'kampas';

    /**
     * Penjualan langsung di tempat (POS) — tidak pernah melalui rute
     * pengantaran driver sama sekali. Barangnya diserahkan begitu pesanan
     * dibuat, jadi sama seperti Kampas: langsung SELESAI, tanpa fase
     * DELIVERY.
     */
    case Pos = 'pos';

    public function label(): string
    {
        return __('pengiriman.jenis_'.$this->value);
    }

    /**
     * Sumber pendapatan untuk keperluan rekap di layar Pendapatan: apakah
     * uangnya berasal dari pengantaran (rute biasa maupun kampas, keduanya
     * lewat kendaraan) atau dari penjualan langsung di tempat.
     */
    public function kategoriPendapatan(): string
    {
        return $this === self::Pos ? 'pos' : 'driver';
    }
}
