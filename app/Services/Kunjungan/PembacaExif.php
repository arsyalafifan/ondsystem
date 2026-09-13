<?php

namespace App\Services\Kunjungan;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Membaca keterangan waktu dan lokasi yang menempel pada berkas foto.
 *
 * Dipakai hanya untuk foto UNGGAHAN. Bidikan langsung dari kamera tidak
 * perlu ini: waktunya dari jam server dan lokasinya dari GPS peramban,
 * keduanya lebih dipercaya daripada apa pun yang tertulis di dalam berkas.
 *
 * Semua nilainya dianggap PETUNJUK, bukan bukti. EXIF ditulis oleh perangkat
 * yang memotret dan bisa disunting siapa saja dengan alat gratis, jadi
 * hasilnya dicatat apa adanya untuk dilihat admin — bukan dipakai aplikasi
 * untuk memutuskan sesuatu. Berkas tanpa EXIF sama sekali (hal yang lumrah:
 * kiriman WhatsApp dan tangkapan layar memang dibersihkan) tetap diterima,
 * karena menolaknya berarti mengunci sales yang kameranya bermasalah dari
 * pekerjaannya — persis keadaan yang hendak ditolong.
 */
class PembacaExif
{
    /**
     * @return array{diambil_at: ?CarbonImmutable, latitude: ?float, longitude: ?float}
     */
    public function baca(string $isiGambar): array
    {
        $kosong = ['diambil_at' => null, 'latitude' => null, 'longitude' => null];

        if (! function_exists('exif_read_data')) {
            return $kosong;
        }

        try {
            // exif_read_data() menuntut sumber yang bisa dicari-posisinya,
            // sementara yang ada di tangan cuma isi berkas di memori.
            $aliran = fopen('php://memory', 'r+');

            if ($aliran === false) {
                return $kosong;
            }

            fwrite($aliran, $isiGambar);
            rewind($aliran);

            // @ dipakai karena exif_read_data melempar warning untuk berkas
            // tanpa EXIF — keadaan yang di sini normal, bukan kesalahan.
            $exif = @exif_read_data($aliran, 'EXIF,GPS', true);

            fclose($aliran);
        } catch (Throwable) {
            return $kosong;
        }

        if ($exif === false || $exif === null) {
            return $kosong;
        }

        return [
            'diambil_at' => $this->waktu($exif),
            ...$this->lokasi($exif),
        ];
    }

    /** @param array<string, mixed> $exif */
    private function waktu(array $exif): ?CarbonImmutable
    {
        // DateTimeOriginal adalah saat rana ditekan. DateTime (IFD0) bisa
        // ikut berubah saat berkas disunting, jadi hanya dipakai kalau yang
        // pertama tidak ada.
        $nilai = $exif['EXIF']['DateTimeOriginal']
            ?? $exif['EXIF']['DateTimeDigitized']
            ?? $exif['IFD0']['DateTime']
            ?? null;

        if (! is_string($nilai) || trim($nilai) === '') {
            return null;
        }

        try {
            // Bentuk baku EXIF "Y:m:d H:i:s" tidak dikenali parser tanggal
            // biasa, jadi dibaca dengan pola yang tegas.
            $waktu = CarbonImmutable::createFromFormat('Y:m:d H:i:s', trim($nilai));
        } catch (Throwable) {
            return null;
        }

        if ($waktu === false) {
            return null;
        }

        // Kamera kerap menulis tanggal kosong "0000:00:00 00:00:00".
        return $waktu->year > 1990 ? $waktu : null;
    }

    /**
     * @param  array<string, mixed>  $exif
     * @return array{latitude: ?float, longitude: ?float}
     */
    private function lokasi(array $exif): array
    {
        $gps = $exif['GPS'] ?? [];

        $lat = $this->derajat($gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? null, 'S');
        $lng = $this->derajat($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? null, 'W');

        if ($lat === null || $lng === null) {
            return ['latitude' => null, 'longitude' => null];
        }

        // Titik yang jelas mustahil dibuang daripada disimpan sebagai fakta.
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
            return ['latitude' => null, 'longitude' => null];
        }

        return ['latitude' => $lat, 'longitude' => $lng];
    }

    /**
     * EXIF menyimpan koordinat sebagai tiga pecahan (derajat, menit, detik)
     * plus huruf arah terpisah — bukan satu angka bertanda seperti yang
     * dipakai peta.
     *
     * @param  mixed  $bagian
     */
    private function derajat($bagian, mixed $arah, string $negatif): ?float
    {
        if (! is_array($bagian) || count($bagian) < 3) {
            return null;
        }

        $nilai = 0.0;

        foreach ([0 => 1, 1 => 60, 2 => 3600] as $indeks => $pembagi) {
            $pecahan = $this->pecahan($bagian[$indeks] ?? null);

            if ($pecahan === null) {
                return null;
            }

            $nilai += $pecahan / $pembagi;
        }

        return strtoupper((string) $arah) === $negatif ? -$nilai : $nilai;
    }

    private function pecahan(mixed $nilai): ?float
    {
        if (is_numeric($nilai)) {
            return (float) $nilai;
        }

        if (! is_string($nilai) || ! str_contains($nilai, '/')) {
            return null;
        }

        [$atas, $bawah] = explode('/', $nilai, 2);

        if (! is_numeric($atas) || ! is_numeric($bawah) || (float) $bawah === 0.0) {
            return null;
        }

        return (float) $atas / (float) $bawah;
    }
}
