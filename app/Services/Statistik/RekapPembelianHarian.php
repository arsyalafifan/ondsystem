<?php

namespace App\Services\Statistik;

use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Support\DepotContext;
use App\Support\ModeDepot;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Data "Form Pembelian Produk Harian Outlet": dus yang diterima tiap toko
 * per hari, satu baris per toko, satu kolom per tanggal.
 *
 * Kriteria pesanannya sengaja sama dengan menu Statistik lain (Dus Terjual
 * Driver, Insentif Sales): status Selesai, dikelompokkan ke hari lewat
 * `Pesanan::tanggalPendapatanAntara()`, dus dihitung dari yang benar-benar
 * diterima toko (`PesananItem::terkirim`), bonus promo ikut dihitung dan
 * bonus manual tidak — supaya angka toko di sini bisa dicocokkan dengan
 * menu-menu itu. Semua jenis pesanan (rute, kampas, POS) ikut, karena yang
 * diukur adalah pembelian toko, bukan kinerja satu pihak tertentu.
 *
 * Toko yang ditampilkan: seluruh toko aktif (termasuk yang belum membeli
 * sama sekali, seperti baris bertotal 0 di form aslinya) ditambah toko
 * nonaktif yang tetap punya pembelian pada periode itu. Toko semu POS
 * "Tanpa Toko" tidak pernah ikut.
 */
