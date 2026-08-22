<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Membuat link "Print Direct" (ondprint://) benar-benar sekali pakai.
 *
 * Latar belakang: link cetak langsung dibangun lewat
 * URL::temporarySignedRoute — sah selama beberapa menit, tapi TIDAK sekali
 * pakai secara bawaan. Protokol kustom seperti ondprint:// dikenal kadang
 * dipicu dua kali oleh Windows/browser untuk satu klik yang sama (atau OND
 * Print Helper mengambil URL yang sama dua kali karena sebab lain di luar
 * jangkauan kode ini) — akibatnya isi yang sama terkirim dua kali ke
 * printer fisik, terlihat seperti "dicetak berkali-kali" di kertas
 * continuous form. Token ini menutup celah itu: percobaan kedua terhadap
 * URL yang sama, apa pun penyebabnya, ditolak alih-alih diam-diam mencetak
 * ulang.
 */
final class TokenCetakSekaliPakai
{
    private const AWALAN = 'cetak-langsung-token:';

    public static function buat(int $menit = 5): string
    {
        $token = (string) Str::uuid();

        Cache::put(self::AWALAN.$token, true, now()->addMinutes($menit));

        return $token;
    }

    /**
     * Memvalidasi sekaligus langsung memakai token ini. Panggilan kedua
     * dengan token yang sama — baik karena dipakai ulang, kedaluwarsa, atau
     * memang tidak pernah ada — selalu mengembalikan false.
     */
    public static function pakai(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return (bool) Cache::pull(self::AWALAN.$token);
    }
}
