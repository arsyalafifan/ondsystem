<?php

namespace App\Livewire\Kunjungan;

use App\Models\PenugasanSales;
use App\Models\Toko;
use App\Models\User;
use App\Services\Kunjungan\PenugasanService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Menyusun daftar toko yang menjadi tanggungan tiap sales untuk satu bulan.
 *
 * Toko yang sudah dipegang sales lain tidak muncul sebagai pilihan, sehingga
 * bentrok dicegah sejak di layar, bukan baru ketahuan saat disimpan.
 */
class Penugasan extends Component
{
    #[Url(as: 'bulan')]
    public string $bulan = '';

    public ?int $salesDipilih = null;

    public string $cari = '';

    /** @var array<int, int> */
    public array $terpilih = [];

    public bool $konfirmasiSalin = false;

    public string $bulanSumber = '';

    public function mount(): void
    {
        if ($this->bulan === '') {
            $this->bulan = CarbonImmutable::today()->format('Y-m');
        }

        $this->salesDipilih = User::sales()->orderBy('name')->value('id');
        $this->muatTerpilih();
    }

    private function tanggalBulan(): string
    {
        return app(PenugasanService::class)->bulan($this->bulan.'-01');
    }

    #[Computed]
    public function salesList(): Collection
    {
        $jumlah = app(PenugasanService::class)->jumlahPerSales($this->tanggalBulan());

        return User::sales()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function (User $s) use ($jumlah): User {
                $s->setAttribute('jumlah_toko', (int) ($jumlah[$s->id] ?? 0));

                return $s;
            });
    }

    /** Toko yang boleh dipilih: belum dipegang siapa pun, atau sudah milik sales ini. */
    #[Computed]
    public function tokoTersedia(): Collection
    {
        if ($this->salesDipilih === null) {
            return collect();
        }

        return app(PenugasanService::class)
            ->tokoTersedia($this->tanggalBulan(), $this->salesDipilih)
            ->with('wilayah:id,nama')
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$this->cari}%")
                ->orWhere('kode', 'like', "%{$this->cari}%")
                ->orWhere('asset_id', 'like', "%{$this->cari}%")
                ->orWhere('alamat', 'like', "%{$this->cari}%")))
            ->orderBy('nama')
            ->limit(400)
            ->get();
    }

    #[Computed]
    public function maksToko(): int
    {
        return app(PenugasanService::class)->maksToko();
    }

    /** Toko aktif yang belum dipegang sales mana pun pada bulan ini. */
    #[Computed]
    public function jumlahBelumDitugaskan(): int
    {
        return app(PenugasanService::class)->tokoTersedia($this->tanggalBulan())->count();
    }

    #[Computed]
    public function tanpaAssetId(): int
    {
        return Toko::aktif()->whereNull('asset_id')->count();
    }

    public function updatedBulan(): void
    {
        $this->muatTerpilih();
        unset($this->salesList, $this->tokoTersedia, $this->jumlahBelumDitugaskan);
    }

    public function pilihSales(int $salesId): void
    {
        $this->salesDipilih = $salesId;
        $this->cari = '';
        $this->muatTerpilih();
        unset($this->tokoTersedia);
    }

    private function muatTerpilih(): void
    {
        $this->terpilih = $this->salesDipilih === null
            ? []
            : PenugasanSales::query()
                ->where('sales_id', $this->salesDipilih)
                ->whereDate('bulan', $this->tanggalBulan())
                ->pluck('toko_id')
                ->map(fn ($id) => (int) $id)
                ->all();
    }

    /** Memilih seluruh toko yang sedang tampil, sebatas sisa kuota. */
    public function pilihSemuaTampil(): void
    {
        $sisa = $this->maksToko - count($this->terpilih);

        foreach ($this->tokoTersedia as $toko) {
            if ($sisa <= 0) {
                break;
            }

            if (! in_array($toko->id, $this->terpilih, true)) {
                $this->terpilih[] = $toko->id;
                $sisa--;
            }
        }
    }

    public function kosongkan(): void
    {
        $this->terpilih = [];
    }

    public function simpan(PenugasanService $service): void
    {
        if ($this->salesDipilih === null) {
            return;
        }

        try {
            $hasil = $service->tetapkan(
                sales: User::findOrFail($this->salesDipilih),
                tokoIds: $this->terpilih,
                bulan: $this->tanggalBulan(),
                admin: auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        // Target periode berjalan ikut disegarkan supaya perubahan langsung
        // terlihat pada halaman pemantauan, bukan menunggu minggu berikutnya.
        app(PeriodeKunjunganService::class)->segarkanTarget(
            app(PeriodeKunjunganService::class)->periodeBerjalan()
        );

        $this->muatTerpilih();
        unset($this->salesList, $this->tokoTersedia, $this->jumlahBelumDitugaskan);

        $pesan = __('kunjungan.notif_penugasan_tersimpan', [
            'ditambah' => $hasil['ditambah'],
            'dihapus' => $hasil['dihapus'],
        ]);

        if ($hasil['ditolak'] !== []) {
            $pesan .= ' '.implode(' ', $hasil['ditolak']);
        }

        $this->dispatch('notifikasi', pesan: $pesan, jenis: $hasil['ditolak'] !== [] ? 'info' : 'sukses');
    }

    public function salinDariBulanLalu(PenugasanService $service): void
    {
        try {
            $jumlah = $service->salinKeBulan(
                dariBulan: $this->bulanSumber.'-01',
                keBulan: $this->tanggalBulan(),
                admin: auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->konfirmasiSalin = false;
        $this->muatTerpilih();
        unset($this->salesList, $this->tokoTersedia, $this->jumlahBelumDitugaskan);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_penugasan_disalin', ['jumlah' => $jumlah]));
    }

    public function bukaSalin(): void
    {
        $this->bulanSumber = CarbonImmutable::parse($this->tanggalBulan())->subMonth()->format('Y-m');
        $this->konfirmasiSalin = true;
    }

    public function render()
    {
        return view('livewire.kunjungan.penugasan')->title(__('kunjungan.judul_penugasan'));
    }
}
