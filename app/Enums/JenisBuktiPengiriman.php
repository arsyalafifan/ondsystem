<?php

namespace App\Enums;

/**
 * Bukti pengiriman tambahan yang wajib difoto driver saat konfirmasi
 * penerimaan toko (App\Livewire\Driver\DaftarKunjungan::simpanKonfirmasi()),
 * di luar foto nota yang sudah ada sejak awal.
 *
 * `FreezerDisusun` dan `TandaTanganTokoSusunSendiri` saling menggantikan,
 * bukan dua bukti yang sama-sama wajib: kalau toko ingin menyusun es krim
 * sendiri (bukan didampingi/difoto driver), driver meminta toko menandatangani
 * konfirmasi itu langsung di layar sebagai gantinya. Karena itu keduanya
 * SENGAJA tidak ikut di wajibFoto() — kelengkapannya dicek terpisah lewat
 * DaftarKunjungan::semuaBuktiLengkap().
 */
enum JenisBuktiPengiriman: string
{
    case Barcode = 'barcode';
    case SuhuFreezer = 'suhu_freezer';
    case DusPesanan = 'dus_pesanan';
    case DepanToko = 'depan_toko';
    case FreezerDisusun = 'freezer_disusun';
    case TandaTanganTokoSusunSendiri = 'tanda_tangan_toko_susun_sendiri';

    public function label(): string
    {
        return __('pengiriman.bukti_'.$this->value);
    }

    public function petunjuk(): string
    {
        return __('pengiriman.petunjuk_bukti_'.$this->value);
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Barcode => 'qr-code',
            self::SuhuFreezer => 'fire',
            self::DusPesanan => 'cube',
            self::DepanToko => 'building-storefront',
            self::FreezerDisusun => 'squares-2x2',
            self::TandaTanganTokoSusunSendiri => 'pencil',
        };
    }

    /**
     * Foto yang wajib diambil driver di SETIAP konfirmasi pengiriman.
     *
     * @return list<self>
     */
    public static function wajibFoto(): array
    {
        return [self::Barcode, self::SuhuFreezer, self::DusPesanan, self::DepanToko];
    }
}
