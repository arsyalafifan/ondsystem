<?php

namespace App\Livewire\TarikFreezer;

use App\Enums\StatusTarikFreezer;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\TarikFreezer;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\TarikFreezer\TarikFreezerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Pengajuan penarikan freezer oleh sales di lapangan — kebalikan dari
 * App\Livewire\Noo\DaftarNoo. Tokonya sudah ada (bukan calon baru), jadi
 * formulirnya jauh lebih ringkas: tinggal pilih toko mana dan alasannya
 * apa. Tidak ada foto di sini sama sekali — bukti fotonya baru diambil
 * driver saat freezernya benar-benar diambil (lihat
 * App\Livewire\Driver\DaftarKunjungan::bukaKonfirmasiTarik()).
 */
class DaftarTarikFreezer extends Component
{
    use MembutuhkanDepotTerkunci, WithPagination;

    #[Url(as: 'q')]
    public string $cari = '';

    #[Url]
    public string $filterStatus = '';

    #[Url(as: 'wilayah')]
    public string $filterWilayah = '';

    #[Url(as: 'tgl')]
    public string $filterTanggal = '';

    #[Url(as: 'pengaju')]
    public string $filterPengaju = '';

    public ?int $dilihat = null;

    public bool $formTerbuka = false;

    public ?int $tokoId = null;

    public string $alasan = '';

    public function mount(): void
    {
        $this->pastikanDepotTerkunci();
    }

    /**
     * Sales hanya melihat pengajuannya sendiri; admin melihat semuanya —
     * persis pola App\Livewire\Noo\DaftarNoo::dasarKueri().
     */
    private function dasarKueri(): Builder
    {
        return TarikFreezer::query()
            ->when(auth()->user()->isSales(), fn (Builder $q) => $q->where('diajukan_oleh', auth()->id()))
            ->when($this->filterWilayah !== '', fn (Builder $q) => $q->whereHas(
                'toko', fn (Builder $t) => $t->where('wilayah_id', $this->filterWilayah)
            ))
            ->when($this->filterTanggal !== '', fn (Builder $q) => $q->whereDate('diajukan_at', $this->filterTanggal))
            ->when($this->filterPengaju !== '', fn (Builder $q) => $q->where('diajukan_oleh', $this->filterPengaju))
            ->when(trim($this->cari) !== '', function (Builder $q): void {
                $kata = trim($this->cari);

                $q->where(fn (Builder $s) => $s
                    ->where('kode', 'like', "%{$kata}%")
                    ->orWhereHas('toko', fn (Builder $t) => $t
                        ->where('nama', 'like', "%{$kata}%")
                        ->orWhere('kode', 'like', "%{$kata}%")));
            });
    }

    /** @return LengthAwarePaginator<int, TarikFreezer> */
    #[Computed]
    public function daftar(): LengthAwarePaginator
    {
        return $this->dasarKueri()
            ->with(['toko:id,kode,nama,wilayah_id,asset_id', 'toko.wilayah:id,nama', 'pengaju:id,name'])
            ->when($this->filterStatus !== '', fn (Builder $q) => $q->where('status', $this->filterStatus))
            ->latest('diajukan_at')
            ->paginate(15);
    }

    /** Hitungan per status, mengikuti filter lain yang aktif kecuali status itu sendiri. */
    #[Computed]
    public function ringkasan(): array
    {
        $hitung = $this->dasarKueri()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return collect(StatusTarikFreezer::cases())
            ->mapWithKeys(fn (StatusTarikFreezer $s) => [$s->value => (int) ($hitung[$s->value] ?? 0)])
            ->all();
    }

    #[Computed]
    public function pengajus(): Collection
    {
        return User::query()
            ->whereIn('id', $this->dasarKueri()->select('diajukan_oleh')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function wilayahs(): Collection
    {
        return Wilayah::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    public function bersihkanFilter(): void
    {
        $this->reset(['filterStatus', 'filterWilayah', 'filterTanggal', 'filterPengaju', 'cari']);
        $this->resetPage();
    }

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterWilayah(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPengaju(): void
    {
        $this->resetPage();
    }

    /** @return array<int, StatusTarikFreezer> */
    #[Computed]
    public function statusCases(): array
    {
        return StatusTarikFreezer::cases();
    }

    #[Computed]
    public function detail(): ?TarikFreezer
    {
        return $this->dilihat === null
            ? null
            : $this->dasarKueri()->with([
                'toko:id,kode,nama,alamat,wilayah_id,asset_id,freezer_tipe', 'toko.wilayah:id,nama',
                'pengaju:id,name', 'penyetuju:id,name', 'penolak:id,name', 'penyelesai:id,name', 'fotos',
            ])->find($this->dilihat);
    }

    // ------------------------------------------------------------------
    // Formulir pengajuan
    // ------------------------------------------------------------------

    /** @return array<int, array{value: string, label: string}> */
    #[Computed]
    public function opsiToko(): array
    {
        return app(TarikFreezerService::class)->tokoBolehDiajukan(auth()->user())
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode'])
            ->map(fn (Toko $t): array => ['value' => (string) $t->id, 'label' => $t->nama.' · '.$t->kode])
            ->all();
    }

    public function buatBaru(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $this->reset(['tokoId', 'alasan']);
        $this->resetValidation();
        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->reset(['tokoId', 'alasan']);
        $this->resetValidation();
        $this->formTerbuka = false;
    }

    public function simpan(TarikFreezerService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $data = $this->validate([
            'tokoId' => 'required|integer',
            'alasan' => 'required|string|max:1000',
        ], [], [
            'tokoId' => __('tarik_freezer.atr_toko'),
            'alasan' => __('tarik_freezer.atr_alasan'),
        ]);

        try {
            $tarik = $service->ajukan($data['tokoId'], $data['alasan'], auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->tutupForm();
        unset($this->daftar, $this->ringkasan, $this->opsiToko);

        $this->dispatch('notifikasi', pesan: __('tarik_freezer.notif_diajukan', ['kode' => $tarik->kode]));
    }

    public function render()
    {
        return view('livewire.tarik-freezer.daftar-tarik-freezer')->title(__('tarik_freezer.judul'));
    }
}
