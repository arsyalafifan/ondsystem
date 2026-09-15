<?php

namespace App\Enums;

/** Data mana yang boleh dilihat satu peran di sebuah menu. */
enum CakupanData: string
{
    case Semua = 'semua';
    case Sendiri = 'sendiri';

    public function label(): string
    {
        return __('hak_akses.cakupan_'.$this->value);
    }
}
