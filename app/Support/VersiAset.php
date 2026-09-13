<?php

namespace App\Support;

/**
 * Penanda versi bangunan aset, dipakai service worker sebagai nama cache.
 *
 * Diambil dari manifest Vite karena isinya berubah tepat ketika aset
 * dibangun ulang — tidak lebih sering (yang akan membuang cache percuma tiap
 * permintaan) dan tidak lebih jarang (yang akan membuat perangkat tertahan
 * di aset lama setelah deploy).
 */
class VersiAset
{
    private static ?string $versi = null;

    public static function sekarang(): string
    {
        if (self::$versi !== null) {
            return self::$versi;
        }

        $manifest = public_path('build/manifest.json');

        // Saat pengembangan lokal aset disajikan Vite dev server dan
        // manifest-nya memang belum ada. Mode offline sendiri mati secara
        // bawaan di sana, jadi nilai tetap sudah cukup.
        self::$versi = is_file($manifest)
            ? substr((string) md5_file($manifest), 0, 12)
            : 'dev';

        return self::$versi;
    }
}
