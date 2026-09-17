<?php

namespace App\Services\Absensi;

use App\Enums\JenisAbsensi;
use App\Models\Karyawan;
use App\Services\Foto\PenandaGambar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan foto selfie absensi lengkap dengan watermark.
 *
 * Alasannya sama seperti foto kunjungan (lihat
 * App\Services\Kunjungan\PenandaFoto): keterangan dicetak KE DALAM gambar,
 * bukan cuma disimpan di basis data, supaya bukti kehadiran tetap terbaca
 * ketika fotonya disalin keluar aplikasi. Waktunya selalu jam SERVER — jam
 * ponsel bisa diubah sendiri oleh pemakainya.
 */
class PenandaFotoAbsensi
{
    /**
     * @param  string  $tempat  nama depot/toko tempat absen, dicetak apa adanya
     * @return array{path: string, lebar: int, tinggi: int, ukuran: int}
     */
    public function simpan(
        string $isiGambar,
        Karyawan $karyawan,
        JenisAbsensi $jenis,
        CarbonImmutable $waktu,
        ?float $lat = null,
        ?float $lng = null,
        ?int $akurasi = null,
        ?int $jarak = null,
        string $tempat = '',
    ): array {
        $gambar = @imagecreatefromstring($isiGambar);

        if ($gambar === false) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $gambar = PenandaGambar::perkecil($gambar);
        PenandaGambar::cetakPanel($gambar, $this->baris($karyawan, $jenis, $waktu, $lat, $lng, $akurasi, $jarak, $tempat));

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        ob_start();
        imagejpeg($gambar, null, (int) config('visit.foto.mutu_jpeg'));
        $keluaran = (string) ob_get_clean();

        imagedestroy($gambar);

        $path = sprintf('absensi/%s/%s/%s.jpg', $waktu->format('Y-m-d'), $karyawan->id, Str::ulid());

        Storage::disk(config('visit.foto.disk'))->put($path, $keluaran);

        return [
            'path' => $path,
            'lebar' => $lebar,
            'tinggi' => $tinggi,
            'ukuran' => strlen($keluaran),
        ];
    }

    /** @return list<string> */
    private function baris(
        Karyawan $karyawan,
        JenisAbsensi $jenis,
        CarbonImmutable $waktu,
        ?float $lat,
        ?float $lng,
        ?int $akurasi,
        ?int $jarak,
        string $tempat,
    ): array {
        return array_values(array_filter([
            $waktu->isoFormat('dddd, D MMMM Y').' · '.$waktu->format('H:i:s').' '.$waktu->format('T'),
            $karyawan->nama_lengkap.' · '.$karyawan->kode_karyawan,
            __('hr.wm_absen').': '.$jenis->label().($karyawan->posisi !== null ? ' · '.$karyawan->posisi->nama : ''),
            $lat !== null && $lng !== null
                ? sprintf('%s: %.6f, %.6f%s', __('kunjungan.wm_lokasi'), $lat, $lng, $akurasi ? " (±{$akurasi} m)" : '')
                : __('kunjungan.wm_tanpa_lokasi'),
            $tempat !== ''
                ? $tempat.($jarak !== null ? " · {$jarak} m" : '')
                : null,
        ]));
    }
}
