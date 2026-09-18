<?php

namespace App\Services\Lembur;

use App\Enums\JenisAbsensi;
use App\Enums\StatusPengajuan;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\PengajuanLembur;
use App\Models\User;
use App\Services\Absensi\AturanAbsensi;
use App\Services\Izin\ApproverIzin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aturan lembur: diajukan sebelum mulai, di luar jam kerja, diputuskan
 * dengan aturan approver yang sama seperti izin, dan jam yang DIAKUI =
 * min(diajukan, batas per hari, yang benar-benar dijalani).
 */
class PengajuanLemburService
{
    /** Satu pengajuan paling panjang ini — penjaga salah isi jam. */
    public const MAKS_DURASI_MENIT = 12 * 60;

    public function __construct(
        private readonly ApproverIzin $approver,
        private readonly AturanAbsensi $absensi,
    ) {}

    /** @throws RuntimeException bila aturannya tidak terpenuhi */
    public function ajukan(
        Karyawan $karyawan,
        User $pengaju,
        CarbonImmutable $tanggal,
        string $jamMulai,
        string $jamSelesai,
        string $tugas,
    ): PengajuanLembur {
        if ($karyawan->posisi === null) {
            throw new RuntimeException(__('hr.galat_posisi_kosong'));
        }

        $tanggal = $tanggal->startOfDay();
        $mulai = CarbonImmutable::parse($tanggal->toDateString().' '.$jamMulai);
        $selesai = CarbonImmutable::parse($tanggal->toDateString().' '.$jamSelesai);
        $lintasHari = $selesai->lessThanOrEqualTo($mulai);

        if ($lintasHari) {
            $selesai = $selesai->addDay();
        }

        $menit = (int) $mulai->diffInMinutes($selesai);

        if ($menit > self::MAKS_DURASI_MENIT) {
            throw new RuntimeException(__('lembur.galat_terlalu_panjang', ['jam' => self::MAKS_DURASI_MENIT / 60]));
        }

        // Pre-approval: lembur yang sudah dimulai tidak bisa diajukan lagi.
        if ($mulai->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw new RuntimeException(__('lembur.galat_sudah_mulai'));
        }

        $this->pastikanDiLuarJamKerja($karyawan, $tanggal, $mulai, $selesai);
        $this->pastikanTidakSedangIzin($karyawan, $tanggal);
        $this->pastikanTidakBertumpuk($karyawan, $tanggal, $mulai, $selesai);

        return PengajuanLembur::create([
            'karyawan_id' => $karyawan->id,
            'tanggal' => $tanggal->toDateString(),
            'jam_mulai' => $mulai->format('H:i:s'),
            'jam_selesai' => $selesai->format('H:i:s'),
            'lintas_hari' => $lintasHari,
            'menit_diajukan' => $menit,
            'menit_maks' => $this->approver->pengaturan()->maks_lembur_menit,
            'tugas' => $tugas,
            'status' => StatusPengajuan::Menunggu,
            'diajukan_oleh' => $pengaju->id,
        ]);
    }

    /**
     * Jam lembur yang diakui. `realisasi` = irisan waktu lembur dengan
     * waktu hadir (absen masuk s.d. absen pulang); null selama absen pulang
     * belum ada dan hari itu belum lewat — belum bisa dinilai, bukan nol.
     *
     * @return array{diajukan: int, batas: int, rencana: int, realisasi: ?int, diakui: ?int}
     */
    public function hitung(PengajuanLembur $lembur): array
    {
        $rencana = min($lembur->menit_diajukan, $lembur->menit_maks);
        $realisasi = $this->realisasi($lembur);

        return [
            'diajukan' => $lembur->menit_diajukan,
            'batas' => $lembur->menit_maks,
            'rencana' => $rencana,
            'realisasi' => $realisasi,
            'diakui' => $realisasi === null ? null : min($rencana, $realisasi),
        ];
    }

    private function realisasi(PengajuanLembur $lembur): ?int
    {
        $absen = Absensi::query()
            ->where('karyawan_id', $lembur->karyawan_id)
            ->whereDate('tanggal', $lembur->tanggal->toDateString())
            ->whereIn('jenis', [JenisAbsensi::Masuk->value, JenisAbsensi::Pulang->value])
            ->get()
            ->keyBy(fn (Absensi $a) => $a->jenis->value);

        $masuk = $absen->get(JenisAbsensi::Masuk->value);
        $pulang = $absen->get(JenisAbsensi::Pulang->value);

        if ($masuk === null || $pulang === null) {
            // Tanpa absen pulang: belum bisa dinilai selama lemburnya belum
            // lewat; sesudah itu dianggap tidak dijalani.
            return $lembur->selesaiAt()->addDay()->isPast() ? 0 : null;
        }

        $dari = max($lembur->mulaiAt()->getTimestamp(), $masuk->waktu->getTimestamp());
        $sampai = min($lembur->selesaiAt()->getTimestamp(), $pulang->waktu->getTimestamp());

        return max(0, intdiv($sampai - $dari, 60));
    }

