<?php

namespace App\Enums;

/**
 * Enam bukti foto yang wajib diambil sales pada setiap kunjungan.
 * Urutannya sengaja mengikuti urutan pekerjaan di lapangan: datang, bersihkan
 * freezer, lalu periksa perlengkapan promosi dan suhu.
 */
enum JenisFotoKunjungan: string
{
    case SalesDepanToko = 'sales_depan_toko';
    case FreezerSebelum = 'freezer_sebelum';
    case FreezerSesudah = 'freezer_sesudah';
    case Spanduk = 'spanduk';
    case FlagHanger = 'flag_hanger';
    case SuhuFreezer = 'suhu_freezer';

    public function label(): string
    {
        return __('kunjungan.foto_'.$this->value);
    }

    public function petunjuk(): string
    {
        return __('kunjungan.petunjuk_'.$this->value);
    }

    public function ikon(): string
    {
        return match ($this) {
            self::SalesDepanToko => 'heroicon-o-user',
            self::FreezerSebelum => 'heroicon-o-cube-transparent',
            self::FreezerSesudah => 'heroicon-o-sparkles',
            self::Spanduk => 'heroicon-o-flag',
            self::FlagHanger => 'heroicon-o-bookmark',
            self::SuhuFreezer => 'heroicon-o-fire',
        };
    }

    /** @return array<int, self> */
    public static function urut(): array
    {
        return array_map(
            fn (string $nilai) => self::from($nilai),
            config('visit.foto_wajib'),
        );
    }
}
