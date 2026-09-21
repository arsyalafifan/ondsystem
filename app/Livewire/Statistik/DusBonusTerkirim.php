<?php

namespace App\Livewire\Statistik;

use App\Enums\JenisPesanan;
use App\Enums\StatusPesanan;
use App\Models\PesananItem;
use App\Services\Statistik\EksporDusBonusTerkirim;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dus Bonus Terkirim:
 * Menampilkan dan menganalisis seluruh dus bonus (gratis / tanpa harga)
 * yang telah benar-benar terkirim ke toko pada pesanan berstatus Selesai.
 *
 * Mengikuti patokan tanggal yang sama dengan menu Pendapatan & Barang Terjual:
 * `Pesanan::tanggalPendapatanAntara()` — tanggal keberangkatan armada kendaraan
 * untuk rute biasa & kampas, tanggal_lunas untuk transaksi POS Kasir.
 *
 * Jumlah dus dihitung dari `PesananItem::terkirim` (sudah memperhitungkan nota
 * yang dicoret di lapangan). Dus yang batal dikirim (jumlah terkirim 0) tidak ikut dihitung.
 */
class DusBonusTerkirim extends Component
{
    use WithPagination;

    #[Url(as: 'mode')]
    public string $mode = 'bulan';

    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'bln')]
    public string $bulan = '';

    #[Url(as: 'dari')]
    public string $dariTanggal = '';

    #[Url(as: 'sampai')]
    public string $sampaiTanggal = '';

    // Filter Riwayat Detail
    #[Url(as: 'q')]
    public string $riwayatCari = '';

    #[Url(as: 'produk')]
    public string $riwayatProduk = '';

    #[Url(as: 'kat')]
    public string $riwayatKategori = '';

    #[Url(as: 'tipe')]
    public string $riwayatTipeBonus = '';

    public function mount(): void
    {
        $this->tanggal = $this->tanggal ?: today()->toDateString();
        $this->bulan = $this->bulan ?: today()->format('Y-m');
        $this->dariTanggal = $this->dariTanggal ?: today()->subDays(6)->toDateString();
        $this->sampaiTanggal = $this->sampaiTanggal ?: today()->toDateString();
    }

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['mode', 'tanggal', 'bulan', 'dariTanggal', 'sampaiTanggal'], true)) {
            unset($this->items, $this->ringkasanProduk, $this->dataChart, $this->riwayat, $this->produkPilihan);
            $this->resetPage();
            $this->dispatch('dus-bonus-diperbarui', data: $this->dataChart);
        } elseif (in_array($kolom, ['riwayatCari', 'riwayatProduk', 'riwayatKategori', 'riwayatTipeBonus'], true)) {
            unset($this->riwayat);
            $this->resetPage();
        }
    }

    public function bersihkanFilterRiwayat(): void
    {
        $this->reset(['riwayatCari', 'riwayatProduk', 'riwayatKategori', 'riwayatTipeBonus']);
        unset($this->riwayat);
        $this->resetPage();
    }

    /**
     * Menerapkan filter status Selesai & rentang tanggal pada query pesanan.
     */
    private function terapkanRentang(Builder $query): void
    {
        $query->status(StatusPesanan::Selesai)
            ->when($this->mode === 'hari', fn (Builder $q) => $q->tanggalPendapatanAntara($this->tanggal, $this->tanggal))
            ->when($this->mode === 'bulan', function (Builder $q) {
                $bulan = CarbonImmutable::parse($this->bulan.'-01');
                $q->tanggalPendapatanAntara($bulan->startOfMonth()->toDateString(), $bulan->endOfMonth()->toDateString());
            })
            ->when($this->mode === 'rentang', fn (Builder $q) => $q->tanggalPendapatanAntara($this->dariTanggal, $this->sampaiTanggal));
        // mode 'semua': tanpa batasan tanggal
    }

    /**
     * Seluruh item dus bonus terkirim pada rentang tanggal aktif.
     *
     * @return Collection<int, PesananItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return PesananItem::query()
            ->where(function (Builder $q) {
                $q->where('is_bonus', true)->orWhere('harga_satuan', 0);
            })
            ->whereRaw('COALESCE(jumlah_dus_terkirim, jumlah_dus) > 0')
            ->whereHas('pesanan', fn (Builder $q) => $this->terapkanRentang($q))
            ->with([
                'produk:id,nama,kode',
                'pesanan:id,kode,jenis,toko_id,tanggal_lunas,dibuat_oleh,sales_id,promo_id',
                'pesanan.toko:id,nama,wilayah_id',
                'pesanan.stop.kendaraan:id,tanggal,driver_id',
            ])
            ->get();
    }

    /**
     * Total seluruh dus bonus yang berhasil terkirim.
     */
    #[Computed]
    public function totalDusBonus(): int
    {
        return (int) $this->items->sum->terkirim;
    }

    /**
     * Ringkasan agregasi per varian produk, diurutkan dari yang paling banyak terkirim.
     *
     * @return Collection<int, array{produk_id: int, kode: string, nama: string, total_dus: int, transaksi: int, toko: int, porsi: float}>
     */
    #[Computed]
    public function ringkasanProduk(): Collection
    {
        $totalSemua = $this->totalDusBonus;

        return $this->items
            ->groupBy('produk_id')
            ->map(function (Collection $grup) use ($totalSemua) {
                $produk = $grup->first()->produk;
                $totalDus = (int) $grup->sum->terkirim;

                return [
                    'produk_id' => $produk->id,
                    'kode' => $produk->kode,
                    'nama' => $produk->nama,
                    'total_dus' => $totalDus,
                    'transaksi' => $grup->pluck('pesanan_id')->unique()->count(),
                    'toko' => $grup->pluck('pesanan.toko_id')->unique()->count(),
                    'porsi' => $totalSemua > 0 ? round(($totalDus / $totalSemua) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total_dus')
            ->values();
    }

    #[Computed]
    public function totalVarian(): int
    {
        return $this->ringkasanProduk->count();
    }

    #[Computed]
    public function totalPesanan(): int
    {
        return $this->items->pluck('pesanan_id')->unique()->count();
    }

    #[Computed]
    public function totalToko(): int
    {
        return $this->items->pluck('pesanan.toko_id')->unique()->count();
    }

    /**
     * Data untuk Chart.js (10 varian produk bonus terbanyak).
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    #[Computed]
    public function dataChart(): array
    {
        $top = $this->ringkasanProduk->take(10);

        return [
            'labels' => $top->pluck('nama')->all(),
            'data' => $top->pluck('total_dus')->all(),
        ];
    }

    /**
     * Daftar pilihan produk untuk dropdown filter riwayat.
     *
     * @return Collection<int, array{id: int, nama: string}>
     */
    #[Computed]
    public function produkPilihan(): Collection
    {
        return $this->ringkasanProduk
            ->map(fn (array $r) => ['id' => $r['produk_id'], 'nama' => $r['nama']])
            ->sortBy('nama')
            ->values();
    }

    /**
     * Keterangan teks periode laporan.
     */
    #[Computed]
    public function periodeLabel(): string
    {
        return match ($this->mode) {
            'hari' => 'Harian (' . CarbonImmutable::parse($this->tanggal)->isoFormat('LL') . ')',
            'bulan' => 'Bulanan (' . CarbonImmutable::parse($this->bulan . '-01')->isoFormat('MMMM Y') . ')',
            'rentang' => 'Rentang (' . CarbonImmutable::parse($this->dariTanggal)->isoFormat('ll') . ' s/d ' . CarbonImmutable::parse($this->sampaiTanggal)->isoFormat('ll') . ')',
            default => 'Semua Periode',
        };
    }

    /**
     * Query baris riwayat detail per item bonus, mendukung filter dan paginasi.
     */
    #[Computed]
    public function riwayat()
    {
        $kata = trim($this->riwayatCari);

        return PesananItem::query()
            ->where(function (Builder $q) {
                $q->where('is_bonus', true)->orWhere('harga_satuan', 0);
            })
            ->whereRaw('COALESCE(jumlah_dus_terkirim, jumlah_dus) > 0')
            ->whereHas('pesanan', fn (Builder $q) => $this->terapkanRentang($q))
            ->when($this->riwayatProduk !== '', fn (Builder $q) => $q->where('produk_id', $this->riwayatProduk))
            ->when($this->riwayatKategori === 'pos', fn (Builder $q) => $q->whereHas(
                'pesanan', fn (Builder $qq) => $qq->where('jenis', JenisPesanan::Pos)
            ))
            ->when($this->riwayatKategori === 'driver', fn (Builder $q) => $q->whereHas(
                'pesanan', fn (Builder $qq) => $qq->whereIn('jenis', [JenisPesanan::Normal, JenisPesanan::Kampas])
            ))
            ->when($this->riwayatTipeBonus === 'promo', fn (Builder $q) => $q->whereHas(
                'pesanan', fn (Builder $qq) => $qq->whereNotNull('promo_id')
            ))
            ->when($this->riwayatTipeBonus === 'manual', fn (Builder $q) => $q->whereHas(
                'pesanan', fn (Builder $qq) => $qq->whereNull('promo_id')
            ))
            ->when($kata !== '', function (Builder $q) use ($kata) {
                $q->where(function (Builder $qq) use ($kata) {
                    $qq->whereHas('produk', fn (Builder $p) => $p->where('nama', 'like', "%{$kata}%"))
                        ->orWhereHas('pesanan', function (Builder $p) use ($kata) {
                            $p->where('kode', 'like', "%{$kata}%")
                                ->orWhereHas('toko', fn (Builder $t) => $t->where('nama', 'like', "%{$kata}%"));
                        });
                });
            })
            ->with([
                'produk:id,nama,kode',
                'pesanan:id,kode,jenis,toko_id,tanggal_lunas,dibuat_oleh,sales_id,promo_id',
                'pesanan.toko:id,nama,wilayah_id',
                'pesanan.toko.wilayah:id,nama',
                'pesanan.stop.kendaraan:id,tanggal,driver_id',
                'pesanan.stop.kendaraan.driver:id,name',
                'pesanan.pembuat:id,name',
                'pesanan.sales:id,name',
                'pesanan.promo:id,nama',
            ])
            ->latest('id')
            ->paginate(15);
    }

    /**
     * Unduh laporan lengkap Dus Bonus Terkirim dalam format Excel (.xlsx).
     */
    public function unduhExcel(): StreamedResponse
    {
        // 1. Data Sheet 1 (Ringkasan per Varian)
        $ringkasan = $this->ringkasanProduk->map(function (array $r, int $i) {
            return [
                'no' => $i + 1,
                'kode' => $r['kode'],
                'nama' => $r['nama'],
                'total_dus' => $r['total_dus'],
                'transaksi' => $r['transaksi'],
                'toko' => $r['toko'],
                'porsi' => $r['porsi'],
            ];
        });

        // 2. Data Sheet 2 (Detail Riwayat tanpa limit paginasi)
        $itemsRiwayat = PesananItem::query()
            ->where(function (Builder $q) {
                $q->where('is_bonus', true)->orWhere('harga_satuan', 0);
            })
            ->whereRaw('COALESCE(jumlah_dus_terkirim, jumlah_dus) > 0')
            ->whereHas('pesanan', fn (Builder $q) => $this->terapkanRentang($q))
            ->with([
                'produk:id,nama,kode',
                'pesanan:id,kode,jenis,toko_id,tanggal_lunas,dibuat_oleh,sales_id,promo_id',
                'pesanan.toko:id,nama,wilayah_id',
                'pesanan.toko.wilayah:id,nama',
                'pesanan.stop.kendaraan:id,tanggal,driver_id',
                'pesanan.stop.kendaraan.driver:id,name',
                'pesanan.pembuat:id,name',
                'pesanan.sales:id,name',
                'pesanan.promo:id,nama',
            ])
            ->latest('id')
            ->get();

        $riwayatData = $itemsRiwayat->map(function (PesananItem $item, int $i) {
            $pesanan = $item->pesanan;
            $pelaksana = match ($pesanan->jenis) {
                JenisPesanan::Pos => $pesanan->pembuat?->name ?? 'Kasir POS',
                default => $pesanan->stop?->kendaraan?->driver?->name
                    ?? $pesanan->sales?->name
                    ?? $pesanan->pembuat?->name
                    ?? '—',
            };

            $tipeBonus = $pesanan->promo_id !== null
                ? ('Promo (' . ($pesanan->promo?->nama ?? 'Promo') . ')')
                : 'Bonus Manual';

            return [
                'no' => $i + 1,
                'tanggal' => $pesanan->tanggal_pendapatan?->toDateString() ?? '—',
                'kode_pesanan' => $pesanan->kode,
                'toko' => $pesanan->toko?->nama ?? '—',
                'wilayah' => $pesanan->toko?->wilayah?->nama ?? '—',
                'kategori' => $pesanan->jenis === JenisPesanan::Pos ? 'POS Kasir' : 'Pengantaran Driver',
                'pelaksana' => $pelaksana,
                'kode_produk' => $item->produk?->kode ?? '—',
                'nama_produk' => $item->produk?->nama ?? '—',
                'qty' => $item->terkirim,
                'tipe_bonus' => $tipeBonus,
            ];
        });

        $spreadsheet = app(EksporDusBonusTerkirim::class)->buat(
            $this->periodeLabel,
            $ringkasan,
            $riwayatData
        );

        $namaFile = sprintf('dus-bonus-terkirim-%s-%s.xlsx', $this->mode, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $namaFile, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render()
    {
        return view('livewire.statistik.dus-bonus-terkirim')
            ->title(__('statistik.judul_dus_bonus'));
    }
}
