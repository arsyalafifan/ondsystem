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
     * @var array{jenis: string, pesanan_id?: int, kendaraan_id?: int}|null
     */
    public ?array $konfirmasi = null;

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

    public function konfirmasiLunas(int $pesananId): void
    {
        $this->konfirmasi = ['jenis' => 'lunas', 'pesanan_id' => $pesananId];
    }

    public function konfirmasiBelumLunas(int $pesananId): void
    {
        $this->konfirmasi = ['jenis' => 'belum_lunas', 'pesanan_id' => $pesananId];
    }

    public function konfirmasiLunasSemua(int $kendaraanId): void
    {
        $this->konfirmasi = ['jenis' => 'lunas_semua', 'kendaraan_id' => $kendaraanId];
    }

    public function batalkanKonfirmasi(): void
    {
        $this->konfirmasi = null;
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
                'lunas_semua' => $this->prosesLunasSemua($svc),
                default => null,
            };
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }

        $this->konfirmasi = null;
        unset($this->kendaraans, $this->ringkasan);
    }

    private function prosesLunas(PelunasanService $svc): void
    {
        $pesanan = Pesanan::findOrFail($this->konfirmasi['pesanan_id']);
        $svc->tandaiLunas($pesanan, auth()->user());
        $this->dispatch('notifikasi', pesan: __('pembayaran.notif_lunas', ['toko' => $pesanan->toko->nama]));
    }

    private function prosesBelumLunas(PelunasanService $svc): void
    {
        $pesanan = Pesanan::findOrFail($this->konfirmasi['pesanan_id']);
        $svc->tandaiBelumLunas($pesanan, auth()->user());
        $this->dispatch('notifikasi', pesan: __('pembayaran.notif_belum_lunas', ['toko' => $pesanan->toko->nama]));
    }

    private function prosesLunasSemua(PelunasanService $svc): void
    {
        $kendaraan = Kendaraan::findOrFail($this->konfirmasi['kendaraan_id']);
        $jumlah = $svc->lunasiSisaKendaraan($kendaraan, auth()->user());
        $this->dispatch('notifikasi', pesan: __('pembayaran.notif_lunas_semua', ['jumlah' => $jumlah]));
    }

    public function render()
    {
        return view('livewire.pembayaran.pelunasan')->title(__('pembayaran.judul_pelunasan'));
    }
}
