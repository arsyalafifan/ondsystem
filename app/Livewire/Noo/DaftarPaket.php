<?php

namespace App\Livewire\Noo;

use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\PaketNoo;
use App\Models\Produk;
use App\Support\DepotContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Setting Default Pesanan NOO: paket penjualan perdana untuk mitra baru.
 *
 * Paket hanya boleh disimpan kalau rincian produknya GENAP persis dengan
 * jumlah dus yang dijanjikan namanya. Penjagaan itu sengaja ditaruh di sini,
 * di depan admin yang sedang menyusunnya dengan tenang — bukan di ujung alur
 * saat driver sudah berdiri di depan toko dan pesanan perdananya gagal
 * terbentuk karena paketnya ternyata timpang.
 *
 * Dua paket bawaan ("15+2" dan "10+1") disemai oleh migrasi tanpa isi, karena
 * katalog produk tiap depot berbeda dan tidak ada tebakan yang benar. Selama
 * produknya belum dipilih, paket itu tidak muncul sebagai pilihan sales
 * (lihat PaketNoo::lengkap).
 */
class DaftarPaket extends Component
{
    use MembutuhkanDepotTerkunci;

    public ?int $paketId = null;

    public bool $formTerbuka = false;

    public string $nama = '';

    public string $dusReguler = '';

    public string $dusBonus = '';

    public bool $aktif = true;

    /** @var array<int, array{produk_id: int|string, jumlah_dus: int|string}> */
    public array $baris = [];

    /** @var array<int, array{produk_id: int|string, jumlah_dus: int|string}> */
    public array $barisBonus = [];

    public ?int $konfirmasiHapus = null;

    /** @return Collection<int, PaketNoo> */
    #[Computed]
    public function pakets(): Collection
    {
        return PaketNoo::query()
            ->with('items.produk:id,nama,kode')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    /** @return Collection<int, Produk> */
    #[Computed]
    public function produkAktif(): Collection
    {
        return Produk::aktif()->orderBy('nama')->get(['id', 'nama', 'kode']);
    }

    /** Hitungan langsung untuk panduan di layar — bukan penjaga; itu ada di simpan(). */
    #[Computed]
    public function terpilihReguler(): int
    {
        return $this->totalBaris($this->baris);
    }

    #[Computed]
    public function terpilihBonus(): int
    {
        return $this->totalBaris($this->barisBonus);
    }

    /** @param array<int, array{produk_id: int|string, jumlah_dus: int|string}> $baris */
    private function totalBaris(array $baris): int
    {
        return (int) collect($baris)->sum(fn (array $b): int => (int) ($b['jumlah_dus'] ?: 0));
    }

    public function tambahBaris(): void
    {
        $this->baris[] = ['produk_id' => '', 'jumlah_dus' => ''];
    }

    public function hapusBaris(int $indeks): void
    {
        unset($this->baris[$indeks]);
        $this->baris = array_values($this->baris);

        if ($this->baris === []) {
            $this->tambahBaris();
        }
    }

    public function tambahBarisBonus(): void
    {
        $this->barisBonus[] = ['produk_id' => '', 'jumlah_dus' => ''];
    }

    public function hapusBarisBonus(int $indeks): void
    {
        unset($this->barisBonus[$indeks]);
        $this->barisBonus = array_values($this->barisBonus);

        if ($this->barisBonus === []) {
            $this->tambahBarisBonus();
        }
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->tambahBaris();
        $this->tambahBarisBonus();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $paket = PaketNoo::with('items')->findOrFail($id);

        $this->paketId = $paket->id;
        $this->nama = $paket->nama;
        $this->dusReguler = (string) $paket->dus_reguler;
        $this->dusBonus = (string) $paket->dus_bonus;
        $this->aktif = $paket->aktif;

        $this->baris = $this->barisDari($paket, false);
        $this->barisBonus = $this->barisDari($paket, true);

        $this->resetValidation();
        $this->formTerbuka = true;
    }

    /** @return array<int, array{produk_id: int|string, jumlah_dus: int|string}> */
    private function barisDari(PaketNoo $paket, bool $bonus): array
    {
        $baris = $paket->items
            ->where('is_bonus', $bonus)
            ->map(fn ($item): array => ['produk_id' => $item->produk_id, 'jumlah_dus' => $item->jumlah_dus])
            ->values()
            ->all();

        return $baris === [] ? [['produk_id' => '', 'jumlah_dus' => '']] : $baris;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['paketId', 'nama', 'dusReguler', 'dusBonus', 'aktif', 'baris', 'barisBonus']);
        $this->resetValidation();
    }

