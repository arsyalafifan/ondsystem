<?php

namespace App\Services\PengantaranRider;

use App\Enums\JenisBuktiPengiriman;
use App\Models\PengantaranRider;
use App\Models\User;
use App\Services\Foto\PenandaGambar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan bukti pengiriman rider (foto QR/suhu/dus/depan toko/freezer,
 * atau tanda tangan digital toko) lengkap dengan watermark — salinan
 * App\Services\Pengiriman\PenandaFotoPengiriman, diketik ke PengantaranRider
 * bukan KendaraanStop. Lihat docblock aslinya untuk alasan watermark-nya.
 */
class PenandaFotoPengantaranRider
{
    /** @return array{path: string, ukuran: int} */
    public function simpan(
        string $isiGambar,
        PengantaranRider $pengantaran,
        JenisBuktiPengiriman $jenis,
        User $rider,
        CarbonImmutable $waktu,
        ?string $namaPenandatangan = null,
    ): array {
        $gambar = @imagecreatefromstring($isiGambar);

        if ($gambar === false) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $gambar = PenandaGambar::perkecil($gambar);
        PenandaGambar::cetakPanel($gambar, $this->baris($pengantaran, $jenis, $rider, $waktu, $namaPenandatangan));

        ob_start();
        imagejpeg($gambar, null, (int) config('visit.foto.mutu_jpeg'));
        $keluaran = (string) ob_get_clean();

        imagedestroy($gambar);

        $path = sprintf('pengiriman/%s/rider-%s/%s.jpg', $waktu->format('Y-m-d'), $pengantaran->id, Str::ulid());

        Storage::disk(config('visit.foto.disk'))->put($path, $keluaran);

        return ['path' => $path, 'ukuran' => strlen($keluaran)];
    }

    /** @return list<string> */
    private function baris(
        PengantaranRider $pengantaran,
        JenisBuktiPengiriman $jenis,
        User $rider,
        CarbonImmutable $waktu,
        ?string $namaPenandatangan,
    ): array {
        $pengantaran->loadMissing('pesanan.toko:id,nama');

        return array_values(array_filter([
            $waktu->isoFormat('dddd, D MMMM Y').' · '.$waktu->format('H:i:s').' '.$waktu->format('T'),
            $pengantaran->pesanan->toko->nama.' · '.$rider->name,
            $jenis->label(),
            $namaPenandatangan !== null ? __('pengiriman.wm_penandatangan', ['nama' => $namaPenandatangan]) : null,
        ]));
    }
}
