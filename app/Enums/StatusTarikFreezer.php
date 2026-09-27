<?php

namespace App\Enums;

/**
 * Perjalanan satu penarikan freezer, dari pengajuan sales sampai freezernya
 * kembali ke gudang dan tokonya nonaktif.
 *
 * Kebalikan dari StatusNoo (NOO mengantar freezer KE toko; ini menarik
 * freezer DARI toko), tapi bentuk alurnya sengaja sama — diajukan, disetujui,
 * dijalankan, tuntas — jadi nama kasusnya juga sama persis dengan StatusNoo/
 * StatusPesanan. Satu-satunya jalan gagal adalah `Ditolak`, admin menilai
 * penarikannya belum perlu SEBELUM ada yang dikerjakan di lapangan.
 */
enum StatusTarikFreezer: string
{
    case Order = 'order';
    case Process = 'process';
    case Delivery = 'delivery';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return __('tarik_freezer.status_'.$this->value);
    }

    public function keterangan(): string
    {
        return __('tarik_freezer.ket_status_'.$this->value);
    }

    /** Kelas badge Tailwind, sewarna dengan status NOO/pesanan yang sepadan. */
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
     * atas toko yang sama dan untuk menyaring antrean admin.
     *
     * @return array<int, string>
     */
    public static function berjalan(): array
    {
        return [self::Order->value, self::Process->value, self::Delivery->value];
    }
}
