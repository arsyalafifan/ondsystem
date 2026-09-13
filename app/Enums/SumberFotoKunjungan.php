<?php

namespace App\Enums;

/**
 * Dari mana sebuah foto bukti kunjungan berasal.
 *
 * Ini bukan sekadar keterangan tampilan — ia menyatakan SEBERAPA KUAT foto
 * itu sebagai bukti, dan ketiganya memang tidak setara:
 *
 *  - `Kamera` paling kuat. Aliran kamera dibaca langsung oleh halaman dan
 *    jam yang dicetak berasal dari server, jadi tidak ada celah memasukkan
 *    gambar lama.
 *  - `Unggah` paling lemah. Berkas apa pun dari galeri bisa dipilih, dan
 *    keterangan waktu/lokasinya berasal dari EXIF milik berkas itu sendiri —
 *    yang bisa saja kosong, atau disunting. Disediakan karena kamera yang
 *    tidak bisa diakses mengunci sales dari pekerjaannya sama sekali, dan
 *    bukti yang lemah masih lebih berguna daripada tidak ada bukti.
 *
 * Karena bedanya nyata, sumber ikut dicetak pada watermark dan ditampilkan
 * apa adanya ke admin — bukan disamarkan supaya semua foto tampak setara.
 */
enum SumberFotoKunjungan: string
{
    case Kamera = 'kamera';
    case Unggah = 'unggah';

    public function label(): string
    {
        return __('kunjungan.sumber_'.$this->value);
    }

    /** Foto yang perlu dilihat admin dengan kecurigaan lebih. */
    public function perluDiperiksa(): bool
    {
        return $this === self::Unggah;
    }
}
