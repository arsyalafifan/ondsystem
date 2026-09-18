<?php

namespace App\Services\Izin;

use App\Enums\JenisAbsensi;
use App\Enums\JenisIzin;
use App\Enums\PorsiIzin;
use App\Enums\StatusPengajuan;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Seluruh aturan pengajuan izin/sakit: siapa boleh mengajukan untuk
 * tanggal apa, berapa hari yang terhitung, dan alur keputusan HR.
 *
 * Aturannya dijaga di sini (bukan cuma di form) supaya sama persis untuk
 * semua jalur masuk, termasuk yang kelak ditambah (API, impor).
 */
class PengajuanIzinService
{
    public function __construct(
        private readonly ApproverIzin $approver,
    ) {}

    /** Satu pengajuan paling panjang ini — mencegah salah ketik tahun, dsb. */
    public const MAKS_HARI_KALENDER = 30;

    /** Lampiran di disk privat — lihat catatan migrasi create_pengajuan_izins_table. */
    public const DISK = 'local';

    /**
     * Hari kerja (menurut posisi karyawan) dalam rentang, dikali porsinya.
     * Hari libur posisi tidak terhitung — sakit Jumat–Senin untuk posisi
     * Senin–Sabtu = 3 hari, bukan 4.
     */
    public function hitungHari(Karyawan $karyawan, CarbonImmutable $mulai, CarbonImmutable $selesai, PorsiIzin $porsi): float
    {
        $hariKerja = $karyawan->posisi?->hariKerja() ?? [];
        $jumlah = 0;

        foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
            if (in_array($tanggal->dayOfWeekIso, $hariKerja, true)) {
                $jumlah++;
            }
        }

