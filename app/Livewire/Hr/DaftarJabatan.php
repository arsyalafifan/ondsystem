<?php

namespace App\Livewire\Hr;

use App\Models\Jabatan;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Master Jabatan — sifatnya sama persis dengan DaftarDepartment (lintas-
 * gudang, tidak memakai MembutuhkanDepotTerkunci). Lihat catatan di sana.
 */
class DaftarJabatan extends Component
{
    public ?int $jabatanId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function jabatans()
    {
        return Jabatan::query()
            ->withCount('karyawans')
            ->orderBy('nama')
            ->get();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $jabatan = Jabatan::findOrFail($id);

        $this->jabatanId = $jabatan->id;
        $this->kode = $jabatan->kode;
        $this->nama = $jabatan->nama;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['jabatanId', 'kode', 'nama']);
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('jabatans', 'kode')->ignore($this->jabatanId)],
            'nama' => 'required|string|max:100',
        ], [], ['kode' => __('hr.atr_kode_jabatan'), 'nama' => __('hr.atr_nama_jabatan')]);

        Jabatan::updateOrCreate(['id' => $this->jabatanId], $data);

        $this->tutupForm();
        unset($this->jabatans);

        $this->dispatch('notifikasi', pesan: __('hr.jabatan_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $jabatan = Jabatan::withCount('karyawans')->findOrFail($id);

        if ($jabatan->karyawans_count > 0) {
            $this->dispatch('notifikasi', pesan: __('hr.jabatan_dipakai', [
                'nama' => $jabatan->nama,
                'jumlah' => $jabatan->karyawans_count,
            ]), jenis: 'error');

            $this->konfirmasiHapus = null;

            return;
        }

        $jabatan->delete();
        $this->konfirmasiHapus = null;
        unset($this->jabatans);

        $this->dispatch('notifikasi', pesan: __('hr.jabatan_dihapus'));
    }

    public function render()
    {
        return view('livewire.hr.daftar-jabatan')->title(__('hr.judul_jabatan'));
    }
}
