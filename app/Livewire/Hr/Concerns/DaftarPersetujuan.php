<?php

namespace App\Livewire\Hr\Concerns;

use App\Enums\StatusPengajuan;
use App\Services\Izin\ApproverIzin;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Perilaku bersama layar Persetujuan Izin & Persetujuan Lembur: filter
 * status/cari/tanggal (bawaan hari ini, dengan pemberitahuan pengajuan
 * menunggu di luar tanggal), siapa boleh memutuskan (ApproverIzin), dan
 * modal setujui/tolak. Kedua layar harus berperilaku persis sama, jadi
 * sengaja satu sumber.
 *
 * Model pengajuannya wajib punya relasi `karyawan`, scope `beririsan()`,
 * dan method `beririsanDengan()`.
 */
trait DaftarPersetujuan
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = 'menunggu';

    #[Url(as: 'cari')]
    public string $cari = '';

    /**
     * Rentang tanggal IZIN/LEMBUR (bukan tanggal diajukan), bawaannya hari
     * ini supaya daftar tidak menumpuk. Kosong = semua tanggal.
     */
    #[Url(as: 'dari')]
    public string $dariTanggal = '';

    #[Url(as: 'sampai')]
    public string $sampaiTanggal = '';

    /** Pengajuan yang sedang diputuskan lewat modal, dan keputusannya. */
    public ?int $pengajuanId = null;

    public string $keputusan = '';

    public string $catatan = '';

    /** @return class-string<Model> */
    abstract protected function modelPengajuan(): string;

    /** Service yang punya setujui()/tolak() untuk model ini. */
    abstract protected function layananPersetujuan(): object;

    /** @return list<string> relasi tambahan untuk tabel */
    protected function relasiDaftar(): array
    {
        return [];
    }

    public function mount(): void
    {
        if (! request()->has('dari') && ! request()->has('sampai')) {
            $this->dariTanggal = $this->sampaiTanggal = today()->toDateString();
        }
    }

    public function updated(string $kolom): void
    {
        if ($kolom === 'dariTanggal' && $this->dariTanggal !== '' && ($this->sampaiTanggal === '' || $this->sampaiTanggal < $this->dariTanggal)) {
            $this->sampaiTanggal = $this->dariTanggal;
        }

        if ($kolom === 'sampaiTanggal' && $this->sampaiTanggal !== '' && ($this->dariTanggal === '' || $this->dariTanggal > $this->sampaiTanggal)) {
            $this->dariTanggal = $this->sampaiTanggal;
        }

        if (in_array($kolom, ['filterStatus', 'cari', 'dariTanggal', 'sampaiTanggal'], true)) {
            $this->resetPage();
            $this->segarkanDaftar();
        }
    }

    #[Computed]
    public function pengajuans()
    {
        $query = $this->modelPengajuan()::query()->with([
            'karyawan:id,nama_lengkap,kode_karyawan,posisi_id,user_id,atasan_id',
            'karyawan.posisi:id,nama',
            'diputuskanOleh:id,name',
            ...$this->relasiDaftar(),
        ]);

        // Atasan (bukan HR/approver umum) hanya melihat milik bawahannya.
        app(ApproverIzin::class)->batasiDaftar($query, auth()->user());

        return $query
            ->when($this->rentang() !== null, fn (Builder $q) => $q->beririsan(...$this->rentang()))
            ->when($this->filterStatus !== '', fn (Builder $q) => $q->where('status', $this->filterStatus))
            ->when($this->cari !== '', fn (Builder $q) => $q->whereHas('karyawan', fn (Builder $k) => $k
                ->where('nama_lengkap', 'like', "%{$this->cari}%")
                ->orWhere('kode_karyawan', 'like', "%{$this->cari}%")))
            // Menunggu: yang paling lama menunggu di atas (antre). Lainnya:
            // yang terbaru diputuskan/diajukan di atas.
            ->when($this->filterStatus === StatusPengajuan::Menunggu->value,
                fn (Builder $q) => $q->oldest(),
                fn (Builder $q) => $q->latest())
            ->paginate(20);
    }

    /** Menunggu DAN boleh diputuskan pengguna ini (semua tanggal) — yang perlu ditindak. */
    #[Computed]
    public function jumlahMenunggu(): int
    {
        return $this->menungguSaya()->count();
    }

    /** Menunggu keputusan pengguna ini tapi tanggalnya di luar filter. */
    #[Computed]
    public function menungguDiLuarTanggal(): int
    {
        $rentang = $this->rentang();

        return $rentang === null
            ? 0
            : $this->menungguSaya()->reject(fn (Model $p) => $p->beririsanDengan(...$rentang))->count();
    }

    /**
     * Untuk tiap pengajuan yang menunggu di halaman ini: boleh diputuskan
     * pengguna ini, atau menunggu siapa.
     *
     * @return array<int, array{boleh: bool, menunggu: string}>
     */
    #[Computed]
    public function hakKeputusan(): array
    {
        $approver = app(ApproverIzin::class);

        return collect($this->pengajuans->items())
            ->filter(fn (Model $p) => $p->status === StatusPengajuan::Menunggu)
            ->mapWithKeys(fn (Model $p) => [$p->id => [
                'boleh' => $approver->bolehMemutuskan(auth()->user(), $p),
                'menunggu' => $approver->untuk($p)['pengguna']->pluck('name')->take(3)->join(', '),
            ]])
            ->all();
    }

    #[Computed]
    public function dipilih(): ?Model
    {
        return $this->pengajuanId === null
            ? null
            : $this->modelPengajuan()::with(['karyawan.posisi'])->find($this->pengajuanId);
    }

    public function hariIni(): void
    {
        $this->dariTanggal = $this->sampaiTanggal = today()->toDateString();
        $this->updated('dariTanggal');
    }

    public function semuaTanggal(): void
    {
        $this->dariTanggal = $this->sampaiTanggal = '';
        $this->updated('dariTanggal');
    }

    public function buka(int $id, string $keputusan): void
    {
        $pengajuan = $this->modelPengajuan()::findOrFail($id);

        if (! app(ApproverIzin::class)->bolehMemutuskan(auth()->user(), $pengajuan)) {
            $this->dispatch('notifikasi', pesan: __('izin.galat_bukan_approver'), jenis: 'error');

            return;
        }

        $this->pengajuanId = $id;
        $this->keputusan = in_array($keputusan, ['setujui', 'tolak'], true) ? $keputusan : 'setujui';
        $this->catatan = '';
        $this->resetValidation();
    }

    public function tutup(): void
    {
        $this->reset(['pengajuanId', 'keputusan', 'catatan']);
    }

    public function putuskan(): void
    {
        $pengajuan = $this->modelPengajuan()::findOrFail($this->pengajuanId);

        // Menolak wajib beralasan, supaya karyawan tahu apa yang perlu
        // diperbaiki; menyetujui boleh tanpa catatan.
        $this->validate([
            'catatan' => $this->keputusan === 'tolak' ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ], [], ['catatan' => __('izin.atr_catatan_keputusan')]);

        $layanan = $this->layananPersetujuan();

        try {
            $this->keputusan === 'tolak'
                ? $layanan->tolak($pengajuan, auth()->user(), trim($this->catatan))
                : $layanan->setujui($pengajuan, auth()->user(), trim($this->catatan) ?: null);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $pesan = $this->keputusan === 'tolak' ? __('izin.notif_ditolak') : __('izin.notif_disetujui');

        $this->tutup();
        $this->segarkanDaftar();

        $this->dispatch('notifikasi', pesan: $pesan);
    }

    private function segarkanDaftar(): void
    {
        unset($this->pengajuans, $this->jumlahMenunggu, $this->hakKeputusan, $this->menungguDiLuarTanggal);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    private function rentang(): ?array
    {
        if ($this->dariTanggal === '' || $this->sampaiTanggal === '') {
            return null;
        }

        try {
            return [CarbonImmutable::parse($this->dariTanggal)->startOfDay(), CarbonImmutable::parse($this->sampaiTanggal)->startOfDay()];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return Collection<int, Model> */
    private function menungguSaya(): Collection
    {
        $approver = app(ApproverIzin::class);
        $query = $this->modelPengajuan()::query()->with('karyawan')->where('status', StatusPengajuan::Menunggu);
        $approver->batasiDaftar($query, auth()->user());

        return $query->get()->filter(fn (Model $p) => $approver->bolehMemutuskan(auth()->user(), $p))->values();
    }
}
