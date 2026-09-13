<?php

namespace App\Services\Kunjungan;

use App\Enums\SumberFotoKunjungan;
use App\Models\Toko;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Memperkecil foto kunjungan lalu membakar keterangan waktu dan lokasi ke
 * dalam gambarnya.
 *
 * Watermark dipasang di server, bukan di peramban, dan jamnya diambil dari jam
 * server. Kalau keduanya dikerjakan di sisi peramban, sales cukup memundurkan
 * jam ponselnya untuk membuat foto lama tampak baru — persis hal yang ingin
 * dicegah. Titik lokasi tetap berasal dari peramban karena hanya di sanalah
 * GPS bisa dibaca, dan itu ditandai apa adanya bila tidak tersedia.
 *
 * SATU pengecualian: foto yang diambil saat tidak ada jaringan. Server tidak
 * pernah melihat foto itu pada saat pengambilannya, jadi waktunya mau tidak
 * mau berasal dari jam ponsel ($diambilAt diisi pemanggil). Karena jaminannya
 * memang lebih lemah, foto seperti itu TIDAK disamarkan sebagai foto biasa:
 * watermark-nya diberi baris tambahan bertuliskan OFFLINE beserta waktu
 * datanya sampai di server, supaya bedanya terlihat langsung di gambar — bukan
 * cuma tersimpan diam-diam di basis data.
 *
 * Hal yang sama berlaku untuk foto UNGGAHAN, dengan alasan berbeda: berkasnya
 * dipilih dari galeri, jadi bisa gambar apa saja dari kapan saja. Waktunya
 * berasal dari EXIF berkas itu sendiri (atau jam server bila EXIF-nya kosong),
 * dan watermark-nya diberi tanda tersendiri.
 *
 * `$offline` dan `$diambilAt` sengaja jadi dua parameter terpisah, bukan satu
 * yang menurunkan yang lain: unggahan daring juga membawa waktunya sendiri
 * tanpa pernah menyentuh keadaan luring, dan menyamakan keduanya akan
 * mencetak tanda OFFLINE pada foto yang sebenarnya dikirim saat ada sinyal.
 */
class PenandaFoto
{
    /**
     * @param  ?CarbonImmutable  $diambilAt  waktu yang dicetak pada watermark:
     *                                       jam ponsel untuk kunjungan luring,
     *                                       atau waktu EXIF untuk unggahan.
     *                                       Null berarti pakai jam server.
     * @param  bool  $offline  menandai foto yang dikerjakan tanpa jaringan
     * @param  SumberFotoKunjungan  $sumber  bidikan langsung atau berkas unggahan;
     *                                       unggahan mendapat tanda tersendiri
     *                                       karena kekuatan buktinya berbeda
     * @return array{path: string, lebar: int, tinggi: int, ukuran: int, diambil_at: CarbonImmutable}
     */
    public function simpan(
        string $isiGambar,
        Toko $toko,
        User $sales,
        ?float $lat = null,
        ?float $lng = null,
        ?int $akurasi = null,
        ?CarbonImmutable $diambilAt = null,
        SumberFotoKunjungan $sumber = SumberFotoKunjungan::Kamera,
        bool $offline = false,
    ): array {
        $diambilAt ??= CarbonImmutable::now();

        $gambar = @imagecreatefromstring($isiGambar);

        if ($gambar === false) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $gambar = $this->perkecil($gambar);
        $this->cetakKeterangan($gambar, $toko, $sales, $diambilAt, $lat, $lng, $akurasi, $offline, $sumber);

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        ob_start();
        imagejpeg($gambar, null, (int) config('visit.foto.mutu_jpeg'));
        $keluaran = (string) ob_get_clean();

        imagedestroy($gambar);

        $path = sprintf(
            'kunjungan/%s/%s/%s.jpg',
            $diambilAt->format('Y-m-d'),
            $toko->id,
            Str::ulid(),
        );

        Storage::disk(config('visit.foto.disk'))->put($path, $keluaran);

        return [
            'path' => $path,
            'lebar' => $lebar,
            'tinggi' => $tinggi,
            'ukuran' => strlen($keluaran),
            'diambil_at' => $diambilAt,
        ];
    }

