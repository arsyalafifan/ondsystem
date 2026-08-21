<?php

namespace App\Livewire\Routing;

use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\RoutingBatch;
use App\Models\Wilayah;
use App\Services\RoutingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman yang menggantikan penyusunan rute manual di mapmarker.app.
 *
 * Admin menekan satu tombol, sistem membentuk kendaraan beserta urutan
 * kunjungannya, lalu menampilkannya di peta. Hasilnya masih bisa disunting:
 * memindahkan toko antar mobil, menggeser urutan, atau meminta satu mobil
 * dihitung ulang. Selama belum disetujui, tidak ada yang berubah di sisi
 * pesanan maupun driver.
 */
class GenerateRouting extends Component
{
    public ?int $batchId = null;

    // --- Pengaturan generate ---
    /** @var array<int, int> */
    public array $wilayahDipilih = [];

    public int $maxToko;

    public int $maxDus;

    public bool $pisahPerWilayah = true;

    /** @var array<int, string> */
    public array $peringatan = [];

    /** Kendaraan yang barisnya sedang dibentangkan. */
    public ?int $kendaraanDibuka = null;

    public ?int $stopDipilih = null;

    public bool $konfirmasiHapus = false;

    public function mount(?RoutingBatch $batch = null): void
    {
        $this->maxToko = (int) config('ond.kendaraan.max_toko');
        $this->maxDus = (int) config('ond.kendaraan.max_dus');

        // Batch dari URL, atau draft terakhir yang belum disetujui supaya
        // pekerjaan admin tidak hilang saat halaman ditutup.
        $this->batchId = $batch?->exists
            ? $batch->id
            : RoutingBatch::draft()->latest('id')->value('id');

        $this->peringatan = Session::pull('routing.peringatan', []);
    }

    /**
     * Input angka lewat wire:model.live mengirim string kosong sesaat ketika
     * pengguna menghapus isinya sebelum mengetik angka baru (mis. select-all
     * lalu ketik ulang). Livewire diam-diam gagal menaruh string kosong ke
     * properti bertipe int tanpa nilai bawaan ini, membuatnya "uninitialized"
     * — akses berikutnya ($this->maxToko di ringkasanMenunggu() saat render)
     * melempar PropertyNotFoundException. Hook ini menormalkan nilainya
     * langsung dari $value mentah, bukan dari $this->maxToko, supaya tidak
     * ikut membaca properti yang mungkin sudah rusak itu.
     */
    public function updatedMaxToko(mixed $value): void
    {
        $this->maxToko = max(1, (int) $value);
    }

    public function updatedMaxDus(mixed $value): void
    {
        $this->maxDus = max(1, (int) $value);
    }

    #[Computed]
    public function batch(): ?RoutingBatch
    {
        return $this->batchId === null
            ? null
            : RoutingBatch::with([
                'kendaraans.wilayah:id,nama',
                'kendaraans.stops.toko:id,nama,kode,alamat,latitude,longitude',
                'kendaraans.stops.pesanan:id,kode,total_dus',
                'pembuat:id,name',
                'penyetuju:id,name',
            ])->find($this->batchId);
    }

    /** @return Collection<int, Pesanan> */
    #[Computed]
    public function pesananMenunggu(): Collection
    {
        return app(RoutingService::class)->pesananSiapRouting($this->wilayahDipilih);
    }

    #[Computed]
    public function ringkasanMenunggu(): array
    {
        $pesanan = $this->pesananMenunggu;

        $tanpaKoordinat = $pesanan->filter(fn ($p) => $p->toko->latitude === null)->count();
        $totalDus = (int) $pesanan->sum('total_dus');
        $layak = $pesanan->count() - $tanpaKoordinat;

        return [
            'total' => $pesanan->count(),
            'layak' => $layak,
            'tanpa_koordinat' => $tanpaKoordinat,
            'total_dus' => $totalDus,
            // Perkiraan kasar jumlah mobil, supaya admin tahu skalanya
            // sebelum menekan tombol.
            'perkiraan_kendaraan' => $layak === 0 ? 0 : max(
                (int) ceil($layak / max(1, $this->maxToko)),
                (int) ceil($totalDus / max(1, $this->maxDus)),
            ),
        ];
    }

