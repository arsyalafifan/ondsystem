<?php

namespace App\Livewire\Driver;

use App\Enums\StatusStop;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Toko;
use App\Services\Kunjungan\PenguraiQr;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Support\Bahasa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Layar kerja driver di lapangan: daftar toko sesuai urutan kunjungan,
 * tombol navigasi ke peta ponsel, dan pengunggahan foto nota.
 *
 * Foto nota adalah bukti serah terima. Begitu terunggah, pesanan otomatis
 * berubah menjadi SELESAI dan stok gudang dipotong.
 */
class DaftarKunjungan extends Component
{
    use WithFileUploads;

    public Kendaraan $kendaraan;

    /** Kunjungan yang sedang diunggah notanya. */
    public ?int $stopAktif = null;

    public $fotoNota;

    public string $catatanDriver = '';

    // --- Pembatalan toko di lapangan ---
    public ?int $stopDibatalkan = null;

    public string $alasanBatal = '';

    public string $catatanBatal = '';

    // --- Coret nota ---
    public ?int $stopDicoret = null;

    /** @var array<int, int|string> jumlah diterima, dikunci pada id item pesanan */
    public array $jumlahCoret = [];

    // --- Kampas ---
    public bool $kampasTerbuka = false;

    public string $caraPilihToko = 'ketik';

    public string $cariToko = '';

    public ?int $tokoKampasId = null;

    /** @var array<int, int|string> jumlah dus, dikunci pada id produk */
    public array $jumlahKampas = [];

    /** @var array<int, int> jatah produk yang sempat dilampaui, dikunci pada id produk */
    public array $lewatJatah = [];

    public string $catatanKampas = '';

    public function mount(Kendaraan $kendaraan): void
    {
        // Driver hanya boleh membuka mobil yang dia ambil sendiri.
        if ($kendaraan->driver_id !== null && $kendaraan->driver_id !== auth()->id()) {
            abort(403, __('driver.mobil_dibawa_lain'));
        }

        $this->kendaraan = $kendaraan;
    }

    #[Computed]
    public function stops()
    {
        return $this->kendaraan->stops()
            ->with(['toko:id,nama,kode,alamat,telepon,latitude,longitude', 'pesanan:id,kode,catatan,total_dus'])
            ->with('pesanan.items.produk:id,nama')
            ->orderBy('urutan')
            ->get();
    }

    #[Computed]
    public function berikutnya(): ?KendaraanStop
    {
        return $this->stops->firstWhere('status', StatusStop::Pending);
    }

    /** Alasan pembatalan, sama dengan yang dipakai admin. */
    #[Computed]
    public function daftarAlasan(): array
    {
        return array_map(fn (string $kunci) => __('pesanan.'.$kunci), [
            'alasan_toko_tutup',
            'alasan_toko_batal',
            'alasan_stok',
            'alasan_salah_input',
            'alasan_alamat',
            'alasan_pembayaran',
            'alasan_lainnya',
        ]);
    }

    /** Sisa muatan yang boleh diampaskan, dirinci per produk. */
    #[Computed]
    public function jatahKampas(): Collection
    {
        return app(PengirimanService::class)->jatahKampas($this->kendaraan);
    }

    #[Computed]
    public function totalJatahKampas(): int
    {
        return (int) $this->jatahKampas->sum('tersedia');
    }

    #[Computed]
    public function stopDicoretModel(): ?KendaraanStop
    {
        return $this->stopDicoret === null
            ? null
            : KendaraanStop::with('pesanan.items.produk:id,nama', 'toko:id,nama')->find($this->stopDicoret);
    }

    #[Computed]
    public function tokoKampas(): ?Toko
    {
        return $this->tokoKampasId === null ? null : Toko::with('wilayah:id,nama')->find($this->tokoKampasId);
    }

