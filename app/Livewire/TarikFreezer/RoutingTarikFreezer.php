<?php

namespace App\Livewire\TarikFreezer;

use App\Enums\StatusStop;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\RoutingBatch;
use App\Models\TarikFreezer;
use App\Models\User;
use App\Services\RoutingService;
use App\Support\DepotContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Menyusun rute PENGAMBILAN freezer dari toko yang berhenti jadi mitra —
 * kebalikan dari App\Livewire\Noo\RoutingFreezer, dan layarnya sengaja
 * dibuat semirip mungkin: penjelasan lengkap soal alasan berdiri sendiri,
 * tanggal keberangkatan per toko, dan batas layar seperlunya ada di sana.
 */
class RoutingTarikFreezer extends Component
{
    use MembutuhkanDepotTerkunci;

    public string $tanggalKeberangkatan = '';

    /** Id Tarik Freezer yang dicentang. @var array<int, int> */
    public array $terpilih = [];

    public bool $pilihSemua = false;

    public ?int $konfirmasiHapus = null;

    public function mount(): void
    {
        if (! $this->pastikanDepotTerkunci()) {
            return;
        }

        $this->tanggalKeberangkatan = CarbonImmutable::today()->toDateString();
    }

    public function updatedPilihSemua(bool $nilai): void
    {
        $this->terpilih = $nilai ? $this->siapRouting->pluck('id')->all() : [];
    }

    /** @return Collection<int, TarikFreezer> */
    #[Computed]
    public function siapRouting(): Collection
    {
        return app(RoutingService::class)->tarikSiapRouting();
    }

    /** @return Collection<int, RoutingBatch> */
    #[Computed]
    public function batches(): Collection
    {
        return RoutingBatch::tarik()
            ->with([
                'kendaraans.wilayah:id,nama',
                'kendaraans.driver:id,name',
                'kendaraans.stops.toko:id,nama,kode,alamat,latitude,longitude',
                'kendaraans.stops.tarikFreezer:id,kode,toko_id',
                'pembuat:id,name',
                'penyetuju:id,name',
            ])
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function drivers(): Collection
    {
        return User::driver()->where('aktif', true)->orderBy('name')->get(['id', 'name', 'role']);
    }

    #[Computed]
    public function dataPeta(): array
    {
        $belumDirutekan = $this->siapRouting
            ->filter(fn (TarikFreezer $t) => $t->toko->latitude !== null)
            ->map(fn (TarikFreezer $t) => [
                'nama' => $t->toko->nama,
                'lat' => $t->toko->latitude,
                'lng' => $t->toko->longitude,
            ])->values()->all();

        return [
            'kendaraan' => $this->batches->flatMap(fn (RoutingBatch $b) => $b->kendaraans->map(fn (Kendaraan $k) => [
                'id' => $k->id,
                'nama' => $k->nama.' · '.$b->kode,
                'warna' => $k->warna,
                'geometry' => $k->geometry,
                'stops' => $k->stops->map(fn (KendaraanStop $s) => [
                    'id' => $s->id,
                    'nama' => $s->toko?->nama,
                    'urutan' => $s->urutan,
                    'dus' => 1,
                    'eta' => $s->eta ? substr((string) $s->eta, 0, 5) : null,
                    'selesai' => $s->status === StatusStop::Selesai,
                    'warnaStatus' => $s->status->warna(),
                    'lat' => $s->toko?->latitude,
                    'lng' => $s->toko?->longitude,
                ])->values()->all(),
            ]))->values()->all(),
            'belumDirutekan' => $belumDirutekan,
        ];
    }

    #[Computed]
    public function konfigPeta(): array
    {
        $depot = DepotContext::currentOrFail();

        return [
            'tileUrl' => config('ond.peta.tile_url'),
            'attribution' => config('ond.peta.attribution'),
            'zoom' => config('ond.peta.zoom_default'),
            'depot' => [
                'lat' => (float) $depot->lat,
                'lng' => (float) $depot->lng,
                'nama' => $depot->nama,
            ],
            'satuanMuatan' => __('noo.satuan_freezer'),
            'bisaDiklik' => true,
        ];
    }

    public function generate(RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        if ($this->terpilih === []) {
            $this->dispatch('notifikasi', pesan: __('tarik_freezer.galat_belum_pilih_calon'), jenis: 'error');

            return;
        }

        try {
            $batch = $service->generateTarik(
                auth()->user(),
                $this->terpilih,
                CarbonImmutable::parse($this->tanggalKeberangkatan),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->terpilih = [];
        $this->pilihSemua = false;
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('tarik_freezer.notif_rute_dibuat', ['kode' => $batch->kode]));
    }

    public function ubahDriver(int $kendaraanId, ?string $driverId): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $kendaraan = Kendaraan::whereKey($kendaraanId)
            ->whereIn('routing_batch_id', RoutingBatch::tarik()->select('id'))
            ->first();

        if ($kendaraan === null) {
            return;
        }

        $driver = $driverId === '' || $driverId === null ? null : $this->drivers->firstWhere('id', (int) $driverId);

        app(RoutingService::class)->ubahDriver($kendaraan, $driver);

        $this->segarkan();
    }

    public function setujui(int $batchId, RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $batch = RoutingBatch::tarik()->find($batchId);

        if ($batch === null) {
            return;
        }

        try {
            $service->setujui($batch, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('tarik_freezer.notif_rute_disetujui', ['kode' => $batch->kode]));
    }

    public function hapusDraft(RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $batch = $this->konfirmasiHapus === null ? null : RoutingBatch::tarik()->find($this->konfirmasiHapus);

        if ($batch === null) {
            $this->konfirmasiHapus = null;

            return;
        }

        try {
            $service->hapusDraft($batch);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->konfirmasiHapus = null;
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('tarik_freezer.notif_rute_dihapus'));
    }

    private function segarkan(): void
    {
        unset($this->batches, $this->siapRouting, $this->dataPeta);

        $this->dispatch('peta-diperbarui', data: $this->dataPeta);
    }

    public function render()
    {
        return view('livewire.tarik-freezer.routing-tarik-freezer')->title(__('tarik_freezer.judul_routing'));
    }
}
