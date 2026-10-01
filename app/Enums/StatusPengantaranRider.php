<?php

namespace App\Enums;

/**
 * Perjalanan satu pengantaran rider: `Tersedia` (di pool gudang, belum
 * diambil siapa pun) → `Diambil` (satu rider sedang membawanya) →
 * `Selesai`. `Diambil` bisa kembali ke `Tersedia` lewat
 * PengantaranRiderService::lepas() — rider salah pencet atau batal
 * membawanya, dicatat lewat `dilepas_at`, bukan status terpisah.
 */
enum StatusPengantaranRider: string
{
    case Tersedia = 'tersedia';
    case Diambil = 'diambil';
    case Selesai = 'selesai';

    public function label(): string
    {
        return __('pengantaran_rider.status_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Tersedia => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Diambil => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Selesai => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        };
    }
}
