<?php

namespace App\Enums;

/** Status satu kejadian absensi terhadap jam acuannya. */
enum StatusAbsensi: string
{
    case TepatWaktu = 'tepat_waktu';
    case Terlambat = 'terlambat';

    /** Absen pulang sebelum jam pulang yang seharusnya. */
    case PulangCepat = 'pulang_cepat';

    public function label(): string
    {
        return __('hr.status_absen_'.$this->value);
    }
}
