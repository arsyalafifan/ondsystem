<?php

namespace App\Enums;

enum PeranPengguna: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Driver = 'driver';
    case Superadmin = 'superadmin';
    case Hr = 'hr';
    case Supervisor = 'supervisor';

    public function label(): string
    {
        return __('status.peran_'.$this->value);
    }

    /**
     * Halaman yang dibuka setelah login. Peran `Hr` mendarat di dasbor HR
     * System, bukan dasbor O&D — mereka tidak punya akses ke O&D sama
     * sekali (lihat middleware rute `hr.*`).
     */
    public function beranda(): string
    {
        return match ($this) {
            self::Admin, self::Superadmin => 'dashboard',
            self::Supervisor => 'pesanan.daftar',
            self::Hr => 'hr.dashboard',
            self::Sales => 'pesanan.buat',
            self::Driver => 'driver.pilih-mobil',
        };
    }
}
