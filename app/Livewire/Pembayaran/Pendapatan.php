<?php

namespace App\Livewire\Pembayaran;

use App\Enums\StatusBayar;
use App\Models\Pesanan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Pendapatan extends Component
{
    #[Url(as: 'mode')]
    public string $mode = 'hari';

    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'bln')]
    public string $bulan = '';

    #[Url(as: 'dari')]
    public string $dariTanggal = '';

    #[Url(as: 'sampai')]
    public string $sampaiTanggal = '';

    // --- Penyaring tabel riwayat — hanya menyaring tabel per pesanan di
    // bawah, bukan kartu ringkasan/kategori/grafik di atasnya. Kartu-kartu
    // itu tetap menunjukkan gambaran keseluruhan rentang tanggal; tabel
    // riwayat adalah alat cari yang menyaring DI DALAM rentang itu. ---

    #[Url(as: 'q')]
    public string $riwayatCari = '';

    #[Url(as: 'kat')]
    public string $riwayatKategori = '';

    #[Url(as: 'metode')]
    public string $riwayatMetode = '';

    public function mount(): void
    {
        $this->tanggal = $this->tanggal ?: today()->toDateString();
        $this->bulan = $this->bulan ?: today()->format('Y-m');
        $this->dariTanggal = $this->dariTanggal ?: today()->subDays(6)->toDateString();
        $this->sampaiTanggal = $this->sampaiTanggal ?: today()->toDateString();
    }

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['mode', 'tanggal', 'bulan', 'dariTanggal', 'sampaiTanggal'], true)) {
            unset(
                $this->pesanans, $this->ringkasanHarian, $this->dataChart,
                $this->totalKeseluruhan, $this->totalCash, $this->totalTransfer,
                $this->totalPerKategori, $this->riwayat,
            );
            $this->dispatch('pendapatan-diperbarui', data: $this->dataChart);
        } elseif (in_array($kolom, ['riwayatCari', 'riwayatKategori', 'riwayatMetode'], true)) {
            unset($this->riwayat);
        }
    }

    public function bersihkanFilterRiwayat(): void
    {
        $this->reset(['riwayatCari', 'riwayatKategori', 'riwayatMetode']);
        unset($this->riwayat);
    }

    #[Computed]
    public function pesanans(): Collection
    {
        return Pesanan::query()
            ->where('status_bayar', StatusBayar::Lunas)
            ->with(['items', 'toko:id,nama'])
            ->when($this->mode === 'hari', fn ($q) => $q->whereDate('tanggal_lunas', $this->tanggal))
            ->when($this->mode === 'bulan', function ($q) {
                $bulan = CarbonImmutable::parse($this->bulan.'-01');
                $q->whereBetween('tanggal_lunas', [$bulan->startOfMonth(), $bulan->endOfMonth()]);
            })
            ->when($this->mode === 'rentang', fn ($q) => $q->whereBetween('tanggal_lunas', [$this->dariTanggal, $this->sampaiTanggal]))
            ->get();
    }

    #[Computed]
    public function ringkasanHarian(): Collection
    {
        return $this->pesanans
            ->groupBy(fn (Pesanan $p) => $p->tanggal_lunas->toDateString())
            ->map(fn (Collection $grup) => $grup->sum->tagihan)
            ->sortKeys();
    }

    #[Computed]
    public function totalKeseluruhan(): float
    {
        return $this->ringkasanHarian->sum();
    }

    /**
     * Pendapatan menurut sumbernya. `nominal_cash`/`nominal_transfer`
     * dicatat manual saat admin menandai lunas (lihat PelunasanService),
     * jadi keduanya selalu berjumlah persis sama dengan tagihan — total
     * keduanya harus sama dengan totalKeseluruhan().
     */
    #[Computed]
    public function totalCash(): float
    {
        return (float) $this->pesanans->sum(fn (Pesanan $p) => (float) $p->nominal_cash);
    }

    #[Computed]
    public function totalTransfer(): float
    {
        return (float) $this->pesanans->sum(fn (Pesanan $p) => (float) $p->nominal_transfer);
    }

    /**
     * Pendapatan menurut sumbernya (kategori), bukan menurut cara bayarnya
     * (cash/transfer di atas). Rute biasa dan kampas sama-sama dikelompokkan
     * "driver" karena keduanya lewat kendaraan — lihat
     * JenisPesanan::kategoriPendapatan().
     *
     * @return array<string, array{total: float, jumlah: int}>
     */
    #[Computed]
    public function totalPerKategori(): array
    {
        $grup = $this->pesanans->groupBy(fn (Pesanan $p) => $p->jenis->kategoriPendapatan());

        return [
            'driver' => [
                'total' => (float) ($grup->get('driver')?->sum->tagihan ?? 0),
                'jumlah' => $grup->get('driver')?->count() ?? 0,
            ],
            'pos' => [
                'total' => (float) ($grup->get('pos')?->sum->tagihan ?? 0),
                'jumlah' => $grup->get('pos')?->count() ?? 0,
            ],
        ];
    }

    /**
     * Riwayat pendapatan per pesanan, bukan agregat per hari — dipakai
     * tabel rincian di bawah ringkasan, terurut yang paling baru lunas
     * dulu, dan disaring lewat riwayatCari/riwayatKategori/riwayatMetode.
     *
     * Disaring di memori (bukan lewat kueri baru ke basis data): tabelnya
     * menyaring DI DALAM `pesanans()` yang sudah diambil untuk kartu
     * ringkasan di atas, bukan permintaan terpisah — rentang tanggalnya
     * sudah sama, jadi tidak ada gunanya bertanya ke basis data dua kali.
     *
     * @return Collection<int, Pesanan>
     */
    #[Computed]
    public function riwayat(): Collection
    {
        $kata = mb_strtolower(trim($this->riwayatCari));

        return $this->pesanans
            ->filter(function (Pesanan $p) use ($kata): bool {
                if ($this->riwayatKategori !== '' && $p->jenis->kategoriPendapatan() !== $this->riwayatKategori) {
                    return false;
                }

                if ($this->riwayatMetode === 'cash' && (float) $p->nominal_cash <= 0) {
                    return false;
                }

                if ($this->riwayatMetode === 'transfer' && (float) $p->nominal_transfer <= 0) {
                    return false;
                }

                if ($kata !== ''
                    && ! str_contains(mb_strtolower($p->kode), $kata)
                    && ! str_contains(mb_strtolower($p->toko->nama), $kata)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(fn (Pesanan $p) => $p->tanggal_lunas.$p->id)
            ->values();
    }

    #[Computed]
    public function dataChart(): array
    {
        return [
            'labels' => $this->ringkasanHarian->keys()->all(),
            'data' => $this->ringkasanHarian->values()->all(),
        ];
    }

    public function render()
    {
        return view('livewire.pembayaran.pendapatan')->title(__('pembayaran.judul_pendapatan'));
    }
}
