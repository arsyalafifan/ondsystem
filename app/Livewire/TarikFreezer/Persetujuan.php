<?php

namespace App\Livewire\TarikFreezer;

use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\TarikFreezer;
use App\Services\TarikFreezer\TarikFreezerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Antrean persetujuan penarikan freezer — kebalikan dari App\Livewire\Noo\Persetujuan,
 * dan jauh lebih sederhana: tidak ada data toko untuk dikoreksi (tokonya
 * sudah lama berdiri, bukan calon baru) dan tidak ada peringatan jarak.
 * Admin tinggal melihat alasan sales lalu menyetujui atau menolak.
 */
class Persetujuan extends Component
{
    use MembutuhkanDepotTerkunci, WithPagination;

    public ?int $dipilih = null;

    public string $alasanTolak = '';

    public bool $formTolakTerbuka = false;

    public function mount(): void
    {
        $this->pastikanDepotTerkunci();
    }

    /** @return LengthAwarePaginator<int, TarikFreezer> */
    #[Computed]
    public function antrean(): LengthAwarePaginator
    {
        return TarikFreezer::query()
            ->menungguPersetujuan()
            ->with(['toko:id,kode,nama,alamat,wilayah_id,asset_id,freezer_tipe', 'toko.wilayah:id,nama', 'pengaju:id,name'])
            ->oldest('diajukan_at')
            ->paginate(15);
    }

    #[Computed]
    public function tarikFreezer(): ?TarikFreezer
    {
        return $this->dipilih === null
            ? null
            : TarikFreezer::query()
                ->menungguPersetujuan()
                ->with(['toko:id,kode,nama,alamat,wilayah_id,asset_id,freezer_tipe,telepon,nama_pemilik', 'toko.wilayah:id,nama', 'pengaju:id,name'])
                ->find($this->dipilih);
    }

    public function pilih(int $id): void
    {
        $tarik = TarikFreezer::menungguPersetujuan()->find($id);

        if ($tarik === null) {
            return;
        }

        $this->dipilih = $tarik->id;
        $this->reset(['alasanTolak', 'formTolakTerbuka']);
        $this->resetValidation();
    }

    public function tutup(): void
    {
        $this->dipilih = null;
        $this->reset(['alasanTolak', 'formTolakTerbuka']);
        $this->resetValidation();
    }

    public function setujui(TarikFreezerService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $tarik = $this->tarikFreezer;

        if ($tarik === null) {
            return;
        }

        try {
            $service->setujui($tarik, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $kode = $tarik->kode;
        $this->tutup();
        unset($this->antrean);

        $this->dispatch('notifikasi', pesan: __('tarik_freezer.notif_disetujui', ['kode' => $kode]));
    }

    public function tolak(TarikFreezerService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $tarik = $this->tarikFreezer;

        if ($tarik === null) {
            return;
        }

        $this->validate(
            ['alasanTolak' => 'required|string|max:500'],
            ['alasanTolak.required' => __('tarik_freezer.galat_alasan_tolak_wajib')],
        );

        try {
            $service->tolak($tarik, auth()->user(), $this->alasanTolak);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $kode = $tarik->kode;
        $this->tutup();
        unset($this->antrean);

        $this->dispatch('notifikasi', pesan: __('tarik_freezer.notif_ditolak', ['kode' => $kode]), jenis: 'info');
    }

    public function render()
    {
        return view('livewire.tarik-freezer.persetujuan')->title(__('tarik_freezer.judul_persetujuan'));
    }
}
