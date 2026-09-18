<?php

namespace App\Services\Kendaraan;

use App\Enums\JenisCatatanBbm;
use App\Enums\LevelBahanBakar;
use App\Models\CatatanBbm;
use App\Models\Kendaraan;
use App\Models\User;
use App\Services\Kunjungan\GambarDataUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Mencatat kondisi kendaraan (KM + bahan bakar) di tiga titik — lihat
 * App\Enums\JenisCatatanBbm. `berangkat()`/`kembali()` masing-masing hanya
 * boleh sekali per kendaraan (dijaga di sini, bukan lewat unique index,
 * supaya pesannya ramah alih-alih galat basis data mentah); `pengisian()`
 * boleh dipanggil berkali-kali.
 */
class CatatanBbmService
{
    public function __construct(
        private readonly PenandaFotoKendaraan $penanda,
    ) {}

    public function berangkat(Kendaraan $kendaraan, User $driver, string $gambarDataUrl, int $km, LevelBahanBakar $levelBbm): CatatanBbm
    {
        if ($kendaraan->catatanBerangkat !== null) {
            throw new RuntimeException(__('kendaraan.galat_sudah_dicatat', ['jenis' => JenisCatatanBbm::Berangkat->label()]));
        }

        return $this->simpanSatu($kendaraan, $driver, JenisCatatanBbm::Berangkat, $gambarDataUrl, $km, $levelBbm);
    }

    public function kembali(Kendaraan $kendaraan, User $driver, string $gambarDataUrl, int $km, LevelBahanBakar $levelBbm): CatatanBbm
    {
        if ($kendaraan->catatanKembali !== null) {
            throw new RuntimeException(__('kendaraan.galat_sudah_dicatat', ['jenis' => JenisCatatanBbm::Kembali->label()]));
        }

        // Baru masuk akal dicatat setelah seluruh kunjungan tuntas — kalau
        // ini boleh diisi lebih dulu, foto "kembali" bisa dijepret padahal
        // mobilnya belum benar-benar selesai mengantar. Status 'selesai'
        // sendiri hanya bisa tercapai lewat aksi driver di layar pengiriman
        // (PengirimanService::segarkanKendaraan()), yang untuk kendaraan
        // BARU sudah mensyaratkan catatan berangkat lebih dulu — jadi
        // pengecekan status di sini otomatis mengunci urutan berangkat
        // sebelum kembali juga, tanpa perlu dicek terpisah.
        if ($kendaraan->status !== 'selesai') {
            throw new RuntimeException(__('kendaraan.galat_kembali_sebelum_selesai'));
        }

        return $this->simpanSatu($kendaraan, $driver, JenisCatatanBbm::Kembali, $gambarDataUrl, $km, $levelBbm);
    }

    private function simpanSatu(
        Kendaraan $kendaraan,
        User $driver,
        JenisCatatanBbm $jenis,
        string $gambarDataUrl,
        int $km,
        LevelBahanBakar $levelBbm,
    ): CatatanBbm {
        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $waktu = CarbonImmutable::now();
        $foto = $this->penanda->simpan($isi, $kendaraan, $jenis, $driver, $waktu, $km, $levelBbm);

        return CatatanBbm::create([
            'kendaraan_id' => $kendaraan->id,
            'jenis' => $jenis,
            'foto' => $foto['path'],
            'km' => $km,
            'level_bbm' => $levelBbm,
            'dicatat_oleh' => $driver->id,
        ]);
    }

    /**
     * Mencatat satu kejadian pengisian bahan bakar — opsional, boleh
     * berkali-kali per kendaraan. Struk/bukti wajib; foto sebelum & sesudah
     * opsional.
     */
    public function pengisian(
        Kendaraan $kendaraan,
        User $driver,
        string $strukDataUrl,
        ?string $sebelumDataUrl = null,
        ?string $sesudahDataUrl = null,
        ?float $liter = null,
        ?float $biaya = null,
        ?string $catatan = null,
    ): CatatanBbm {
        $isiStruk = GambarDataUrl::dekode($strukDataUrl);

        if ($isiStruk === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $waktu = CarbonImmutable::now();
        $fotoStruk = $this->penanda->simpan($isiStruk, $kendaraan, JenisCatatanBbm::Pengisian, $driver, $waktu, labelTambahan: __('kendaraan.label_struk'));

        $pathSebelum = $this->simpanFotoOpsional($sebelumDataUrl, $kendaraan, $driver, $waktu, __('kendaraan.label_sebelum_isi'));
        $pathSesudah = $this->simpanFotoOpsional($sesudahDataUrl, $kendaraan, $driver, $waktu, __('kendaraan.label_sesudah_isi'));

        try {
            return CatatanBbm::create([
                'kendaraan_id' => $kendaraan->id,
                'jenis' => JenisCatatanBbm::Pengisian,
                'foto' => $fotoStruk['path'],
                'foto_sebelum' => $pathSebelum,
                'foto_sesudah' => $pathSesudah,
                'liter' => $liter,
                'biaya' => $biaya,
                'catatan' => $catatan,
                'dicatat_oleh' => $driver->id,
            ]);
        } catch (\Throwable $e) {
            // Bersihkan berkas yang sudah terlanjur tersimpan bila
            // penyimpanan barisnya sendiri gagal, supaya tidak ada foto
            // yatim yang tidak tertaut ke catatan mana pun.
            Storage::disk(config('visit.foto.disk'))->delete(array_filter([$fotoStruk['path'], $pathSebelum, $pathSesudah]));

            throw $e;
        }
    }

    private function simpanFotoOpsional(?string $dataUrl, Kendaraan $kendaraan, User $driver, CarbonImmutable $waktu, string $label): ?string
    {
        if ($dataUrl === null) {
            return null;
        }

        $isi = GambarDataUrl::dekode($dataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        return $this->penanda->simpan($isi, $kendaraan, JenisCatatanBbm::Pengisian, $driver, $waktu, labelTambahan: $label)['path'];
    }
}
