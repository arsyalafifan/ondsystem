<?php

namespace App\Livewire\Kunjungan;

use App\Models\Kunjungan;
use App\Models\PeriodeKunjungan;
use App\Services\Kunjungan\PeriodeKunjunganService;
use App\Support\DepotContext;
use App\Support\ModeDepot;
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
        // Superadmin dalam mode "Semua Depot" tetap boleh MELIHAT daftar
        // periode lintas depot (listing di bawah tidak butuh satu depot
        // spesifik) — cuma bookkeeping otomatisnya (bikin periode minggu
        // berjalan, tutup periode lama) yang dilewati, karena itu aksi
        // tulis yang cuma bermakna untuk satu depot tertentu.
        if (DepotContext::mode() !== ModeDepot::Terkunci) {
            return;
        }

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
