<?php

namespace App\Livewire\Statistik;

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Berapa dus yang berhasil terantar per akun driver — kumpulan pesanannya
 * PERSIS sama dengan Insentif Sales (`InsentifSales`): pesanan status
 * Selesai yang penginputnya (`dibuat_oleh`) berperan Sales. Scope kerja
 * sales adalah MENGINPUT pesanan (itu yang dihitung Insentif Sales), scope
 * kerja driver adalah MENGANTARKAN pesanan yang sales input itu — jadi di
 * sini kumpulan pesanan yang sama justru dikelompokkan ulang menurut siapa
 * yang mengantarkannya (`stop.kendaraan.driver_id`), bukan siapa yang
 * menginputnya. Satu pesanan yang sama karenanya ikut menyumbang ke
 * Insentif Sales SEKALIGUS Dus Terjual Driver — dua sisi tanggung jawab
 * dari transaksi yang sama, bukan dua transaksi yang tumpang tindih.
 *
 * Pesanan kampas (yang driver input sendiri saat di lapangan) TIDAK ikut
 * di sini — penginputnya berperan Driver, bukan Sales, jadi otomatis
 * tersaring lewat `whereHas('pembuat', role Sales)`, konsisten dengan
 * alasan Insentif Sales mengecualikannya juga.
 *
 * Dus yang dihitung adalah yang BENAR-BENAR terkirim
 * (`PesananItem::terkirim`, yang sudah memperhitungkan koreksi nota
 * dicoret), bukan jumlah pesanan mentah — sama seperti Insentif Sales.
 *
 * Patokan tanggalnya `Pesanan::tanggalPendapatanAntara()` — scope yang
 * sama dipakai Insentif Sales dan Pendapatan, supaya ketiganya konsisten.
 *
 * Bonus PROMO ikut dihitung penuh, bonus MANUAL dikecualikan — alasannya
 * sama seperti Insentif Sales (lihat dokumentasinya di sana).
 */
class DusTerjualDriver extends Component
{
    #[Url(as: 'mode')]
    public string $mode = 'bulan';

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
            unset($this->pesanans, $this->perDriver, $this->dataChart, $this->totalDusKeseluruhan);
            $this->dispatch('dus-terjual-diperbarui', data: $this->dataChart);
        }
    }

    /** @return Collection<int, Pesanan> */
    #[Computed]
    public function pesanans(): Collection
    {
        return Pesanan::query()
            ->where('status', StatusPesanan::Selesai)
            ->whereHas('pembuat', fn ($q) => $q->where('role', PeranPengguna::Sales))
            // Hanya pesanan yang benar-benar diantar lewat kendaraan
            // berdriver yang ikut dihitung — POS (tidak pernah lewat
            // kendaraan sama sekali) otomatis tersaring lewat syarat ini.
            ->whereHas('stop.kendaraan', fn ($q) => $q->whereNotNull('driver_id'))
            ->with(['items', 'stop.kendaraan.driver:id,name'])
            ->when($this->mode === 'hari', fn ($q) => $q->tanggalPendapatanAntara($this->tanggal, $this->tanggal))
            ->when($this->mode === 'bulan', function ($q) {
                $bulan = CarbonImmutable::parse($this->bulan.'-01');
                $q->tanggalPendapatanAntara($bulan->startOfMonth()->toDateString(), $bulan->endOfMonth()->toDateString());
            })
            ->when($this->mode === 'rentang', fn ($q) => $q->tanggalPendapatanAntara($this->dariTanggal, $this->sampaiTanggal))
            // mode 'semua': tidak ada penyaring tanggal sama sekali.
            ->get();
    }

    /**
     * Rekap per driver, terurut dus terbanyak dulu.
     *
     * @return Collection<int, array{user_id: int, nama: string, total_dus: int, total_pesanan: int, total_toko: int}>
     */
    #[Computed]
    public function perDriver(): Collection
    {
        return $this->pesanans
            ->groupBy(fn (Pesanan $p) => $p->stop->kendaraan->driver_id)
            ->map(function (Collection $grup) {
                $driver = $grup->first()->stop->kendaraan->driver;

                return [
                    'user_id' => $driver->id,
                    'nama' => $driver->name,
                    'total_dus' => (int) $grup->sum(function (Pesanan $p) {
                        return $p->items
                            ->filter(fn ($item) => ! $item->is_bonus || $p->promo_id !== null)
                            ->sum->terkirim;
                    }),
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
        return (int) $this->perDriver->sum('total_dus');
    }

    #[Computed]
    public function dataChart(): array
    {
        return [
            'labels' => $this->perDriver->pluck('nama')->all(),
            'data' => $this->perDriver->pluck('total_dus')->all(),
        ];
    }

    public function render()
    {
        return view('livewire.statistik.dus-terjual-driver')->title(__('statistik.judul_dus_terjual'));
    }
}
