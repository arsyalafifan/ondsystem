<?php

namespace App\Enums;

/**
 * Perjalanan satu transfer stok antar gudang penyimpanan.
 *
 * Tidak ada tahap persetujuan terpisah seperti NOO/Tarik Freezer — begitu
 * gudang asal menekan kirim, stoknya langsung berpindah (dikurangi dari
 * asal) dan statusnya `Dikirim`. Dari situ hanya ada dua jalan: gudang
 * tujuan menerima (`Diterima`, boleh merevisi jumlah), atau gudang asal
 * membatalkannya SEBELUM diterima (`Dibatalkan`, stoknya dikembalikan).
 */
enum StatusTransferStok: string
{
    case Dikirim = 'dikirim';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return __('transfer_stok.status_'.$this->value);
    }

    public function keterangan(): string
    {
        return __('transfer_stok.ket_status_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Dikirim => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Diterima => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Dibatalkan => 'bg-red-100 text-red-800 ring-red-600/20',
        };
    }
}
