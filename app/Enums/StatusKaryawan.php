<?php

namespace App\Enums;

enum StatusKaryawan: string
{
    case Tetap = 'tetap';
    case Kontrak = 'kontrak';

    public function label(): string
    {
        return __('hr.status_karyawan_'.$this->value);
    }
}
