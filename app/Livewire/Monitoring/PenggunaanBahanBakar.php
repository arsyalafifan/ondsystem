<?php

namespace App\Livewire\Monitoring;

use App\Enums\JenisCatatanBbm;
use App\Models\CatatanBbm;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monitoring > Penggunaan Bahan Bakar: seluruh catatan KM/BBM kendaraan
 * (berangkat, pengisian, kembali) satu hari, lengkap dengan foto buktinya.
 *
 * Tanggal patokannya tanggal KEBERANGKATAN kendaraan (`Kendaraan::tanggal`),
 * sama seperti layar Statistik lain yang berbasis kendaraan — bukan kapan
 * baris catatannya kebetulan dibuat.
 */
class PenggunaanBahanBakar extends Component
{
    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'driver')]
    public string $filterDriver = '';

    #[Url(as: 'jenis')]
    public string $filterJenis = '';

    public ?int $fotoDilihat = null;

    public function mount(): void
    {
        $this->tanggal = $this->tanggal ?: today()->toDateString();
    }

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['tanggal', 'filterDriver', 'filterJenis'], true)) {
            unset($this->catatans, $this->ringkasan);
        }
    }

    /** @return Collection<int, User> driver yang punya kendaraan pada tanggal ini */
    #[Computed]
    public function drivers(): Collection
    {
        return User::driver()
            ->whereHas('kendaraans', fn ($q) => $q->whereDate('tanggal', $this->tanggal))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return Collection<int, CatatanBbm> */
    #[Computed]
    public function catatans(): Collection
    {
        return CatatanBbm::query()
            ->with(['kendaraan:id,nama,nomor,driver_id', 'kendaraan.driver:id,name', 'dicatatOleh:id,name'])
            ->whereHas('kendaraan', fn ($q) => $q
                ->whereDate('tanggal', $this->tanggal)
                ->when($this->filterDriver !== '', fn ($k) => $k->where('driver_id', $this->filterDriver)))
            ->when($this->filterJenis !== '', fn ($q) => $q->where('jenis', $this->filterJenis))
            ->latest('created_at')
            ->get();
    }

    /** @return array{berangkat: int, kembali: int, pengisian: int, liter: float, biaya: float} */
    #[Computed]
    public function ringkasan(): array
    {
        $catatans = $this->catatans;

        return [
            'berangkat' => $catatans->where('jenis', JenisCatatanBbm::Berangkat)->count(),
            'kembali' => $catatans->where('jenis', JenisCatatanBbm::Kembali)->count(),
            'pengisian' => $catatans->where('jenis', JenisCatatanBbm::Pengisian)->count(),
            'liter' => (float) $catatans->sum('liter'),
            'biaya' => (float) $catatans->sum('biaya'),
        ];
    }

    #[Computed]
    public function foto(): ?CatatanBbm
    {
        return $this->fotoDilihat === null
            ? null
            : CatatanBbm::with(['kendaraan:id,nama,nomor', 'dicatatOleh:id,name'])->find($this->fotoDilihat);
    }

    public function render()
    {
        return view('livewire.monitoring.penggunaan-bahan-bakar')->title(__('kendaraan.judul_monitoring'));
    }
}
