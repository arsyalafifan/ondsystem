<?php

namespace App\Enums;

enum StatusPesanan: string
{
    case Order = 'order';
    case Process = 'process';
    case Delivery = 'delivery';
    case Selesai = 'selesai';
    case Cancel = 'cancel';

    /** Nama status dalam bahasa yang sedang aktif. */
    public function label(): string
    {
        return __('status.'.$this->value);
    }

    public function keterangan(): string
    {
        return __('status.ket_'.$this->value);
    }

    /** Warna marker pada peta monitoring. */
    public function warna(): string
    {
        return match ($this) {
            self::Order => '#2563eb',
            self::Process => '#f59e0b',
            self::Delivery => '#7c3aed',
            self::Selesai => '#059669',
            self::Cancel => '#dc2626',
        };
    }

    /** Kelas badge Tailwind. */
    public function badge(): string
    {
        return match ($this) {
            self::Order => 'bg-blue-100 text-blue-800 ring-blue-600/20',
            self::Process => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Delivery => 'bg-violet-100 text-violet-800 ring-violet-600/20',
            self::Selesai => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Cancel => 'bg-red-100 text-red-800 ring-red-600/20',
        };
    }

    /**
     * Status yang membuat sebuah toko dianggap masih punya pesanan berjalan.
     * Selama salah satu status ini ada, toko tidak boleh dipesan lagi.
     *
     * @return array<int, string>
     */
    public static function aktif(): array
    {
        return [self::Order->value, self::Process->value, self::Delivery->value];
    }

    /** Status yang masih boleh dibatalkan. */
    public function bisaDibatalkan(): bool
    {
        return in_array($this, [self::Order, self::Process, self::Delivery], true);
    }
}
