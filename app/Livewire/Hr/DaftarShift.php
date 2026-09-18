<?php

namespace App\Livewire\Hr;

use App\Models\Shift;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Master Shift Kerja. Shift menempel ke KARYAWAN (dipilih di Master
 * Karyawan), bukan ke posisi — dalam satu posisi pun karyawannya bisa
 * berbeda shift. Karyawan tanpa shift mengikuti jam kerja posisinya.
 */
class DaftarShift extends Component
{
    public ?int $shiftId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public string $jamMasuk = '08:00';

    public string $jamPulang = '17:00';

    public bool $lintasHari = false;

    /** '' = ikut lama istirahat posisi karyawan. */
    public string $durasiIstirahatMenit = '';

    public bool $aktif = true;

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function shifts()
    {
        return Shift::query()
            ->withCount('karyawans')
            ->orderBy('jam_masuk')
            ->get();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $shift = Shift::findOrFail($id);

        $this->shiftId = $shift->id;
        $this->kode = $shift->kode;
        $this->nama = $shift->nama;
        $this->jamMasuk = substr((string) $shift->jam_masuk, 0, 5);
        $this->jamPulang = substr((string) $shift->jam_pulang, 0, 5);
        $this->lintasHari = $shift->lintas_hari;
        $this->durasiIstirahatMenit = $shift->durasi_istirahat_menit === null ? '' : (string) $shift->durasi_istirahat_menit;
        $this->aktif = $shift->aktif;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['shiftId', 'kode', 'nama', 'lintasHari', 'durasiIstirahatMenit']);
        $this->jamMasuk = '08:00';
        $this->jamPulang = '17:00';
        $this->aktif = true;
        $this->resetValidation();
    }

    /**
     * Jam disimpan lengkap dengan detik ('H:i:s'). MySQL menormalkannya
     * sendiri, SQLite tidak — tanpa ini nilainya berbeda antara produksi dan
     * pengujian.
     */
    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('shifts', 'kode')->ignore($this->shiftId)],
            'nama' => 'required|string|max:100',
            'jamMasuk' => 'required|date_format:H:i',
            'jamPulang' => 'required|date_format:H:i',
            'lintasHari' => 'boolean',
            'durasiIstirahatMenit' => 'nullable|integer|min:0|max:480',
            'aktif' => 'boolean',
        ], [], [
            'kode' => __('hr.atr_kode_shift'),
            'nama' => __('hr.atr_nama_shift'),
            'jamMasuk' => __('hr.atr_jam_masuk'),
            'jamPulang' => __('hr.atr_jam_pulang'),
            'durasiIstirahatMenit' => __('hr.atr_durasi_istirahat'),
        ]);

        Shift::updateOrCreate(['id' => $this->shiftId], [
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'jam_masuk' => $data['jamMasuk'].':00',
            'jam_pulang' => $data['jamPulang'].':00',
            'lintas_hari' => $this->lintasHari,
            'durasi_istirahat_menit' => $this->durasiIstirahatMenit === '' ? null : (int) $this->durasiIstirahatMenit,
            'aktif' => $this->aktif,
        ]);

        $this->tutupForm();
        unset($this->shifts);

        $this->dispatch('notifikasi', pesan: __('hr.shift_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $shift = Shift::withCount('karyawans')->findOrFail($id);

        if ($shift->karyawans_count > 0) {
            $this->dispatch('notifikasi', pesan: __('hr.shift_dipakai', [
                'nama' => $shift->nama,
                'jumlah' => $shift->karyawans_count,
            ]), jenis: 'error');

            $this->konfirmasiHapus = null;

            return;
        }

        $shift->delete();
        $this->konfirmasiHapus = null;
        unset($this->shifts);

        $this->dispatch('notifikasi', pesan: __('hr.shift_dihapus'));
    }

    public function render()
    {
        return view('livewire.hr.daftar-shift')->title(__('hr.judul_shift'));
    }
}