    /**
     * Baris yang sama sekali belum disentuh dibuang dulu, bukan dianggap
     * salah isi — layar ini selalu menyediakan satu baris kosong di bawah
     * (dan paket tanpa bonus memang meninggalkannya kosong), jadi menolaknya
     * sebagai "produk wajib diisi" akan menyalahkan admin atas baris yang
     * memang tidak ia niatkan isi. Baris yang terisi SEBAGIAN tetap tinggal
     * supaya kena validasi dan galatnya terlihat di barisnya sendiri.
     *
     * @param  array<int, array{produk_id: int|string, jumlah_dus: int|string}>  $baris
     * @return array<int, array{produk_id: int|string, jumlah_dus: int|string}>
     */
    private function buangBarisKosong(array $baris): array
    {
        return array_values(array_filter(
            $baris,
            fn (array $b): bool => ! ($b['produk_id'] === '' && (string) $b['jumlah_dus'] === ''),
        ));
    }

    public function simpan(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $depotId = DepotContext::currentOrFail()->id;

        $this->baris = $this->buangBarisKosong($this->baris);
        $this->barisBonus = $this->buangBarisKosong($this->barisBonus);

        $produkDepot = Rule::exists('produks', 'id')->where('depot_id', $depotId);

        $data = $this->validate([
            'nama' => ['required', 'string', 'max:60',
                Rule::unique('paket_noos', 'nama')->ignore($this->paketId)->where('depot_id', $depotId)],
            'dusReguler' => 'required|integer|min:1',
            'dusBonus' => 'required|integer|min:0',
            'baris' => 'required|array|min:1',
            'baris.*.produk_id' => ['required', 'distinct', $produkDepot],
            'baris.*.jumlah_dus' => 'required|integer|min:1',
            'barisBonus' => 'array',
            'barisBonus.*.produk_id' => ['required', 'distinct', $produkDepot],
            'barisBonus.*.jumlah_dus' => 'required|integer|min:1',
        ], [
            'nama.unique' => __('noo.galat_nama_paket_dipakai'),
            'baris.*.produk_id.distinct' => __('noo.galat_produk_dobel'),
            'barisBonus.*.produk_id.distinct' => __('noo.galat_produk_dobel'),
            'baris.required' => __('noo.galat_item_kosong'),
        ], [
            'nama' => __('noo.atr_nama_paket'),
            'dusReguler' => __('noo.atr_dus_reguler'),
            'dusBonus' => __('noo.atr_dus_bonus'),
            'baris.*.produk_id' => __('noo.atr_produk'),
            'baris.*.jumlah_dus' => __('noo.atr_jumlah_dus'),
            'barisBonus.*.produk_id' => __('noo.atr_produk'),
            'barisBonus.*.jumlah_dus' => __('noo.atr_jumlah_dus'),
        ]);

        // Inti aturannya: nama paket adalah janji ke pemilik toko, jadi
        // rinciannya harus genap persis — bukan sekadar "kira-kira segitu".
        if ($this->terpilihReguler !== (int) $data['dusReguler']) {
            $this->addError('baris', __('noo.galat_dus_reguler_tidak_cocok', [
                'terpilih' => $this->terpilihReguler,
                'target' => (int) $data['dusReguler'],
            ]));

            return;
        }

        if ($this->terpilihBonus !== (int) $data['dusBonus']) {
            $this->addError('barisBonus', __('noo.galat_dus_bonus_tidak_cocok', [
                'terpilih' => $this->terpilihBonus,
                'target' => (int) $data['dusBonus'],
            ]));

            return;
        }

        DB::transaction(function () use ($data): void {
            $paket = PaketNoo::updateOrCreate(['id' => $this->paketId], [
                'nama' => $data['nama'],
                'dus_reguler' => (int) $data['dusReguler'],
                'dus_bonus' => (int) $data['dusBonus'],
                'aktif' => $this->aktif,
                'urutan' => $this->paketId === null ? ((int) PaketNoo::max('urutan') + 1) : $this->urutanLama(),
            ]);

            // Ditulis ulang seluruhnya, bukan disamakan baris per baris:
            // isinya sedikit, dan menghapus-lalu-menulis jauh lebih mudah
            // dipastikan benar daripada mencocokkan baris lama dengan baru.
            $paket->items()->delete();

            foreach ([false, true] as $bonus) {
                foreach ($bonus ? $this->barisBonus : $this->baris as $b) {
                    $paket->items()->create([
                        'produk_id' => (int) $b['produk_id'],
                        'jumlah_dus' => (int) $b['jumlah_dus'],
                        'is_bonus' => $bonus,
                    ]);
                }
            }
        });

        $this->tutupForm();
        unset($this->pakets);

        $this->dispatch('notifikasi', pesan: __('noo.paket_tersimpan'));
    }

    private function urutanLama(): int
    {
        return (int) PaketNoo::whereKey($this->paketId)->value('urutan');
    }

    public function hapus(int $id): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $paket = PaketNoo::findOrFail($id);

        $paket->items()->delete();
        $paket->delete();

        $this->konfirmasiHapus = null;
        unset($this->pakets);

        $this->dispatch('notifikasi', pesan: __('noo.paket_dihapus'));
    }

    public function render()
    {
        return view('livewire.noo.daftar-paket')->title(__('noo.judul_paket'));
    }
}
