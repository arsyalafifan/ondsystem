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

    /** Diisi manual oleh admin — tidak pernah ditebak atau dibagi otomatis. */
    public string $nominalCash = '';

    public string $nominalTransfer = '';

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

    /** Pesanan yang sedang dikonfirmasi lunas, untuk ditampilkan di modal. */
    #[Computed]
    public function pesananKonfirmasi(): ?Pesanan
    {
        return $this->konfirmasiPesananId === null
            ? null
            : Pesanan::with('toko:id,nama')->find($this->konfirmasiPesananId);
    }

    /**
     * Selisih antara tagihan dan jumlah cash+transfer yang diisi. Nol berarti
     * pas, dipakai untuk mengunci tombol Proses sebelum disimpan ke server.
     */
    #[Computed]
    public function selisihNominal(): float
    {
        $tagihan = (float) ($this->pesananKonfirmasi?->tagihan ?? 0);
        $terisi = (float) ($this->nominalCash ?: 0) + (float) ($this->nominalTransfer ?: 0);

        return round($tagihan - $terisi, 2);
    }

    public function konfirmasi(int $id): void
    {
        $this->konfirmasiPesananId = $id;
        $this->nominalCash = '';
        $this->nominalTransfer = '';
    }

    public function batalkanKonfirmasi(): void
    {
        $this->konfirmasiPesananId = null;
        $this->nominalCash = '';
        $this->nominalTransfer = '';
    }

    public function tandaiLunas(PelunasanService $svc): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        try {
            $pesanan = Pesanan::findOrFail($this->konfirmasiPesananId);

            $svc->tandaiLunas(
                $pesanan,
                auth()->user(),
                (float) ($this->nominalCash ?: 0),
                (float) ($this->nominalTransfer ?: 0),
            );

            $this->dispatch('notifikasi', pesan: __('pembayaran.notif_lunas', ['toko' => $pesanan->toko->nama]));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->konfirmasiPesananId = null;
        $this->nominalCash = '';
        $this->nominalTransfer = '';
        unset($this->pesanans, $this->pesananKonfirmasi);
    }

    public function render()
    {
        return view('livewire.pembayaran.belum-lunas')->title(__('pembayaran.judul_belum_lunas'));
    }
}
