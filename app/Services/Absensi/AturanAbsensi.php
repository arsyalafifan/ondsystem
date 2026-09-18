<?php

namespace App\Services\Absensi;

use App\Enums\JenisAbsensi;
use App\Enums\LokasiAbsensi;
use App\Enums\PorsiIzin;
use App\Enums\StatusAbsensi;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\Posisi;
use App\Models\Toko;
use App\Services\Kunjungan\GambarDataUrl;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Seluruh aturan absensi ada di sini: jam acuan (posisi atau shift),
 * penilaian tepat waktu/terlambat, dan pemeriksaan tempat absen.
 *
 * Waktu absen SELALU jam server (`CarbonImmutable::now()`), tidak pernah
 * dari perangkat — sama seperti foto kunjungan, karena jam ponsel bisa
 * diubah sendiri oleh pemakainya.
 */
class AturanAbsensi
{
    public function __construct(
        private PenandaFotoAbsensi $penanda,
        private KunjunganService $kunjungan,
        private PeriodeKunjunganService $periode,
    ) {}

    /**
     * Jam yang jadi patokan penilaian untuk satu jenis absen.
     *
     * Shift karyawan MENIMPA jam posisi bila ada (itulah arti "shift
     * menempel ke karyawan"); istirahat tidak punya padanan di shift, jadi
     * selalu memakai batas dari posisinya.
     *
     * Dengan izin setengah hari yang disetujui ($izin), acuannya bergeser:
     * izin paruh pertama → jam masuk = jam pulang − ½ hari kerja bersih;
     * izin paruh kedua → jam pulang = jam masuk + ½ hari kerja bersih.
     * Dihitung dari ujung jam kerja, jadi posisi istirahat di tengah hari
     * tidak perlu diketahui.
     */
    public function jamAcuan(Karyawan $karyawan, JenisAbsensi $jenis, CarbonImmutable $tanggal, ?PengajuanIzin $izin = null): ?CarbonImmutable
    {
        if ($izin !== null && $izin->porsi->setengahHari()) {
            $setengah = $this->menitSetengahHari($karyawan);

            if ($setengah !== null && $jenis === JenisAbsensi::Masuk && $izin->porsi === PorsiIzin::ParuhPertama) {
                return $this->jamAcuan($karyawan, JenisAbsensi::Pulang, $tanggal)?->subMinutes($setengah);
            }

            if ($setengah !== null && $jenis === JenisAbsensi::Pulang && $izin->porsi === PorsiIzin::ParuhKedua) {
                return $this->jamAcuan($karyawan, JenisAbsensi::Masuk, $tanggal)?->addMinutes($setengah);
            }
        }

        $posisi = $karyawan->posisi;

        if ($posisi === null) {
            return null;
        }

        $shift = $karyawan->shift;

        $jam = match ($jenis) {
            JenisAbsensi::Masuk => $shift?->jam_masuk ?? $posisi->jam_masuk,
            JenisAbsensi::Pulang => $shift?->jam_pulang ?? $posisi->jam_pulang,
            JenisAbsensi::Istirahat => $posisi->istirahat_paling_lambat,
        };

        if ($jam === null) {
            return null;
        }

        $acuan = CarbonImmutable::parse($tanggal->toDateString().' '.$jam);

        // Shift malam pulangnya di tanggal berikutnya (mis. masuk 22:00,
        // pulang 06:00) — tanpa ini, absen pulang pagi hari akan dinilai
        // terhadap jam 06:00 tanggal masuknya.
        return $jenis === JenisAbsensi::Pulang && ($shift?->lintas_hari ?? false)
            ? $acuan->addDay()
            : $acuan;
    }

    /**
     * Separuh jam kerja BERSIH (jam masuk s.d. pulang, dikurangi istirahat)
     * dalam menit — dasar izin setengah hari. Istirahat shift menimpa
     * istirahat posisi bila diisi, sama seperti jamnya.
     */
    public function menitSetengahHari(Karyawan $karyawan): ?int
    {
        $posisi = $karyawan->posisi;

        if ($posisi === null) {
            return null;
        }

        $hari = CarbonImmutable::today();
        $masuk = $this->jamAcuan($karyawan, JenisAbsensi::Masuk, $hari);
        $pulang = $this->jamAcuan($karyawan, JenisAbsensi::Pulang, $hari);

        if ($masuk === null || $pulang === null) {
            return null;
        }

        // Shift yang pulangnya melewati tengah malam tapi belum ditandai
        // lintas hari tetap dihitung wajar, bukan durasi negatif.
        if ($pulang->lessThanOrEqualTo($masuk)) {
            $pulang = $pulang->addDay();
        }

        $istirahat = $karyawan->shift?->durasi_istirahat_menit ?? $posisi->durasi_istirahat_menit;
        $bersih = (int) $masuk->diffInMinutes($pulang) - (int) $istirahat;

        return max(0, intdiv($bersih, 2));
    }

