<?php

namespace App\Services\Kunjungan;

/**
 * Mengubah data URL kamera menjadi isi berkas mentah.
 *
 * Dipakai dua jalur masuk yang berbeda — bidikan langsung dari layar sales
 * (daring) dan kiriman susulan dari perangkat (offline) — jadi pemeriksaannya
 * sengaja tinggal di satu tempat. Kalau batasan jenis berkas atau ukurannya
 * nanti diperketat, keduanya ikut terkunci sekaligus, bukan cuma salah satu.
 */
class GambarDataUrl
{
    /** Mengembalikan null bila bukan JPEG/PNG, rusak, atau melebihi batas ukuran. */
    public static function dekode(string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(jpeg|jpg|png);base64,#', $dataUrl, $cocok)) {
            return null;
        }

        $isi = base64_decode(substr($dataUrl, strlen($cocok[0])), true);

        if ($isi === false || $isi === '') {
            return null;
        }

        if (strlen($isi) > (int) config('visit.foto.ukuran_maks_kb') * 1024) {
            return null;
        }

        return $isi;
    }
}
