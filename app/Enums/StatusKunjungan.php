<?php

namespace App\Enums;

enum StatusKunjungan: string
{
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';
    case TutupDiajukan = 'tutup_diajukan';
    case TutupDisetujui = 'tutup_disetujui';
    case TutupDitolak = 'tutup_ditolak';

    public function label(): string
    {
        return __('kunjungan.status_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Berjalan => 'bg-blue-100 text-blue-800 ring-blue-600/20',
            self::Selesai => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::TutupDiajukan => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::TutupDisetujui => 'bg-gray-100 text-gray-700 ring-gray-500/20',
            self::TutupDitolak => 'bg-red-100 text-red-800 ring-red-600/20',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Berjalan => '#2563eb',
            self::Selesai => '#059669',
            self::TutupDiajukan => '#f59e0b',
            self::TutupDisetujui => '#6b7280',
            self::TutupDitolak => '#dc2626',
        };
    }

    /**
     * Status yang membuat sebuah toko dianggap sudah tertangani minggu ini,
     * sehingga sales lain tidak boleh lagi memindainya.
     *
     * Kunjungan yang masih berjalan ikut mengunci toko, kalau tidak dua sales
     * bisa sama-sama mulai mengambil foto di toko yang sama.
     *
     * @return array<int, string>
     */
    public static function mengunciToko(): array
    {
        return [
            self::Berjalan->value,
            self::Selesai->value,
            self::TutupDiajukan->value,
            self::TutupDisetujui->value,
        ];
    }

    /** Toko yang laporan tutupnya sudah dibenarkan admin keluar dari target. */
    public function keluarDariTarget(): bool
    {
        return $this === self::TutupDisetujui;
    }

    public function sudahTuntas(): bool
    {
        return $this === self::Selesai;
    }

    public function menungguAdmin(): bool
    {
        return $this === self::TutupDiajukan;
    }
}
