<?php

namespace App\Livewire\Hr;

use App\Enums\JenisAbsensi;
use App\Enums\JenisIzin;
use App\Enums\PorsiIzin;
use App\Enums\StatusPengajuan;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Services\Absensi\AturanAbsensi;
use App\Services\Izin\ApproverIzin;
use App\Services\Izin\PengajuanIzinService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Menu Ajukan Izin: karyawan mengajukan izin (penuh / ½ hari pertama /
 * ½ hari kedua) atau sakit, lalu HR memutuskan di menu Persetujuan Izin.
 * Aturannya di App\Services\Izin\PengajuanIzinService.
 */
class AjukanIzin extends Component
{
    use WithFileUploads;

    public string $jenis = 'izin';

    public string $porsi = 'penuh';

    public string $tanggalMulai = '';

    public string $tanggalSelesai = '';

    public string $alasan = '';

    public $lampiran;

    public ?int $konfirmasiBatal = null;

    public function mount(): void
    {
        $this->tanggalMulai = today()->toDateString();
        $this->tanggalSelesai = today()->toDateString();
    }

    #[Computed]
    public function karyawan(): ?Karyawan
    {
        return Karyawan::query()->with(['posisi', 'shift'])->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function riwayat()
    {
        $karyawan = $this->karyawan;

        return $karyawan === null
            ? collect()
            : PengajuanIzin::query()
                ->where('karyawan_id', $karyawan->id)
                ->with('diputuskanOleh:id,name')
                ->latest()
                ->limit(30)
                ->get();
    }

    /** @return array<int, string> nama penyetuju tiap pengajuan yang masih menunggu */
    #[Computed]
    public function penyetuju(): array
    {
        $approver = app(ApproverIzin::class);

        return $this->riwayat
            ->filter(fn (PengajuanIzin $p) => $p->status === StatusPengajuan::Menunggu)
            ->mapWithKeys(fn (PengajuanIzin $p) => [$p->id => $approver->untuk($p)['pengguna']->pluck('name')->take(3)->join(', ')])
            ->all();
    }

    public function updatedJenis(): void
    {
        // Sakit selalu sehari penuh.
        if (! JenisIzin::from($this->jenis)->bolehSetengahHari()) {
            $this->porsi = PorsiIzin::Penuh->value;
        }
    }

    public function updatedTanggalMulai(): void
    {
        if ($this->tanggalSelesai === '' || $this->tanggalSelesai < $this->tanggalMulai) {
            $this->tanggalSelesai = $this->tanggalMulai;
        }
    }

    /**
     * Pratinjau sebelum mengirim: berapa hari kerja yang terhitung, dan
     * untuk setengah hari, jam masuk/pulang yang berlaku hari itu.
     *
     * @return array{hari: float, jam: ?string}|null
     */
    #[Computed]
    public function pratinjau(): ?array
    {
        $karyawan = $this->karyawan;
        $jenis = JenisIzin::tryFrom($this->jenis);
        $porsi = PorsiIzin::tryFrom($this->porsi);

        if ($karyawan?->posisi === null || $jenis === null || $porsi === null) {
            return null;
        }

        try {
            $mulai = CarbonImmutable::parse($this->tanggalMulai);
            $selesai = $porsi->setengahHari() ? $mulai : CarbonImmutable::parse($this->tanggalSelesai);
        } catch (\Throwable) {
            return null;
        }

        if ($selesai->lessThan($mulai) || $mulai->diffInDays($selesai) > PengajuanIzinService::MAKS_HARI_KALENDER) {
            return null;
        }

        $jam = null;

        if ($porsi->setengahHari()) {
            $aturan = app(AturanAbsensi::class);
            $izinSemu = new PengajuanIzin(['porsi' => $porsi]);

            $jam = $porsi === PorsiIzin::ParuhPertama
                ? __('izin.pratinjau_masuk', ['jam' => $aturan->jamAcuan($karyawan, JenisAbsensi::Masuk, $mulai, $izinSemu)?->format('H:i')])
                : __('izin.pratinjau_pulang', ['jam' => $aturan->jamAcuan($karyawan, JenisAbsensi::Pulang, $mulai, $izinSemu)?->format('H:i')]);
        }

        return [
            'hari' => app(PengajuanIzinService::class)->hitungHari($karyawan, $mulai, $selesai, $porsi),
            'jam' => $jam,
        ];
    }

    public function ajukan(PengajuanIzinService $service): void
    {
        $karyawan = $this->karyawan;

        if ($karyawan === null) {
            $this->dispatch('notifikasi', pesan: __('hr.galat_tanpa_karyawan'), jenis: 'error');

            return;
        }

        $setengah = PorsiIzin::tryFrom($this->porsi)?->setengahHari() ?? false;

        $this->validate([
            'jenis' => ['required', Rule::enum(JenisIzin::class)],
            'porsi' => ['required', Rule::enum(PorsiIzin::class)],
            'tanggalMulai' => 'required|date',
            'tanggalSelesai' => $setengah ? 'nullable' : 'required|date|after_or_equal:tanggalMulai',
            'alasan' => 'required|string|min:5|max:1000',
            'lampiran' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [], [
            'jenis' => __('izin.atr_jenis'),
            'porsi' => __('izin.atr_porsi'),
            'tanggalMulai' => __('izin.atr_tanggal_mulai'),
            'tanggalSelesai' => __('izin.atr_tanggal_selesai'),
            'alasan' => __('izin.atr_alasan'),
            'lampiran' => __('izin.atr_lampiran'),
        ]);

        try {
            $service->ajukan(
                karyawan: $karyawan,
                pengaju: auth()->user(),
                jenis: JenisIzin::from($this->jenis),
                porsi: PorsiIzin::from($this->porsi),
                mulai: CarbonImmutable::parse($this->tanggalMulai),
                selesai: CarbonImmutable::parse($setengah ? $this->tanggalMulai : $this->tanggalSelesai),
                alasan: trim($this->alasan),
                lampiran: $this->lampiran,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->reset(['alasan', 'lampiran']);
        $this->resetValidation();
        unset($this->riwayat, $this->penyetuju);

        $this->dispatch('notifikasi', pesan: __('izin.notif_diajukan'));
    }

    public function batalkan(int $id, PengajuanIzinService $service): void
    {
        $pengajuan = PengajuanIzin::query()
            ->where('karyawan_id', $this->karyawan?->id)
            ->findOrFail($id);

        try {
            $service->batalkan($pengajuan, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }

        $this->konfirmasiBatal = null;
        unset($this->riwayat, $this->penyetuju);
    }

    public function render()
    {
        return view('livewire.hr.ajukan-izin')->title(__('izin.judul_ajukan'));
    }
}
