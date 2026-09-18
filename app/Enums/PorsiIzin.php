<?php

namespace App\Enums;

/**
 * Seberapa banyak dari satu hari kerja yang ditinggalkan. Setengah hari =
 * separuh jam kerja BERSIH (tanpa istirahat) — lihat
 * App\Services\Absensi\AturanAbsensi::menitSetengahHari().
 */
enum PorsiIzin: string
{
    case Penuh = 'penuh';

    /** Tidak masuk di paruh pertama; datang di paruh kedua. */
    case ParuhPertama = 'paruh_pertama';

    /** Masuk di paruh pertama; pulang lebih awal. */
    case ParuhKedua = 'paruh_kedua';

    public function label(): string
    {
        return __('izin.porsi_'.$this->value);
    }

    public function setengahHari(): bool
    {
        return $this !== self::Penuh;
    }

    public function faktor(): float
    {
        return $this->setengahHari() ? 0.5 : 1.0;
    }
}
