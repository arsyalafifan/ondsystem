<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Mengubah nilai Rupiah menjadi rangkaian kata, dipakai pada baris
 * "Terbilang" di nota cetak (lihat resources/views/cetak).
 */
final class Terbilang
{
    /** @var array<int, string> */
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    public static function rupiah(int|float $nilai): string
    {
        $bulat = (int) round($nilai);

        return Str::ucfirst(trim(self::kata(abs($bulat))).' rupiah');
    }

    private static function kata(int $n): string
    {
        if ($n === 0) {
            return 'nol';
        }

        if ($n < 12) {
            return self::SATUAN[$n];
        }

        if ($n < 20) {
            return self::kata($n - 10).' belas';
        }

        if ($n < 100) {
            return self::gabung(self::kata(intdiv($n, 10)).' puluh', $n % 10);
        }

        if ($n < 200) {
            return self::gabung('seratus', $n - 100);
        }

        if ($n < 1000) {
            return self::gabung(self::kata(intdiv($n, 100)).' ratus', $n % 100);
        }

        if ($n < 2000) {
            return self::gabung('seribu', $n - 1000);
        }

        if ($n < 1_000_000) {
            return self::gabung(self::kata(intdiv($n, 1000)).' ribu', $n % 1000);
        }

        if ($n < 1_000_000_000) {
            return self::gabung(self::kata(intdiv($n, 1_000_000)).' juta', $n % 1_000_000);
        }

        if ($n < 1_000_000_000_000) {
            return self::gabung(self::kata(intdiv($n, 1_000_000_000)).' miliar', $n % 1_000_000_000);
        }

        return self::gabung(self::kata(intdiv($n, 1_000_000_000_000)).' triliun', $n % 1_000_000_000_000);
    }

    private static function gabung(string $depan, int $sisa): string
    {
        return $sisa === 0 ? $depan : $depan.' '.self::kata($sisa);
    }
}
