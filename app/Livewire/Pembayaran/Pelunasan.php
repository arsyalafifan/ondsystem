<?php

namespace App\Livewire\Pembayaran;

use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Models\Kendaraan;
use App\Models\Pesanan;
use App\Services\PelunasanService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

class Pelunasan extends Component
{
    #[Url(as: 'tgl')]
    public string $tanggal = '';

    /**
     * Keadaan modal konfirmasi ganda.
     *
     * @var array{jenis: string, pesanan_id?: int}|null
     */
    public ?array $konfirmasi = null;

    /** Diisi manual oleh admin — tidak pernah ditebak atau dibagi otomatis. */
    public string $nominalCash = '';

    public string $nominalTransfer = '';

    public function mount(): void
    {
        $this->tanggal = $this->tanggal ?: today()->toDateString();
    }

    public function updatedTanggal(): void
    {
        unset($this->kendaraans, $this->ringkasan);
    }

    /**
     * "Per hari" di sini mengikuti tanggal pesanan benar-benar tuntas
     * dikirim (`Pesanan::selesai_at`), bukan tanggal rute-nya dibuat
     * (`RoutingBatch::tanggal`). Rute bisa dibuat jauh sebelum driver
     * benar-benar menuntaskan kunjungannya, jadi memakai tanggal batch
     * membuat pesanan yang baru selesai hari ini tidak pernah muncul kalau
     * rutenya dibuat di hari lain.
     */
    #[Computed]
    public function kendaraans(): Collection
    {
        return Kendaraan::query()
            ->whereHas('stops', fn ($q) => $q
                ->whereHas('pesanan', fn ($q2) => $q2
                    ->where('status', StatusPesanan::Selesai)
                    ->whereDate('selesai_at', $this->tanggal)))
            ->with(['wilayah:id,nama', 'driver:id,name', 'stops' => fn ($q) => $q
                ->whereHas('pesanan', fn ($q2) => $q2
                    ->where('status', StatusPesanan::Selesai)
                    ->whereDate('selesai_at', $this->tanggal))
                ->with(['toko:id,nama,kode', 'pesanan.items'])])
            ->orderBy('nomor')
            ->get();
    }

    /** Ringkasan uang per kendaraan, dikunci pada id kendaraan. */
    #[Computed]
    public function ringkasan(): Collection
    {
        return $this->kendaraans->mapWithKeys(function (Kendaraan $k) {
            $tagihan = $k->stops->sum(fn ($s) => $s->pesanan->tagihan);
            $lunas = $k->stops->filter(fn ($s) => $s->pesanan->status_bayar === StatusBayar::Lunas)
                ->sum(fn ($s) => $s->pesanan->tagihan);
            $pending = $k->stops->filter(fn ($s) => $s->pesanan->status_bayar === StatusBayar::Pending)->count();

            return [$k->id => [
                'tagihan' => $tagihan,
                'lunas' => $lunas,
                'pending' => $pending,
                'tuntas' => $pending === 0,
            ]];
        });
    }

    /** Pesanan yang sedang dikonfirmasi lunas, untuk ditampilkan di modal. */
    #[Computed]
    public function pesananKonfirmasi(): ?Pesanan
    {
        $id = $this->konfirmasi['pesanan_id'] ?? null;

        return $id === null ? null : Pesanan::with('toko:id,nama')->find($id);
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

    public function konfirmasiLunas(int $pesananId): void
    {
        $this->konfirmasi = ['jenis' => 'lunas', 'pesanan_id' => $pesananId];
        $this->nominalCash = '';
        $this->nominalTransfer = '';
        $this->resetValidation();
    }

    public function konfirmasiBelumLunas(int $pesananId): void
    {
        $this->konfirmasi = ['jenis' => 'belum_lunas', 'pesanan_id' => $pesananId];
    }

    public function batalkanKonfirmasi(): void
    {
        $this->konfirmasi = null;
        $this->nominalCash = '';
        $this->nominalTransfer = '';
    }

    public function proses(PelunasanService $svc): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        try {
            match ($this->konfirmasi['jenis'] ?? null) {
                'lunas' => $this->prosesLunas($svc),
                'belum_lunas' => $this->prosesBelumLunas($svc),
                default => null,
            };
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->konfirmasi = null;
        $this->nominalCash = '';
        $this->nominalTransfer = '';
        unset($this->kendaraans, $this->ringkasan, $this->pesananKonfirmasi);
    }

    private function prosesLunas(PelunasanService $svc): void
    {
        $pesanan = Pesanan::findOrFail($this->konfirmasi['pesanan_id']);

        $svc->tandaiLunas(
            $pesanan,
            auth()->user(),
            (float) ($this->nominalCash ?: 0),
            (float) ($this->nominalTransfer ?: 0),
        );

        $this->dispatch('notifikasi', pesan: __('pembayaran.notif_lunas', ['toko' => $pesanan->toko->nama]));
    }

    private function prosesBelumLunas(PelunasanService $svc): void
    {
        $pesanan = Pesanan::findOrFail($this->konfirmasi['pesanan_id']);
        $svc->tandaiBelumLunas($pesanan, auth()->user());
        $this->dispatch('notifikasi', pesan: __('pembayaran.notif_belum_lunas', ['toko' => $pesanan->toko->nama]));
    }

    public function render()
    {
        return view('livewire.pembayaran.pelunasan')->title(__('pembayaran.judul_pelunasan'));
    }
}