    /**
     * Pencarian toko untuk kampas.
     *
     * Berbeda dari input pesanan biasa, toko yang masih punya pesanan berjalan
     * tetap boleh dipilih: kampas adalah penjualan terpisah atas barang yang
     * sudah ada di mobil, bukan pesanan baru yang menunggu dikirim.
     *
     * @return Collection<int, Toko>
     */
    #[Computed]
    public function hasilCariToko(): Collection
    {
        if (mb_strlen(trim($this->cariToko)) < 2) {
            return collect();
        }

        $kata = trim($this->cariToko);
        $aset = mb_strtoupper(preg_replace('/\s+/', '', $kata) ?? '');

        return Toko::query()
            ->aktif()
            ->with('wilayah:id,nama')
            ->where(fn ($q) => $q
                ->where('nama', 'like', "%{$kata}%")
                ->orWhere('kode', 'like', "%{$kata}%")
                ->orWhere('alamat', 'like', "%{$kata}%")
                ->orWhere('asset_id', 'like', "%{$aset}%"))
            ->orderByRaw('CASE WHEN asset_id = ? THEN 0 ELSE 1 END', [$aset])
            ->orderBy('nama')
            ->limit(12)
            ->get();
    }

    #[Computed]
    public function progres(): array
    {
        $stops = $this->stops;
        $tuntas = $stops->filter(fn (KendaraanStop $s) => $s->status->tuntas())->count();
        $target = $this->kendaraan->target_dus > 0
            ? $this->kendaraan->target_dus
            : $this->kendaraan->total_dus;
        $terkirim = (int) $stops->sum('total_dus_terkirim');

        return [
            'total' => $stops->count(),
            'selesai' => $tuntas,
            'belum' => $stops->count() - $tuntas,
            'dibatalkan' => $stops->where('status', StatusStop::Dibatalkan)->count(),
            'target_dus' => $target,
            'dus_terkirim' => $terkirim,
            // Sisa muatan yang masih benar-benar di mobil: yang tidak jadi
            // diterima toko, dikurangi yang sudah diampaskan ke toko lain.
            'dus_sisa' => max(0,
                (int) $stops->filter(fn (KendaraanStop $s) => ! $s->isKampas())
                    ->sum(fn (KendaraanStop $s) => $s->dus_tersisa)
                - (int) $stops->filter(fn (KendaraanStop $s) => $s->isKampas())
                    ->sum('total_dus_terkirim')
            ),
            // Kemajuan diukur dari dus yang keluar dari mobil, bukan dari
            // jumlah toko: satu toko besar tidak setara satu toko kecil.
            'persen' => $target === 0 ? 0 : (int) min(100, round($terkirim / $target * 100)),
        ];
    }

    public function bukaUnggah(int $stopId): void
    {
        $this->stopAktif = $stopId;
        $this->fotoNota = null;
        $this->catatanDriver = '';
        $this->resetValidation();
    }

    public function tutupUnggah(): void
    {
        $this->reset(['stopAktif', 'fotoNota', 'catatanDriver']);
    }

