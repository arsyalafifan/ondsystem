<?php

namespace App\Services\Produk;

use App\Services\Foto\PenandaGambar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Foto produk untuk kartu pemilihan di Input Pesanan & POS. Diperkecil &
 * dikompres ulang ke JPEG sebelum disimpan — kartu itu dibuka sales di HP
 * lewat data seluler, puluhan foto berukuran asli kamera akan membuat
 * layarnya lambat sekali dimuat.
 */
class FotoProduk
{
    public const DISK = 'public';

    private const LEBAR_MAKS = 600;

    private const MUTU_JPEG = 80;

    public function simpan(UploadedFile $berkas): string
    {
        $gambar = @imagecreatefromstring((string) file_get_contents($berkas->getRealPath()));

        if ($gambar === false) {
            throw new RuntimeException(__('master.galat_foto_produk_rusak'));
        }

        $gambar = PenandaGambar::perkecil($gambar, self::LEBAR_MAKS);

        // PNG transparan diberi alas putih supaya tidak jadi hitam di JPEG.
        $alas = imagecreatetruecolor(imagesx($gambar), imagesy($gambar));
        imagefill($alas, 0, 0, imagecolorallocate($alas, 255, 255, 255));
        imagecopy($alas, $gambar, 0, 0, 0, 0, imagesx($gambar), imagesy($gambar));
        imagedestroy($gambar);

        ob_start();
        imagejpeg($alas, null, self::MUTU_JPEG);
        $isi = (string) ob_get_clean();
        imagedestroy($alas);

        $path = 'produk/'.Str::ulid().'.jpg';
        Storage::disk(self::DISK)->put($path, $isi);

        return $path;
    }

    public function hapus(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
