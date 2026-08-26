<?php

namespace App\Services\Peta;

/** Perhitungan geometri bumi yang dipakai mesin routing. */
final class Geo
{
    private const RADIUS_BUMI_M = 6_371_000;

    /** Jarak garis lurus antara dua titik, dalam meter. */
    public static function haversine(Koordinat $a, Koordinat $b): float
    {
        $lat1 = deg2rad($a->lat);
        $lat2 = deg2rad($b->lat);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b->lng - $a->lng);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * self::RADIUS_BUMI_M * asin(min(1.0, sqrt($h)));
    }

    /**
     * Sudut sebuah titik dilihat dari pusat, dalam radian 0..2π.
     * Dipakai algoritma sweep untuk memotong wilayah jadi juring-juring.
     */
    public static function sudut(Koordinat $pusat, Koordinat $titik): float
    {
        // Bujur dikoreksi cos(lat) supaya satu derajat bujur dan satu derajat
        // lintang punya panjang yang sebanding di garis lintang tersebut.
        $x = ($titik->lng - $pusat->lng) * cos(deg2rad($pusat->lat));
        $y = $titik->lat - $pusat->lat;

        $sudut = atan2($y, $x);

        return $sudut < 0 ? $sudut + 2 * M_PI : $sudut;
    }

    /**
     * Titik tengah dari sekumpulan koordinat.
     *
     * @param  array<int, Koordinat>  $titik
     */
    public static function centroid(array $titik): ?Koordinat
    {
        if ($titik === []) {
            return null;
        }

        $lat = 0.0;
        $lng = 0.0;

        foreach ($titik as $t) {
            $lat += $t->lat;
            $lng += $t->lng;
        }

        $n = count($titik);

        return new Koordinat($lat / $n, $lng / $n);
    }

    /**
     * Encode daftar koordinat menjadi polyline Google (presisi 5).
     * Dipakai sebagai cadangan ketika OSRM tidak bisa dihubungi, supaya
     * peta tetap bisa menggambar garis rute meski lurus antar titik.
     *
     * @param  array<int, Koordinat>  $titik
     */
    public static function encodePolyline(array $titik): string
    {
        $hasil = '';
        $lastLat = 0;
        $lastLng = 0;

        foreach ($titik as $t) {
            $lat = (int) round($t->lat * 1e5);
            $lng = (int) round($t->lng * 1e5);

            $hasil .= self::encodeNilai($lat - $lastLat).self::encodeNilai($lng - $lastLng);

            $lastLat = $lat;
            $lastLng = $lng;
        }

        return $hasil;
    }

    /**
     * Kebalikan encodePolyline(): membaca garis rute tersimpan menjadi
     * daftar koordinat. Dipakai untuk memeriksa apakah garis rute yang
     * tersimpan masih sungguh melewati toko-tokonya.
     *
     * @return array<int, Koordinat>
     */
    public static function decodePolyline(string $terenkode, int $presisi = 5): array
    {
        $titik = [];
        $indeks = 0;
        $panjang = strlen($terenkode);
        $faktor = 10 ** $presisi;
        $lat = 0;
        $lng = 0;

        while ($indeks < $panjang) {
            $dLat = self::decodeNilai($terenkode, $indeks, $panjang);
            $dLng = self::decodeNilai($terenkode, $indeks, $panjang);

            if ($dLat === null || $dLng === null) {
                break;
            }

            $lat += $dLat;
            $lng += $dLng;

            $titik[] = new Koordinat($lat / $faktor, $lng / $faktor);
        }

        return $titik;
    }

    private static function decodeNilai(string $terenkode, int &$indeks, int $panjang): ?int
    {
        $hasil = 0;
        $geser = 0;

        do {
            if ($indeks >= $panjang) {
                return null;
            }

            $b = ord($terenkode[$indeks++]) - 63;
            $hasil |= ($b & 0x1F) << $geser;
            $geser += 5;
        } while ($b >= 0x20);

        return ($hasil & 1) ? ~($hasil >> 1) : ($hasil >> 1);
    }

    private static function encodeNilai(int $nilai): string
    {
        $v = $nilai < 0 ? ~($nilai << 1) : ($nilai << 1);
        $keluar = '';

        while ($v >= 0x20) {
            $keluar .= chr((0x20 | ($v & 0x1F)) + 63);
            $v >>= 5;
        }

        return $keluar.chr($v + 63);
    }
}
