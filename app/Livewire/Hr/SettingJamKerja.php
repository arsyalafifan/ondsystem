<?php

namespace App\Livewire\Hr;

use App\Enums\LokasiAbsensi;
use App\Models\Posisi;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Setting Jam Kerja per posisi: jam masuk/pulang, toleransi keterlambatan,
 * hari kerja, kondisi absen (di depot / di toko tanggungan / bebas) beserta
 * radiusnya, dan absen kembali dari istirahat.
 *
 * Menyunting kolom aturan pada baris `posisis` yang sama dengan Master
 * Posisi — dua menu, satu tabel (lihat migrasi `create_posisis_table`).
 */
class SettingJamKerja extends Component
{
    public ?int $posisiId = null;

    public bool $formTerbuka = false;

    public string $jamMasuk = '08:00';

    public string $jamPulang = '17:00';

    public string $toleransiTelatMenit = '0';

    public string $durasiIstirahatMenit = '60';

    /** @var array<int, string> hari ISO-8601 yang dicentang */
    public array $hariKerja = [];

    public string $lokasiJenis = 'depot';

    public string $radiusMeter = '100';

    public bool $pakaiAbsenIstirahat = false;

    public string $istirahatPalingLambat = '13:00';

    #[Computed]
    public function posisis()
    {
        return Posisi::query()->orderBy('nama')->get();
    }

    #[Computed]
    public function lokasiCases(): array
    {
        return LokasiAbsensi::cases();
    }

    /** @return array<int, string> Senin..Minggu untuk ceklis hari kerja */
    #[Computed]
    public function namaHari(): array
    {
        return [
            1 => __('hr.hari_1'), 2 => __('hr.hari_2'), 3 => __('hr.hari_3'), 4 => __('hr.hari_4'),
            5 => __('hr.hari_5'), 6 => __('hr.hari_6'), 7 => __('hr.hari_7'),
        ];
    }

    public function sunting(int $id): void
    {
        $posisi = Posisi::findOrFail($id);

        $this->posisiId = $posisi->id;
        $this->jamMasuk = substr((string) $posisi->jam_masuk, 0, 5);
        $this->jamPulang = substr((string) $posisi->jam_pulang, 0, 5);
        $this->toleransiTelatMenit = (string) $posisi->toleransi_telat_menit;
        $this->durasiIstirahatMenit = (string) $posisi->durasi_istirahat_menit;
        $this->hariKerja = array_map('strval', $posisi->hariKerja());
        $this->lokasiJenis = $posisi->lokasi_jenis->value;
        $this->radiusMeter = (string) $posisi->radius_meter;
        $this->pakaiAbsenIstirahat = $posisi->pakai_absen_istirahat;
        $this->istirahatPalingLambat = substr((string) ($posisi->istirahat_paling_lambat ?? '13:00'), 0, 5);

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->reset(['posisiId', 'hariKerja', 'pakaiAbsenIstirahat']);
        $this->resetValidation();
        $this->formTerbuka = false;
    }

    /**
     * Jam disimpan lengkap dengan detik ('H:i:s') — lihat catatan yang sama
     * di App\Livewire\Hr\DaftarShift::simpan().
     */
    public function simpan(): void
    {
        $data = $this->validate([
            'jamMasuk' => 'required|date_format:H:i',
            'jamPulang' => 'required|date_format:H:i',
            'toleransiTelatMenit' => 'required|integer|min:0|max:240',
            'durasiIstirahatMenit' => 'required|integer|min:0|max:480',
            'hariKerja' => 'array|min:1',
            'hariKerja.*' => 'integer|min:1|max:7',
            'lokasiJenis' => ['required', Rule::enum(LokasiAbsensi::class)],
            'radiusMeter' => 'required|integer|min:10|max:5000',
            'pakaiAbsenIstirahat' => 'boolean',
            // Wajib hanya kalau absen istirahatnya dinyalakan — tanpa jam
            // acuan, "terlambat kembali istirahat" tidak bisa dinilai.
            'istirahatPalingLambat' => 'required_if:pakaiAbsenIstirahat,true|nullable|date_format:H:i',
        ], [], [
            'jamMasuk' => __('hr.atr_jam_masuk'),
            'jamPulang' => __('hr.atr_jam_pulang'),
            'toleransiTelatMenit' => __('hr.atr_toleransi'),
            'durasiIstirahatMenit' => __('hr.atr_durasi_istirahat'),
            'hariKerja' => __('hr.atr_hari_kerja'),
            'lokasiJenis' => __('hr.atr_kondisi_absen'),
            'radiusMeter' => __('hr.atr_radius'),
            'istirahatPalingLambat' => __('hr.atr_istirahat_paling_lambat'),
        ]);

        Posisi::findOrFail($this->posisiId)->update([
            'jam_masuk' => $data['jamMasuk'].':00',
            'jam_pulang' => $data['jamPulang'].':00',
            'toleransi_telat_menit' => (int) $data['toleransiTelatMenit'],
            'durasi_istirahat_menit' => (int) $data['durasiIstirahatMenit'],
            'hari_kerja' => array_values(array_map('intval', $data['hariKerja'])),
            'lokasi_jenis' => $data['lokasiJenis'],
            'radius_meter' => (int) $data['radiusMeter'],
            'pakai_absen_istirahat' => $this->pakaiAbsenIstirahat,
            'istirahat_paling_lambat' => $this->pakaiAbsenIstirahat ? $data['istirahatPalingLambat'].':00' : null,
        ]);

        $this->tutupForm();
        unset($this->posisis);

        $this->dispatch('notifikasi', pesan: __('hr.jam_kerja_tersimpan'));
    }

    public function render()
    {
        return view('livewire.hr.setting-jam-kerja')->title(__('hr.judul_jam_kerja'));
    }
}
