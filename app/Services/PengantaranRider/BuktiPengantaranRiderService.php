<?php

namespace App\Services\PengantaranRider;

use App\Enums\JenisBuktiPengiriman;
use App\Models\PengantaranRider;
use App\Models\User;
use App\Services\Kunjungan\GambarDataUrl;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Mendekode bidikan kamera (data URL) menjadi satu baris bukti pengiriman
 * rider siap simpan — salinan App\Services\Pengiriman\BuktiPengirimanService,
 * diketik ke PengantaranRider bukan KendaraanStop. Lihat docblock aslinya:
 * kelengkapan bukti SENGAJA tidak diperiksa di sini, itu tanggung jawab
 * komponen Livewire-nya (App\Livewire\PengantaranRider\DaftarPengantaranRider::semuaBuktiLengkap()).
 */
class BuktiPengantaranRiderService
{
    public function __construct(
        private readonly PenandaFotoPengantaranRider $penanda,
    ) {}

    /** @return array{jenis: JenisBuktiPengiriman, path: string, catatan: ?string} */
    public function simpanFoto(PengantaranRider $pengantaran, JenisBuktiPengiriman $jenis, string $gambarDataUrl, User $rider): array
    {
        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $foto = $this->penanda->simpan($isi, $pengantaran, $jenis, $rider, CarbonImmutable::now());

        return ['jenis' => $jenis, 'path' => $foto['path'], 'catatan' => null];
    }

    /** @return array{jenis: JenisBuktiPengiriman, path: string, catatan: ?string} */
    public function simpanTandaTangan(PengantaranRider $pengantaran, string $gambarDataUrl, User $rider, string $namaPenandatangan): array
    {
        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $foto = $this->penanda->simpan(
            $isi, $pengantaran, JenisBuktiPengiriman::TandaTanganTokoSusunSendiri, $rider, CarbonImmutable::now(), $namaPenandatangan,
        );

        return ['jenis' => JenisBuktiPengiriman::TandaTanganTokoSusunSendiri, 'path' => $foto['path'], 'catatan' => $namaPenandatangan];
    }

    /**
     * Mencatat baris PengantaranRiderFoto dari bukti yang SUDAH didekode &
     * disimpan (lewat simpanFoto()/simpanTandaTangan()) — dipanggil di
     * dalam transaksi yang sama dengan perubahan status pengantaran.
     *
     * @param  array<int, array{jenis: JenisBuktiPengiriman, path: string, catatan: ?string}>  $bukti
     */
    public function simpanSemua(PengantaranRider $pengantaran, array $bukti): void
    {
        foreach ($bukti as $satu) {
            $pengantaran->fotos()->create([
                'jenis' => $satu['jenis'],
                'path' => $satu['path'],
                'catatan' => $satu['catatan'] ?? null,
            ]);
        }
    }
}