final class RekapPembelianHarian
{
    /**
     * @param  ?CarbonImmutable  $dari  null bersama $sampai = semua periode
     * @return array{
     *     dari: CarbonImmutable,
     *     sampai: CarbonImmutable,
     *     judul_zh: string,
     *     judul_id: string,
     *     bulan: list<array{label: string, tanggal: list<string>}>,
     *     baris: list<array{no: int, sales: string, asset_id: string, nama: string, alamat: string, pemilik: string, telepon: string, latitude: ?float, longitude: ?float, harian: array<string, int>, total: int}>,
     *     total_dus: int,
     * }
     */
    public function susun(string $mode, ?CarbonImmutable $dari, ?CarbonImmutable $sampai): array
    {
        $dusPerToko = $this->dusPerTokoPerHari($dari, $sampai);

        if ($dari === null || $sampai === null) {
            $semuaTanggal = collect($dusPerToko)->flatMap(fn (array $harian) => array_keys($harian));
            $dari = $semuaTanggal->isEmpty() ? CarbonImmutable::today()->startOfMonth() : CarbonImmutable::parse($semuaTanggal->min());
            $sampai = $semuaTanggal->isEmpty() ? CarbonImmutable::today()->endOfMonth() : CarbonImmutable::parse($semuaTanggal->max());
        }

        $baris = Toko::query()
            ->where('kode', '!=', Toko::KODE_INTERNAL)
            ->where(fn ($q) => $q->where('aktif', true)->orWhereIn('id', array_keys($dusPerToko)))
            ->with('penugasanToko.sales:id,name')
            ->get()
            ->map(function (Toko $toko) use ($dusPerToko): array {
                $harian = $dusPerToko[$toko->id] ?? [];

                return [
                    'sales' => $toko->penugasanToko?->sales?->name ?? '',
                    'asset_id' => (string) $toko->asset_id,
                    'nama' => $toko->nama,
                    'alamat' => (string) $toko->alamat,
                    'pemilik' => (string) $toko->nama_pemilik,
                    'telepon' => (string) $toko->telepon,
                    'latitude' => $toko->latitude,
                    'longitude' => $toko->longitude,
                    'harian' => $harian,
                    'total' => array_sum($harian),
                ];
            })
            // Dikelompokkan per sales supaya satu sales bisa langsung
            // melihat seluruh tokonya berurutan; toko yang belum punya sales
            // ditaruh paling bawah, bukan di atas gara-gara namanya kosong.
            ->sortBy([
                fn (array $a, array $b) => ($a['sales'] === '') <=> ($b['sales'] === ''),
                fn (array $a, array $b) => strcasecmp($a['sales'], $b['sales']),
                fn (array $a, array $b) => strcasecmp($a['nama'], $b['nama']),
            ])
            ->values()
            ->map(fn (array $b, int $i) => ['no' => $i + 1] + $b)
            ->all();

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'judul_zh' => $this->judulMandarin($mode, $dari, $sampai),
            'judul_id' => $this->judulIndonesia($mode, $dari, $sampai),
            'bulan' => $this->kolomBulan($dari, $sampai),
            'baris' => $baris,
            'total_dus' => array_sum(array_column($baris, 'total')),
        ];
    }

    /** @return array<int, array<string, int>> toko_id => ['Y-m-d' => dus] */
    private function dusPerTokoPerHari(?CarbonImmutable $dari, ?CarbonImmutable $sampai): array
    {
        $hasil = [];

        Pesanan::query()
            ->where('status', StatusPesanan::Selesai)
            // Lewat kode, bukan Toko::internal(): itu firstOrCreate, dan
            // membuka laporan tidak boleh diam-diam menulis baris baru.
            ->whereHas('toko', fn ($q) => $q->where('kode', '!=', Toko::KODE_INTERNAL))
            ->when($dari !== null && $sampai !== null, fn ($q) => $q->tanggalPendapatanAntara($dari->toDateString(), $sampai->toDateString()))
            ->with(['items', 'stop.kendaraan'])
            // lazyById, bukan get(): mode "Semua" bisa menarik seluruh
            // riwayat pesanan sekaligus — dimuat per potongan supaya
            // memorinya tidak ikut membengkak seiring umur data.
            ->lazyById(500)
            ->each(function (Pesanan $pesanan) use (&$hasil): void {
                $dus = (int) $pesanan->items
                    ->filter(fn ($item) => ! $item->is_bonus || $pesanan->promo_id !== null)
                    ->sum->terkirim;

                if ($dus === 0) {
                    return;
                }

                $tanggal = $pesanan->tanggal_pendapatan->toDateString();
                $hasil[$pesanan->toko_id][$tanggal] = ($hasil[$pesanan->toko_id][$tanggal] ?? 0) + $dus;
            });

        return $hasil;
    }

    /** @return list<array{label: string, tanggal: list<string>}> */
    private function kolomBulan(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $bulan = [];

        foreach (CarbonPeriod::create($dari, $sampai) as $hari) {
            // Label "9.2026" = bulan 9 tahun 2026, persis cara form aslinya
            // menuliskan bulan di atas deretan tanggal.
            $label = $hari->month.'.'.$hari->year;
            $bulan[$label] ??= ['label' => $label, 'tanggal' => []];
            $bulan[$label]['tanggal'][] = $hari->toDateString();
        }

        return array_values($bulan);
    }

    private function namaDepot(): string
    {
        return DepotContext::mode() === ModeDepot::SemuaDepot
            ? 'Semua Gudang'
            : (string) DepotContext::current()?->nama;
    }

    /**
     * Judul form sengaja TIDAK ikut bahasa antarmuka — form aslinya
     * dwibahasa Mandarin/Indonesia tetap, dan dibaca pihak yang sama
     * apa pun bahasa yang dipakai admin saat mengunduhnya.
     */
    private function judulMandarin(string $mode, CarbonImmutable $dari, CarbonImmutable $sampai): string
    {
        $tgl = fn (CarbonImmutable $t) => "{$t->year}年{$t->month}月{$t->day}日";

        $periode = match ($mode) {
            'hari' => $tgl($dari),
            'bulan' => "{$dari->month}月",
            'tahun' => "{$dari->year}年",
            default => $tgl($dari).'至'.$tgl($sampai),
        };

        return "好巧{$this->namaDepot()}市场{$periode}终端日进货表";
    }

    private function judulIndonesia(string $mode, CarbonImmutable $dari, CarbonImmutable $sampai): string
    {
        $tgl = fn (CarbonImmutable $t) => $t->locale('id')->translatedFormat('j F Y');

        $periode = match ($mode) {
            'hari' => 'tanggal '.$tgl($dari),
            'bulan' => 'bulan '.$dari->locale('id')->translatedFormat('F'),
            'tahun' => 'tahun '.$dari->year,
            default => 'periode '.$tgl($dari).' - '.$tgl($sampai),
        };

        return "Form Pembelian Produk Harian Outlet halocoko Market {$this->namaDepot()} {$periode}";
    }
}
