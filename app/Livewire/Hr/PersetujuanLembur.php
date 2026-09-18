<?php

namespace App\Livewire\Hr;

use App\Livewire\Hr\Concerns\DaftarPersetujuan;
use App\Models\PengajuanLembur;
use App\Services\Lembur\PengajuanLemburService;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Menu Persetujuan Lembur — aturan & perilaku daftar sama persis dengan
 * Persetujuan Izin (Concerns\DaftarPersetujuan, App\Services\Izin\ApproverIzin).
 */
class PersetujuanLembur extends Component
{
    use DaftarPersetujuan;

    protected function modelPengajuan(): string
    {
        return PengajuanLembur::class;
    }

    protected function layananPersetujuan(): object
    {
        return app(PengajuanLemburService::class);
    }

    /** @return array<int, array<string, mixed>> jam diajukan/diakui tiap baris di halaman ini */
    #[Computed]
    public function hitungan(): array
    {
        $service = app(PengajuanLemburService::class);

        return collect($this->pengajuans->items())
            ->mapWithKeys(fn (PengajuanLembur $l) => [$l->id => $service->hitung($l)])
            ->all();
    }

    public function render()
    {
        return view('livewire.hr.persetujuan-lembur')->title(__('lembur.judul_persetujuan'));
    }
}
