<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * Penjaga tunggal untuk jalan pintas pengujian.
 *
 * Aturan "foto wajib diambil langsung dari kamera" adalah inti dari fitur
 * kunjungan — tanpa itu bukti kunjungan kehilangan artinya. Karena itu jalan
 * pintasnya dikunci dua lapis: hanya hidup di lingkungan lokal, dan hanya bila
 * penandanya dinyalakan sendiri di berkas .env.
 *
 * Keduanya diperiksa di satu tempat ini saja, supaya tidak mungkin ada bagian
 * aplikasi yang lupa memeriksa salah satunya.
 */
final class ModeUji
{
    public static function aktif(): bool
    {
        return App::environment('local') && (bool) config('visit.mode_uji');
    }

    /** Menghentikan permintaan bila jalan pintas dipakai di luar tempatnya. */
    public static function pastikanAktif(): void
    {
        abort_unless(self::aktif(), 403, 'Mode uji tidak aktif.');
    }
}
