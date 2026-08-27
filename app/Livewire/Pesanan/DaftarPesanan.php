<?php

namespace App\Livewire\Pesanan;

use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Illuminate\Database\Eloquent\Builder;
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

    public function render()
    {
        return view('livewire.pesanan.daftar-pesanan', [
            'statusCases' => StatusPesanan::cases(),
        ])->title(__('pesanan.judul_daftar'));
    }
}