    /** Izin/sakit yang DISETUJUI dan mencakup tanggal kerja ini, bila ada. */
    public function izinPada(Karyawan $karyawan, CarbonImmutable $tanggal): ?PengajuanIzin
    {
        return PengajuanIzin::query()
            ->where('karyawan_id', $karyawan->id)
            ->disetujui()
            ->mencakup($tanggal)
            ->first();
    }

    /**
     * Tanggal kerja sebuah absen. Untuk istirahat/pulang, absennya menempel
     * pada baris "masuk" yang masih terbuka — termasuk milik kemarin bagi
     * karyawan bershift lintas hari.
     */
    public function tanggalKerja(Karyawan $karyawan, JenisAbsensi $jenis, CarbonImmutable $waktu): CarbonImmutable
    {
        $hariIni = $waktu->startOfDay();

        if ($jenis === JenisAbsensi::Masuk || $this->sudahAbsen($karyawan, $hariIni, JenisAbsensi::Masuk)) {
            return $hariIni;
        }

        return ($karyawan->shift?->lintas_hari ?? false)
            && $this->sudahAbsen($karyawan, $hariIni->subDay(), JenisAbsensi::Masuk)
                ? $hariIni->subDay()
                : $hariIni;
    }

    /**
     * @return array{0: StatusAbsensi, 1: int} status dan selisih menit dari
     *                                         jam acuan (telat untuk masuk/istirahat, lebih awal untuk pulang)
     */
    public function nilai(JenisAbsensi $jenis, ?CarbonImmutable $acuan, CarbonImmutable $waktu, int $toleransiMenit): array
    {
        if ($acuan === null) {
            return [StatusAbsensi::TepatWaktu, 0];
        }

        if ($jenis === JenisAbsensi::Pulang) {
            return $waktu->lessThan($acuan)
                ? [StatusAbsensi::PulangCepat, (int) $waktu->diffInMinutes($acuan)]
                : [StatusAbsensi::TepatWaktu, 0];
        }

        return $waktu->lessThanOrEqualTo($acuan->addMinutes($toleransiMenit))
            ? [StatusAbsensi::TepatWaktu, 0]
            : [StatusAbsensi::Terlambat, (int) $acuan->diffInMinutes($waktu)];
    }

