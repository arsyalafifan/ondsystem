<?php

namespace App\Enums;

/**
 * Posisi jarum indikator bahan bakar, dibaca visual oleh driver dari
 * speedometer — bukan pengukuran presisi, cukup untuk memantau kewajaran
 * (mis. berangkat penuh, kembali kosong padahal rute pendek).
 */
enum LevelBahanBakar: string
{
    case Kosong = 'E';
    case Seperempat = '1/4';
    case Setengah = '1/2';
    case TigaPerempat = '3/4';
    case Penuh = 'F';

    public function label(): string
    {
        return match ($this) {
            self::Kosong => __('kendaraan.bbm_kosong'),
            self::Seperempat => '¼',
            self::Setengah => '½',
            self::TigaPerempat => '¾',
            self::Penuh => __('kendaraan.bbm_penuh'),
        };
    }
}
