<?php

namespace App\Livewire\Master;

use App\Models\Wilayah;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DaftarWilayah extends Component
{
    public ?int $wilayahId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public string $keterangan = '';

    public bool $aktif = true;

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function wilayahs()
    {
        return Wilayah::query()
            ->withCount(['tokos', 'pesanans'])
            ->orderBy('nama')
            ->get();
    }

    /** Wilayah yang baru dihapus, untuk jaga-jaga kalau terhapus keliru. */
    #[Computed]
    public function wilayahsTerhapus()
    {
        return Wilayah::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->limit(20)
            ->get();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $wilayah = Wilayah::findOrFail($id);

        $this->wilayahId = $wilayah->id;
        $this->kode = $wilayah->kode;
        $this->nama = $wilayah->nama;
        $this->keterangan = $wilayah->keterangan ?? '';
        $this->aktif = $wilayah->aktif;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['wilayahId', 'kode', 'nama', 'keterangan']);
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('wilayahs', 'kode')->ignore($this->wilayahId)],
            'nama' => 'required|string|max:255',
            'keterangan' => 'nullable|string',
        ], [], ['kode' => __('master.atr_kode_wilayah'), 'nama' => __('master.atr_nama_wilayah')]);

        Wilayah::updateOrCreate(['id' => $this->wilayahId], [
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'keterangan' => $this->keterangan ?: null,
            'aktif' => $this->aktif,
        ]);

        $this->tutupForm();
        unset($this->wilayahs);

        $this->dispatch('notifikasi', pesan: __('master.wilayah_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $wilayah = Wilayah::withCount('tokos')->findOrFail($id);

        // Wilayah yang masih dipakai toko tidak boleh hilang, karena akan
        // memutus data pesanan yang sudah terlanjur tercatat.
        if ($wilayah->tokos_count > 0) {
            $this->dispatch(
                'notifikasi',
                pesan: __('master.wilayah_dipakai', [
                    'nama' => $wilayah->nama,
                    'jumlah' => $wilayah->tokos_count,
                ]),
                jenis: 'error',
            );

            $this->konfirmasiHapus = null;

            return;
        }

        $wilayah->delete();
        $this->konfirmasiHapus = null;
        unset($this->wilayahs, $this->wilayahsTerhapus);

        $this->dispatch('notifikasi', pesan: __('master.wilayah_dihapus'));
    }

    public function pulihkan(int $id): void
    {
        $wilayah = Wilayah::onlyTrashed()->findOrFail($id);
        $wilayah->restore();

        unset($this->wilayahs, $this->wilayahsTerhapus);

        $this->dispatch('notifikasi', pesan: __('master.wilayah_dipulihkan', ['nama' => $wilayah->nama]));
    }

    public function render()
    {
        return view('livewire.master.daftar-wilayah')->title(__('master.judul_wilayah'));
    }
}
