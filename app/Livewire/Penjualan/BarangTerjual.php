<?php

namespace App\Livewire\Penjualan;

use App\Enums\JenisPesanan;
use App\Enums\StatusPesanan;
use App\Models\PesananItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Barang yang BERHASIL terjual — pasangan dari packing list, tapi
 * kebalikannya. Packing list (`Kendaraan::ringkasan_produk_packing`)
 * menunjukkan total & varian dus yang BERANGKAT, dihitung dari `jumlah_dus`
 * (yang DIMUAT ke mobil). Layar ini menunjukkan total & varian dus yang
 * BENAR-BENAR terjual, dihitung dari `PesananItem::terkirim` (yang sudah
 * memperhitungkan nota yang dicoret dan koreksi pasca-selesai) — dua angka
 * itu sengaja berbeda persis sebesar dus yang batal/dicoret/kurang kirim,
 * karena itulah dus yang dimuat tapi TIDAK terjual.
 *
 * Tanggal patokannya SAMA dengan Pendapatan/Insentif Sales:
 * `Pesanan::tanggalPendapatanAntara()` — tanggal keberangkatan kendaraan
 * untuk rute biasa & kampas, tanggal_lunas untuk POS. Hanya pesanan
 * berstatus SELESAI yang dihitung: pesanan yang batal (baik dibatalkan
 * admin maupun driver di lapangan) tidak pernah menyerahkan barang apa
 * pun, jadi tidak terhitung "terjual" walau kebetulan tanggalnya masuk
 * rentang yang sama.
 */
class BarangTerjual extends Component
{
    use WithPagination;

