<?php

namespace App\Livewire\Statistik;

use App\Enums\PeranPengguna;
use App\Models\Pesanan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Berapa dus yang DIINPUT tiap sales — murni volume aktivitas input, bukan
 * capaian penjualan. Beda dari Insentif Sales: SEMUA status pesanan ikut
 * dihitung (order/process/delivery/selesai/dibatalkan), bukan cuma yang
 * tuntas — pesanan yang akhirnya batal pun tetap mencerminkan usaha sales
 * menginputnya. Dus yang dijumlahkan karena itu `jumlah_dus` mentah (apa
 * yang diketik saat input), bukan `PesananItem::terkirim` — pesanan yang
 * batal tidak pernah "terkirim" sama sekali, jadi memakai terkirim di
 * sini akan salah menghitungnya sebagai nol untuk status-status selain
 * SELESAI.
 *
 * Patokan tanggalnya karena itu juga BUKAN `Pesanan::tanggal_pendapatan`
 * (yang mengandalkan tanggal keberangkatan kendaraan atau tanggal
 * pelunasan — keduanya belum tentu ada untuk pesanan yang belum tuntas
 * atau sudah batal duluan sebelum sempat dirutekan), melainkan
 * `Pesanan::tanggal` — tanggal target yang tercatat sejak pesanan
 * pertama kali diinput, selalu ada untuk status apa pun.
 *
 * Hanya pesanan yang penginputnya (`dibuat_oleh`) berperan SALES yang
 * dihitung — sama seperti Insentif Sales — supaya pesanan kampas
 * (diinput driver) dan penjualan POS yang diinput admin tidak ikut.
 */
class RepeatOrderSales extends Component
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
            unset($this->pesanans, $this->perSales, $this->dataChart, $this->totalDusKeseluruhan);
            $this->dispatch('repeat-order-diperbarui', data: $this->dataChart);
        }
    }

    /** @return Collection<int, Pesanan> */
    #[Computed]
    public function pesanans(): Collection
    {
        return Pesanan::query()
            ->whereHas('pembuat', fn ($q) => $q->where('role', PeranPengguna::Sales))
            ->with(['items', 'pembuat:id,name'])
            ->when($this->mode === 'hari', fn ($q) => $q->whereDate('tanggal', $this->tanggal))
            ->when($this->mode === 'bulan', function ($q) {
                $bulan = CarbonImmutable::parse($this->bulan.'-01');
                $q->whereDate('tanggal', '>=', $bulan->startOfMonth()->toDateString())
                    ->whereDate('tanggal', '<=', $bulan->endOfMonth()->toDateString());
            })
            ->when($this->mode === 'rentang', fn ($q) => $q
                ->whereDate('tanggal', '>=', $this->dariTanggal)
                ->whereDate('tanggal', '<=', $this->sampaiTanggal))
            // mode 'semua': tidak ada penyaring tanggal sama sekali.
            ->get();
    }

    /**
     * Rekap per sales, terurut dus terbanyak dulu — supaya langsung
     * terlihat siapa yang paling banyak menginput tanpa perlu diurutkan
     * manual dari tabel.
     *
     * @return Collection<int, array{user_id: int, nama: string, total_dus: int, total_pesanan: int, total_toko: int}>
     */
    #[Computed]
    public function perSales(): Collection
    {
        return $this->pesanans
            ->groupBy('dibuat_oleh')
            ->map(function (Collection $grup) {
                $pembuat = $grup->first()->pembuat;

                return [
                    'user_id' => $pembuat->id,
                    'nama' => $pembuat->name,
                    'total_dus' => (int) $grup->sum(fn (Pesanan $p) => $p->items->sum('jumlah_dus')),
                    'total_pesanan' => $grup->count(),
                    'total_toko' => $grup->pluck('toko_id')->unique()->count(),
                ];
            })
            ->sortByDesc('total_dus')
            ->values();
    }

    #[Computed]
    public function totalDusKeseluruhan(): int
    {
        return (int) $this->perSales->sum('total_dus');
    }

    #[Computed]
    public function dataChart(): array
    {
        return [
            'labels' => $this->perSales->pluck('nama')->all(),
            'data' => $this->perSales->pluck('total_dus')->all(),
        ];
    }

    public function render()
    {
        return view('livewire.statistik.repeat-order-sales')->title(__('statistik.judul_repeat_order'));
    }
}
