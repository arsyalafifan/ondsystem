<?php

namespace App\Livewire\Hr;

use App\Enums\JenisAbsensi;
use App\Enums\StatusAbsensi;
use App\Models\Absensi;
use App\Models\Depot;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\PengajuanLembur;
use App\Models\Posisi;
use App\Services\Absensi\AturanAbsensi;
use App\Services\Lembur\PengajuanLemburService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Attendance Monitoring: SELURUH karyawan aktif muncul tiap hari, statusnya
 * berubah begitu yang bersangkutan absen. Baris yang belum absen tetap
 * tampil — justru itu yang perlu dilihat HR.
 *
 * Status harian dihitung dari baris kejadian hari itu (tidak ada tabel
 * rekap harian yang harus dijaga sinkron).
 */
class MonitoringAbsensi extends Component
{
    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'depot')]
    public string $filterDepot = '';

    #[Url(as: 'posisi')]
    public string $filterPosisi = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'cari')]
    public string $cari = '';

    public ?int $fotoDilihat = null;

    public function mount(): void
    {
        $this->tanggal = $this->tanggal ?: today()->toDateString();
    }

    #[Computed]
    public function depots()
    {
        return Depot::aktif()->berurutan()->get(['id', 'nama']);
    }

    #[Computed]
    public function posisis()
    {
        return Posisi::query()->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Satu baris per karyawan: jam masuk/istirahat/pulang, status harian,
     * durasi kerja, dan tempat absennya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function baris(): Collection
    {
        $tanggal = CarbonImmutable::parse($this->tanggal);

        $karyawans = Karyawan::query()
            ->where('aktif', true)
            ->with(['posisi', 'shift', 'depot:id,nama', 'department:id,nama'])
            ->when($this->filterDepot !== '', fn ($q) => $q->where('depot_id', $this->filterDepot))
            ->when($this->filterPosisi !== '', fn ($q) => $q->where('posisi_id', $this->filterPosisi))
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama_lengkap', 'like', "%{$this->cari}%")
                ->orWhere('kode_karyawan', 'like', "%{$this->cari}%")))
            ->orderBy('nama_lengkap')
            ->get();

        $absensi = Absensi::query()
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('karyawan_id', $karyawans->pluck('id'))
            ->with(['depot:id,nama', 'toko:id,nama'])
            ->get()
            ->groupBy('karyawan_id');

        // Hanya yang DISETUJUI HR — pengajuan yang masih menunggu belum
        // mengubah apa pun di sini.
        $izin = PengajuanIzin::query()
            ->disetujui()
            ->mencakup($tanggal)
            ->whereIn('karyawan_id', $karyawans->pluck('id'))
            ->get()
            ->keyBy('karyawan_id');

        $lembur = PengajuanLembur::query()
            ->disetujui()
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('karyawan_id', $karyawans->pluck('id'))
            ->get()
            ->groupBy('karyawan_id');
        $layananLembur = app(PengajuanLemburService::class);

        return $karyawans
            ->map(function (Karyawan $karyawan) use ($absensi, $izin, $lembur, $layananLembur, $tanggal): array {
                $milik = ($absensi->get($karyawan->id) ?? collect())->keyBy(fn (Absensi $a) => $a->jenis->value);
                $masuk = $milik->get(JenisAbsensi::Masuk->value);
                $pulang = $milik->get(JenisAbsensi::Pulang->value);
                $izinnya = $izin->get($karyawan->id);

                return [
                    'karyawan' => $karyawan,
                    'masuk' => $masuk,
                    'istirahat' => $milik->get(JenisAbsensi::Istirahat->value),
                    'pulang' => $pulang,
                    'izin' => $izinnya,
                    'lembur' => ($lembur->get($karyawan->id) ?? collect())
                        ->map(fn (PengajuanLembur $l) => ['lembur' => $l, 'hitung' => $layananLembur->hitung($l)])
                        ->values(),
                    'status' => $izinnya?->statusHarian() ?? $this->statusHarian($karyawan, $masuk, $pulang, $tanggal),
                    'durasi_menit' => $masuk !== null && $pulang !== null
                        ? (int) $masuk->waktu->diffInMinutes($pulang->waktu)
                        : null,
                ];
            })
            ->when($this->filterStatus !== '', fn (Collection $baris) => $baris->where('status', $this->filterStatus))
            ->values();
    }

    /**
     * Status harian turunan. "Alfa" hanya diberikan bila hari itu memang
     * hari kerja posisinya DAN jam pulangnya sudah lewat — sebelum itu
     * statusnya "belum absen", bukan tuduhan mangkir.
     *
     * Hari yang dicakup izin/sakit yang disetujui tidak sampai ke sini —
     * statusnya diambil langsung dari pengajuannya (lihat baris()).
     */
    private function statusHarian(Karyawan $karyawan, ?Absensi $masuk, ?Absensi $pulang, CarbonImmutable $tanggal): string
    {
        if ($masuk !== null) {
            if ($pulang !== null) {
                return 'selesai';
            }

            return $masuk->status === StatusAbsensi::Terlambat ? 'terlambat' : 'hadir';
        }

        $posisi = $karyawan->posisi;

        if ($posisi === null || ! in_array($tanggal->dayOfWeekIso, $posisi->hariKerja(), true)) {
            return 'libur';
        }

        $batas = app(AturanAbsensi::class)->jamAcuan($karyawan, JenisAbsensi::Pulang, $tanggal);

        return $batas !== null && CarbonImmutable::now()->greaterThan($batas) ? 'alfa' : 'belum_absen';
    }

    /** @return array<string, int> jumlah karyawan per status, untuk kartu ringkasan */
    #[Computed]
    public function ringkasan(): array
    {
        $hitung = $this->baris->countBy('status');

        $hitung['izin'] = ($hitung['izin'] ?? 0) + ($hitung['izin_paruh_pertama'] ?? 0) + ($hitung['izin_paruh_kedua'] ?? 0);

        return collect(['hadir', 'terlambat', 'selesai', 'izin', 'sakit', 'belum_absen', 'alfa', 'libur'])
            ->mapWithKeys(fn (string $status) => [$status => (int) ($hitung[$status] ?? 0)])
            ->all();
    }

    #[Computed]
    public function foto(): ?Absensi
    {
        return $this->fotoDilihat === null
            ? null
            : Absensi::with(['karyawan:id,nama_lengkap', 'depot:id,nama', 'toko:id,nama'])->find($this->fotoDilihat);
    }

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['tanggal', 'filterDepot', 'filterPosisi', 'filterStatus', 'cari'], true)) {
            unset($this->baris, $this->ringkasan);
        }
    }

    public function render()
    {
        return view('livewire.hr.monitoring-absensi')->title(__('hr.judul_monitoring'));
    }
}
