<?php

namespace App\Services\Kunjungan;

use App\Enums\JenisFotoKunjungan;
use App\Support\ModeUji;

/**
 * Membuat gambar tiruan untuk menguji alur kunjungan tanpa kamera.
 *
 * Hanya dipakai saat mode uji menyala di lingkungan lokal. Gambarnya sengaja
 * dibuat mencolok dan bertuliskan "CONTOH UJI", supaya kalau sampai terbawa ke
 * data sungguhan langsung ketahuan dan tidak bisa disamarkan sebagai bukti
 * kunjungan yang sah.
 */
class GambarContoh
{
    /** Warna berbeda per jenis foto, agar mudah dibedakan saat diperiksa. */
    private const WARNA = [
        'sales_depan_toko' => [52, 111, 191],
        'freezer_sebelum' => [124, 92, 175],
        'freezer_sesudah' => [37, 152, 122],
        'spanduk' => [201, 116, 44],
        'flag_hanger' => [186, 63, 96],
        'suhu_freezer' => [70, 130, 150],
    ];

    public function buat(JenisFotoKunjungan $jenis, int $lebar = 1280, int $tinggi = 960): string
    {
        ModeUji::pastikanAktif();

        $gambar = imagecreatetruecolor($lebar, $tinggi);

        [$r, $g, $b] = self::WARNA[$jenis->value] ?? [90, 90, 90];
        imagefilledrectangle($gambar, 0, 0, $lebar, $tinggi, imagecolorallocate($gambar, $r, $g, $b));

        // Garis serong sebagai penanda visual bahwa ini bukan foto sungguhan.
        $garis = imagecolorallocatealpha($gambar, 255, 255, 255, 100);

        for ($x = -$tinggi; $x < $lebar; $x += 60) {
            imagefilledpolygon($gambar, [
                $x, 0,
                $x + 24, 0,
                $x + 24 + $tinggi, $tinggi,
                $x + $tinggi, $tinggi,
            ], $garis);
        }

        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $gelap = imagecolorallocate($gambar, 20, 20, 20);

        $this->tulisTengah($gambar, 'CONTOH UJI - BUKAN FOTO ASLI', $tinggi / 2 - 60, 5, $putih, $gelap);
        $this->tulisTengah($gambar, strtoupper(str_replace('_', ' ', $jenis->value)), $tinggi / 2 + 10, 4, $putih, $gelap);

        ob_start();
        imagejpeg($gambar, null, 85);
        $isi = (string) ob_get_clean();

        imagedestroy($gambar);

        return $isi;
    }

    /** @param \GdImage $gambar */
    private function tulisTengah($gambar, string $teks, float $y, int $font, int $warna, int $bayangan): void
    {
        $x = (int) ((imagesx($gambar) - imagefontwidth($font) * strlen($teks)) / 2);

        imagestring($gambar, $font, $x + 2, (int) $y + 2, $teks, $bayangan);
        imagestring($gambar, $font, $x, (int) $y, $teks, $warna);
    }
}