    /** @throws RuntimeException */
    public function setujui(PengajuanLembur $lembur, User $approver, ?string $catatan = null): void
    {
        $this->putuskan($lembur, $approver, StatusPengajuan::Disetujui, $catatan);
    }

    /** @throws RuntimeException */
    public function tolak(PengajuanLembur $lembur, User $approver, string $catatan): void
    {
        $this->putuskan($lembur, $approver, StatusPengajuan::Ditolak, $catatan);
    }

    /** Karyawan menarik pengajuannya sendiri — hanya selama belum diputuskan. */
    public function batalkan(PengajuanLembur $lembur, User $pengguna): void
    {
        DB::transaction(function () use ($lembur, $pengguna) {
            $lembur = PengajuanLembur::query()->lockForUpdate()->findOrFail($lembur->id);
            $this->pastikanMenunggu($lembur);

            $lembur->update([
                'status' => StatusPengajuan::Dibatalkan,
                'diputuskan_oleh' => $pengguna->id,
                'diputuskan_at' => now(),
            ]);
        });
    }

    private function putuskan(PengajuanLembur $lembur, User $approver, StatusPengajuan $status, ?string $catatan): void
    {
        DB::transaction(function () use ($lembur, $approver, $status, $catatan) {
            $lembur = PengajuanLembur::query()->lockForUpdate()->findOrFail($lembur->id);
            $this->pastikanMenunggu($lembur);

            if (! $this->approver->bolehMemutuskan($approver, $lembur)) {
                throw new RuntimeException(__('izin.galat_bukan_approver'));
            }

            $lembur->update([
                'status' => $status,
                'diputuskan_oleh' => $approver->id,
                'diputuskan_at' => now(),
                'catatan_keputusan' => $catatan,
            ]);
        });
    }

    private function pastikanMenunggu(PengajuanLembur $lembur): void
    {
        if ($lembur->status !== StatusPengajuan::Menunggu) {
            throw new RuntimeException(__('izin.galat_sudah_diputuskan', ['status' => $lembur->status->label()]));
        }
    }

    /**
     * Di hari kerja posisinya, lembur tidak boleh beririsan dengan jam kerja
     * normal (posisi/shift). Hari libur posisi: jam berapa pun.
     */
    private function pastikanDiLuarJamKerja(Karyawan $karyawan, CarbonImmutable $tanggal, CarbonImmutable $mulai, CarbonImmutable $selesai): void
    {
        if (! in_array($tanggal->dayOfWeekIso, $karyawan->posisi->hariKerja(), true)) {
            return;
        }

        $masuk = $this->absensi->jamAcuan($karyawan, JenisAbsensi::Masuk, $tanggal);
        $pulang = $this->absensi->jamAcuan($karyawan, JenisAbsensi::Pulang, $tanggal);

        if ($masuk === null || $pulang === null) {
            return;
        }

        if ($pulang->lessThanOrEqualTo($masuk)) {
            $pulang = $pulang->addDay();
        }

        if ($mulai->lessThan($pulang) && $selesai->greaterThan($masuk)) {
            throw new RuntimeException(__('lembur.galat_jam_kerja', [
                'masuk' => $masuk->format('H:i'),
                'pulang' => $pulang->format('H:i'),
            ]));
        }
    }

    /** Lembur di hari izin/sakit sehari penuh tidak masuk akal. */
    private function pastikanTidakSedangIzin(Karyawan $karyawan, CarbonImmutable $tanggal): void
    {
        $izin = PengajuanIzin::query()
            ->where('karyawan_id', $karyawan->id)
            ->aktif()
            ->mencakup($tanggal)
            ->get()
            ->first(fn (PengajuanIzin $i) => ! $i->porsi->setengahHari());

        if ($izin !== null) {
            throw new RuntimeException(__('lembur.galat_sedang_izin', ['jenis' => mb_strtolower($izin->jenis->label())]));
        }
    }

    private function pastikanTidakBertumpuk(Karyawan $karyawan, CarbonImmutable $tanggal, CarbonImmutable $mulai, CarbonImmutable $selesai): void
    {
        $bentrok = PengajuanLembur::query()
            ->where('karyawan_id', $karyawan->id)
            ->aktif()
            ->whereDate('tanggal', '>=', $tanggal->subDay()->toDateString())
            ->whereDate('tanggal', '<=', $tanggal->addDay()->toDateString())
            ->get()
            ->first(fn (PengajuanLembur $l) => $mulai->lessThan($l->selesaiAt()) && $selesai->greaterThan($l->mulaiAt()));

        if ($bentrok !== null) {
            throw new RuntimeException(__('lembur.galat_bertumpuk', [
                'tanggal' => $bentrok->tanggal->isoFormat('D MMM Y'),
                'jam' => $bentrok->jamTeks(),
            ]));
        }
    }
}
