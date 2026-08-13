<?php

namespace App\Enums;

enum PeranPengguna: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Driver = 'driver';
    case Superadmin = 'superadmin';

    public function label(): string
    {
        return __('status.peran_'.$this->value);
    }

    /** Halaman yang dibuka setelah login. */
    public function beranda(): string
    {
        return match ($this) {
            self::Admin, self::Superadmin => 'dashboard',
            self::Sales => 'pesanan.buat',
            self::Driver => 'driver.pilih-mobil',
        };
    }
}
