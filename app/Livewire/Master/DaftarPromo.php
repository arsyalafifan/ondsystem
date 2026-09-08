<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Produk;
use App\Models\Promo;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Setting promo "beli N dus gratis M dus" yang muncul otomatis di Input
 * Pesanan begitu periodenya berjalan — lihat `Promo::aktifPada()` dan
 * `BuatPesanan::promoAktif()`. Tidak ada tombol aktif/nonaktif terpisah:
 * periodenya sendiri (tanggal_mulai/tanggal_selesai) sudah menentukan kapan
 * promo tampil dan kapan hilang.
 */
class DaftarPromo extends Component
{
    use MembutuhkanDepotTerkunci;

    public ?int $promoId = null;

    public bool $formTerbuka = false;

    public string $nama = '';

    public string $tanggalMulai = '';

    public string $tanggalSelesai = '';

    public string $minimalDus = '';

    public string $bonusDus = '';

    /** @var array<int, int> */
    public array $produkTerpilih = [];

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function promos(): Collection
    {
        return Promo::query()
            ->withCount('pesanans')
            ->with('produks:id,nama')
            ->orderByDesc('tanggal_mulai')
            ->get();
    }

    #[Computed]
    public function produkAktif(): Collection
    {
        return Produk::aktif()->orderBy('nama')->get();
    }

    public function pilihSemuaProduk(): void
    {
        $this->produkTerpilih = $this->produkAktif->pluck('id')->all();
    }

    public function kosongkanProduk(): void
    {
        $this->produkTerpilih = [];
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $promo = Promo::with('produks')->findOrFail($id);

        $this->promoId = $promo->id;
        $this->nama = $promo->nama;
        $this->tanggalMulai = $promo->tanggal_mulai->toDateString();
        $this->tanggalSelesai = $promo->tanggal_selesai->toDateString();
        $this->minimalDus = (string) $promo->minimal_dus;
        $this->bonusDus = (string) $promo->bonus_dus;
        $this->produkTerpilih = $promo->produks->pluck('id')->all();

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['promoId', 'nama', 'tanggalMulai', 'tanggalSelesai', 'minimalDus', 'bonusDus', 'produkTerpilih']);
        $this->resetValidation();
    }

    public function simpan(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $data = $this->validate([
            'nama' => 'required|string|max:255',
            'tanggalMulai' => 'required|date',
            'tanggalSelesai' => 'required|date|after_or_equal:tanggalMulai',
            'minimalDus' => 'required|integer|min:1',
            'bonusDus' => 'required|integer|min:1',
            'produkTerpilih' => 'required|array|min:1',
            'produkTerpilih.*' => 'exists:produks,id',
        ], [], [
            'nama' => __('master.atr_nama_promo'),
            'tanggalMulai' => __('master.atr_tanggal_mulai'),
            'tanggalSelesai' => __('master.atr_tanggal_selesai'),
            'minimalDus' => __('master.atr_minimal_dus'),
            'bonusDus' => __('master.atr_bonus_dus'),
            'produkTerpilih' => __('master.produk_berhak'),
        ]);

        // V1 cuma mendukung satu promo aktif dalam satu waktu — periksa
        // bentrok terhadap SEMUA promo lain, bukan cuma yang sedang aktif
        // hari ini, karena rentang MASA DEPAN yang tumpang tindih tetap
        // tidak valid begitu tanggal itu tiba nanti.
        $bentrok = Promo::query()
            ->when($this->promoId, fn ($q) => $q->where('id', '!=', $this->promoId))
            ->whereDate('tanggal_mulai', '<=', $data['tanggalSelesai'])
            ->whereDate('tanggal_selesai', '>=', $data['tanggalMulai'])
            ->exists();

        if ($bentrok) {
            $this->addError('tanggalMulai', __('master.promo_bentrok'));

            return;
        }

        $promo = Promo::updateOrCreate(['id' => $this->promoId], [
            'nama' => $data['nama'],
            'tanggal_mulai' => $data['tanggalMulai'],
            'tanggal_selesai' => $data['tanggalSelesai'],
            'minimal_dus' => $data['minimalDus'],
            'bonus_dus' => $data['bonusDus'],
        ]);

        $promo->produks()->sync($data['produkTerpilih']);

        $this->tutupForm();
        unset($this->promos);

        $this->dispatch('notifikasi', pesan: __('master.promo_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $promo = Promo::withCount('pesanans')->findOrFail($id);

        // Promo yang sudah pernah dipakai pesanan tidak boleh hilang —
        // riwayat pesanan lama masih menunjuk ke sini lewat promo_id.
        if ($promo->pesanans_count > 0) {
            $this->dispatch(
                'notifikasi',
                pesan: __('master.promo_dipakai', [
                    'nama' => $promo->nama,
                    'jumlah' => $promo->pesanans_count,
                ]),
                jenis: 'error',
            );

            $this->konfirmasiHapus = null;

            return;
        }

        $promo->delete();
        $this->konfirmasiHapus = null;
        unset($this->promos);

        $this->dispatch('notifikasi', pesan: __('master.promo_dihapus'));
    }

    public function render()
    {
        return view('livewire.master.daftar-promo')->title(__('master.judul_promo'));
    }
}
