<?php

namespace App\Enums;

/**
 * Keadaan satu kunjungan pengiriman.
 *
 * `Dibatalkan` berarti tanggung jawab driver atas toko itu sudah tuntas —
 * tokonya dihitung selesai — tetapi dusnya tidak terkirim dan tetap ada di
 * mobil sampai diampaskan ke toko lain.
 */
enum StatusStop: string
{
    case Pending = 'pending';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return __('pengiriman.status_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-700 ring-gray-500/20',
            self::Selesai => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Dibatalkan => 'bg-red-100 text-red-800 ring-red-600/20',
        };
    }

    /** Kunjungan yang tidak lagi menunggu tindakan driver. */
    public function tuntas(): bool
    {
        return $this !== self::Pending;
    }
}
