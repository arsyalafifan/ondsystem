<?php

namespace App\Services\Noo;

use App\Enums\JenisBuktiNoo;
use App\Models\Noo;
use App\Models\User;
use App\Services\Foto\PenandaGambar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan bukti foto NOO lengkap dengan watermark.
 *
 * Alasannya sama seperti foto kunjungan/pengiriman (lihat
 * App\Services\Kunjungan\PenandaFoto dkk.): keterangan dicetak KE DALAM
 * gambar, bukan cuma disimpan di basis data, dan waktunya selalu jam SERVER.
 *
 * Bedanya cuma tempat menyimpan — disk privat, bukan publik, karena di
 * antara foto ini ada KTP dan kartu keluarga pemilik toko.
 */
class PenandaFotoNoo
{
    /** @return array{path: string, ukuran: int} */
    public function simpan(
        string $isiGambar,
        Noo $noo,
        JenisBuktiNoo $jenis,
        User $pengambil,
        CarbonImmutable $waktu,
    ): array {
        $gambar = @imagecreatefromstring($isiGambar);

        if ($gambar === false) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $gambar = PenandaGambar::perkecil($gambar);
        PenandaGambar::cetakPanel($gambar, $this->baris($noo, $jenis, $pengambil, $waktu));

        ob_start();
        imagejpeg($gambar, null, (int) config('visit.foto.mutu_jpeg'));
        $keluaran = (string) ob_get_clean();

        imagedestroy($gambar);

        $path = sprintf('noo/%s/%s/%s.jpg', $waktu->format('Y-m-d'), $noo->id, Str::ulid());

        Storage::disk(BuktiNooService::DISK)->put($path, $keluaran);

        return ['path' => $path, 'ukuran' => strlen($keluaran)];
    }

    /** @return list<string> */
    private function baris(Noo $noo, JenisBuktiNoo $jenis, User $pengambil, CarbonImmutable $waktu): array
    {
        return [
            $waktu->isoFormat('dddd, D MMMM Y').' · '.$waktu->format('H:i:s').' '.$waktu->format('T'),
            $noo->nama.' · '.$pengambil->name,
            $noo->kode.' · '.$jenis->label(),
        ];
    }
}
