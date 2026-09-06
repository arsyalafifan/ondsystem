<?php

namespace App\Livewire\Kunjungan;

use App\Enums\HariKunjungan;
use App\Models\PenugasanToko;
use App\Models\Toko;
use App\Models\User;
use App\Services\Kunjungan\PenugasanTokoService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Menyusun jadwal kunjungan MINGGUAN tiap sales, per hari — lihat
 * dokumentasi `PenugasanToko`. Jadwalnya berdiri terus (bukan disusun ulang
 * tiap bulan): sekali toko masuk ke satu (sales, hari), ia tidak bisa
 * dipilih untuk (sales, hari) lain sampai dilepas dulu di sini.
 */
class Penugasan extends Component
{
    #[Url(as: 'hari')]
    public int $hari = 0;

    public ?int $salesDipilih = null;

    public string $cari = '';

    /** @var array<int, int> */
    public array $terpilih = [];

    public bool $konfirmasiRestore = false;

    public string $maksInput = '';

    public function mount(): void
    {
        if ($this->hari < 1 || $this->hari > 7) {
            $this->hari = HariKunjungan::hariIni()->value;
        }

        $this->salesDipilih = User::sales()->orderBy('name')->value('id');
        $this->maksInput = (string) app(PenugasanTokoService::class)->maksPerHari();
        $this->muatTerpilih();
    }

    /** @return array<int, array{hari: HariKunjungan, jumlah: int}> */
    #[Computed]
    public function hariList(): array
    {
        $jumlah = $this->salesDipilih === null
            ? []
            : app(PenugasanTokoService::class)->jumlahPerHariUntukSales($this->salesDipilih);

        return collect(HariKunjungan::cases())
            ->map(fn (HariKunjungan $h) => ['hari' => $h, 'jumlah' => $jumlah[$h->value] ?? 0])
            ->all();
    }

    #[Computed]
    public function salesList(): Collection
    {
        $jumlah = app(PenugasanTokoService::class)->jumlahPerSales();

        return User::sales()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function (User $s) use ($jumlah): User {
                $s->setAttribute('jumlah_toko', (int) ($jumlah[$s->id] ?? 0));

                return $s;
            });
    }

    /** Toko yang boleh dipilih untuk slot (sales, hari) yang sedang aktif. */
    #[Computed]
    public function tokoTersedia(): Collection
    {
        if ($this->salesDipilih === null) {
            return collect();
        }

        return app(PenugasanTokoService::class)
            ->tokoTersedia(HariKunjungan::from($this->hari), $this->salesDipilih)
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
        return app(PenugasanTokoService::class)->maksPerHari();
    }

    /** Toko aktif yang belum dijadwalkan sama sekali, di hari mana pun. */
    #[Computed]
    public function jumlahBelumDitugaskan(): int
    {
        return Toko::aktif()->whereDoesntHave('penugasanToko')->count();
    }

    #[Computed]
    public function tanpaAssetId(): int
    {
        return Toko::aktif()->whereNull('asset_id')->count();
    }

    public function pilihHari(int $hari): void
    {
        $this->hari = $hari;
        $this->cari = '';
        $this->muatTerpilih();
        unset($this->tokoTersedia);
    }

    public function pilihSales(int $salesId): void
    {
        $this->salesDipilih = $salesId;
        $this->cari = '';
        $this->muatTerpilih();
        unset($this->tokoTersedia, $this->hariList);
    }

    private function muatTerpilih(): void
    {
        $this->terpilih = $this->salesDipilih === null
            ? []
            : PenugasanToko::query()
                ->where('sales_id', $this->salesDipilih)
                ->where('hari', $this->hari)
                ->pluck('toko_id')
                ->map(fn ($id) => (int) $id)
                ->all();
    }

    /** Memilih seluruh toko yang sedang tampil, sebatas sisa kuota hari ini. */
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

    public function simpan(PenugasanTokoService $service): void
    {
        if ($this->salesDipilih === null) {
            return;
        }

        try {
            $hasil = $service->tetapkan(
                sales: User::findOrFail($this->salesDipilih),
                hari: HariKunjungan::from($this->hari),
                tokoIds: $this->terpilih,
                admin: auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->segarkanTargetPeriode();
        $this->muatTerpilih();
        unset($this->salesList, $this->tokoTersedia, $this->hariList, $this->jumlahBelumDitugaskan);

        $pesan = __('kunjungan.notif_penugasan_tersimpan', [
            'ditambah' => $hasil['ditambah'],
            'dihapus' => $hasil['dihapus'],
        ]);

        if ($hasil['ditolak'] !== []) {
            $pesan .= ' '.implode(' ', $hasil['ditolak']);
        }

        $this->dispatch('notifikasi', pesan: $pesan, jenis: $hasil['ditolak'] !== [] ? 'info' : 'sukses');
    }

    public function jadikanDefault(PenugasanTokoService $service): void
    {
        if ($this->salesDipilih === null) {
            return;
        }

        $service->jadikanDefault(User::findOrFail($this->salesDipilih));

        $this->dispatch('notifikasi', pesan: __('kunjungan.default_tersimpan'));
    }

    public function bukaKonfirmasiRestore(): void
    {
        $this->konfirmasiRestore = true;
    }

    public function restoreDefault(PenugasanTokoService $service): void
    {
        if ($this->salesDipilih === null) {
            return;
        }

        try {
            $hasil = $service->restoreDefault(User::findOrFail($this->salesDipilih), auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
            $this->konfirmasiRestore = false;

            return;
        }

        $this->konfirmasiRestore = false;
        $this->segarkanTargetPeriode();
        $this->muatTerpilih();
        unset($this->salesList, $this->tokoTersedia, $this->hariList, $this->jumlahBelumDitugaskan);

        $pesan = __('kunjungan.default_dipulihkan', ['dipulihkan' => $hasil['dipulihkan']]);

        if ($hasil['ditolak'] !== []) {
            $pesan .= ' '.implode(' ', $hasil['ditolak']);
        }

        $this->dispatch('notifikasi', pesan: $pesan, jenis: $hasil['ditolak'] !== [] ? 'info' : 'sukses');
    }

    public function simpanMaks(PenugasanTokoService $service): void
    {
        try {
            $service->ubahMaksPerHari((int) $this->maksInput);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        unset($this->maksToko);
        $this->dispatch('notifikasi', pesan: __('kunjungan.maks_per_hari_disimpan'));
    }

    /**
     * Target periode berjalan ikut disegarkan supaya perubahan langsung
     * terlihat pada halaman pemantauan, bukan menunggu minggu berikutnya.
     */
    private function segarkanTargetPeriode(): void
    {
        $service = app(PeriodeKunjunganService::class);
        $service->segarkanTarget($service->periodeBerjalan());
    }

    public function render()
    {
        return view('livewire.kunjungan.penugasan')->title(__('kunjungan.judul_penugasan'));
    }
}
