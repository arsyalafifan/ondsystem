<?php

namespace App\Livewire\Driver;

use App\Models\Kendaraan;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PilihMobil extends Component
{
    /**
     * Mobil yang sudah disetujui dan belum tuntas dikirim.
     *
     * Mobil yang belum diambil siapa pun tetap ditampilkan agar driver bisa
     * mengambilnya sendiri, sesuai kebiasaan di lapangan: siapa yang siap
     * berangkat, dia yang mengambil mobil.
     *
     * @return Collection<int, Kendaraan>
     */
    #[Computed]
    public function kendaraans(): Collection
    {
        return Kendaraan::query()
            ->with(['wilayah:id,nama', 'driver:id,name', 'stops'])
            ->whereHas('batch', fn ($q) => $q->where('status', 'disetujui'))
            ->whereIn('status', ['siap', 'jalan'])
            ->where(fn ($q) => $q->whereNull('driver_id')->orWhere('driver_id', auth()->id()))
            ->orderBy('nomor')
            ->get();
    }

    /** @return Collection<int, Kendaraan> */
    #[Computed]
    public function riwayat(): Collection
    {
        return Kendaraan::query()
            ->with('wilayah:id,nama')
            ->where('driver_id', auth()->id())
            ->where('status', 'selesai')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function ambil(int $kendaraanId)
    {
        $kendaraan = Kendaraan::findOrFail($kendaraanId);

        if ($kendaraan->driver_id !== null && $kendaraan->driver_id !== auth()->id()) {
            $this->dispatch('notifikasi', pesan: __('driver.mobil_diambil_lain'), jenis: 'error');
            unset($this->kendaraans);

            return null;
        }

        if ($kendaraan->driver_id === null) {
            $kendaraan->update([
                'driver_id' => auth()->id(),
                'diambil_at' => now(),
            ]);
        }

        return redirect()->route('driver.kunjungan', $kendaraan);
    }

    public function render()
    {
        return view('livewire.driver.pilih-mobil')->title(__('driver.judul_pilih'));
    }
}
