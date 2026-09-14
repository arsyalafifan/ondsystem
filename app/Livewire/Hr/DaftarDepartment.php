<?php

namespace App\Livewire\Hr;

use App\Models\Department;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Master Department — lintas-gudang, sengaja tidak memakai
 * MembutuhkanDepotTerkunci (beda dari pola master data O&D): Department
 * tidak punya kolom depot_id sama sekali, jadi tidak ada aksi tulis di
 * sini yang bergantung pada konteks depot yang sedang aktif.
 */
class DaftarDepartment extends Component
{
    public ?int $departmentId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function departments()
    {
        return Department::query()
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
        $department = Department::findOrFail($id);

        $this->departmentId = $department->id;
        $this->kode = $department->kode;
        $this->nama = $department->nama;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['departmentId', 'kode', 'nama']);
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('departments', 'kode')->ignore($this->departmentId)],
            'nama' => 'required|string|max:100',
        ], [], ['kode' => __('hr.atr_kode_department'), 'nama' => __('hr.atr_nama_department')]);

        Department::updateOrCreate(['id' => $this->departmentId], $data);

        $this->tutupForm();
        unset($this->departments);

        $this->dispatch('notifikasi', pesan: __('hr.department_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $department = Department::withCount('karyawans')->findOrFail($id);

        if ($department->karyawans_count > 0) {
            $this->dispatch('notifikasi', pesan: __('hr.department_dipakai', [
                'nama' => $department->nama,
                'jumlah' => $department->karyawans_count,
            ]), jenis: 'error');

            $this->konfirmasiHapus = null;

            return;
        }

        $department->delete();
        $this->konfirmasiHapus = null;
        unset($this->departments);

        $this->dispatch('notifikasi', pesan: __('hr.department_dihapus'));
    }

    public function render()
    {
        return view('livewire.hr.daftar-department')->title(__('hr.judul_department'));
    }
}
