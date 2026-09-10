<?php

namespace App\Livewire\Master;

use App\Enums\JenisMutasiStok;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Support\DepotContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarProduk extends Component
{
    use MembutuhkanDepotTerkunci;
    use WithPagination;

    public string $cari = '';

    // --- Formulir ---
    public ?int $produkId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $barcode = '';

    public string $nama = '';

    public string $satuan = 'dus';

    public int $stok = 0;

    public string $harga = '0';

    public bool $aktif = true;

    // --- Penyesuaian stok ---
    public ?int $produkDisesuaikan = null;

    public int $jumlahPenyesuaian = 0;

    public string $keteranganPenyesuaian = '';

    // --- Riwayat mutasi stok ---
    public ?int $produkRiwayat = null;

    public string $riwayatTipe = '';

    public string $riwayatDari = '';

    public string $riwayatSampai = '';

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
        $this->barcode = $produk->barcode ?? '';
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
        $this->reset(['produkId', 'kode', 'barcode', 'nama', 'stok', 'harga']);
        $this->satuan = 'dus';
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        // kode & barcode unik PER DEPOT sejak Stage 4, bukan unik global —
        // Rule::unique polos akan salah menolak nilai yang kebetulan sudah
        // dipakai produk di DEPOT LAIN.
        $depotId = DepotContext::currentOrFail()->id;

        $data = $this->validate([
            'kode' => [
                'required', 'string', 'max:30',
                Rule::unique('produks', 'kode')->ignore($this->produkId)->where('depot_id', $depotId),
            ],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('produks', 'barcode')->ignore($this->produkId)->where('depot_id', $depotId),
            ],
            'nama' => 'required|string|max:255',
            'satuan' => 'required|string|max:20',
            'stok' => 'required|integer|min:0',
            'harga' => 'required|numeric|min:0',
        ], [], [
            'kode' => __('master.atr_kode_produk'),
            'barcode' => __('master.atr_barcode'),
            'nama' => __('master.atr_nama_produk'),
        ]);

        $produk = Produk::find($this->produkId);
        $stokLama = $produk?->stok ?? 0;

        $atribut = [
            'kode' => $data['kode'],
            'barcode' => $data['barcode'] !== '' ? $data['barcode'] : null,
            'nama' => $data['nama'],
            'satuan' => $data['satuan'],
            'stok' => $data['stok'],
            'harga' => $data['harga'],
            'aktif' => $this->aktif,
        ];

        // updateOrCreate(['id' => $this->produkId], ...) tampak lebih
        // ringkas, tapi begitu produkId null ia jatuh ke firstOrNew([])
        // Eloquent yang mencoba fill(['id' => null, ...]) — 'id' bukan
        // fillable, jadi meledak MassAssignmentException dalam mode
        // Model::shouldBeStrict() (aktif di testing/lokal). Percabangan
        // eksplisit di sini menghindari jalur itu sama sekali.
        if ($produk === null) {
            $produk = Produk::create($atribut);
        } else {
            $produk->update($atribut);
        }

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
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

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

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['riwayatTipe', 'riwayatDari', 'riwayatSampai'], true)) {
            $this->resetPage('mutasiPage');
        }
    }

    #[Computed]
    public function produkRiwayatModel(): ?Produk
    {
        return $this->produkRiwayat === null ? null : Produk::find($this->produkRiwayat);
    }

    /** @return array<int, JenisMutasiStok> */
    #[Computed]
    public function tipeMutasiCases(): array
    {
        return JenisMutasiStok::cases();
    }

    public function bukaRiwayat(int $id): void
    {
        $this->produkRiwayat = $id;
        $this->reset(['riwayatTipe', 'riwayatDari', 'riwayatSampai']);
        $this->resetPage('mutasiPage');
    }

    public function tutupRiwayat(): void
    {
        $this->reset(['produkRiwayat', 'riwayatTipe', 'riwayatDari', 'riwayatSampai']);
    }

    public function bersihkanFilterRiwayat(): void
    {
        $this->reset(['riwayatTipe', 'riwayatDari', 'riwayatSampai']);
        $this->resetPage('mutasiPage');
    }

    /**
     * Riwayat keluar-masuk satu produk, terurut yang paling baru dulu —
     * bisa disaring per jenis mutasi dan rentang tanggal. Memuat relasi
     * pesanan/kendaraan/user sekaligus supaya tiap baris bisa menunjukkan
     * KENAPA stoknya berubah (pesanan mana, kendaraan mana, siapa),
     * bukan cuma angkanya saja.
     */
    #[Computed]
    public function riwayatMutasi()
    {
        return StokMutasi::query()
            ->where('produk_id', $this->produkRiwayat)
            ->with(['pesanan:id,kode', 'kendaraan:id,nama', 'user:id,name'])
            ->when($this->riwayatTipe !== '', fn ($q) => $q->where('tipe', $this->riwayatTipe))
            ->when($this->riwayatDari !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->riwayatDari))
            ->when($this->riwayatSampai !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->riwayatSampai))
            ->latest('id')
            ->paginate(15, ['*'], 'mutasiPage');
    }

    public function render()
    {
        return view('livewire.master.daftar-produk')->title(__('master.judul_produk'));
    }
}
