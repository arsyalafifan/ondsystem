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
            unset($this->pesanans, $this->ringkasanHarian, $this->dataChart, $this->totalKeseluruhan);
            $this->dispatch('pendapatan-diperbarui', data: $this->dataChart);
        }
    }

    #[Computed]
    public function pesanans(): Collection
    {
        return Pesanan::query()
            ->where('status_bayar', StatusBayar::Lunas)
            ->with('items')
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
