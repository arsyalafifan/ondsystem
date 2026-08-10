<?php

namespace App\Livewire\Kunjungan;

use App\Models\Kunjungan;
use App\Models\PeriodeKunjungan;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Transaksi utama fitur kunjungan: daftar periode mingguan.
 * Dari sini admin menelusuri ke progres tiap sales, lalu ke kunjungan per toko.
 */
class DaftarPeriode extends Component
{
    use WithPagination;

    public function mount(): void
    {
        // Membuka halaman ini sekaligus memastikan periode minggu berjalan
        // sudah ada dan periode lama sudah ditutup.
        $service = app(PeriodeKunjunganService::class);
        $service->periodeBerjalan();
        $service->tutupPeriodeLama();
    }

    #[Computed]
    public function periodes()
    {
        return PeriodeKunjungan::query()
            ->with(['periodeSales', 'kunjungans:id,periode_kunjungan_id,status'])
            ->orderByDesc('tanggal_mulai')
            ->paginate(12);
    }

    /** Laporan toko tutup yang masih menunggu keputusan admin. */
    #[Computed]
    public function menungguTinjauan(): int
    {
        return Kunjungan::menungguTinjauan()->count();
    }

    public function render()
    {
        return view('livewire.kunjungan.daftar-periode')->title(__('kunjungan.judul_periode'));
    }
}
