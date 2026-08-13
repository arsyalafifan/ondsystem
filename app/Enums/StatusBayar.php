<?php

namespace App\Enums;

enum StatusBayar: string
{
    case Pending = 'pending';
    case Lunas = 'lunas';
    case BelumLunas = 'belum_lunas';

    public function label(): string
    {
        return __('pembayaran.status_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Lunas => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::BelumLunas => 'bg-red-100 text-red-800 ring-red-600/20',
        };
    }
}
