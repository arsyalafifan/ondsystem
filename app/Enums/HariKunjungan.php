<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Hari dalam jadwal kunjungan mingguan — angka mengikuti ISO-8601 (1=Senin
 * ... 7=Minggu), sama seperti yang sudah dipakai `config('visit.hari_mulai'/
 * 'hari_selesai')` untuk batas periode. Minggu (7) bukan hari yang diblokir
 * sistem, cuma biasanya dibiarkan kosong — lihat dokumentasi PenugasanToko.
 */
enum HariKunjungan: int
{
    case Senin = 1;
    case Selasa = 2;
    case Rabu = 3;
    case Kamis = 4;
    case Jumat = 5;
    case Sabtu = 6;
    case Minggu = 7;

    public function label(): string
    {
        return __('kunjungan.hari_'.mb_strtolower($this->name));
    }

    /** Hari ini, menurut ISO-8601 (Senin=1 ... Minggu=7). */
    public static function hariIni(): self
    {
        return self::from(CarbonImmutable::today()->isoWeekday());
    }

    /** @return array<int, self> Senin sampai Minggu, berurutan. */
    public static function seminggu(): array
    {
        return self::cases();
    }
}
