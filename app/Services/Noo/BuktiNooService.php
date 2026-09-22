<?php

namespace App\Services\Noo;

use App\Enums\JenisBuktiNoo;
use App\Models\Noo;
use App\Models\User;
use App\Services\Kunjungan\GambarDataUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Menyiapkan dan menyimpan bukti foto NOO.
 *
 * Bentuknya sengaja sama dengan App\Services\Pengiriman\BuktiPengirimanService:
 * service ini hanya mengurus "gambar ini jadi berkas ber-watermark dan baris
 * di basis data". Aturan KELENGKAPAN (foto mana saja yang wajib ada sebelum
 * boleh disimpan) tetap tinggal di komponen Livewire-nya, karena itu aturan
 * alur kerja di layar, bukan aturan data.
 */
class BuktiNooService
{
    /**
     * Disk privat. Foto NOO memuat KTP dan kartu keluarga pemilik toko,
     * jadi berkasnya tidak boleh bisa dibuka siapa pun yang menebak URL —
     * satu-satunya jalan masuk adalah rute `noo.foto` yang memeriksa dulu
     * siapa yang meminta. Bandingkan dengan foto bukti lain di aplikasi ini
     * yang memakai config('visit.foto.disk') dan memang publik.
     */
    public const DISK = 'local';

    public function __construct(private readonly PenandaFotoNoo $penanda) {}

    /**
     * Mengubah satu jepretan/unggahan jadi berkas ber-watermark. Barisnya di
     * basis data BELUM ditulis di sini — lihat simpanSemua().
     *
     * @return array{jenis: JenisBuktiNoo, path: string}
     *
     * @throws RuntimeException bila gambarnya tidak bisa dibaca
     */
    public function simpanFoto(Noo $noo, JenisBuktiNoo $jenis, string $gambarDataUrl, User $pengambil): array
    {
        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $foto = $this->penanda->simpan($isi, $noo, $jenis, $pengambil, CarbonImmutable::now());

        return ['jenis' => $jenis, 'path' => $foto['path']];
    }

    /**
     * Menulis baris fotonya. updateOrCreate, bukan create: satu jenis hanya
     * boleh punya satu foto (lihat batasan unik di migrasi), dan mengambil
     * ulang berarti menimpa — bukan menumpuk baris kedua yang akan bentrok.
     *
     * @param  array<int, array{jenis: JenisBuktiNoo, path: string}>  $bukti
     */
    public function simpanSemua(Noo $noo, array $bukti): void
    {
        foreach ($bukti as $satu) {
            $noo->fotos()->updateOrCreate(
                ['jenis' => $satu['jenis']],
                ['path' => $satu['path']],
            );
        }
    }

    /**
     * Membuang berkas yang terlanjur ditulis ketika penyimpanannya gagal di
     * tengah jalan. Tanpa ini, percobaan yang batal meninggalkan berkas
     * yatim di disk — barisnya ikut hilang bersama transaksi, berkasnya
     * tidak.
     *
     * @param  array<int, array{jenis: JenisBuktiNoo, path: string}>  $bukti
     */
    public function hapusBerkas(array $bukti): void
    {
        if ($bukti === []) {
            return;
        }

        Storage::disk(self::DISK)->delete(array_column($bukti, 'path'));
    }
}
