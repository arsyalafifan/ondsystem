<?php

namespace App\Livewire\Noo;

use App\Enums\StatusStop;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Noo;
use App\Models\RoutingBatch;
use App\Models\User;
use App\Services\RoutingService;
use App\Support\DepotContext;
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
 * Tanggal keberangkatan SENGAJA tidak berlaku untuk seluruh antrean
 * sekaligus: admin memilih toko mana yang dirutekan (checklist di
 * `$terpilih`), lalu menyusun satu batch untuk satu tanggal. Toko yang
 * belum dipilih tetap menunggu di daftar dan bisa dirutekan lagi nanti
 * dengan tanggal lain — persis kebutuhan lapangan, toko satu dan toko dua
 * boleh berangkat hari berbeda, boleh juga hari yang sama.
 *
 * Karena itu pula layar ini menampilkan SEMUA batch yang belum tuntas
 * (draft maupun yang sudah disetujui), bukan cuma yang terakhir dibuat —
 * beberapa rute dengan tanggal berbeda bisa berjalan berdampingan.
 *
 * Layarnya dibuat seperlunya saja (susun, tentukan driver, setujui) —
 * penyuntingan halus seperti pindah stop antar mobil belum disediakan karena
 * satu rute freezer isinya sedikit; kalau salah, buang drafnya dan susun ulang.
 */
class RoutingFreezer extends Component
{
    use MembutuhkanDepotTerkunci;

    public string $tanggalKeberangkatan = '';

    /** Id NOO yang dicentang untuk disusun jadi satu rute berikutnya. @var array<int, int> */
    public array $terpilih = [];

    public bool $pilihSemua = false;

    /** @var array<int, string> */
    public array $peringatan = [];

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

    /** @return Collection<int, Noo> */
    #[Computed]
    public function siapRouting(): Collection
    {
        return app(RoutingService::class)->nooSiapRouting();
    }

    /**
     * Semua batch freezer yang belum tuntas — draft (masih perlu driver +
     * persetujuan) maupun yang sudah disetujui (sedang berjalan). Jamak,
     * bukan tunggal: itulah yang membuat tanggal keberangkatan per toko
     * bisa berbeda-beda, lihat docblock kelas ini.
     *
     * limit(20) sekadar jaga-jaga: volume rute freezer jauh lebih kecil
     * dari rute pesanan (satu NOO baru = satu freezer), jadi tidak perlu
     * paginasi — tapi tetap dibatasi supaya layar ini tidak diam-diam
     * tumbuh tak terbatas kalau suatu saat ada batch yang tidak pernah
     * dibereskan.
     *
     * @return Collection<int, RoutingBatch>
     */
    #[Computed]
    public function batches(): Collection
    {
        return RoutingBatch::noo()
            ->with([
                'kendaraans.wilayah:id,nama',
                'kendaraans.driver:id,name',
                'kendaraans.stops.toko:id,nama,kode,alamat',
                'kendaraans.stops.noo:id,kode,nama,alamat,nama_pemilik,freezer_tipe,latitude,longitude',
                'pembuat:id,name',
                'penyetuju:id,name',
            ])
            ->latest('id')
            ->limit(20)
            ->get();
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

    /**
     * Bentuk data yang dibaca peta di sisi browser — sama persis dengan
     * App\Livewire\Routing\GenerateRouting::dataPeta(), bedanya titiknya
     * datang dari NOO (lewat `stop->noo`), bukan pesanan.
     */
    #[Computed]
    public function dataPeta(): array
    {
        // Titik yang belum dirutekan SELALU ikut tergambar, terlepas ada
        // atau tidaknya batch — supaya peta juga berguna sebagai gambaran
        // "siapa saja yang masih menunggu" saat memilih toko mana yang mau
        // disusun untuk tanggal ini.
        $belumDirutekan = $this->siapRouting
            ->filter(fn (Noo $n) => $n->latitude !== null)
            ->map(fn (Noo $n) => [
                'nama' => $n->nama,
                'lat' => $n->latitude,
                'lng' => $n->longitude,
            ])->values()->all();

        return [
            // Kendaraan dari SELURUH batch digabung jadi satu daftar — tiap
            // batch (tanggal) tetap dibedakan lewat id & nama kendaraannya
            // sendiri di tooltip, meski warnanya bisa kebetulan sama antar
            // batch (satu batch biasanya cuma berisi 1-2 mobil).
            'kendaraan' => $this->batches->flatMap(fn (RoutingBatch $b) => $b->kendaraans->map(fn (Kendaraan $k) => [
                'id' => $k->id,
                'nama' => $k->nama.' · '.$b->kode,
                'warna' => $k->warna,
                'geometry' => $k->geometry,
                'stops' => $k->stops->map(fn (KendaraanStop $s) => [
                    'id' => $s->id,
                    'nama' => $s->noo?->nama,
                    'urutan' => $s->urutan,
                    'dus' => 1,
                    'eta' => $s->eta ? substr((string) $s->eta, 0, 5) : null,
                    'selesai' => $s->status === StatusStop::Selesai,
                    'warnaStatus' => $s->status->warna(),
                    'lat' => $s->noo?->latitude,
                    'lng' => $s->noo?->longitude,
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
            // Tooltip penanda menyebut "freezer", bukan "dus" — lihat
            // resources/js/peta-rute.js.
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
            $this->dispatch('notifikasi', pesan: __('noo.galat_belum_pilih_calon'), jenis: 'error');

            return;
        }

        try {
            $batch = $service->generateNoo(
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

        $this->segarkan();
    }

    public function setujui(int $batchId, RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $batch = RoutingBatch::noo()->find($batchId);

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

        $this->dispatch('notifikasi', pesan: __('noo.notif_rute_disetujui', ['kode' => $batch->kode]));
    }

    public function hapusDraft(RoutingService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $batch = $this->konfirmasiHapus === null ? null : RoutingBatch::noo()->find($this->konfirmasiHapus);

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

        $this->dispatch('notifikasi', pesan: __('noo.notif_rute_dihapus'));
    }

    /** Membuang cache computed lalu mengirim data baru ke peta. */
    private function segarkan(): void
    {
        unset($this->batches, $this->siapRouting, $this->dataPeta);

        $this->dispatch('peta-diperbarui', data: $this->dataPeta);
    }

    public function render()
    {
        return view('livewire.noo.routing-freezer')->title(__('noo.judul_routing'));
    }
}