    #[Url(as: 'mode')]
    public string $mode = 'hari';

    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'bln')]
    public string $bulan = '';

    #[Url(as: 'dari')]
    public string $dariTanggal = '';

    #[Url(as: 'sampai')]
    public string $sampaiTanggal = '';

    // --- Penyaring tabel riwayat — hanya menyaring tabel baris per item di
    // bawah, bukan kartu ringkasan/grafik di atasnya. Sama seperti pola
    // riwayat di Pendapatan. ---

    #[Url(as: 'q')]
    public string $riwayatCari = '';

    #[Url(as: 'kat')]
    public string $riwayatKategori = '';

    #[Url(as: 'produk')]
    public string $riwayatProduk = '';

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
            $this->dispatch('barang-terjual-diperbarui', data: $this->dataChart);
        } elseif (in_array($kolom, ['riwayatCari', 'riwayatKategori', 'riwayatProduk'], true)) {
            unset($this->riwayat);
            $this->resetPage();
        }
    }

    public function bersihkanFilterRiwayat(): void
    {
        $this->reset(['riwayatCari', 'riwayatKategori', 'riwayatProduk']);
        unset($this->riwayat);
        $this->resetPage();
    }

    /**
     * Menerapkan penyaring tanggal (mode hari/bulan/rentang/semua) yang
     * sama ke query pesanan mana pun — dipakai oleh items() untuk kartu
     * ringkasan/grafik, dan riwayat() untuk tabel detail, supaya keduanya
     * tidak bisa diam-diam melenceng satu sama lain.
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
        // mode 'semua': tidak ada penyaring tanggal sama sekali.
    }

    /**
     * Seluruh item dalam rentang tanggal terpilih — dasar BERSAMA untuk
     * kartu ringkasan, ringkasan per produk, dan grafik di bawah. Sengaja
     * TIDAK ikut menyaring riwayatCari/riwayatKategori/riwayatProduk:
     * angka-angka di atas selalu menunjukkan gambaran SELURUH rentang,
     * penyaring tabel riwayat cuma mempersempit tabelnya sendiri.
     *
     * @return Collection<int, PesananItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return PesananItem::query()
            ->whereHas('pesanan', fn (Builder $q) => $this->terapkanRentang($q))
            ->with(['produk:id,nama,kode', 'pesanan:id,kode,jenis,toko_id,tanggal_lunas'])
            ->get();
    }

    /**
     * Ringkasan per produk (varian) — jawaban langsung untuk "varian dus
     * apa saja yang terjual", padanan `ringkasan_produk_packing` pada
     * packing list. Terurut dari yang paling banyak terjual, supaya
     * produk terlaris langsung terlihat tanpa perlu diurutkan manual.
     *
     * @return Collection<int, array{produk_id: int, nama: string, kode: string, qty: int, qty_bonus: int, transaksi: int}>
     */
    #[Computed]
    public function ringkasanProduk(): Collection
    {
        return $this->items
            ->groupBy('produk_id')
            ->map(function (Collection $grup) {
                $produk = $grup->first()->produk;

                return [
                    'produk_id' => $produk->id,
                    'nama' => $produk->nama,
                    'kode' => $produk->kode,
                    'qty' => (int) $grup->sum->terkirim,
                    'qty_bonus' => (int) $grup->where('is_bonus', true)->sum->terkirim,
                    'transaksi' => $grup->pluck('pesanan_id')->unique()->count(),
                ];
            })
            ->sortByDesc('qty')
            ->values();
    }

    #[Computed]
    public function totalDusTerjual(): int
    {
        return (int) $this->items->sum->terkirim;
    }

    #[Computed]
    public function totalBonus(): int
    {
        return (int) $this->items->where('is_bonus', true)->sum->terkirim;
    }

    #[Computed]
    public function totalVarian(): int
    {
        return $this->ringkasanProduk->count();
    }

    #[Computed]
    public function totalTransaksi(): int
    {
        return $this->items->pluck('pesanan_id')->unique()->count();
    }

    /**
     * Dus terjual menurut sumbernya — padanan `Pendapatan::totalPerKategori()`
     * tapi dalam satuan dus, bukan rupiah. Rute biasa dan kampas sama-sama
     * "driver" (lihat `JenisPesanan::kategoriPendapatan()`).
     *
     * @return array{driver: int, pos: int}
     */
    #[Computed]
    public function totalPerKategori(): array
    {
        $grup = $this->items->groupBy(fn (PesananItem $i) => $i->pesanan->jenis->kategoriPendapatan());

        return [
            'driver' => (int) ($grup->get('driver')?->sum->terkirim ?? 0),
            'pos' => (int) ($grup->get('pos')?->sum->terkirim ?? 0),
        ];
    }

    /** Sepuluh produk terlaris saja — grafik batang tetap terbaca walau varian produknya puluhan. */
    #[Computed]
    public function dataChart(): array
    {
        $top = $this->ringkasanProduk->take(10);

        return [
            'labels' => $top->pluck('nama')->all(),
            'data' => $top->pluck('qty')->all(),
        ];
    }

    /** @return Collection<int, array{id: int, nama: string}> */
    #[Computed]
    public function produkPilihan(): Collection
    {
        return $this->ringkasanProduk
            ->map(fn (array $r) => ['id' => $r['produk_id'], 'nama' => $r['nama']])
            ->sortBy('nama')
            ->values();
    }

    /**
     * Baris detail (satu baris = satu item pada satu pesanan) untuk tabel
     * riwayat yang bisa disaring dan dipaging. Query TERPISAH dari
     * items() lewat basis data langsung (bukan menyaring koleksi yang
     * sudah dimuat di memori) supaya jumlah halamannya akurat — beda dari
     * pola riwayat di Pendapatan yang cukup memfilter koleksi di memori
     * karena tidak dipaging.
     */
    #[Computed]
    public function riwayat()
    {
        $kata = trim($this->riwayatCari);

        return PesananItem::query()
            ->whereHas('pesanan', fn (Builder $q) => $this->terapkanRentang($q))
            ->when($this->riwayatProduk !== '', fn (Builder $q) => $q->where('produk_id', $this->riwayatProduk))
            ->when($this->riwayatKategori === 'pos', fn (Builder $q) => $q->whereHas(
                'pesanan', fn (Builder $qq) => $qq->where('jenis', JenisPesanan::Pos)
            ))
            ->when($this->riwayatKategori === 'driver', fn (Builder $q) => $q->whereHas(
                'pesanan', fn (Builder $qq) => $qq->whereIn('jenis', [JenisPesanan::Normal, JenisPesanan::Kampas])
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
            ->with(['produk:id,nama,kode', 'pesanan:id,kode,jenis,toko_id,tanggal_lunas', 'pesanan.toko:id,nama', 'pesanan.stop.kendaraan.batch'])
            ->latest('id')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.penjualan.barang-terjual')->title(__('penjualan.judul_barang_terjual'));
    }
}
