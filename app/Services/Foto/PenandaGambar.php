<?php

namespace App\Services\Foto;

/**
 * Primitif menggambar yang dipakai bersama oleh semua foto bukti: memperkecil
 * gambar dan mencetak panel keterangan di tepi bawahnya.
 *
 * Isi barisnya sendiri BUKAN urusan kelas ini — foto kunjungan dan foto
 * absensi menuliskan keterangan yang berbeda (lihat
 * App\Services\Kunjungan\PenandaFoto dan App\Services\Absensi\PenandaFotoAbsensi).
 * Yang disatukan di sini hanya cara menggambarnya, supaya kedua foto itu
 * tampil dengan gaya dan keterbacaan yang sama.
 */
final class PenandaGambar
{
    /**
     * @param  \GdImage  $gambar
     * @return \GdImage
     */
    public static function perkecil($gambar, ?int $lebarMaks = null)
    {
        $lebarMaks ??= (int) config('visit.foto.lebar_maks');
        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        if ($lebar <= $lebarMaks) {
            return $gambar;
        }

        $lebarBaru = $lebarMaks;
        $tinggiBaru = (int) round($tinggi * ($lebarMaks / $lebar));

        $kecil = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
        imagedestroy($gambar);

        return $kecil;
    }

    /**
     * @param  \GdImage  $gambar
     * @param  list<string>  $baris  dicetak berurutan dari atas ke bawah panel
     */
    public static function cetakPanel($gambar, array $baris): void
    {
        if ($baris === []) {
            return;
        }

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        // Ukuran huruf mengikuti lebar gambar supaya tetap terbaca baik pada
        // foto kecil maupun besar.
        $font = max(2, min(5, (int) round($lebar / 320)));
        $tinggiBaris = imagefontheight($font) + 4;
        $padding = 10;
        $tinggiPanel = $tinggiBaris * count($baris) + $padding * 2;

        $hitam = imagecolorallocatealpha($gambar, 0, 0, 0, 45);
        imagefilledrectangle($gambar, 0, $tinggi - $tinggiPanel, $lebar, $tinggi, $hitam);

        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $bayangan = imagecolorallocate($gambar, 0, 0, 0);

        $y = $tinggi - $tinggiPanel + $padding;

        foreach ($baris as $teks) {
            // Teks Latin saja: GD tanpa berkas font TrueType tidak bisa
            // menggambar aksara Han, jadi keterangan sengaja dibuat netral.
            $bersih = self::keLatin($teks);

            imagestring($gambar, $font, $padding + 1, $y + 1, $bersih, $bayangan);
            imagestring($gambar, $font, $padding, $y, $bersih, $putih);

            $y += $tinggiBaris;
        }
    }

    /**
     * Menyiapkan teks agar aman digambar oleh GD.
     *
     * imagestring() hanya mengenal satu bita per huruf, sehingga aksara di
     * luar Latin-1 akan tampil sebagai sampah. Teks diubah ke ASCII dulu,
     * dan huruf yang tidak punya padanan dibuang.
     */
    public static function keLatin(string $teks): string
    {
        $hasil = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $teks);

        return $hasil === false ? preg_replace('/[^\x20-\x7E]/', '', $teks) ?? '' : $hasil;
    }
}
