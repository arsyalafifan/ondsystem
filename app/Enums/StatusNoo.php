<?php

namespace App\Enums;

/**
 * Perjalanan satu calon mitra baru, dari pendataan sales sampai freezernya
 * terpasang.
 *
 * Empat status pertamanya sengaja senama dengan StatusPesanan — alurnya
 * memang sebangun (diajukan, disetujui, dijalankan, tuntas) dan penyebutan
 * yang sama membuat admin tidak perlu belajar kosakata kedua. Satu-satunya
 * jalan gagal adalah `Ditolak`, yaitu admin menilai calonnya tidak layak
 * SEBELUM ada yang dikerjakan di lapangan.
 */
enum StatusNoo: string
{
    case Order = 'order';
    case Process = 'process';
    case Delivery = 'delivery';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return __('noo.status_'.$this->value);
    }

    public function keterangan(): string
    {
        return __('noo.ket_status_'.$this->value);
    }

    /** Kelas badge Tailwind, sewarna dengan status pesanan yang sepadan. */
    public function badge(): string
    {
        return match ($this) {
            self::Order => 'bg-blue-100 text-blue-800 ring-blue-600/20',
            self::Process => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Delivery => 'bg-violet-100 text-violet-800 ring-violet-600/20',
            self::Selesai => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Ditolak => 'bg-red-100 text-red-800 ring-red-600/20',
        };
    }

    /**
     * Status yang masih berjalan — dipakai untuk menahan pengajuan kedua
     * atas toko calon yang sama dan untuk menyaring antrean admin.
     *
     * @return array<int, string>
     */
    public static function berjalan(): array
    {
        return [self::Order->value, self::Process->value, self::Delivery->value];
    }

    /** Masih boleh disunting admin: belum ada apa pun yang dikerjakan di lapangan. */
    public function bisaDisunting(): bool
    {
        return $this === self::Order;
    }
}
