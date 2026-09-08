<?php

namespace App\Livewire\Pesanan;

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\PenugasanToko;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Support\DepotContext;
use App\Support\ModeDepot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class DaftarPesanan extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'wilayah')]
    public string $filterWilayah = '';

    #[Url(as: 'q')]
    public string $cari = '';

    #[Url(as: 'tgl')]
    public string $filterTanggal = '';

    #[Url(as: 'sales')]
    public string $filterPenginput = '';

    /** @var array<int, int> */
    public array $terpilih = [];

    public bool $pilihSemua = false;

    // --- Jendela pembatalan ---
    public ?int $pesananDibatalkan = null;

    public string $alasanCancel = '';

    public string $catatanCancel = '';

    /** Pesanan yang sedang dilihat rinciannya. */
    public ?int $pesananDilihat = null;

    // --- Order ulang (pesanan yang dibatalkan driver di lapangan) ---
    public ?int $pesananOrderUlang = null;

    /** @var array<int, array{produk_id: int|string, jumlah_dus: int|string}> */
    public array $barisOrderUlang = [];

    public ?int $salesOrderUlang = null;

    public string $catatanOrderUlang = '';

    // --- Toko yang belum pesan 1 bulan ---
    public bool $tokoTidakAktifTerbuka = false;

    public string $filterSalesTidakAktif = '';

    public string $cariTokoTidakAktif = '';

    /**
     * Alasan pembatalan. Disediakan sebagai daftar terjemahan agar admin
     * berbahasa apa pun melihat pilihan yang sama.
     *
     * @return array<int, string>
     */
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

    public function updated(string $kolom): void
    {
        if (str_starts_with($kolom, 'filter') || $kolom === 'cari') {
            $this->resetPage();
            $this->terpilih = [];
            $this->pilihSemua = false;
        }
    }

    public function bersihkanFilter(): void
    {
        $this->reset(['filterStatus', 'filterWilayah', 'cari', 'filterTanggal', 'filterPenginput']);
        $this->resetPage();
    }

    private function dasarKueri(): Builder
    {
        return Pesanan::query()
            ->with([
                'toko:id,nama,kode,alamat,latitude,longitude', 'wilayah:id,nama', 'stop.kendaraan:id,nomor,nama',
                // Keempatnya dimuat di depan untuk kolom "Update By | Date"
                // (Pesanan::pembaruTerakhir()) — mode ketat model melempar
                // galat kalau salah satunya diakses belum termuat.
                'pembuat:id,name', 'pemroses:id,name', 'pembatal:id,name', 'dilunasiOleh:id,name',
            ])
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterWilayah !== '', fn ($q) => $q->where('wilayah_id', $this->filterWilayah))
            ->when($this->filterTanggal !== '', fn ($q) => $q->whereDate('tanggal', $this->filterTanggal))
            ->when($this->filterPenginput !== '', fn ($q) => $q->where('dibuat_oleh', $this->filterPenginput))
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('kode', 'like', "%{$this->cari}%")
                ->orWhereHas('toko', fn ($t) => $t
                    ->where('nama', 'like', "%{$this->cari}%")
                    ->orWhere('kode', 'like', "%{$this->cari}%"))));
    }

    #[Computed]
    public function pesanans()
    {
        // Yang masih perlu tindakan admin ditaruh di atas. Ditulis sebagai
        // CASE, bukan FIELD(), supaya kueri yang sama juga jalan di SQLite
        // yang dipakai saat pengujian.
        return $this->dasarKueri()
            ->orderByRaw("CASE status
                WHEN 'order' THEN 1
                WHEN 'process' THEN 2
                WHEN 'delivery' THEN 3
                WHEN 'selesai' THEN 4
                ELSE 5 END")
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function ringkasan(): array
    {
        $hitung = Pesanan::query()
            ->when($this->filterWilayah !== '', fn ($q) => $q->where('wilayah_id', $this->filterWilayah))
            ->when($this->filterTanggal !== '', fn ($q) => $q->whereDate('tanggal', $this->filterTanggal))
            ->when($this->filterPenginput !== '', fn ($q) => $q->where('dibuat_oleh', $this->filterPenginput))
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return collect(StatusPesanan::cases())
            ->mapWithKeys(fn (StatusPesanan $s) => [$s->value => (int) ($hitung[$s->value] ?? 0)])
            ->all();
    }

    #[Computed]
    public function wilayahs()
    {
        return Wilayah::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Penginput untuk pilihan pada penyaring — diambil dari siapa saja yang
     * PERNAH menginput pesanan (bukan seluruh sales aktif), supaya sales
     * yang belum pernah input tidak memenuhi daftar pilihan.
     */
    #[Computed]
    public function penginputs()
    {
        return User::query()
            ->whereIn('id', Pesanan::query()->select('dibuat_oleh')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function detail(): ?Pesanan
    {
        return $this->pesananDilihat === null
            ? null
            : Pesanan::with(['items.produk:id,nama,kode', 'toko.wilayah:id,nama', 'pembuat:id,name', 'pemroses:id,name', 'pembatal:id,name', 'stop.kendaraan:id,nomor,nama'])
                ->find($this->pesananDilihat);
    }

    /** Id pesanan berstatus ORDER pada halaman ini — hanya itu yang bisa disetujui. */
    #[Computed]
    public function idBisaDisetujui(): array
    {
        return $this->pesanans
            ->filter(fn (Pesanan $p) => $p->status === StatusPesanan::Order)
            ->pluck('id')
            ->all();
    }

    public function updatedPilihSemua(bool $nilai): void
    {
        $this->terpilih = $nilai ? $this->idBisaDisetujui : [];
    }

    public function setujui(int $id, PesananService $service): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        try {
            $service->setujui(Pesanan::findOrFail($id), auth()->user());
            $this->dispatch('notifikasi', pesan: __('pesanan.notif_disetujui'));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }

        unset($this->pesanans, $this->ringkasan);
    }

    public function setujuiTerpilih(PesananService $service): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $berhasil = 0;
        $gagal = 0;

        foreach (Pesanan::whereIn('id', $this->terpilih)->get() as $pesanan) {
            try {
                $service->setujui($pesanan, auth()->user());
                $berhasil++;
            } catch (RuntimeException) {
                $gagal++;
            }
        }

        $this->terpilih = [];
        $this->pilihSemua = false;
        unset($this->pesanans, $this->ringkasan);

        $pesan = __('pesanan.notif_disetujui_massal', ['jumlah' => $berhasil]);

        if ($gagal > 0) {
            $pesan .= ' '.__('pesanan.notif_dilewati', ['jumlah' => $gagal]);
        }

        $this->dispatch('notifikasi', pesan: $pesan, jenis: $gagal > 0 ? 'info' : 'sukses');
    }

    public function bukaPembatalan(int $id): void
    {
        $this->pesananDibatalkan = $id;
        $this->alasanCancel = '';
        $this->catatanCancel = '';
        $this->resetValidation();
    }

    public function batalkan(PesananService $service): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $this->validate(
            ['alasanCancel' => 'required|string'],
            ['alasanCancel.required' => __('pesanan.alasan_wajib')],
        );

        try {
            $service->batalkan(
                pesanan: Pesanan::findOrFail($this->pesananDibatalkan),
                admin: auth()->user(),
                alasan: $this->alasanCancel,
                catatan: $this->catatanCancel ?: null,
            );

            $this->dispatch('notifikasi', pesan: __('pesanan.notif_dibatalkan'));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }

        $this->pesananDibatalkan = null;
        unset($this->pesanans, $this->ringkasan);
    }

    // ------------------------------------------------------------------
    // Order ulang (pesanan yang dibatalkan driver di lapangan)
    // ------------------------------------------------------------------

    #[Computed]
    public function pesananOrderUlangModel(): ?Pesanan
    {
        return $this->pesananOrderUlang === null
            ? null
            : Pesanan::with(['items.produk:id,nama,kode', 'toko', 'pembuat'])->find($this->pesananOrderUlang);
    }

    /** @return Collection<int, Produk> */
    #[Computed]
    public function produkOrderUlang(): Collection
    {
        return Produk::aktif()->orderBy('nama')->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function salesListOrderUlang(): Collection
    {
        return User::sales()->orderBy('name')->get(['id', 'name']);
    }

    public function bukaOrderUlang(int $id): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $pesanan = Pesanan::with(['items', 'pembuat'])->findOrFail($id);

        if (! $pesanan->bisa_order_ulang) {
            $this->dispatch('notifikasi', pesan: __('pesanan.galat_bukan_batal_lapangan'), jenis: 'error');

            return;
        }

        $this->pesananOrderUlang = $id;
        $this->barisOrderUlang = $pesanan->items->map(fn ($i) => [
            'produk_id' => $i->produk_id,
            'jumlah_dus' => $i->jumlah_dus,
        ])->all();
        // Kalau pesanan aslinya diinput sales sendiri, atas nama sales-nya
        // otomatis diwariskan — admin tinggal ganti kalau memang perlu.
        $this->salesOrderUlang = $pesanan->pembuat->role === PeranPengguna::Sales ? $pesanan->pembuat->id : null;
        $this->catatanOrderUlang = __('pesanan.catatan_order_ulang', ['kode' => $pesanan->kode]);
        $this->resetValidation();
    }

    public function tutupOrderUlang(): void
    {
        $this->reset(['pesananOrderUlang', 'barisOrderUlang', 'salesOrderUlang', 'catatanOrderUlang']);
    }

    public function tambahBarisOrderUlang(): void
    {
        $this->barisOrderUlang[] = ['produk_id' => '', 'jumlah_dus' => ''];
    }

    public function hapusBarisOrderUlang(int $indeks): void
    {
        unset($this->barisOrderUlang[$indeks]);
        $this->barisOrderUlang = array_values($this->barisOrderUlang);

        if ($this->barisOrderUlang === []) {
            $this->tambahBarisOrderUlang();
        }
    }

    /**
     * Membuat pesanan baru dengan item yang sama seperti pesanan yang
     * dibatalkan (driver di lapangan MAUPUN admin langsung dari sini) —
     * lewat PesananService::buat() apa adanya, supaya seluruh aturan biasa
     * (stok tersedia, minimal dus, toko tidak lagi punya pesanan aktif
     * lain) tetap berlaku sama persis seperti Input Pesanan. Kalau stoknya
     * kurang, galatnya muncul di modal ini juga — admin tinggal menunggu
     * stok tersedia atau mengubah baris produknya langsung di sini, tanpa
     * perlu pindah layar.
     */
    public function simpanOrderUlang(PesananService $service): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        if (DepotContext::mode() !== ModeDepot::Terkunci) {
            $this->dispatch('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');
            $this->tutupOrderUlang();

            return;
        }

        // Galat dari percobaan SEBELUMNYA (mis. stok kurang) harus bersih
        // dulu — tanpa ini, percobaan yang berhasil setelah baris produknya
        // diperbaiki tetap menampilkan galat lama yang sudah tidak relevan.
        $this->resetValidation();

        $pesananAsli = Pesanan::with(['toko'])->findOrFail($this->pesananOrderUlang);

        if (! $pesananAsli->bisa_order_ulang) {
            $this->dispatch('notifikasi', pesan: __('pesanan.galat_bukan_batal_lapangan'), jenis: 'error');
            $this->tutupOrderUlang();
            unset($this->pesanans, $this->ringkasan);

            return;
        }

        try {
            $baru = $service->buat(
                toko: $pesananAsli->toko,
                items: $this->barisOrderUlang,
                pembuat: auth()->user(),
                catatan: $this->catatanOrderUlang ?: null,
                atasNamaSales: $this->salesOrderUlang !== null ? User::find($this->salesOrderUlang) : null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $kolomTampil = match ($kolom) {
                    'items' => 'barisOrderUlang',
                    'atasNamaSales' => 'salesOrderUlang',
                    default => $kolom,
                };

                $this->addError($kolomTampil, $pesan[0]);
            }

            $this->dispatch('notifikasi', pesan: __('pesanan.ditolak'), jenis: 'error');

            return;
        }

        $this->tutupOrderUlang();
        unset($this->pesanans, $this->ringkasan);

        $this->dispatch('notifikasi', pesan: __('pesanan.notif_order_ulang', ['kode' => $baru->kode]));
    }

    /**
     * Menandai final pesanan yang dibatalkan driver di lapangan sebagai
     * batal karena toko — dipakai admin ketika memutuskan TIDAK akan
     * order ulang lagi. Lihat PesananService::tandaiBatalKarenaToko(): ini
     * cuma mengubah catatan alasannya, TIDAK menyentuh stok sama sekali.
     */
    public function tandaiBatalKarenaToko(int $id, PesananService $service): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        try {
            $service->tandaiBatalKarenaToko(Pesanan::findOrFail($id), auth()->user());
            $this->dispatch('notifikasi', pesan: __('pesanan.notif_batal_final'));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }

        unset($this->pesanans, $this->ringkasan);
    }

    // ------------------------------------------------------------------
    // Toko yang belum pesan 1 bulan
    // ------------------------------------------------------------------

    public function bukaTokoTidakAktif(): void
    {
        $this->tokoTidakAktifTerbuka = true;
    }

    public function tutupTokoTidakAktif(): void
    {
        $this->tokoTidakAktifTerbuka = false;
        $this->reset(['filterSalesTidakAktif', 'cariTokoTidakAktif']);
    }

    /**
     * Toko yang menjadi tanggungan sales saat ini (jadwal mingguan
     * Penugasan Toko, berdiri terus — lihat dokumentasi `PenugasanToko`)
     * tapi belum punya pesanan SELESAI dalam 1 bulan terakhir,
     * dikelompokkan per sales. Dasar hitung "1 bulan"-nya jendela bergulir
     * dari hari ini (`selesai_at >= sebulan lalu`), bukan batas bulan
     * kalender — toko yang terakhir pesanannya tuntas 29 hari lalu tetap
     * dianggap aktif walau sudah berganti bulan kalender.
     *
     * Tidak disaring lewat filterSalesTidakAktif/cariTokoTidakAktif di
     * sini — itu tugas tokoTidakAktif() di bawah — supaya badge jumlah
     * pada tombol selalu menunjukkan angka SEBENARNYA, tidak terpengaruh
     * filter yang kebetulan masih tersisa dari sesi sebelumnya.
     *
     * @return Collection<int, array{sales: User, tokos: Collection<int, Toko>}>
     */
    #[Computed]
    public function tokoTidakAktifSemua(): Collection
    {
        $batasWaktu = CarbonImmutable::now()->subMonth();

        $penugasan = PenugasanToko::query()
            ->with(['toko:id,nama,kode,wilayah_id', 'toko.wilayah:id,nama', 'sales:id,name'])
            ->get();

        if ($penugasan->isEmpty()) {
            return collect();
        }

        $tokoAktifIds = Pesanan::query()
            ->where('status', StatusPesanan::Selesai)
            ->where('selesai_at', '>=', $batasWaktu)
            ->whereIn('toko_id', $penugasan->pluck('toko_id'))
            ->distinct()
            ->pluck('toko_id');

        return $penugasan
            ->reject(fn (PenugasanToko $p) => $tokoAktifIds->contains($p->toko_id))
            ->groupBy('sales_id')
            ->map(fn (Collection $grup) => [
                'sales' => $grup->first()->sales,
                'tokos' => $grup->pluck('toko')->sortBy('nama')->values(),
            ])
            ->sortBy(fn (array $g) => $g['sales']->name)
            ->values();
    }

    /** @return Collection<int, array{sales: User, tokos: Collection<int, Toko>}> */
    #[Computed]
    public function tokoTidakAktif(): Collection
    {
        $kata = mb_strtolower(trim($this->cariTokoTidakAktif));

        return $this->tokoTidakAktifSemua
            ->when($this->filterSalesTidakAktif !== '', fn (Collection $c) => $c
                ->filter(fn (array $g) => (string) $g['sales']->id === $this->filterSalesTidakAktif))
            ->map(fn (array $g) => [
                'sales' => $g['sales'],
                'tokos' => $kata === ''
                    ? $g['tokos']
                    : $g['tokos']->filter(fn ($t) => str_contains(mb_strtolower($t->nama), $kata)
                        || str_contains(mb_strtolower($t->kode), $kata))->values(),
            ])
            ->filter(fn (array $g) => $g['tokos']->isNotEmpty())
            ->values();
    }

    #[Computed]
    public function totalTokoTidakAktif(): int
    {
        return (int) $this->tokoTidakAktifSemua->sum(fn (array $g) => $g['tokos']->count());
    }

    /** Membedakan "belum ada penugasan sama sekali" dari "semua toko tanggungan sudah aktif". */
    #[Computed]
    public function adaPenugasan(): bool
    {
        return PenugasanToko::query()->exists();
    }

    public function render()
    {
        return view('livewire.pesanan.daftar-pesanan', [
            'statusCases' => StatusPesanan::cases(),
        ])->title(__('pesanan.judul_daftar'));
    }
}