    public function sudahAbsen(Karyawan $karyawan, CarbonImmutable $tanggal, JenisAbsensi $jenis): bool
    {
        return Absensi::query()
            ->where('karyawan_id', $karyawan->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->where('jenis', $jenis->value)
            ->exists();
    }

    /** Absen hari ini per jenis, untuk kartu status di layar Absensi. */
    public function hariIni(Karyawan $karyawan, ?CarbonImmutable $waktu = null): Collection
    {
        $waktu ??= CarbonImmutable::now();
        $tanggal = $this->tanggalKerja($karyawan, JenisAbsensi::Pulang, $waktu);

        return Absensi::query()
            ->where('karyawan_id', $karyawan->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->get()
            ->keyBy(fn (Absensi $a) => $a->jenis->value);
    }

    /**
     * Toko tanggungan yang masih boleh dipakai absen: ditugaskan ke akun
     * karyawan ini DAN belum dikunjungi pada periode berjalan. Memakai
     * sumber yang sama dengan menu Visit Sales, jadi begitu sebuah toko
     * dikunjungi ia otomatis hilang dari daftar ini.
     *
     * @return Collection<int, Toko>
     */
    public function tokoTersisa(Karyawan $karyawan): Collection
    {
        if ($karyawan->user === null) {
            return collect();
        }

        return $this->kunjungan
            ->tanggungan($karyawan->user, $this->periode->periodeBerjalan())
            ->filter(fn (Toko $toko) => $toko->perluDikunjungi() && $toko->punya_koordinat)
            ->values();
    }

    /** @throws RuntimeException bila aturannya tidak terpenuhi */
    public function catat(
        Karyawan $karyawan,
        JenisAbsensi $jenis,
        string $gambarDataUrl,
        ?float $lat = null,
        ?float $lng = null,
        ?int $akurasi = null,
    ): Absensi {
        $posisi = $karyawan->posisi;

        if ($posisi === null) {
            throw new RuntimeException(__('hr.galat_posisi_kosong'));
        }

        if ($jenis === JenisAbsensi::Istirahat && ! $posisi->pakai_absen_istirahat) {
            throw new RuntimeException(__('hr.galat_istirahat_tidak_aktif'));
        }

        $waktu = CarbonImmutable::now();
        $tanggal = $this->tanggalKerja($karyawan, $jenis, $waktu);

        if ($jenis !== JenisAbsensi::Masuk && ! $this->sudahAbsen($karyawan, $tanggal, JenisAbsensi::Masuk)) {
            throw new RuntimeException(__('hr.galat_belum_absen_masuk'));
        }

        if ($this->sudahAbsen($karyawan, $tanggal, $jenis)) {
            throw new RuntimeException(__('hr.galat_sudah_absen', ['jenis' => $jenis->label()]));
        }

        $izin = $this->izinPada($karyawan, $tanggal);

        // Izin/sakit sehari penuh yang sudah disetujui: tidak ada yang perlu
        // diabsen — kalau karyawannya ternyata masuk, HR membatalkan izinnya
        // dulu, bukan dua catatan yang saling bertentangan.
        if ($izin !== null && ! $izin->porsi->setengahHari()) {
            throw new RuntimeException(__('izin.galat_absen_saat_izin', ['jenis' => $izin->jenis->label()]));
        }

        // Setengah hari tidak melewati jam istirahat (datang sesudahnya atau
        // pulang sebelumnya), jadi absen kembali istirahat tidak berlaku.
        if ($izin !== null && $jenis === JenisAbsensi::Istirahat) {
            throw new RuntimeException(__('izin.galat_istirahat_saat_setengah_hari'));
        }

        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        // Tempat diperiksa SEBELUM berkas ditulis, supaya absen yang ditolak
        // tidak meninggalkan foto yatim di disk.
        $lokasi = $this->periksaLokasi($karyawan, $posisi, $lat, $lng);

        $acuan = $this->jamAcuan($karyawan, $jenis, $tanggal, $izin);
        [$status, $selisih] = $this->nilai($jenis, $acuan, $waktu, $posisi->toleransi_telat_menit);

        $foto = $this->penanda->simpan($isi, $karyawan, $jenis, $waktu, $lat, $lng, $akurasi, $lokasi->jarakM, $lokasi->nama);

        try {
            return Absensi::create([
                'karyawan_id' => $karyawan->id,
                'tanggal' => $tanggal->toDateString(),
                'jenis' => $jenis,
                'waktu' => $waktu,
                'status' => $status,
                'telat_menit' => $selisih,
                'jam_acuan' => $acuan?->format('H:i:s'),
                'posisi_id' => $posisi->id,
                'shift_id' => $karyawan->shift_id,
                'foto' => $foto['path'],
                'latitude' => $lat,
                'longitude' => $lng,
                'akurasi_m' => $akurasi,
                'jarak_m' => $lokasi->jarakM,
                'lokasi_jenis' => $posisi->lokasi_jenis,
                'depot_id' => $lokasi->depotId,
                'toko_id' => $lokasi->tokoId,
            ]);
        } catch (Throwable $e) {
            // Barisnya gagal tersimpan — fotonya ikut dibuang, bukan
            // ditinggalkan sebagai berkas yatim yang tak pernah terhapus.
            Storage::disk(config('visit.foto.disk'))->delete($foto['path']);

            throw $e;
        }
    }

    /** @throws RuntimeException bila di luar radius tempat kerjanya */
    private function periksaLokasi(Karyawan $karyawan, Posisi $posisi, ?float $lat, ?float $lng): HasilLokasi
    {
        if ($posisi->lokasi_jenis === LokasiAbsensi::Bebas) {
            return new HasilLokasi(null, null, null, '');
        }

        if ($lat === null || $lng === null) {
            throw new RuntimeException(__('hr.galat_lokasi_kosong'));
        }

        $titik = new Koordinat($lat, $lng);

        if ($posisi->lokasi_jenis === LokasiAbsensi::Depot) {
            $depot = $karyawan->depot;

            if ($depot === null || $depot->lat === null || $depot->lng === null) {
                throw new RuntimeException(__('hr.galat_depot_tanpa_koordinat'));
            }

            $jarak = (int) round(Geo::haversine($titik, new Koordinat($depot->lat, $depot->lng)));

            if ($jarak > $posisi->radius_meter) {
                throw new RuntimeException(__('hr.galat_jarak_depot', [
                    'tempat' => $depot->nama,
                    'jarak' => $jarak,
                    'radius' => $posisi->radius_meter,
                ]));
            }

            return new HasilLokasi($jarak, $depot->id, null, $depot->nama);
        }

        if ($karyawan->user === null) {
            throw new RuntimeException(__('hr.galat_tanpa_akun'));
        }

        $kandidat = $this->tokoTersisa($karyawan);

        if ($kandidat->isEmpty()) {
            throw new RuntimeException(__('hr.galat_tanpa_toko_tersisa'));
        }

        /** @var array{toko: Toko, jarak: int} $terdekat */
        $terdekat = $kandidat
            ->map(fn (Toko $toko) => [
                'toko' => $toko,
                'jarak' => (int) round(Geo::haversine($titik, new Koordinat($toko->latitude, $toko->longitude))),
            ])
            ->sortBy('jarak')
            ->first();

        if ($terdekat['jarak'] > $posisi->radius_meter) {
            throw new RuntimeException(__('hr.galat_jarak_toko', [
                'tempat' => $terdekat['toko']->nama,
                'jarak' => $terdekat['jarak'],
                'radius' => $posisi->radius_meter,
            ]));
        }

        return new HasilLokasi($terdekat['jarak'], null, $terdekat['toko']->id, $terdekat['toko']->nama);
    }
}
