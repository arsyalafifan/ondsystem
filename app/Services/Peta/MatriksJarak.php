<?php

namespace App\Services\Peta;

/**
 * Matriks jarak dan durasi antar titik. Indeks 0 selalu depot.
 */
final readonly class MatriksJarak
{
    /**
     * @param  array<int, array<int, float>>  $jarak  meter
     * @param  array<int, array<int, float>>  $durasi  detik
     * @param  string  $sumber  'osrm' atau 'haversine'
     */
    public function __construct(
        public array $jarak,
        public array $durasi,
        public string $sumber,
    ) {}

    public function jarak(int $dari, int $ke): float
    {
        return $this->jarak[$dari][$ke] ?? 0.0;
    }

    public function durasi(int $dari, int $ke): float
    {
        return $this->durasi[$dari][$ke] ?? 0.0;
    }

    /**
     * Matriks dari perhitungan garis lurus. Durasi diperkirakan dari
     * kecepatan rata-rata kendaraan di kota, dengan faktor kelokan jalan.
     *
     * @param  array<int, Koordinat>  $titik
     */
    public static function dariHaversine(array $titik, float $kmPerJam = 25.0, float $faktorJalan = 1.35): self
    {
        $n = count($titik);
        $jarak = [];
        $durasi = [];
        $meterPerDetik = $kmPerJam * 1000 / 3600;

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $jarak[$i][$j] = 0.0;
                    $durasi[$i][$j] = 0.0;

                    continue;
                }

                // Jarak tempuh nyata selalu lebih panjang dari garis lurus
                // karena jalan berbelok, jadi dikalikan faktor kelokan.
                $m = Geo::haversine($titik[$i], $titik[$j]) * $faktorJalan;
                $jarak[$i][$j] = $m;
                $durasi[$i][$j] = $m / $meterPerDetik;
            }
        }

        return new self($jarak, $durasi, 'haversine');
    }
}
