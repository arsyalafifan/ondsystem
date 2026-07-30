<?php

namespace App\Livewire\Master;

use App\Models\Produk;
use App\Models\StokMutasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarProduk extends Component
{
    use WithPagination;

    public string $cari = '';

    // --- Formulir ---
    public ?int $produkId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public string $satuan = 'dus';

    public int $stok = 0;

    public string $harga = '0';

    public bool $aktif = true;

    // --- Penyesuaian stok ---
    public ?int $produkDisesuaikan = null;

    public int $jumlahPenyesuaian = 0;

    public string $keteranganPenyesuaian = '';

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function produks()
    {
        return Produk::query()
            ->when($this->cari !== '', fn ($q) => $q
                ->where('nama', 'like', "%{$this->cari}%")
                ->orWhere('kode', 'like', "%{$this->cari}%"))
            ->orderBy('nama')
            ->paginate(20);
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $produk = Produk::findOrFail($id);

        $this->produkId = $produk->id;
        $this->kode = $produk->kode;
        $this->nama = $produk->nama;
        $this->satuan = $produk->satuan;
        $this->stok = $produk->stok;
        $this->harga = (string) $produk->harga;
        $this->aktif = $produk->aktif;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['produkId', 'kode', 'nama', 'stok', 'harga']);
        $this->satuan = 'dus';
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:30', Rule::unique('produks', 'kode')->ignore($this->produkId)],
            'nama' => 'required|string|max:255',
            'satuan' => 'required|string|max:20',
            'stok' => 'required|integer|min:0',
            'harga' => 'required|numeric|min:0',
        ], [], ['kode' => __('master.atr_kode_produk'), 'nama' => __('master.atr_nama_produk')]);

        $produk = Produk::find($this->produkId);
        $stokLama = $produk?->stok ?? 0;

        $produk = Produk::updateOrCreate(['id' => $this->produkId], [
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'satuan' => $data['satuan'],
            'stok' => $data['stok'],
            'harga' => $data['harga'],
            'aktif' => $this->aktif,
        ]);

        // Perubahan stok lewat formulir tetap dicatat sebagai mutasi, supaya
        // riwayat gudang tidak berlubang.
        if ($data['stok'] !== $stokLama) {
            StokMutasi::create([
                'produk_id' => $produk->id,
                'tipe' => $this->produkId === null ? 'masuk' : 'penyesuaian',
                'jumlah' => $data['stok'] - $stokLama,
                'stok_sesudah' => $produk->stok,
                'reserved_sesudah' => $produk->stok_reserved,
                'keterangan' => $this->produkId === null ? __('master.stok_awal') : __('master.diubah_form'),
                'user_id' => auth()->id(),
            ]);
        }

        $this->tutupForm();
        unset($this->produks);

        $this->dispatch('notifikasi', pesan: __('master.produk_tersimpan'));
    }

    public function bukaPenyesuaian(int $id): void
    {
        $this->produkDisesuaikan = $id;
        $this->jumlahPenyesuaian = 0;
        $this->keteranganPenyesuaian = '';
        $this->resetValidation();
    }

    /** Menambah atau mengurangi stok, misalnya setelah barang datang. */
    public function simpanPenyesuaian(): void
    {
        $this->validate([
            'jumlahPenyesuaian' => 'required|integer|not_in:0',
            'keteranganPenyesuaian' => 'required|string|max:255',
        ], [
            'jumlahPenyesuaian.not_in' => __('master.jumlah_bukan_nol'),
            'keteranganPenyesuaian.required' => __('master.keterangan_wajib'),
        ]);

        DB::transaction(function (): void {
            $produk = Produk::lockForUpdate()->findOrFail($this->produkDisesuaikan);

            $stokBaru = $produk->stok + $this->jumlahPenyesuaian;

            if ($stokBaru < $produk->stok_reserved) {
                $this->addError('jumlahPenyesuaian', __('master.stok_tidak_boleh_turun', ['jumlah' => $produk->stok_reserved]));

                return;
            }

            $produk->update(['stok' => $stokBaru]);

            StokMutasi::create([
                'produk_id' => $produk->id,
                'tipe' => $this->jumlahPenyesuaian > 0 ? 'masuk' : 'penyesuaian',
                'jumlah' => $this->jumlahPenyesuaian,
                'stok_sesudah' => $stokBaru,
                'reserved_sesudah' => $produk->stok_reserved,
                'keterangan' => $this->keteranganPenyesuaian,
                'user_id' => auth()->id(),
            ]);
        });

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->produkDisesuaikan = null;
        unset($this->produks);

        $this->dispatch('notifikasi', pesan: __('master.stok_diperbarui'));
    }

    public function render()
    {
        return view('livewire.master.daftar-produk')->title(__('master.judul_produk'));
    }
}
