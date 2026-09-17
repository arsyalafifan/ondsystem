<?php

namespace App\Livewire\Hr;

use App\Models\Posisi;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Master Posisi — identitas posisi saja (kode, nama, aktif). Jam kerja dan
 * kondisi absennya disunting di menu Setting Jam Kerja, yang menyunting
 * kolom lain pada baris yang sama (lihat App\Livewire\Hr\SettingJamKerja).
 */
class DaftarPosisi extends Component
{
    public ?int $posisiId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public bool $aktif = true;

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function posisis()
    {
        return Posisi::query()
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
        $posisi = Posisi::findOrFail($id);

        $this->posisiId = $posisi->id;
        $this->kode = $posisi->kode;
        $this->nama = $posisi->nama;
        $this->aktif = $posisi->aktif;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['posisiId', 'kode', 'nama']);
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('posisis', 'kode')->ignore($this->posisiId)],
            'nama' => 'required|string|max:100',
            'aktif' => 'boolean',
        ], [], ['kode' => __('hr.atr_kode_posisi'), 'nama' => __('hr.atr_nama_posisi')]);

        Posisi::updateOrCreate(['id' => $this->posisiId], $data);

        $this->tutupForm();
        unset($this->posisis);

        $this->dispatch('notifikasi', pesan: __('hr.posisi_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $posisi = Posisi::withCount('karyawans')->findOrFail($id);

        if ($posisi->karyawans_count > 0) {
            $this->dispatch('notifikasi', pesan: __('hr.posisi_dipakai', [
                'nama' => $posisi->nama,
                'jumlah' => $posisi->karyawans_count,
            ]), jenis: 'error');

            $this->konfirmasiHapus = null;

            return;
        }

        $posisi->delete();
        $this->konfirmasiHapus = null;
        unset($this->posisis);

        $this->dispatch('notifikasi', pesan: __('hr.posisi_dihapus'));
    }

    public function render()
    {
        return view('livewire.hr.daftar-posisi')->title(__('hr.judul_posisi'));
    }
}
