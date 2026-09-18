<?php

namespace App\Services\Kendaraan;

use App\Enums\JenisCatatanBbm;
use App\Enums\LevelBahanBakar;
use App\Models\Kendaraan;
use App\Models\User;
use App\Services\Foto\PenandaGambar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan foto kondisi kendaraan (KM/BBM saat berangkat & kembali, struk
 * dan foto sebelum/sesudah saat pengisian) lengkap dengan watermark.
 *
 * Alasannya sama seperti foto kunjungan/absensi (lihat
 * App\Services\Kunjungan\PenandaFoto / App\Services\Absensi\PenandaFotoAbsensi):
 * keterangan dicetak KE DALAM gambar, bukan cuma disimpan di basis data,
 * dan waktunya selalu jam SERVER — jam ponsel bisa diubah sendiri
 * pemakainya.
 */
class PenandaFotoKendaraan
{
    /** @return array{path: string, ukuran: int} */
    public function simpan(
        string $isiGambar,
        Kendaraan $kendaraan,
        JenisCatatanBbm $jenis,
        User $driver,
        CarbonImmutable $waktu,
        ?int $km = null,
        ?LevelBahanBakar $levelBbm = null,
        ?string $labelTambahan = null,
    ): array {
        $gambar = @imagecreatefromstring($isiGambar);

        if ($gambar === false) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $gambar = PenandaGambar::perkecil($gambar);
        PenandaGambar::cetakPanel($gambar, $this->baris($kendaraan, $jenis, $driver, $waktu, $km, $levelBbm, $labelTambahan));

        ob_start();
        imagejpeg($gambar, null, (int) config('visit.foto.mutu_jpeg'));
        $keluaran = (string) ob_get_clean();

        imagedestroy($gambar);

        $path = sprintf('kendaraan-bbm/%s/%s/%s.jpg', $waktu->format('Y-m-d'), $kendaraan->id, Str::ulid());

        Storage::disk(config('visit.foto.disk'))->put($path, $keluaran);

        return ['path' => $path, 'ukuran' => strlen($keluaran)];
    }

    /** @return list<string> */
    private function baris(
        Kendaraan $kendaraan,
        JenisCatatanBbm $jenis,
        User $driver,
        CarbonImmutable $waktu,
        ?int $km,
        ?LevelBahanBakar $levelBbm,
        ?string $labelTambahan,
    ): array {
        return array_values(array_filter([
            $waktu->isoFormat('dddd, D MMMM Y').' · '.$waktu->format('H:i:s').' '.$waktu->format('T'),
            $kendaraan->nama.' · '.$driver->name,
            $jenis->label().($labelTambahan !== null ? ' — '.$labelTambahan : ''),
            $km !== null ? __('kendaraan.wm_km', ['km' => number_format($km, 0, ',', '.')]) : null,
            $levelBbm !== null ? __('kendaraan.wm_bbm', ['level' => $levelBbm->label()]) : null,
        ]));
    }
}
