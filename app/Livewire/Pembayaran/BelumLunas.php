<?php

namespace App\Livewire\Pembayaran;

use App\Enums\StatusBayar;
use App\Models\Pesanan;
use App\Services\PelunasanService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class BelumLunas extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $cari = '';

    public ?int $konfirmasiPesananId = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function pesanans()
    {
        return Pesanan::query()
            ->where('status_bayar', StatusBayar::BelumLunas)
            ->with(['toko:id,nama,kode', 'items', 'stop.kendaraan:id,nomor,nama'])
            ->when($this->cari !== '', fn ($q) => $q->whereHas('toko', fn ($t) => $t
                ->where('nama', 'like', "%{$this->cari}%")
                ->orWhere('kode', 'like', "%{$this->cari}%")))
            ->orderBy('tanggal')
            ->paginate(20);
    }

    public function konfirmasi(int $id): void
    {
        $this->konfirmasiPesananId = $id;
    }

    public function batalkanKonfirmasi(): void
    {
        $this->konfirmasiPesananId = null;
    }

    public function tandaiLunas(PelunasanService $svc): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        try {
            $pesanan = Pesanan::findOrFail($this->konfirmasiPesananId);
            $svc->tandaiLunas($pesanan, auth()->user());
            $this->dispatch('notifikasi', pesan: __('pembayaran.notif_lunas', ['toko' => $pesanan->toko->nama]));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }

        $this->konfirmasiPesananId = null;
        unset($this->pesanans);
    }

    public function render()
    {
        return view('livewire.pembayaran.belum-lunas')->title(__('pembayaran.judul_belum_lunas'));
    }
}
