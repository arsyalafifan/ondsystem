<?php

namespace App\Enums;

/**
 * Jenis unit kendaraan di Master Armada. Daftar tertutup untuk saat ini —
 * lihat catatan di App\Models\Armada soal kenapa belum jadi tabel referensi
 * tersendiri.
 */
enum JenisArmada: string
{
    case Berpendingin = 'berpendingin';
    case BakTerbuka = 'bak_terbuka';

    public function label(): string
    {
        return __('master.jenis_armada_'.$this->value);
    }

    /**
     * Mencocokkan teks bebas (isian kolom "jenis" saat impor) ke salah satu
     * kasus di atas — tanpa peduli huruf besar/kecil maupun spasi/garis
     * bawah ("Bak Terbuka", "bak_terbuka", "BAK TERBUKA" semua cocok jadi
     * BakTerbuka). Null kalau kosong atau tidak cocok satu pun.
     */
    public static function dariTeks(?string $teks): ?self
    {
        $teks = trim((string) $teks);

        return $teks === '' ? null : self::tryFrom(str_replace(' ', '_', mb_strtolower($teks)));
    }
}
