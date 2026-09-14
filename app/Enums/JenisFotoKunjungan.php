<?php

namespace App\Enums;

/**
 * Jenis foto bukti kunjungan. Yang WAJIB diambil sales ditentukan lewat
 * `config('visit.foto_wajib')`, bukan lewat daftar case ini — lihat
 * `urut()`. `SalesDepanToko`, `FreezerSebelum`, `FreezerSesudah`, `Spanduk`,
 * dan `FlagHanger` masih ada di sini walau sudah dinonaktifkan dari daftar
 * wajib (digantikan pasangan atas/bawah freezer dan foto spanduk+sales
 * gabungan), supaya kunjungan lama yang pernah menyimpan foto jenis itu
 * tetap bisa di-cast dan ditampilkan.
 *
 * Urutan case yang WAJIB sengaja mengikuti urutan pekerjaan di lapangan:
 * bersihkan freezer (bawah lalu atas, sebelum lalu sesudah), periksa suhu,
 * pindai barcode, lalu foto spanduk bersama sales sebagai penutup.
 */
enum JenisFotoKunjungan: string
{
    case SalesDepanToko = 'sales_depan_toko';
    case FreezerSebelum = 'freezer_sebelum';
    case FreezerSesudah = 'freezer_sesudah';
    case Spanduk = 'spanduk';
    case FlagHanger = 'flag_hanger';
    case SuhuFreezer = 'suhu_freezer';
    case FreezerBawahSebelum = 'freezer_bawah_sebelum';
    case FreezerAtasSebelum = 'freezer_atas_sebelum';
    case FreezerBawahSesudah = 'freezer_bawah_sesudah';
    case FreezerAtasSesudah = 'freezer_atas_sesudah';
    case Barcode = 'barcode';
    case SpandukBesertaSales = 'spanduk_beserta_sales';

    public function label(): string
    {
        return __('kunjungan.foto_'.$this->value);
    }

    public function petunjuk(): string
    {
        return __('kunjungan.petunjuk_'.$this->value);
    }

    public function ikon(): string
    {
        return match ($this) {
            self::SalesDepanToko => 'heroicon-o-user',
            self::FreezerSebelum => 'heroicon-o-cube-transparent',
            self::FreezerSesudah => 'heroicon-o-sparkles',
            self::Spanduk => 'heroicon-o-flag',
            self::FlagHanger => 'heroicon-o-bookmark',
            self::SuhuFreezer => 'heroicon-o-fire',
            self::FreezerBawahSebelum, self::FreezerBawahSesudah => 'heroicon-o-cube-transparent',
            self::FreezerAtasSebelum, self::FreezerAtasSesudah => 'heroicon-o-sparkles',
            self::Barcode => 'heroicon-o-qr-code',
            self::SpandukBesertaSales => 'heroicon-o-flag',
        };
    }

    /** @return array<int, self> */
    public static function urut(): array
    {
        return array_map(
            fn (string $nilai) => self::from($nilai),
            config('visit.foto_wajib'),
        );
    }
}
