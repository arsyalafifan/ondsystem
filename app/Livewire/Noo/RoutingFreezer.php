<?php

namespace App\Livewire\Noo;

use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Kendaraan;
use App\Models\Noo;
use App\Models\RoutingBatch;
use App\Models\User;
use App\Services\RoutingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Menyusun rute pengantaran FREEZER untuk calon mitra yang baru disetujui.
 *
 * Sengaja berdiri sendiri, terpisah dari Generate Routing pesanan: muatannya
 * unit freezer (bukan dus), kapasitas mobilnya ditentukan ukuran bak (lihat
 * config `ond.noo.maks_freezer_per_mobil`), dan pekerjaan driver di tokonya
 * pun berbeda — ia memasang freezer dan melengkapi data toko, bukan
 * menurunkan dus. Mencampur keduanya dalam satu layar hanya akan membuat
 * angka "berapa dus di mobil ini" jadi tidak berarti.
 *
 * Layarnya dibuat seperlunya saja (susun, tentukan driver, setujui) —
 * penyuntingan halus seperti pindah stop antar mobil belum disediakan karena
 * satu rute freezer isinya sedikit; kalau salah, buang drafnya dan susun ulang.
 */
class RoutingFreezer extends Component
{
    use MembutuhkanDepotTerkunci;

    public ?int $batchId = null;

    public string $tanggalKeberangkatan = '';

    /** @var array<int, string> */
    public array $peringatan = [];

    public ?int $konfirmasiHapus = null;

    public function mount(): void
    {
        if (! $this->pastikanDepotTerkunci()) {
            return;
        }

        $this->tanggalKeberangkatan = CarbonImmutable::today()->toDateString();
        $this->batchId = RoutingBatch::noo()->draft()->latest('id')->value('id');
    }

    /** @return Collection<int, Noo> */
    #[Computed]
    public function siapRouting(): Collection
    {
        return app(RoutingService::class)->nooSiapRouting();
    }

    #[Computed]
    public function batch(): ?RoutingBatch
    {
        return $this->batchId === null
            ? null
            : RoutingBatch::noo()
                ->with([
                    'kendaraans.wilayah:id,nama',
                    'kendaraans.driver:id,name',
                    'kendaraans.stops.toko:id,nama,kode,alamat',
                    'kendaraans.stops.noo:id,kode,nama,alamat,nama_pemilik,freezer_tipe',
                    'pembuat:id,name',
                    'penyetuju:id,name',
                ])
                ->find($this->batchId);
    }

    /**
     * `role` ikut diambil, bukan cuma id+nama: objek inilah yang dioper ke
     * RoutingService::ubahDriver(), dan di sana perannya diperiksa ulang.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function drivers(): Collection
    {
        return User::driver()->where('aktif', true)->orderBy('name')->get(['id', 'name', 'role']);
    }

    public function generate(RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        try {
            $batch = $service->generateNoo(auth()->user(), CarbonImmutable::parse($this->tanggalKeberangkatan));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->batchId = $batch->id;
        unset($this->batch, $this->siapRouting);

        $this->dispatch('notifikasi', pesan: __('noo.notif_rute_dibuat', ['kode' => $batch->kode]));
    }

    public function ubahDriver(int $kendaraanId, ?string $driverId): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $kendaraan = Kendaraan::whereKey($kendaraanId)
            ->whereIn('routing_batch_id', RoutingBatch::noo()->select('id'))
            ->first();

        if ($kendaraan === null) {
            return;
        }

        $driver = $driverId === '' || $driverId === null ? null : $this->drivers->firstWhere('id', (int) $driverId);

        app(RoutingService::class)->ubahDriver($kendaraan, $driver);

        unset($this->batch);
    }

    public function setujui(RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $batch = $this->batch;

        if ($batch === null) {
            return;
        }

        try {
            $service->setujui($batch, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        unset($this->batch, $this->siapRouting);

        $this->dispatch('notifikasi', pesan: __('noo.notif_rute_disetujui', ['kode' => $batch->kode]));
    }

    public function hapusDraft(RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $batch = $this->batch;

        if ($batch === null) {
            return;
        }

        try {
            $service->hapusDraft($batch);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->batchId = null;
        $this->konfirmasiHapus = null;
        unset($this->batch, $this->siapRouting);

        $this->dispatch('notifikasi', pesan: __('noo.notif_rute_dihapus'));
    }

    public function render()
    {
        return view('livewire.noo.routing-freezer')->title(__('noo.judul_routing'));
    }
}