    #[Computed]
    public function wilayahs(): Collection
    {
        return Wilayah::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Bentuk data yang dibaca peta di sisi browser.
     */
    #[Computed]
    public function dataPeta(): array
    {
        $batch = $this->batch;

        if ($batch === null) {
            // Belum ada rute: yang ditampilkan hanya toko yang menunggu.
            return [
                'kendaraan' => [],
                'belumDirutekan' => $this->pesananMenunggu
                    ->filter(fn ($p) => $p->toko->latitude !== null)
                    ->map(fn ($p) => [
                        'nama' => $p->toko->nama,
                        'lat' => (float) $p->toko->latitude,
                        'lng' => (float) $p->toko->longitude,
                    ])->values()->all(),
            ];
        }

        return [
            'kendaraan' => $batch->kendaraans->map(fn (Kendaraan $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'warna' => $k->warna,
                'geometry' => $k->geometry,
                'stops' => $k->stops->map(fn (KendaraanStop $s) => [
                    'id' => $s->id,
                    'nama' => $s->toko->nama,
                    'urutan' => $s->urutan,
                    'dus' => $s->total_dus,
                    'eta' => $s->eta ? substr((string) $s->eta, 0, 5) : null,
                    'selesai' => $s->status === 'selesai',
                    'lat' => $s->toko->latitude !== null ? (float) $s->toko->latitude : null,
                    'lng' => $s->toko->longitude !== null ? (float) $s->toko->longitude : null,
                ])->values()->all(),
            ])->values()->all(),
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
            'bisaDiklik' => true,
        ];
    }

    // ------------------------------------------------------------------
    // Tindakan
    // ------------------------------------------------------------------

    public function generate(RoutingService $service): void
    {
        try {
            $batch = $service->generate(
                admin: auth()->user(),
                wilayahIds: $this->wilayahDipilih,
                maxToko: $this->maxToko,
                maxDus: $this->maxDus,
                pisahPerWilayah: $this->pisahPerWilayah,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->batchId = $batch->id;
        $this->kendaraanDibuka = $batch->kendaraans->first()?->id;

        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('routing.notif_selesai', [
            'kendaraan' => $batch->total_kendaraan,
            'toko' => $batch->total_toko,
        ]));
    }

    public function setujui(RoutingService $service): void
    {
        try {
            $service->setujui($this->batch, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->segarkan();
        $this->dispatch('notifikasi', pesan: __('routing.notif_disetujui'));
    }

    public function hapusDraft(RoutingService $service): void
    {
        try {
            $service->hapusDraft($this->batch);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->batchId = null;
        $this->konfirmasiHapus = false;
        $this->peringatan = [];

        $this->segarkan();
        $this->dispatch('notifikasi', pesan: __('routing.notif_draft_dihapus'));
    }

    public function pindahStop(int $stopId, int $kendaraanTujuanId, RoutingService $service): void
    {
        $stop = KendaraanStop::with(['kendaraan', 'toko'])->findOrFail($stopId);
        $tujuan = Kendaraan::findOrFail($kendaraanTujuanId);

        $this->pastikanMasihDraft($stop->kendaraan->routing_batch_id);

        try {
            $service->pindahStop($stop, $tujuan);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->stopDipilih = null;
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('routing.notif_dipindah', [
            'toko' => $stop->toko->nama,
            'mobil' => $tujuan->nama,
        ]), jenis: 'info');
    }

    public function geserUrutan(int $stopId, int $arah, RoutingService $service): void
    {
        $stop = KendaraanStop::with('kendaraan')->findOrFail($stopId);
        $this->pastikanMasihDraft($stop->kendaraan->routing_batch_id);

        $urutan = $stop->kendaraan->stops()->orderBy('urutan')->pluck('id')->all();
        $posisi = array_search($stop->id, $urutan, true);
        $posisiBaru = $posisi + $arah;

        if ($posisi === false || $posisiBaru < 0 || $posisiBaru >= count($urutan)) {
            return;
        }

        [$urutan[$posisi], $urutan[$posisiBaru]] = [$urutan[$posisiBaru], $urutan[$posisi]];

        $service->ubahUrutan($stop->kendaraan, $urutan);
        $this->segarkan();
    }

    public function optimalkanUlang(int $kendaraanId, RoutingService $service): void
    {
        $kendaraan = Kendaraan::findOrFail($kendaraanId);
        $this->pastikanMasihDraft($kendaraan->routing_batch_id);

        $sebelum = $kendaraan->total_durasi_s;
        $service->optimalkanUlang($kendaraan);
        $sesudah = $kendaraan->fresh()->total_durasi_s;

        $this->segarkan();

        $selisih = (int) round(($sebelum - $sesudah) / 60);

        $this->dispatch(
            'notifikasi',
            pesan: $selisih > 0
                ? __('routing.notif_dioptimalkan', ['mobil' => $kendaraan->nama, 'menit' => $selisih])
                : __('routing.notif_sudah_terbaik', ['mobil' => $kendaraan->nama]),
            jenis: 'info',
        );
    }

    public function tambahKendaraan(RoutingService $service): void
    {
        $this->pastikanMasihDraft($this->batchId);

        $kendaraan = $service->tambahKendaraan($this->batch);
        $this->kendaraanDibuka = $kendaraan->id;

        $this->segarkan();
        $this->dispatch('notifikasi', pesan: __('routing.notif_mobil_ditambah', ['mobil' => $kendaraan->nama]), jenis: 'info');
    }

    public function hapusKendaraanKosong(int $kendaraanId): void
    {
        $kendaraan = Kendaraan::withCount('stops')->findOrFail($kendaraanId);
        $this->pastikanMasihDraft($kendaraan->routing_batch_id);

        if ($kendaraan->stops_count > 0) {
            $this->dispatch('notifikasi', pesan: __('routing.galat_mobil_berisi'), jenis: 'error');

            return;
        }

        $kendaraan->delete();
        $this->segarkan();
    }

    /** Berkas CSV berisi seluruh rute, untuk dicetak atau diarsipkan. */
    public function unduhCsv(): StreamedResponse
    {
        $batch = $this->batch;
        $namaBerkas = "routing-{$batch->kode}.csv";

        return response()->streamDownload(function () use ($batch): void {
            $keluaran = fopen('php://output', 'w');

            fputcsv($keluaran, [
                __('routing.csv_kendaraan'), __('routing.csv_urutan'), __('routing.csv_kode_toko'),
                __('routing.csv_nama_toko'), __('umum.alamat'), __('umum.wilayah'),
                __('umum.dus'), __('umum.latitude'), __('umum.longitude'),
                __('routing.csv_jarak'), __('routing.csv_eta'),
            ]);

            foreach ($batch->kendaraans as $kendaraan) {
                foreach ($kendaraan->stops as $stop) {
                    fputcsv($keluaran, [
                        $kendaraan->nama,
                        $stop->urutan,
                        $stop->toko->kode,
                        $stop->toko->nama,
                        $stop->toko->alamat,
                        $kendaraan->wilayah?->nama ?? '-',
                        $stop->total_dus,
                        $stop->toko->latitude,
                        $stop->toko->longitude,
                        number_format($stop->jarak_dari_sebelumnya_m / 1000, 2, '.', ''),
                        $stop->eta ? substr((string) $stop->eta, 0, 5) : '',
                    ]);
                }
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv']);
    }

    private function pastikanMasihDraft(?int $batchId): void
    {
        $batch = $batchId === null ? null : RoutingBatch::find($batchId);

        if ($batch === null || ! $batch->isDraft()) {
            abort(422, __('routing.galat_sudah_disetujui'));
        }
    }

    /** Membuang cache computed lalu mengirim data baru ke peta. */
    private function segarkan(): void
    {
        unset($this->batch, $this->dataPeta, $this->pesananMenunggu, $this->ringkasanMenunggu);

        $this->dispatch('peta-diperbarui', data: $this->dataPeta);
    }

    public function render()
    {
        return view('livewire.routing.generate-routing')->title(__('routing.judul'));
    }
}
