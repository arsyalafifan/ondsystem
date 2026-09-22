<?php

namespace App\Livewire\Routing;

use App\Models\RoutingBatch;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RiwayatRouting extends Component
{
    use WithPagination;

    #[Computed]
    public function batches()
    {
        // reguler(): rute pengantaran freezer NOO punya layarnya sendiri dan
        // tidak boleh tercampur di riwayat rute pesanan.
        return RoutingBatch::reguler()
            ->with(['pembuat:id,name', 'penyetuju:id,name'])
            ->withCount('kendaraans')
            ->latest('id')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.routing.riwayat-routing')->title(__('routing.judul_riwayat'));
    }
}
