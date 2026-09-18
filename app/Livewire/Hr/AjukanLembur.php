<?php

namespace App\Livewire\Hr;

use App\Enums\StatusPengajuan;
use App\Models\Karyawan;
use App\Models\PengajuanLembur;
use App\Services\Izin\ApproverIzin;
use App\Services\Lembur\PengajuanLemburService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Menu Ajukan Lembur: karyawan mengajukan lembur SEBELUM jam mulai; setelah
 * disetujui, jam yang diakui = min(diajukan, batas, yang dijalani menurut
 * absen). Aturannya di App\Services\Lembur\PengajuanLemburService.
 */
class AjukanLembur extends Component
{
    public string $tanggal = '';

    public string $jamMulai = '';

    public string $jamSelesai = '';

    public string $tugas = '';

    public ?int $konfirmasiBatal = null;

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();

        // Usulan awal: mulai tepat di jam pulang, selama satu batas lembur.
        $karyawan = $this->karyawan;
        $pulang = $karyawan?->shift?->jam_pulang ?? $karyawan?->posisi?->jam_pulang;

        if ($pulang !== null) {
            $mulai = CarbonImmutable::parse($this->tanggal.' '.$pulang);
            $this->jamMulai = $mulai->format('H:i');
            $this->jamSelesai = $mulai->addMinutes($this->batasMenit)->format('H:i');
        }
    }

    #[Computed]
    public function karyawan(): ?Karyawan
    {
        return Karyawan::query()->with(['posisi', 'shift'])->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function batasMenit(): int
    {
        return app(ApproverIzin::class)->pengaturan()->maks_lembur_menit;
    }

    #[Computed]
    public function riwayat()
    {
        $karyawan = $this->karyawan;

        return $karyawan === null
            ? collect()
            : PengajuanLembur::query()
                ->where('karyawan_id', $karyawan->id)
                ->with('diputuskanOleh:id,name')
                ->latest('tanggal')->latest('id')
                ->limit(30)
                ->get();
    }

    /** @return array<int, array<string, mixed>> perhitungan jam per pengajuan di riwayat */
    #[Computed]
    public function hitungan(): array
    {
        $service = app(PengajuanLemburService::class);

        return $this->riwayat->mapWithKeys(fn (PengajuanLembur $l) => [$l->id => $service->hitung($l)])->all();
    }

    /** @return array<int, string> penyetuju tiap pengajuan yang masih menunggu */
    #[Computed]
    public function penyetuju(): array
    {
        $approver = app(ApproverIzin::class);

        return $this->riwayat
            ->filter(fn (PengajuanLembur $l) => $l->status === StatusPengajuan::Menunggu)
            ->mapWithKeys(fn (PengajuanLembur $l) => [$l->id => $approver->untuk($l)['pengguna']->pluck('name')->take(3)->join(', ')])
            ->all();
    }

    /**
     * Durasi yang diisi dan yang nanti diakui paling banyak — ditampilkan
     * sebelum mengirim, supaya karyawan tahu sejak awal bahwa kelebihan
     * dari batas tidak dihitung.
     *
     * @return array{menit: int, diakui: int, lintas: bool}|null
     */
    #[Computed]
    public function pratinjau(): ?array
    {
        if ($this->tanggal === '' || $this->jamMulai === '' || $this->jamSelesai === '') {
            return null;
        }

        try {
            $mulai = CarbonImmutable::parse($this->tanggal.' '.$this->jamMulai);
            $selesai = CarbonImmutable::parse($this->tanggal.' '.$this->jamSelesai);
        } catch (\Throwable) {
            return null;
        }

        $lintas = $selesai->lessThanOrEqualTo($mulai);
        $menit = (int) $mulai->diffInMinutes($lintas ? $selesai->addDay() : $selesai);

        return ['menit' => $menit, 'diakui' => min($menit, $this->batasMenit), 'lintas' => $lintas];
    }

    public function ajukan(PengajuanLemburService $service): void
    {
        $karyawan = $this->karyawan;

        if ($karyawan === null) {
            $this->dispatch('notifikasi', pesan: __('hr.galat_tanpa_karyawan'), jenis: 'error');

            return;
        }

        $this->validate([
            'tanggal' => 'required|date',
            'jamMulai' => 'required|date_format:H:i',
            'jamSelesai' => 'required|date_format:H:i|different:jamMulai',
            'tugas' => 'required|string|min:5|max:1000',
        ], [], [
            'tanggal' => __('lembur.atr_tanggal'),
            'jamMulai' => __('lembur.atr_jam_mulai'),
            'jamSelesai' => __('lembur.atr_jam_selesai'),
            'tugas' => __('lembur.atr_tugas'),
        ]);

        try {
            $service->ajukan($karyawan, auth()->user(), CarbonImmutable::parse($this->tanggal), $this->jamMulai, $this->jamSelesai, trim($this->tugas));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->reset('tugas');
        $this->resetValidation();
        unset($this->riwayat, $this->hitungan, $this->penyetuju);

        $this->dispatch('notifikasi', pesan: __('lembur.notif_diajukan'));
    }

    public function batalkan(int $id, PengajuanLemburService $service): void
    {
        $lembur = PengajuanLembur::query()->where('karyawan_id', $this->karyawan?->id)->findOrFail($id);

        try {
            $service->batalkan($lembur, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }

        $this->konfirmasiBatal = null;
        unset($this->riwayat, $this->hitungan, $this->penyetuju);
    }

    public function render()
    {
        return view('livewire.hr.ajukan-lembur')->title(__('lembur.judul_ajukan'));
    }
}
