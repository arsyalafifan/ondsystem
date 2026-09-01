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
use App\Support\KmlRuteBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Layar kerja driver di lapangan: daftar toko sesuai urutan kunjungan,
 * tombol navigasi ke peta ponsel, dan pengunggahan foto nota.
 *
 * Foto nota adalah bukti serah terima, tapi mengunggahnya tidak pernah
 * langsung menuntaskan pesanan begitu saja: driver wajib mencentang/mengisi
 * dulu jumlah dus yang BENAR-BENAR diambil toko untuk tiap produk (bawaannya
 * jumlah pesanan penuh, tinggal dikurangi kalau ada yang tidak diambil).
 * Ini yang mencegah kasus "toko cuma ambil sebagian tapi driver lupa
 * mengoreksi, jadi seluruhnya tercatat terjual" — sisa yang tidak diambil
 * otomatis jadi jatah kampas, bukan hilang begitu saja dari catatan.
 */
class DaftarKunjungan extends Component
{
    use WithFileUploads;

    public Kendaraan $kendaraan;

    public $fotoNota;

    public string $catatanDriver = '';

    // --- Pembatalan toko di lapangan ---
    public ?int $stopDibatalkan = null;

    public string $alasanBatal = '';

    public string $catatanBatal = '';

    // --- Konfirmasi penerimaan & unggah nota ---
    public ?int $stopKonfirmasi = null;

    /** @var array<int, int|string> jumlah yang BENAR-BENAR diambil toko, dikunci pada id item pesanan */
    public array $jumlahKonfirmasi = [];

    /**
     * Ceklis "sudah saya periksa" per baris produk, dikunci pada id item
     * pesanan. Ini penjaga di sisi tampilan saja — memaksa driver benar-benar
     * melihat tiap baris satu per satu, bukan sekadar membiarkan angka
     * bawaan lewat begitu saja tanpa dilihat.
     *
     * @var array<int, bool>
     */
    public array $dicekKonfirmasi = [];

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

    // --- Selesaikan kendaraan (admin) ---
    public bool $konfirmasiSelesaikanKendaraan = false;

    public function mount(Kendaraan $kendaraan): void
    {
        // Driver hanya boleh membuka mobil yang dia ambil sendiri. Admin
        // dan superadmin boleh membuka kendaraan siapa pun — tapi hanya
        // untuk memantau, lihat pastikanBisaBertindak() di bawah yang
        // mengunci semua tindakan driver untuk peran selain driver.
        if (auth()->user()->isDriver()
            && $kendaraan->driver_id !== null
            && $kendaraan->driver_id !== auth()->id()) {
            abort(403, __('driver.mobil_dibawa_lain'));
        }

        $this->kendaraan = $kendaraan;
    }

    /** Layar ini bisa dilihat admin/superadmin, tapi tindakan driver bukan urusan mereka. */
    #[Computed]
    public function melihatSebagaiAdmin(): bool
    {
        return ! auth()->user()->isDriver();
    }

