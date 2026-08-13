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

    public function label(): string
    {
        return __('pengiriman.jenis_'.$this->value);
    }
}
