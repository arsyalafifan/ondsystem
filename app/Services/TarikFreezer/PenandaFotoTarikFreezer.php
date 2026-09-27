<?php

namespace App\Services\TarikFreezer;

use App\Enums\JenisBuktiTarikFreezer;
use App\Models\TarikFreezer;
use App\Models\User;
use App\Services\Foto\PenandaGambar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan bukti foto Tarik Freezer lengkap dengan watermark — bentuknya
 * sama persis dengan App\Services\Noo\PenandaFotoNoo, disk privat karena
 * mengikuti pola foto NOO (meski isinya sendiri tidak memuat dokumen
 * identitas, konsisten satu tempat lebih sederhana daripada dua aturan disk
 * berbeda untuk dua hal yang serupa).
 */
class PenandaFotoTarikFreezer
{
    /** @return array{path: string, ukuran: int} */
    public function simpan(
        string $isiGambar,
        TarikFreezer $tarikFreezer,
        JenisBuktiTarikFreezer $jenis,
        User $pengambil,
        CarbonImmutable $waktu,
    ): array {
        $gambar = @imagecreatefromstring($isiGambar);

        if ($gambar === false) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $gambar = PenandaGambar::perkecil($gambar);
        PenandaGambar::cetakPanel($gambar, $this->baris($tarikFreezer, $jenis, $pengambil, $waktu));

        ob_start();
        imagejpeg($gambar, null, (int) config('visit.foto.mutu_jpeg'));
        $keluaran = (string) ob_get_clean();

        imagedestroy($gambar);

        $path = sprintf('tarik-freezer/%s/%s/%s.jpg', $waktu->format('Y-m-d'), $tarikFreezer->id, Str::ulid());

        Storage::disk(BuktiTarikFreezerService::DISK)->put($path, $keluaran);

        return ['path' => $path, 'ukuran' => strlen($keluaran)];
    }

    /** @return list<string> */
    private function baris(TarikFreezer $tarikFreezer, JenisBuktiTarikFreezer $jenis, User $pengambil, CarbonImmutable $waktu): array
    {
        $tarikFreezer->loadMissing('toko:id,nama');

        return [
            $waktu->isoFormat('dddd, D MMMM Y').' · '.$waktu->format('H:i:s').' '.$waktu->format('T'),
            $tarikFreezer->toko->nama.' · '.$pengambil->name,
            $tarikFreezer->kode.' · '.$jenis->label(),
        ];
    }
}