    public function kirimNota(PesananService $service): void
    {
        $this->validate([
            'fotoNota' => 'required|image|max:5120',
        ], [
            'fotoNota.required' => __('driver.foto_wajib'),
            'fotoNota.image' => __('driver.foto_harus_gambar'),
            'fotoNota.max' => __('driver.foto_maks'),
        ]);

        $stop = KendaraanStop::with('pesanan', 'toko')->findOrFail($this->stopAktif);

        if ($stop->kendaraan_id !== $this->kendaraan->id) {
            abort(403);
        }

        $path = $this->fotoNota->store("nota/{$this->kendaraan->batch->tanggal->format('Y-m-d')}", 'public');

        try {
            $service->selesaikanPengiriman($stop, $path, auth()->user(), $this->catatanDriver ?: null);
        } catch (RuntimeException $e) {
            // Foto yang terlanjur tersimpan dibuang agar tidak menumpuk.
            Storage::disk('public')->delete($path);

            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->tutupUnggah();
        $this->kendaraan->refresh();
        unset($this->stops, $this->progres, $this->berikutnya);

        $this->dispatch('notifikasi', pesan: __('driver.notif_selesai', [
            'toko' => $stop->toko->nama,
            'kode' => $stop->pesanan->kode,
        ]));
    }

    // ------------------------------------------------------------------
    // Membatalkan toko
    // ------------------------------------------------------------------

    public function bukaBatal(int $stopId): void
    {
        $this->stopDibatalkan = $stopId;
        $this->alasanBatal = '';
        $this->catatanBatal = '';
        $this->resetValidation();
    }

    public function batalkanToko(PengirimanService $service): void
    {
        $this->validate(
            ['alasanBatal' => 'required|string'],
            ['alasanBatal.required' => __('pesanan.alasan_wajib')],
        );

        $stop = $this->stopMilikMobil($this->stopDibatalkan);

        try {
            $service->batalkanDiLapangan($stop, auth()->user(), $this->alasanBatal, $this->catatanBatal ?: null);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->stopDibatalkan = null;
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('pengiriman.notif_dibatalkan', [
            'toko' => $stop->toko->nama,
            'dus' => $stop->total_dus,
        ]), jenis: 'info');
    }

    // ------------------------------------------------------------------
    // Coret nota
    // ------------------------------------------------------------------

    public function bukaCoret(int $stopId): void
    {
        $stop = $this->stopMilikMobil($stopId);
        $stop->loadMissing('pesanan.items');

        $this->stopDicoret = $stopId;
        $this->fotoNota = null;
        $this->catatanDriver = '';

        // Diisi jumlah pesanan semula, jadi driver tinggal mengurangi baris
        // yang memang tidak jadi diterima toko.
        $this->jumlahCoret = $stop->pesanan->items
            ->mapWithKeys(fn ($item) => [$item->id => $item->jumlah_dus])
            ->all();

        $this->resetValidation();
    }

    public function tutupCoret(): void
    {
        $this->reset(['stopDicoret', 'jumlahCoret', 'fotoNota', 'catatanDriver']);
    }

    #[Computed]
    public function totalCoret(): int
    {
        return (int) array_sum(array_map('intval', $this->jumlahCoret));
    }

    public function simpanCoret(PengirimanService $service): void
    {
        $this->validate([
            'fotoNota' => 'required|image|max:5120',
        ], [
            'fotoNota.required' => __('driver.foto_wajib'),
            'fotoNota.image' => __('driver.foto_harus_gambar'),
            'fotoNota.max' => __('driver.foto_maks'),
        ]);

        $stop = $this->stopMilikMobil($this->stopDicoret);
        $path = $this->fotoNota->store($this->folderNota(), 'public');

        try {
            $service->coretNota(
                stop: $stop,
                jumlahTerkirim: array_map('intval', $this->jumlahCoret),
                pathFotoNota: $path,
                driver: auth()->user(),
                catatan: $this->catatanDriver ?: null,
            );
        } catch (RuntimeException $e) {
            Storage::disk('public')->delete($path);
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $nama = $stop->toko->nama;
        $this->tutupCoret();
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('pengiriman.notif_coret', ['toko' => $nama]));
    }

    // ------------------------------------------------------------------
    // Kampas
    // ------------------------------------------------------------------

    public function bukaKampas(): void
    {
        $this->kampasTerbuka = true;
        $this->reset(['tokoKampasId', 'cariToko', 'jumlahKampas', 'lewatJatah', 'catatanKampas', 'fotoNota']);
        $this->caraPilihToko = 'ketik';
        $this->resetValidation();
    }

    public function tutupKampas(): void
    {
        $this->reset(['kampasTerbuka', 'tokoKampasId', 'cariToko', 'jumlahKampas', 'lewatJatah', 'catatanKampas', 'fotoNota']);
    }

    public function gantiCaraPilih(string $cara): void
    {
        $this->caraPilihToko = in_array($cara, ['ketik', 'pindai'], true) ? $cara : 'ketik';
        $this->cariToko = '';
    }

    public function pilihTokoKampas(int $tokoId): void
    {
        $this->tokoKampasId = $tokoId;
        $this->cariToko = '';
    }

    public function batalPilihTokoKampas(): void
    {
        $this->tokoKampasId = null;
    }

    public function pilihTokoKampasDariQr(string $isi, PenguraiQr $pengurai): void
    {
        $hasil = $pengurai->urai($isi);

        if ($hasil === null) {
            $this->tolakPindaian(__('kunjungan.galat_qr_tidak_terbaca'));

            return;
        }

        $toko = Toko::where('asset_id', $hasil->assetId)->first();

        if ($toko === null) {
            $this->tolakPindaian(__('kunjungan.galat_aset_tidak_dikenal', ['aset' => $hasil->assetId]));

            return;
        }

        if (! $toko->aktif) {
            $this->tolakPindaian(__('pesanan.galat_toko_nonaktif', ['nama' => $toko->nama]));

            return;
        }

        $this->pilihTokoKampas($toko->id);

        $this->dispatch('notifikasi', pesan: __('pesanan.notif_toko_dari_qr', [
            'nama' => $toko->nama,
            'aset' => $toko->asset_id,
        ]));
    }

    private function tolakPindaian(string $pesan): void
    {
        $this->dispatch('notifikasi', pesan: $pesan, jenis: 'error');
        $this->dispatch('qr-toko-ditolak');
    }

    /**
     * Menjaga isian tetap di dalam jatah produknya.
     *
     * Atribut `max` pada input angka hanya menahan saat form disubmit,
     * sedangkan simpan kampas dipanggil lewat wire:click — tanpa penjaga ini
     * driver baru tahu kelebihan setelah menekan simpan dan mengunggah nota.
     */
    public function updatedJumlahKampas(mixed $nilai, string $kunci): void
    {
        $produkId = (int) $kunci;
        $diminta = max(0, (int) $nilai);

        $tersedia = (int) ($this->jatahKampas
            ->first(fn (array $b) => $b['produk']->id === $produkId)['tersedia'] ?? 0);

        if ($diminta <= $tersedia) {
            unset($this->lewatJatah[$produkId]);
            $this->jumlahKampas[$produkId] = $diminta;

            return;
        }

        // Dipotong ke jatahnya, lalu dikatakan terus terang — angka yang
        // diam-diam berubah lebih membingungkan daripada penolakan.
        $this->jumlahKampas[$produkId] = $tersedia;
        $this->lewatJatah[$produkId] = $tersedia;

        $this->dispatch(
            'notifikasi',
            pesan: __('pengiriman.lewat_jatah', [
                'produk' => $this->jatahKampas->first(fn (array $b) => $b['produk']->id === $produkId)['produk']->nama ?? '',
                'jumlah' => Bahasa::angka($tersedia),
            ]),
            jenis: 'error',
        );
    }

    #[Computed]
    public function totalKampas(): int
    {
        return (int) array_sum(array_map('intval', $this->jumlahKampas));
    }

    /** Benar bila ada isian yang melebihi jatah produknya. */
    #[Computed]
    public function kampasMelebihiJatah(): bool
    {
        return $this->jatahKampas->contains(
            fn (array $b) => (int) ($this->jumlahKampas[$b['produk']->id] ?? 0) > $b['tersedia'],
        );
    }

    public function simpanKampas(PengirimanService $service): void
    {
        if ($this->tokoKampasId === null) {
            $this->dispatch('notifikasi', pesan: __('pesanan.pilih_toko_dulu'), jenis: 'error');

            return;
        }

        if ($this->kampasMelebihiJatah) {
            $this->dispatch('notifikasi', pesan: __('pengiriman.ada_lewat_jatah'), jenis: 'error');

            return;
        }

        $this->validate([
            'fotoNota' => 'required|image|max:5120',
        ], [
            'fotoNota.required' => __('driver.foto_wajib'),
            'fotoNota.image' => __('driver.foto_harus_gambar'),
            'fotoNota.max' => __('driver.foto_maks'),
        ]);

        $path = $this->fotoNota->store($this->folderNota(), 'public');

        try {
            $pesanan = $service->kampas(
                kendaraan: $this->kendaraan,
                toko: Toko::findOrFail($this->tokoKampasId),
                items: array_map('intval', $this->jumlahKampas),
                pathFotoNota: $path,
                driver: auth()->user(),
                catatan: $this->catatanKampas ?: null,
            );
        } catch (RuntimeException $e) {
            Storage::disk('public')->delete($path);
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $nama = $this->tokoKampas->nama;
        $this->tutupKampas();
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('pengiriman.notif_kampas', [
            'toko' => $nama,
            'dus' => $pesanan->total_dus,
        ]));
    }

    // ------------------------------------------------------------------

    /** Memastikan kunjungan memang milik mobil yang sedang dibawa driver ini. */
    private function stopMilikMobil(?int $stopId): KendaraanStop
    {
        $stop = KendaraanStop::with(['pesanan.items.produk', 'toko'])->findOrFail($stopId);

        abort_unless($stop->kendaraan_id === $this->kendaraan->id, 403);

        return $stop;
    }

    private function folderNota(): string
    {
        return 'nota/'.$this->kendaraan->batch->tanggal->format('Y-m-d');
    }

    private function segarkan(): void
    {
        $this->kendaraan->refresh();

        unset(
            $this->stops, $this->progres, $this->berikutnya,
            $this->jatahKampas, $this->totalJatahKampas, $this->kampasMelebihiJatah, $this->stopDicoretModel,
        );
    }

    public function render()
    {
        return view('livewire.driver.daftar-kunjungan')->title(__('driver.judul_kunjungan'));
    }
}
