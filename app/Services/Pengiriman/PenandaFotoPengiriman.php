<?php

namespace App\Services\Pengiriman;

use App\Enums\JenisBuktiPengiriman;
use App\Models\KendaraanStop;
use App\Models\User;
use App\Services\Foto\PenandaGambar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan bukti pengiriman tambahan (foto QR/suhu/dus/depan toko/freezer,
 * atau tanda tangan digital toko) lengkap dengan watermark.
 *
 * Alasannya sama seperti foto kunjungan/absensi/kendaraan (lihat
 * App\Services\Kunjungan\PenandaFoto dkk.): keterangan dicetak KE DALAM
 * gambar, bukan cuma disimpan di basis data, dan waktunya selalu jam
 * SERVER. Tanda tangan toko (kanvas di layar driver) melewati jalur yang
 * sama persis — bedanya cuma baris tambahan berisi nama penanggung jawab
 * yang menandatangani.
 */
class PenandaFotoPengiriman
{
    /** @return array{path: string, ukuran: int} */
    public function simpan(
        string $isiGambar,
        KendaraanStop $stop,
        JenisBuktiPengiriman $jenis,
        User $driver,
        CarbonImmutable $waktu,
        ?string $namaPenandatangan = null,
    ): array {
        $gambar = @imagecreatefromstring($isiGambar);

        if ($gambar === false) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $gambar = PenandaGambar::perkecil($gambar);
        PenandaGambar::cetakPanel($gambar, $this->baris($stop, $jenis, $driver, $waktu, $namaPenandatangan));

        ob_start();
        imagejpeg($gambar, null, (int) config('visit.foto.mutu_jpeg'));
        $keluaran = (string) ob_get_clean();

        imagedestroy($gambar);

        $path = sprintf('pengiriman/%s/%s/%s.jpg', $waktu->format('Y-m-d'), $stop->id, Str::ulid());

        Storage::disk(config('visit.foto.disk'))->put($path, $keluaran);

        return ['path' => $path, 'ukuran' => strlen($keluaran)];
    }

    /** @return list<string> */
    private function baris(
        KendaraanStop $stop,
        JenisBuktiPengiriman $jenis,
        User $driver,
        CarbonImmutable $waktu,
        ?string $namaPenandatangan,
    ): array {
        $stop->loadMissing('toko:id,nama');

        return array_values(array_filter([
            $waktu->isoFormat('dddd, D MMMM Y').' · '.$waktu->format('H:i:s').' '.$waktu->format('T'),
            $stop->toko->nama.' · '.$driver->name,
            $jenis->label(),
            $namaPenandatangan !== null ? __('pengiriman.wm_penandatangan', ['nama' => $namaPenandatangan]) : null,
        ]));
    }
}
