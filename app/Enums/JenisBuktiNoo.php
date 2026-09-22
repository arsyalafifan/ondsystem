<?php

namespace App\Enums;

/**
 * Bukti foto sepanjang alur NOO. Dua kelompok, dua tangan, dua waktu yang
 * berbeda:
 *
 * - `wajibSales()` diambil saat pendataan di lapangan. KTP dan kartu
 *   keluarga adalah dokumen identitas pemilik — dasar hukum kerja sama,
 *   sekaligus alasan seluruh foto NOO disimpan di disk privat dan hanya bisa
 *   dibuka lewat rute bergerbang, bukan URL publik seperti foto bukti lain.
 * - `wajibDriver()` diambil saat freezernya benar-benar terpasang. Keenamnya
 *   wajib: masing-masing membuktikan hal berbeda yang tidak bisa diwakili
 *   foto lain — tokonya benar ada, perjanjiannya benar diserahterimakan,
 *   freezernya benar milik toko ini (QR), benar masuk ke dalam toko, dan
 *   materi promosinya benar terpasang.
 */
enum JenisBuktiNoo: string
{
    // --- Diambil sales saat mendata calon toko ---
    case KtpPemilik = 'ktp_pemilik';
    case KartuKeluarga = 'kartu_keluarga';
    case TampakDepanSales = 'tampak_depan_sales';

    // --- Diambil driver saat memasang freezer ---
    case TampakDepanDriver = 'tampak_depan_driver';
    case SuratPerjanjian = 'surat_perjanjian';
    case QrCode = 'qr_code';
    case PosisiFreezer = 'posisi_freezer';
    case Spanduk = 'spanduk';
    case FlagHanger = 'flag_hanger';

    public function label(): string
    {
        return __('noo.bukti_'.$this->value);
    }

    public function petunjuk(): string
    {
        return __('noo.petunjuk_bukti_'.$this->value);
    }

    public function ikon(): string
    {
        return match ($this) {
            self::KtpPemilik => 'identification',
            self::KartuKeluarga => 'users',
            self::TampakDepanSales, self::TampakDepanDriver => 'building-storefront',
            self::SuratPerjanjian => 'document-text',
            self::QrCode => 'qr-code',
            self::PosisiFreezer => 'cube',
            self::Spanduk => 'flag',
            self::FlagHanger => 'sparkles',
        };
    }

    /**
     * Foto yang wajib dilengkapi sales sebelum NOO bisa diajukan.
     *
     * @return list<self>
     */
    public static function wajibSales(): array
    {
        return [self::KtpPemilik, self::KartuKeluarga, self::TampakDepanSales];
    }

    /**
     * Foto yang wajib dilengkapi driver sebelum NOO bisa dituntaskan.
     *
     * @return list<self>
     */
    public static function wajibDriver(): array
    {
        return [
            self::TampakDepanDriver,
            self::SuratPerjanjian,
            self::QrCode,
            self::PosisiFreezer,
            self::Spanduk,
            self::FlagHanger,
        ];
    }
}
