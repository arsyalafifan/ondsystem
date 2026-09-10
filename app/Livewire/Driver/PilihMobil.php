<?php

namespace App\Livewire\Driver;

use App\Models\Kendaraan;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class PilihMobil extends Component
{
    /**
     * Per tanggal KEBERANGKATAN kendaraan (`Kendaraan::tanggal`), bawaannya
     * hari ini. Tanpa ini, mobil kemarin yang belum tuntas dikirim tetap
     * "berkumpul" bersama mobil hari ini selama statusnya masih
     * siap/jalan — padahal keduanya rute yang benar-benar beda hari.
     */
    #[Url(as: 'tgl')]
    public string $tanggal = '';

    public function mount(): void
    {
        if ($this->tanggal === '') {
            $this->tanggal = today()->toDateString();
        }
    }

    public function updatedTanggal(): void
    {
        unset($this->kendaraans);
    }

    /**
     * Mobil yang sudah disetujui dan belum tuntas dikirim, pada tanggal
     * keberangkatan yang dipilih.
     *
     * Mobil yang belum diambil siapa pun tetap ditampilkan agar driver bisa
     * mengambilnya sendiri, sesuai kebiasaan di lapangan: siapa yang siap
     * berangkat, dia yang mengambil mobil. Driver hanya melihat mobil yang
     * belum diambil siapa pun atau yang sudah jadi miliknya sendiri — bukan
     * mobil yang dibawa driver lain.
     *
     * Admin/superadmin beda aturan: mereka di sini cuma memantau (lihat
     * ambil() di bawah), jadi SEMUA mobil ditampilkan tanpa terkecuali,
     * termasuk yang sudah dibawa driver lain — itulah yang justru perlu
     * mereka lihat.
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
            ->whereDate('tanggal', $this->tanggal)
            ->when(! auth()->user()->isAdmin(), fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('driver_id')->orWhere('driver_id', auth()->id())))
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

        // Admin/superadmin cuma memantau di sini — boleh membuka kendaraan
        // siapa pun, langsung ke layar kunjungannya, dan TIDAK PERNAH ditolak
        // dengan "mobil sudah diambil orang lain" (itu penolakan yang cuma
        // berlaku untuk driver sungguhan) atau ikut mengklaim mobilnya.
        if (auth()->user()->isAdmin()) {
            return redirect()->route('driver.kunjungan', $kendaraan);
        }

        if ($kendaraan->driver_id !== null && $kendaraan->driver_id !== auth()->id()) {
            $this->dispatch('notifikasi', pesan: __('driver.mobil_diambil_lain'), jenis: 'error');
            unset($this->kendaraans);

            return null;
        }

        // diambil_at masih kosong dicek terpisah dari driver_id, karena admin
        // sekarang bisa menetapkan driver dari layar Generate Routing —
        // driver_id-nya sudah terisi begitu ia belum sempat membuka layar ini
        // sendiri, dan diambil_at yang menandai kapan itu sungguhan terjadi.
        if ($kendaraan->diambil_at === null && auth()->user()->isDriver()) {
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
