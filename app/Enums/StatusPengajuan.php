<?php

namespace App\Enums;

/** Status pengajuan yang diputuskan approver — dipakai izin dan lembur. */
enum StatusPengajuan: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return __('izin.status_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-amber-100 text-amber-800',
            self::Disetujui => 'bg-emerald-100 text-emerald-800',
            self::Ditolak => 'bg-red-100 text-red-800',
            self::Dibatalkan => 'bg-gray-100 text-gray-600',
        };
    }

    /** Pengajuan yang masih "memegang" tanggalnya — dipakai cek tumpang-tindih. */
    public function aktif(): bool
    {
        return in_array($this, [self::Menunggu, self::Disetujui], true);
    }
}