    /**
     * Menjaga semua tindakan driver (batal, unggah nota, kampas) supaya
     * tidak bisa dipicu peran lain — admin/superadmin cuma boleh memantau
     * dan menjalankan selesaikanKendaraan(). Dipanggil di awal tiap method
     * yang mengubah data, bukan cuma disembunyikan di tampilan: tombol yang
     * disembunyikan tetap bisa dipicu langsung lewat panggilan komponen.
     */
    private function pastikanBisaBertindak(): void
    {
        abort_unless(auth()->user()->isDriver(), 403);
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
    public function stopKonfirmasiModel(): ?KendaraanStop
    {
        return $this->stopKonfirmasi === null
            ? null
            : KendaraanStop::with('pesanan.items.produk:id,nama', 'toko:id,nama')->find($this->stopKonfirmasi);
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

    /**
     * Bentuk data yang dibaca peta di sisi browser — satu kendaraan saja
     * (milik driver ini), berbeda dari halaman admin yang menampilkan
     * banyak kendaraan sekaligus.
     */
    #[Computed]
    public function dataPeta(): array
    {
        return [
            'kendaraan' => [[
                'id' => $this->kendaraan->id,
                'nama' => $this->kendaraan->nama,
                'warna' => $this->kendaraan->warna,
                'geometry' => $this->kendaraan->geometry,
                'stops' => $this->stops->map(fn (KendaraanStop $s) => [
                    'id' => $s->id,
                    'nama' => $s->toko->nama,
                    'urutan' => $s->urutan,
                    'dus' => $s->total_dus,
                    'eta' => $s->eta ? substr((string) $s->eta, 0, 5) : null,
                    // Centang cuma untuk yang benar-benar terkirim — dibatalkan
                    // tetap dapat warna sendiri (warnaStatus) tapi bukan
                    // centang, supaya tidak terlihat seperti terkirim sukses.
                    'selesai' => $s->status === StatusStop::Selesai,
                    'warnaStatus' => $s->status->warna(),
                    'lat' => $s->toko->latitude !== null ? (float) $s->toko->latitude : null,
                    'lng' => $s->toko->longitude !== null ? (float) $s->toko->longitude : null,
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
            'bisaDiklik' => true,
        ];
    }

    /**
     * Berkas KML berisi seluruh titik toko pada rute ini, diunduh SEBELUM
     * berangkat — supaya kalau driver kehilangan sinyal di jalan dan tidak
     * bisa membuka aplikasi ini, dia tetap bisa melihat titik-titik
     * tujuannya lewat aplikasi peta offline (mis. Map Marker) yang sudah
     * dipasang lebih dulu di ponselnya.
     */
    public function unduhKml(): StreamedResponse
    {
        $isi = KmlRuteBuilder::build($this->kendaraan, $this->stops);
        $namaBerkas = 'Rute-'.str_replace(['/', ' '], '-', $this->kendaraan->nama).'-'.now()->format('Ymd').'.kml';

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, $namaBerkas, ['Content-Type' => 'application/vnd.google-earth.kml+xml']);
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

    // ------------------------------------------------------------------
    // Membatalkan toko
    // ------------------------------------------------------------------

    public function bukaBatal(int $stopId): void
    {
        $this->pastikanBisaBertindak();

        $this->stopDibatalkan = $stopId;
        $this->alasanBatal = '';
        $this->catatanBatal = '';
        $this->resetValidation();
    }

    public function batalkanToko(PengirimanService $service): void
    {
        $this->pastikanBisaBertindak();

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
    // Konfirmasi penerimaan & unggah nota
    // ------------------------------------------------------------------

    public function bukaKonfirmasi(int $stopId): void
    {
        $this->pastikanBisaBertindak();

        $stop = $this->stopMilikMobil($stopId);
        $stop->loadMissing('pesanan.items');

        $this->stopKonfirmasi = $stopId;
        $this->fotoNota = null;
        $this->catatanDriver = '';

        // Diisi jumlah pesanan semula, jadi driver tinggal mengurangi baris
        // yang memang tidak jadi diambil toko. Kalau semuanya dibiarkan apa
        // adanya, hasilnya sama dengan pengiriman penuh — tidak ada langkah
        // terpisah lagi untuk itu.
        $this->jumlahKonfirmasi = $stop->pesanan->items
            ->mapWithKeys(fn ($item) => [$item->id => $item->jumlah_dus])
            ->all();

        // Ceklisnya selalu kosong setiap kali modal dibuka — supaya driver
        // wajib melihat ulang tiap baris, bukan sekadar mewarisi ceklis dari
        // toko sebelumnya.
        $this->dicekKonfirmasi = $stop->pesanan->items
            ->mapWithKeys(fn ($item) => [$item->id => false])
            ->all();

        $this->resetValidation();
    }

    public function tutupKonfirmasi(): void
    {
        $this->reset(['stopKonfirmasi', 'jumlahKonfirmasi', 'dicekKonfirmasi', 'fotoNota', 'catatanDriver']);
    }

    #[Computed]
    public function totalKonfirmasi(): int
    {
        return (int) array_sum(array_map('intval', $this->jumlahKonfirmasi));
    }

    /** Benar hanya kalau setiap baris produk sudah dicentang driver. */
    #[Computed]
    public function semuaTercekKonfirmasi(): bool
    {
        return $this->dicekKonfirmasi !== []
            && ! in_array(false, $this->dicekKonfirmasi, true);
    }

    /**
     * Menyimpan konfirmasi penerimaan sekaligus mengunggah nota.
     *
     * Kalau seluruh jumlah dibiarkan penuh (sama seperti pesanan semula),
     * ini setara pengiriman penuh biasa. Begitu ada satu saja yang dikurangi,
     * jalurnya sama dengan "coret nota": yang diterima dicatat apa adanya,
     * sisanya jadi jatah kampas — pesanan tidak pernah dianggap terjual penuh
     * hanya karena notanya terunggah.
     */
    public function simpanKonfirmasi(PesananService $pesananService, PengirimanService $pengirimanService): void
    {
        $this->pastikanBisaBertindak();

        if (! $this->semuaTercekKonfirmasi) {
            $this->dispatch('notifikasi', pesan: __('driver.galat_belum_tercek'), jenis: 'error');

            return;
        }

        $this->validate([
            'fotoNota' => 'required|image|max:5120',
        ], [
            'fotoNota.required' => __('driver.foto_wajib'),
            'fotoNota.image' => __('driver.foto_harus_gambar'),
            'fotoNota.max' => __('driver.foto_maks'),
        ]);

        $stop = $this->stopMilikMobil($this->stopKonfirmasi);
        $jumlah = array_map('intval', $this->jumlahKonfirmasi);
        $path = $this->fotoNota->store($this->folderNota(), 'public');

        try {
            if (array_sum($jumlah) === $stop->total_dus) {
                $pesananService->selesaikanPengiriman($stop, $path, auth()->user(), $this->catatanDriver ?: null);
            } else {
                $pengirimanService->coretNota(
                    stop: $stop,
                    jumlahTerkirim: $jumlah,
                    pathFotoNota: $path,
                    driver: auth()->user(),
                    catatan: $this->catatanDriver ?: null,
                );
            }
        } catch (RuntimeException $e) {
            Storage::disk('public')->delete($path);
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $nama = $stop->toko->nama;
        $kode = $stop->pesanan->kode;
        $this->tutupKonfirmasi();
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('driver.notif_selesai', ['toko' => $nama, 'kode' => $kode]));
    }

    // ------------------------------------------------------------------
    // Kampas
    // ------------------------------------------------------------------

    public function bukaKampas(): void
    {
        $this->pastikanBisaBertindak();

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

        if ($toko->wilayah_id === null) {
            $this->tolakPindaian(__('pesanan.galat_toko_tanpa_wilayah', ['nama' => $toko->nama]));

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
        $this->pastikanBisaBertindak();

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
    // Selesaikan kendaraan (admin/superadmin)
    // ------------------------------------------------------------------

    public function bukaKonfirmasiSelesaikanKendaraan(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->konfirmasiSelesaikanKendaraan = true;
    }

    /**
     * Satu-satunya tindakan yang boleh dilakukan admin/superadmin di
     * layar ini: mengembalikan sisa kampas yang belum diampaskan driver
     * ke stok gudang, dipakai kalau driver sudah tidak akan menghabiskan
     * sisa muatannya lagi hari itu.
     */
    public function selesaikanKendaraan(PengirimanService $service): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $dus = $this->totalJatahKampas;

        try {
            $service->selesaikanKendaraan($this->kendaraan, auth()->user());
        } catch (RuntimeException $e) {
            $this->konfirmasiSelesaikanKendaraan = false;
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->konfirmasiSelesaikanKendaraan = false;
        $this->segarkan();

        $this->dispatch('notifikasi', pesan: __('pengiriman.notif_selesai_kendaraan', [
            'dus' => Bahasa::angka($dus),
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
            $this->stops, $this->progres, $this->berikutnya, $this->dataPeta,
            $this->jatahKampas, $this->totalJatahKampas, $this->kampasMelebihiJatah, $this->stopKonfirmasiModel,
        );

        $this->dispatch('peta-diperbarui', data: $this->dataPeta);
    }

    public function render()
    {
        return view('livewire.driver.daftar-kunjungan')->title(__('driver.judul_kunjungan'));
    }
}
