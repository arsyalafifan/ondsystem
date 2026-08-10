<?php

namespace App\Services\Kunjungan;

/**
 * Mengurai isi QR code yang tertempel pada freezer.
 *
 * Bentuk isinya berupa daftar berlabel bahasa Mandarin:
 *
 *   客户名称：IDN Halocoko
 *   资产编号：IDNAH202528004381
 *   产品型号：SD-280
 *
 * Yang dipakai untuk mengenali toko adalah nomor aset (资产编号). Dua hal yang
 * perlu diperhatikan: labelnya memakai titik dua lebar (：, U+FF1A) yang
 * berbeda dari titik dua biasa, dan sebagian pemindai mengembalikan pemisah
 * baris yang tidak seragam. Keduanya ditangani di sini.
 */
class PenguraiQr
{
    public function urai(string $isi): ?HasilPindaiQr
    {
        $isi = trim($isi);

        if ($isi === '') {
            return null;
        }

        $baris = preg_split('/\R+/u', $isi) ?: [];
        $nilai = [];

        foreach ($baris as $satuBaris) {
            [$label, $isiBaris] = $this->pisah($satuBaris);

            if ($label !== null) {
                $nilai[$label] = $isiBaris;
            }
        }

        $assetId = $this->ambil($nilai, config('visit.qr.label_aset'));

        // Sebagian QR hanya memuat nomor asetnya saja, tanpa label apa pun.
        if ($assetId === null && $this->miripNomorAset($isi)) {
            $assetId = $isi;
        }

        if ($assetId === null) {
            return null;
        }

        return new HasilPindaiQr(
            assetId: $this->rapikanAset($assetId),
            namaPelanggan: $this->ambil($nilai, config('visit.qr.label_pelanggan')),
            model: $this->ambil($nilai, config('visit.qr.label_model')),
            mentah: $isi,
        );
    }

    /**
     * Memecah satu baris menjadi label dan isinya.
     *
     * @return array{0: string|null, 1: string}
     */
    private function pisah(string $baris): array
    {
        // Titik dua lebar, titik dua biasa, dan tanda sama dengan semuanya
        // dipakai sebagai pemisah oleh pencetak label yang berbeda-beda.
        $bagian = preg_split('/[：:=]/u', trim($baris), 2);

        if ($bagian === false || count($bagian) < 2) {
            return [null, ''];
        }

        return [$this->normalkan($bagian[0]), trim($bagian[1])];
    }

    /**
     * @param  array<string, string>  $nilai
     * @param  array<int, string>  $labelYangDicari
     */
    private function ambil(array $nilai, array $labelYangDicari): ?string
    {
        foreach ($labelYangDicari as $label) {
            $kunci = $this->normalkan($label);

            if (isset($nilai[$kunci]) && $nilai[$kunci] !== '') {
                return $nilai[$kunci];
            }
        }

        return null;
    }

    private function normalkan(string $teks): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', '', $teks) ?? ''));
    }

    private function miripNomorAset(string $isi): bool
    {
        return preg_match((string) config('visit.qr.pola_aset'), $this->rapikanAset($isi)) === 1;
    }

    /** Nomor aset disimpan huruf besar tanpa spasi agar pencocokan konsisten. */
    private function rapikanAset(string $assetId): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', '', $assetId) ?? ''));
    }
}
