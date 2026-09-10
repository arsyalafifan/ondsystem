<?php

namespace App\Livewire\Statistik;

use App\Enums\JenisMutasiStok;
use App\Models\StokMutasi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Berapa dus yang "pulang" ke gudang per driver — dus yang masih tersisa
 * di mobil saat admin menekan tombol "Selesaikan Kendaraan" (lihat
 * `PengirimanService::selesaikanKendaraan()`; tombolnya sendiri di layar
 * kunjungan kendaraan sudah menunjukkan angka X dus itu sebelum ditekan).
 *
 * Satu-satunya sumber datanya baris `StokMutasi` bertipe `release` yang
 * punya `kendaraan_id` terisi — itulah penanda PERSIS baris ini lahir
 * dari selesaikanKendaraan(), beda dari baris `release` LAIN yang lahir
 * dari pembatalan pesanan biasa (`PesananService::lepasKunciStok()`, yang
 * mengisi `pesanan_id`, bukan `kendaraan_id`). Tanpa penyaring
 * `kendaraan_id` ini, dus pulang akan bercampur dengan dus lepas kunci
 * dari pesanan batal yang sama sekali tidak berhubungan dengan driver
 * mana pun.
 *
 * Tanggal patokannya tanggal KEBERANGKATAN kendaraan (`Kendaraan::tanggal`)
 * — sama seperti layar Statistik lain yang berbasis kendaraan (Pendapatan,
 * Barang Terjual, dst.) — bukan kapan admin kebetulan menekan tombolnya.
 */
class DusPulangDriver extends Component
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
            unset($this->mutasis, $this->perDriver, $this->dataChart, $this->totalDusKeseluruhan);
            $this->dispatch('dus-pulang-diperbarui', data: $this->dataChart);
        }
    }

    /** @return Collection<int, StokMutasi> */
    #[Computed]
    public function mutasis(): Collection
    {
        return StokMutasi::query()
            ->where('tipe', JenisMutasiStok::Release)
            ->whereNotNull('kendaraan_id')
            ->whereHas('kendaraan', function (Builder $q): void {
                $q->whereNotNull('driver_id')
                    ->when($this->mode === 'hari', fn (Builder $k) => $k->whereDate('tanggal', $this->tanggal))
                    ->when($this->mode === 'bulan', function (Builder $k) {
                        $bulan = CarbonImmutable::parse($this->bulan.'-01');
                        $k->whereDate('tanggal', '>=', $bulan->startOfMonth()->toDateString())
                            ->whereDate('tanggal', '<=', $bulan->endOfMonth()->toDateString());
                    })
                    ->when($this->mode === 'rentang', fn (Builder $k) => $k
                        ->whereDate('tanggal', '>=', $this->dariTanggal)
                        ->whereDate('tanggal', '<=', $this->sampaiTanggal));
                // mode 'semua': tidak ada penyaring tanggal sama sekali.
            })
            ->with(['kendaraan:id,driver_id', 'kendaraan.driver:id,name'])
            ->get();
    }

    /**
     * Rekap per driver, terurut dus terbanyak dulu.
     *
     * @return Collection<int, array{user_id: int, nama: string, total_dus: int, total_kendaraan: int}>
     */
    #[Computed]
    public function perDriver(): Collection
    {
        return $this->mutasis
            ->groupBy(fn (StokMutasi $m) => $m->kendaraan->driver_id)
            ->map(function (Collection $grup) {
                $driver = $grup->first()->kendaraan->driver;

                return [
                    'user_id' => $driver->id,
                    'nama' => $driver->name,
                    'total_dus' => (int) $grup->sum('jumlah'),
                    'total_kendaraan' => $grup->pluck('kendaraan_id')->unique()->count(),
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
        return view('livewire.statistik.dus-pulang-driver')->title(__('statistik.judul_dus_pulang'));
    }
}
