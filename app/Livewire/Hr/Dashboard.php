<?php

namespace App\Livewire\Hr;

use Livewire\Component;

/**
 * Placeholder yang disengaja — sistem absensi (hadir/alfa/izin/sakit) belum
 * dirancang, jadi dasbor ini sengaja kosong daripada menampilkan kartu KPI
 * berangka 0 yang bisa disalahartikan sebagai data sungguhan. Lihat
 * livewire.hr.dashboard untuk isi tampilannya.
 */
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.hr.dashboard')->title(__('hr.judul_dashboard'));
    }
}