    /** @param \GdImage $gambar */
    private function perkecil($gambar)
    {
        $lebarMaks = (int) config('visit.foto.lebar_maks');
        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        if ($lebar <= $lebarMaks) {
            return $gambar;
        }

        $lebarBaru = $lebarMaks;
        $tinggiBaru = (int) round($tinggi * ($lebarMaks / $lebar));

        $kecil = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
        imagedestroy($gambar);

        return $kecil;
    }

    /** @param \GdImage $gambar */
    private function cetakKeterangan(
        $gambar,
        Toko $toko,
        User $sales,
        CarbonImmutable $waktu,
        ?float $lat,
        ?float $lng,
        ?int $akurasi,
        bool $offline = false,
        SumberFotoKunjungan $sumber = SumberFotoKunjungan::Kamera,
    ): void {
        $baris = array_values(array_filter([
            $waktu->isoFormat('dddd, D MMMM Y').' · '.$waktu->format('H:i:s').' '.$waktu->format('T'),
            $toko->nama.($toko->asset_id ? ' · '.$toko->asset_id : ''),
            __('kunjungan.wm_sales').': '.$sales->name,
            $lat !== null && $lng !== null
                ? sprintf('%s: %.6f, %.6f%s', __('kunjungan.wm_lokasi'), $lat, $lng, $akurasi ? " (±{$akurasi} m)" : '')
                : __('kunjungan.wm_tanpa_lokasi'),
            // Baris ini sengaja paling bawah supaya paling dekat dengan tepi
            // gambar dan sulit dipotong tanpa merusak baris waktu di atasnya.
            $offline
                ? __('kunjungan.wm_offline', ['waktu' => CarbonImmutable::now()->format('d/m/Y H:i')])
                : null,
            // Foto unggahan tidak pernah dilihat kamera halaman ini. Tanda
            // ini yang membedakannya dari bidikan langsung ketika gambarnya
            // sudah keluar dari aplikasi — dicetak ke dalam gambar, bukan
            // cuma disimpan di basis data yang bisa tertinggal saat foto
            // disalin atau dikirim ulang.
            $sumber === SumberFotoKunjungan::Unggah ? __('kunjungan.wm_unggahan') : null,
        ]));

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        // Ukuran huruf mengikuti lebar gambar supaya tetap terbaca baik pada
        // foto kecil maupun besar.
        $font = max(2, min(5, (int) round($lebar / 320)));
        $tinggiBaris = imagefontheight($font) + 4;
        $padding = 10;
        $tinggiPanel = $tinggiBaris * count($baris) + $padding * 2;

        $hitam = imagecolorallocatealpha($gambar, 0, 0, 0, 45);
        imagefilledrectangle($gambar, 0, $tinggi - $tinggiPanel, $lebar, $tinggi, $hitam);

        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $bayangan = imagecolorallocate($gambar, 0, 0, 0);

        $y = $tinggi - $tinggiPanel + $padding;

        foreach ($baris as $teks) {
            // Teks Latin saja: GD tanpa berkas font TrueType tidak bisa
            // menggambar aksara Han, jadi keterangan sengaja dibuat netral.
            $bersih = $this->keLatin($teks);

            imagestring($gambar, $font, $padding + 1, $y + 1, $bersih, $bayangan);
            imagestring($gambar, $font, $padding, $y, $bersih, $putih);

            $y += $tinggiBaris;
        }
    }

    /**
     * Menyiapkan teks agar aman digambar oleh GD.
     *
     * imagestring() hanya mengenal satu bita per huruf, sehingga aksara di
     * luar Latin-1 akan tampil sebagai sampah. Teks diubah ke ASCII dulu,
     * dan huruf yang tidak punya padanan dibuang.
     */
    private function keLatin(string $teks): string
    {
        $hasil = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $teks);

        return $hasil === false ? preg_replace('/[^\x20-\x7E]/', '', $teks) ?? '' : $hasil;
    }
}
