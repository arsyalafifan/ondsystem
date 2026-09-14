<?php

namespace App\Enums;

enum JenisKelamin: string
{
    case L = 'L';
    case P = 'P';

    public function label(): string
    {
        return __('hr.jk_'.$this->value);
    }
}
