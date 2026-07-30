<?php

namespace App\Livewire\Driver;

use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Services\PesananService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Layar kerja driver di lapangan: daftar toko sesuai urutan kunjungan,
 * tombol navigasi ke peta ponsel, dan pengunggahan foto nota.
 *
 * Foto nota adalah bukti serah terima. Begitu terunggah, pesanan otomatis
 * berubah menjadi SELESAI dan stok gudang dipotong.
 */
class DaftarKunjungan extends Component
{
    use WithFileUploads;

    public Kendaraan $kendaraan;

    /** Kunjungan yang sedang diunggah notanya. */
    public ?int $stopAktif = null;

    public $fotoNota;

    public string $catatanDriver = '';

    public function mount(Kendaraan $kendaraan): void
    {
        // Driver hanya boleh membuka mobil yang dia ambil sendiri.
        if ($kendaraan->driver_id !== null && $kendaraan->driver_id !== auth()->id()) {
            abort(403, __('driver.mobil_dibawa_lain'));
        }

        $this->kendaraan = $kendaraan;
    }

    #[Computed]
    public function stops()
    {
        return $this->kendaraan->stops()
            ->with(['toko:id,nama,kode,alamat,telepon,latitude,longitude', 'pesanan:id,kode,catatan,total_dus'])
            ->with('pesanan.items.produk:id,nama')
            ->orderBy('urutan')
            ->get();
    }

    #[Computed]
    public function berikutnya(): ?KendaraanStop
    {
        return $this->stops->firstWhere('status', 'pending');
    }

    #[Computed]
    public function progres(): array
    {
        $stops = $this->stops;
        $selesai = $stops->where('status', 'selesai')->count();

        return [
            'total' => $stops->count(),
            'selesai' => $selesai,
            'belum' => $stops->count() - $selesai,
            'persen' => $stops->count() === 0 ? 0 : (int) round($selesai / $stops->count() * 100),
            'dus_terkirim' => (int) $stops->where('status', 'selesai')->sum('total_dus'),
        ];
    }

    public function bukaUnggah(int $stopId): void
    {
        $this->stopAktif = $stopId;
        $this->fotoNota = null;
        $this->catatanDriver = '';
        $this->resetValidation();
    }

    public function tutupUnggah(): void
    {
        $this->reset(['stopAktif', 'fotoNota', 'catatanDriver']);
    }

    public function kirimNota(PesananService $service): void
    {
        $this->validate([
            'fotoNota' => 'required|image|max:5120',
        ], [
            'fotoNota.required' => __('driver.foto_wajib'),
            'fotoNota.image' => __('driver.foto_harus_gambar'),
            'fotoNota.max' => __('driver.foto_maks'),
        ]);

        $stop = KendaraanStop::with('pesanan', 'toko')->findOrFail($this->stopAktif);

        if ($stop->kendaraan_id !== $this->kendaraan->id) {
            abort(403);
        }

        $path = $this->fotoNota->store("nota/{$this->kendaraan->batch->tanggal->format('Y-m-d')}", 'public');

        try {
            $service->selesaikanPengiriman($stop, $path, auth()->user(), $this->catatanDriver ?: null);
        } catch (RuntimeException $e) {
            // Foto yang terlanjur tersimpan dibuang agar tidak menumpuk.
            Storage::disk('public')->delete($path);

            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->tutupUnggah();
        $this->kendaraan->refresh();
        unset($this->stops, $this->progres, $this->berikutnya);

        $this->dispatch('notifikasi', pesan: __('driver.notif_selesai', [
            'toko' => $stop->toko->nama,
            'kode' => $stop->pesanan->kode,
        ]));
    }

    public function render()
    {
        return view('livewire.driver.daftar-kunjungan')->title(__('driver.judul_kunjungan'));
    }
}
