<?php

namespace App\Livewire\Hr;

use App\Livewire\Hr\Concerns\DaftarPersetujuan;
use App\Models\PengajuanIzin;
use App\Services\Izin\PengajuanIzinService;
use Livewire\Component;

/**
 * Menu Persetujuan Izin: menyetujui/menolak pengajuan izin & sakit. Yang
 * disetujui langsung tampil sebagai status harian di Attendance Monitoring.
 * Aturannya di App\Services\Izin\PengajuanIzinService; perilaku daftarnya
 * (filter, siapa boleh memutuskan) di Concerns\DaftarPersetujuan.
 */
class PersetujuanIzin extends Component
{
    use DaftarPersetujuan;

    protected function modelPengajuan(): string
    {
        return PengajuanIzin::class;
    }

    protected function layananPersetujuan(): object
    {
        return app(PengajuanIzinService::class);
    }

    public function render()
    {
        return view('livewire.hr.persetujuan-izin')->title(__('izin.judul_persetujuan'));
    }
}
