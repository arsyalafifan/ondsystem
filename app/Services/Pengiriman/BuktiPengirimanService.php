<?php

namespace App\Services\Pengiriman;

use App\Enums\JenisBuktiPengiriman;
use App\Models\KendaraanStop;
use App\Models\User;
use App\Services\Kunjungan\GambarDataUrl;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Mendekode bidikan kamera (data URL) menjadi satu baris bukti pengiriman
 * siap simpan — dipanggil App\Livewire\Driver\DaftarKunjungan untuk tiap
 * foto SEBELUM stop-nya sungguh diselesaikan, persis pola yang sama dengan
 * foto nota (fotoNota->store() dulu, baru diserahkan ke
 * PesananService::selesaikanPengiriman() / PengirimanService::coretNota()).
 *
 * Kelengkapan bukti (mana yang wajib, tanda tangan sebagai pengganti foto
 * freezer disusun) SENGAJA tidak diperiksa di sini — itu aturan alur kerja
 * driver, diperiksa di komponen Livewire-nya sendiri
 * (DaftarKunjungan::semuaBuktiLengkap()), supaya pemanggil lain (mis. tes,
 * atau skrip pemulihan data) tetap bisa menyelesaikan pengiriman tanpa
 * terikat pada aturan itu.
 */
class BuktiPengirimanService
{
    public function __construct(
        private readonly PenandaFotoPengiriman $penanda,
    ) {}

    /** @return array{jenis: JenisBuktiPengiriman, path: string, catatan: ?string} */
    public function simpanFoto(KendaraanStop $stop, JenisBuktiPengiriman $jenis, string $gambarDataUrl, User $driver): array
    {
        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $foto = $this->penanda->simpan($isi, $stop, $jenis, $driver, CarbonImmutable::now());

        return ['jenis' => $jenis, 'path' => $foto['path'], 'catatan' => null];
    }

    /**
     * Tanda tangan digital toko — dipakai sebagai pengganti foto "freezer
     * disusun" saat toko memilih menyusun es krimnya sendiri. Nama
     * penanggung jawab wajib diisi supaya tanda tangannya tertaut ke
     * seseorang, bukan cuma coretan anonim.
     *
     * @return array{jenis: JenisBuktiPengiriman, path: string, catatan: ?string}
     */
    public function simpanTandaTangan(KendaraanStop $stop, string $gambarDataUrl, User $driver, string $namaPenandatangan): array
    {
        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $foto = $this->penanda->simpan(
            $isi, $stop, JenisBuktiPengiriman::TandaTanganTokoSusunSendiri, $driver, CarbonImmutable::now(), $namaPenandatangan,
        );

        return ['jenis' => JenisBuktiPengiriman::TandaTanganTokoSusunSendiri, 'path' => $foto['path'], 'catatan' => $namaPenandatangan];
    }

    /**
     * Mencatat baris StopFoto dari bukti yang SUDAH didekode & disimpan
     * (lewat simpanFoto()/simpanTandaTangan()) — dipanggil PesananService
     * & PengirimanService di dalam transaksi yang sama dengan perubahan
     * status stop-nya, supaya atomis: kalau transaksinya batal, baris
     * bukti ini ikut batal, walau berkas gambarnya sendiri (di luar
     * transaksi basis data) tetap jadi tanggung jawab pemanggil untuk
     * dibersihkan — persis seperti foto nota.
     *
     * @param  array<int, array{jenis: JenisBuktiPengiriman, path: string, catatan: ?string}>  $bukti
     */
    public function simpanSemua(KendaraanStop $stop, array $bukti): void
    {
        foreach ($bukti as $satu) {
            $stop->fotos()->create([
                'jenis' => $satu['jenis'],
                'path' => $satu['path'],
                'catatan' => $satu['catatan'] ?? null,
            ]);
        }
    }
}
