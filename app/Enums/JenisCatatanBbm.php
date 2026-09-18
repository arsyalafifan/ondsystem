<?php

namespace App\Enums;

/**
 * Titik pencatatan kondisi kendaraan (KM + bahan bakar), lihat
 * App\Models\CatatanBbm.
 *
 * `Berangkat` dan `Kembali` masing-masing hanya boleh sekali per kendaraan
 * (wajib) — dijaga App\Services\Kendaraan\CatatanBbmService, bukan unique
 * index, karena `Pengisian` justru boleh berulang kali (opsional, hanya
 * ketika driver benar-benar mengisi bahan bakar).
 */
enum JenisCatatanBbm: string
{
    case Berangkat = 'berangkat';
    case Pengisian = 'pengisian';
    case Kembali = 'kembali';

    public function label(): string
    {
        return __('kendaraan.jenis_'.$this->value);
    }
}
