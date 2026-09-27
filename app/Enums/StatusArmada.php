<?php

namespace App\Enums;

/**
 * Status unit kendaraan di Master Armada — SEMENTARA diisi manual lewat
 * dropdown. Rencananya nanti mengikuti otomatis dari menu Maintenance
 * Kendaraan begitu data armadanya lengkap; sampai saat itu admin yang
 * menjaganya tetap akurat sendiri.
 */
enum StatusArmada: string
{
    case Normal = 'normal';
    case MenungguPerbaikan = 'menunggu_perbaikan';
    case SedangDiperbaiki = 'sedang_diperbaiki';

    public function label(): string
    {
        return __('master.status_armada_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Normal => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::MenungguPerbaikan => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::SedangDiperbaiki => 'bg-red-100 text-red-800 ring-red-600/20',
        };
    }

    /**
     * Mencocokkan teks bebas (isian kolom "status" saat impor) ke salah
     * satu kasus di atas — tanpa peduli huruf besar/kecil maupun
     * spasi/garis bawah. Null kalau kosong atau tidak cocok satu pun; baris
     * impornya sendiri tetap diproses dengan status bawaan/sebelumnya,
     * lihat DaftarArmada::lanjutkanImporCsv().
     */
    public static function dariTeks(?string $teks): ?self
    {
        $teks = trim((string) $teks);

        return $teks === '' ? null : self::tryFrom(str_replace(' ', '_', mb_strtolower($teks)));
    }
}