        return $jumlah * $porsi->faktor();
    }

    /** @throws RuntimeException bila aturannya tidak terpenuhi */
    public function ajukan(
        Karyawan $karyawan,
        User $pengaju,
        JenisIzin $jenis,
        PorsiIzin $porsi,
        CarbonImmutable $mulai,
        CarbonImmutable $selesai,
        string $alasan,
        ?UploadedFile $lampiran = null,
    ): PengajuanIzin {
        $mulai = $mulai->startOfDay();
        $selesai = $selesai->startOfDay();

        if ($karyawan->posisi === null) {
            throw new RuntimeException(__('hr.galat_posisi_kosong'));
        }

        if ($porsi->setengahHari() && ! $jenis->bolehSetengahHari()) {
            throw new RuntimeException(__('izin.galat_setengah_hari_tidak_boleh', ['jenis' => $jenis->label()]));
        }

        if ($porsi->setengahHari()) {
            $selesai = $mulai;
        }

        if ($selesai->lessThan($mulai)) {
            throw new RuntimeException(__('izin.galat_rentang_terbalik'));
        }

        if ($mulai->diffInDays($selesai) + 1 > self::MAKS_HARI_KALENDER) {
            throw new RuntimeException(__('izin.galat_rentang_terlalu_panjang', ['hari' => self::MAKS_HARI_KALENDER]));
        }

        $batas = CarbonImmutable::today()->subDays($jenis->batasMundurHari());

        if ($mulai->lessThan($batas)) {
            throw new RuntimeException($jenis->batasMundurHari() === 0
                ? __('izin.galat_tidak_boleh_mundur', ['jenis' => $jenis->label()])
                : __('izin.galat_mundur_maks', ['jenis' => $jenis->label(), 'hari' => $jenis->batasMundurHari()]));
        }

        $jumlahHari = $this->hitungHari($karyawan, $mulai, $selesai, $porsi);

        if ($jumlahHari <= 0) {
            throw new RuntimeException(__('izin.galat_tanpa_hari_kerja'));
        }

        $this->pastikanTidakBeririsan($karyawan, $mulai, $selesai);

        if (! $porsi->setengahHari()) {
            $this->pastikanBelumAbsen($karyawan, $mulai, $selesai);
        }

        $path = $lampiran?->storeAs(
            'izin/'.$karyawan->id,
            Str::ulid().'.'.strtolower($lampiran->getClientOriginalExtension() ?: $lampiran->extension()),
            self::DISK,
        );

        try {
            return PengajuanIzin::create([
                'karyawan_id' => $karyawan->id,
                'jenis' => $jenis,
                'porsi' => $porsi,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jumlah_hari' => $jumlahHari,
                'dibayar' => $jenis->dibayar(),
                'alasan' => $alasan,
                'lampiran' => $path ?: null,
                'lampiran_nama' => $lampiran !== null ? Str::limit($lampiran->getClientOriginalName(), 250, '') : null,
                'status' => StatusPengajuan::Menunggu,
                'diajukan_oleh' => $pengaju->id,
            ]);
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }

            throw $e;
        }
    }

    /** @throws RuntimeException */
    public function setujui(PengajuanIzin $pengajuan, User $hr, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuan, $hr, $catatan) {
            $pengajuan = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);

            $this->pastikanMenunggu($pengajuan);
            $this->pastikanBolehMemutuskan($hr, $pengajuan);

            // Diperiksa ulang saat keputusan: karyawan bisa saja sudah absen
            // masuk di antara waktu mengajukan dan waktu HR memutuskan.
            if (! $pengajuan->porsi->setengahHari()) {
                $this->pastikanBelumAbsen($pengajuan->karyawan, $pengajuan->tanggal_mulai->toImmutable(), $pengajuan->tanggal_selesai->toImmutable());
            }

            $pengajuan->update([
                'status' => StatusPengajuan::Disetujui,
                'diputuskan_oleh' => $hr->id,
                'diputuskan_at' => now(),
                'catatan_keputusan' => $catatan,
            ]);
        });
    }

    /** @throws RuntimeException */
    public function tolak(PengajuanIzin $pengajuan, User $hr, string $catatan): void
    {
        DB::transaction(function () use ($pengajuan, $hr, $catatan) {
            $pengajuan = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);

            $this->pastikanMenunggu($pengajuan);
            $this->pastikanBolehMemutuskan($hr, $pengajuan);

            $pengajuan->update([
                'status' => StatusPengajuan::Ditolak,
                'diputuskan_oleh' => $hr->id,
                'diputuskan_at' => now(),
                'catatan_keputusan' => $catatan,
            ]);
        });
    }

    /** Karyawan menarik pengajuannya sendiri — hanya selama belum diputuskan. */
    public function batalkan(PengajuanIzin $pengajuan, User $pengguna): void
    {
        DB::transaction(function () use ($pengajuan, $pengguna) {
            $pengajuan = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);

            $this->pastikanMenunggu($pengajuan);

            $pengajuan->update([
                'status' => StatusPengajuan::Dibatalkan,
                'diputuskan_oleh' => $pengguna->id,
                'diputuskan_at' => now(),
            ]);
        });
    }

    /** Termasuk larangan memutuskan pengajuan sendiri — lihat ApproverIzin. */
    private function pastikanBolehMemutuskan(User $pengguna, PengajuanIzin $pengajuan): void
    {
        if (! $this->approver->bolehMemutuskan($pengguna, $pengajuan)) {
            throw new RuntimeException(__('izin.galat_bukan_approver'));
        }
    }

    private function pastikanMenunggu(PengajuanIzin $pengajuan): void
    {
        if ($pengajuan->status !== StatusPengajuan::Menunggu) {
            throw new RuntimeException(__('izin.galat_sudah_diputuskan', ['status' => $pengajuan->status->label()]));
        }
    }

    private function pastikanTidakBeririsan(Karyawan $karyawan, CarbonImmutable $mulai, CarbonImmutable $selesai): void
    {
        $bentrok = PengajuanIzin::query()
            ->where('karyawan_id', $karyawan->id)
            ->aktif()
            ->beririsan($mulai, $selesai)
            ->first();

        if ($bentrok !== null) {
            throw new RuntimeException(__('izin.galat_beririsan', [
                'tanggal' => $bentrok->tanggalTeks(),
                'status' => $bentrok->status->label(),
            ]));
        }
    }

    /**
     * Izin/sakit sehari penuh tidak masuk akal untuk hari yang sudah diabsen
     * masuk — dua catatan yang saling bertentangan di Monitoring.
     */
    private function pastikanBelumAbsen(Karyawan $karyawan, CarbonImmutable $mulai, CarbonImmutable $selesai): void
    {
        $sudah = Absensi::query()
            ->where('karyawan_id', $karyawan->id)
            ->where('jenis', JenisAbsensi::Masuk->value)
            ->whereDate('tanggal', '>=', $mulai->toDateString())
            ->whereDate('tanggal', '<=', $selesai->toDateString())
            ->orderBy('tanggal')
            ->first();

        if ($sudah !== null) {
            throw new RuntimeException(__('izin.galat_sudah_absen', ['tanggal' => $sudah->tanggal->isoFormat('D MMM Y')]));
        }
    }
}
