<?php

namespace App\Livewire;

use App\Enums\StatusPesanan;
use App\Models\Kendaraan;
use App\Models\Pesanan;
use App\Models\Toko;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pemantauan operasional harian: berapa pesanan di tiap status, sejauh mana
 * tiap mobil berjalan, dan di mana posisi toko-tokonya di peta.
 */
class Dashboard extends Component
{
    #[Url(as: 'tgl')]
    public string $tanggal = '';

    public ?int $kendaraanDilihat = null;

    public function mount(): void
    {
        if ($this->tanggal === '') {
            $this->tanggal = today()->toDateString();
        }
    }

    /** Ganti tanggal berarti isi peta ikut berganti seluruhnya. */
    public function updatedTanggal(): void
    {
        unset($this->ringkasan, $this->kendaraans, $this->totalHariIni, $this->dataPeta);

        $this->dispatch('peta-diperbarui', data: $this->dataPeta);
    }

    #[Computed]
    public function ringkasan(): array
    {
        $hitung = Pesanan::whereDate('tanggal', $this->tanggal)
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return collect(StatusPesanan::cases())
            ->map(fn (StatusPesanan $s) => [
                'status' => $s,
                'jumlah' => (int) ($hitung[$s->value] ?? 0),
            ])->all();
    }

    /** @return Collection<int, Kendaraan> */
    #[Computed]
    public function kendaraans(): Collection
    {
        return Kendaraan::query()
            ->with(['wilayah:id,nama', 'driver:id,name', 'stops'])
            ->whereHas('batch', fn ($q) => $q
                ->where('status', 'disetujui')
                ->whereDate('tanggal', $this->tanggal))
            ->orderBy('nomor')
            ->get();
    }

    #[Computed]
    public function totalHariIni(): array
    {
        $kendaraans = $this->kendaraans;
        $totalToko = (int) $kendaraans->sum('total_toko');
        $selesai = $kendaraans->sum(fn (Kendaraan $k) => $k->total_selesai);

        return [
            'kendaraan' => $kendaraans->count(),
            'toko' => $totalToko,
            'selesai' => $selesai,
            'belum' => $totalToko - $selesai,
            'dus' => (int) $kendaraans->sum('total_dus'),
            'jarak_km' => round($kendaraans->sum('total_jarak_m') / 1000, 1),
            'persen' => $totalToko === 0 ? 0 : (int) round($selesai / $totalToko * 100),
        ];
    }

    #[Computed]
    public function detailKendaraan(): ?Kendaraan
    {
        return $this->kendaraanDilihat === null
            ? null
            : Kendaraan::with(['stops.toko:id,nama,kode,alamat', 'stops.pesanan:id,kode', 'wilayah:id,nama', 'driver:id,name'])
                ->find($this->kendaraanDilihat);
    }

    /**
     * Penanda peta diwarnai menurut status pesanannya, bukan menurut mobil,
     * supaya admin langsung melihat mana yang sudah beres dan mana yang belum.
     */
    #[Computed]
    public function dataPeta(): array
    {
        $pesanans = Pesanan::query()
            ->whereDate('tanggal', $this->tanggal)
            ->with(['toko:id,nama,latitude,longitude', 'stop.kendaraan:id,nomor,nama'])
            ->whereHas('toko', fn ($q) => $q->whereNotNull('latitude'))
            ->get();

        // Semua titik dimasukkan sebagai satu "kendaraan semu" agar peta bisa
        // memakai komponen yang sama dengan halaman routing.
        return [
            'kendaraan' => [[
                'id' => 0,
                'nama' => __('dashboard.pesanan_hari_ini'),
                'warna' => '#6b7280',
                'geometry' => '',
                'stops' => $pesanans->map(fn (Pesanan $p) => [
                    'id' => $p->id,
                    'nama' => $p->toko->nama.' · '.$p->status->label().($p->stop?->kendaraan ? ' · '.$p->stop->kendaraan->nama : ''),
                    'urutan' => $p->stop?->urutan ?? '•',
                    'dus' => $p->total_dus,
                    'eta' => $p->stop?->eta ? substr((string) $p->stop->eta, 0, 5) : null,
                    'selesai' => false,
                    'warnaStatus' => $p->status->warna(),
                    'lat' => (float) $p->toko->latitude,
                    'lng' => (float) $p->toko->longitude,
                ])->values()->all(),
            ]],
            'belumDirutekan' => [],
        ];
    }

    #[Computed]
    public function konfigPeta(): array
    {
        return [
            'tileUrl' => config('ond.peta.tile_url'),
            'attribution' => config('ond.peta.attribution'),
            'zoom' => config('ond.peta.zoom_default'),
            'depot' => [
                'lat' => (float) config('ond.depot.lat'),
                'lng' => (float) config('ond.depot.lng'),
                'nama' => config('ond.depot.nama'),
            ],
            'bisaDiklik' => false,
        ];
    }

    /** Hal-hal yang menghambat operasional dan sebaiknya segera ditangani. */
    #[Computed]
    public function perluTindakan(): array
    {
        return [
            'tanpa_koordinat' => Toko::aktif()->tanpaKoordinat()->count(),
            'menunggu_persetujuan' => Pesanan::status(StatusPesanan::Order)->count(),
            'menunggu_routing' => Pesanan::siapRouting()->count(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard')->title(__('dashboard.judul'));
    }
}
