<?php

namespace App\Enums;

/** Jenis kejadian absensi dalam satu hari kerja. */
enum JenisAbsensi: string
{
    case Masuk = 'masuk';

    /**
     * Absen KEMBALI dari istirahat (bukan saat mulai istirahat) — hanya
     * berlaku untuk posisi yang mengaktifkannya, dengan acuan "paling
     * lambat jam berapa" di setting jam kerja posisinya.
     */
    case Istirahat = 'istirahat';

    case Pulang = 'pulang';

    public function label(): string
    {
        return __('hr.absen_'.$this->value);
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Masuk => 'arrow-right-on-rectangle',
            self::Istirahat => 'cup',
            self::Pulang => 'arrow-left-on-rectangle',
        };
    }
}
