<?php

namespace App\Enums;

/**
 * Kategori toko: Sekolah, Perusahaan, Pemerintah, Toko, atau Lainnya.
 *
 * Nilai enum SENGAJA berupa kata Indonesia biasa (bukan kode singkat) —
 * itulah yang membuat `dariTeks()` di bawah bisa mengenali isian impor
 * apa adanya, tanpa tabel padanan terpisah.
 */
enum KategoriToko: string
{
    case Sekolah = 'sekolah';
    case Perusahaan = 'perusahaan';
    case Pemerintah = 'pemerintah';
    case Toko = 'toko';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return __('master.kategori_'.$this->value);
    }

    /**
     * Teks tetap untuk impor/ekspor — SENGAJA tidak ikut bahasa antarmuka
     * (beda dari label(), yang dipakai di dropdown/tabel). Berkas Excel
     * yang diekspor lalu diimpor ulang harus tetap cocok apa pun bahasa
     * yang aktif saat admin mengunduhnya — sama seperti kolom "status"
     * (aktif/nonaktif) di ekspor Master Toko.
     */
    public function teks(): string
    {
        return match ($this) {
            self::Sekolah => 'Sekolah',
            self::Perusahaan => 'Perusahaan',
            self::Pemerintah => 'Pemerintah',
            self::Toko => 'Toko',
            self::Lainnya => 'Lainnya',
        };
    }

    /**
     * Mencocokkan teks bebas (mis. isian kolom "kategori" saat impor) ke
     * salah satu kategori di atas, tanpa peduli huruf besar/kecil —
     * "sekolah", "SEKOLAH", maupun "Sekolah" sama-sama dikenali sebagai
     * Sekolah. Null kalau kosong atau tidak cocok satu pun; baris impornya
     * sendiri tetap diproses, cuma kategorinya yang tidak ikut ditimpa
     * (lihat DaftarToko::lanjutkanImporCsv()).
     */
    public static function dariTeks(?string $teks): ?self
    {
        $teks = trim((string) $teks);

        return $teks === '' ? null : self::tryFrom(mb_strtolower($teks));
    }
}
