<?php

namespace App\Services;

use App\Enums\JenisPesanan;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tindakan driver di lapangan yang mengubah isi muatan mobil.
 *
 * Tiga keadaan yang ditangani, dan semuanya berujung pada satu pertanyaan yang
 * sama: berapa dus yang benar-benar keluar dari mobil?
 *
 *  - **Batal** — toko tidak bisa menerima sama sekali. Kewajiban driver atas
 *    toko itu tuntas, tapi dusnya masih di mobil.
 *  - **Coret nota** — toko hanya mau sebagian. Yang diterima dicatat apa
 *    adanya, sisanya masih di mobil.
 *  - **Kampas** — sisa dari dua keadaan di atas dijual ke toko lain di jalan.
 *
 * Aturan stok yang mengikat ketiganya: stok gudang hanya berkurang sebanyak
 * barang yang benar-benar diterima toko. Dus yang tidak terkirim dan tidak
 * diampaskan kembali ke gudang bersama mobilnya, jadi stoknya pun harus utuh.
 */
class PengirimanService
{
    /**
     * Membatalkan satu toko dari rute karena tidak bisa dikirimi.
     *
     * Kunjungannya tidak dihapus — berbeda dengan pembatalan oleh admin —
     * karena driver tetap perlu melihatnya di daftar, dan dusnya perlu terus
     * terhitung sebagai sisa yang bisa diampaskan.
     */
    public function batalkanDiLapangan(KendaraanStop $stop, User $driver, string $alasan, ?string $catatan = null): void
    {
        if (trim($alasan) === '') {
            throw new RuntimeException(__('pesanan.alasan_wajib'));
        }

        $stop->loadMissing(['pesanan.items.produk', 'kendaraan']);

        if ($stop->status !== StatusStop::Pending) {
            throw new RuntimeException(__('pengiriman.galat_stop_tuntas'));
        }

        if ($stop->isKampas()) {
            throw new RuntimeException(__('pengiriman.galat_kampas_tak_bisa_dibatalkan'));
        }

        DB::transaction(function () use ($stop, $driver, $alasan, $catatan): void {
            $pesanan = $stop->pesanan()->lockForUpdate()->first();

            if ($pesanan->status !== StatusPesanan::Delivery) {
                throw new RuntimeException(__('pesanan.galat_bukan_delivery', ['kode' => $pesanan->kode]));
            }

            // Kuncian stok dilepas: barangnya tidak jadi diterima toko ini,
            // jadi tidak boleh terus dihitung sebagai sudah dijanjikan.
            foreach ($pesanan->items()->with('produk')->get() as $item) {
                $this->lepasKunci($item->produk, $item->jumlah_dus, $pesanan, $driver);
            }

            $pesanan->update([
                'status' => StatusPesanan::Cancel,
                'alasan_cancel' => $alasan,
                'catatan_cancel' => $catatan,
                'dibatalkan_oleh' => $driver->id,
                'dibatalkan_at' => now(),
            ]);

            $stop->update([
                'status' => StatusStop::Dibatalkan,
                'total_dus_terkirim' => 0,
                'alasan_batal' => $alasan,
                'catatan_batal' => $catatan,
                'dibatalkan_at' => now(),
            ]);

            $this->segarkanKendaraan($stop->kendaraan);
        });
    }

    /**
     * Mencoret nota: toko hanya menerima sebagian dari yang dipesan.
     *
     * @param  array<int, int>  $jumlahTerkirim  jumlah diterima, dikunci pada id item pesanan
     *
     * @throws RuntimeException bila jumlahnya tidak masuk akal
     */
    public function coretNota(
        KendaraanStop $stop,
        array $jumlahTerkirim,
        string $pathFotoNota,
        User $driver,
        ?string $catatan = null,
    ): void {
        $stop->loadMissing(['pesanan.items.produk', 'kendaraan']);

        if ($stop->status !== StatusStop::Pending) {
            throw new RuntimeException(__('pengiriman.galat_stop_tuntas'));
        }

        $items = $stop->pesanan->items;
        $minDus = (int) config('ond.min_dus_per_toko');

        $rapi = [];
        $total = 0;

        foreach ($items as $item) {
            $diminta = (int) ($jumlahTerkirim[$item->id] ?? $item->jumlah_dus);

            if ($diminta < 0 || $diminta > $item->jumlah_dus) {
                throw new RuntimeException(__('pengiriman.galat_coret_melebihi', [
                    'produk' => $item->produk->nama,
                    'maks' => $item->jumlah_dus,
                ]));
            }

            $rapi[$item->id] = $diminta;
            $total += $diminta;
        }

        if ($total === $stop->pesanan->total_dus) {
            throw new RuntimeException(__('pengiriman.galat_coret_tanpa_perubahan'));
        }

        // Kalau yang diterima sudah di bawah batas minimal pesanan, artinya
        // toko itu sebenarnya menolak — yang tepat adalah membatalkannya,
        // bukan mengirim jumlah yang tidak sah.
        if ($total < $minDus) {
            throw new RuntimeException(__('pengiriman.galat_coret_di_bawah_minimal', [
                'min' => $minDus,
                'total' => $total,
            ]));
        }

        DB::transaction(function () use ($stop, $rapi, $total, $pathFotoNota, $driver, $catatan): void {
            $pesanan = $stop->pesanan()->lockForUpdate()->first();

            if ($pesanan->status !== StatusPesanan::Delivery) {
                throw new RuntimeException(__('pesanan.galat_bukan_delivery', ['kode' => $pesanan->kode]));
            }

            foreach ($pesanan->items()->with('produk')->get() as $item) {
                $terkirim = $rapi[$item->id];

                $item->update(['jumlah_dus_terkirim' => $terkirim]);

                // Stok fisik berkurang hanya sebanyak yang diterima toko,
                // sedangkan seluruh kuncian dilepas karena pesanannya ditutup.
                $this->keluarkanStok($item->produk, $terkirim, $item->jumlah_dus, $pesanan, $driver);
            }

            $pesanan->update([
                'status' => StatusPesanan::Selesai,
                'kurang_kirim' => true,
                'selesai_at' => now(),
            ]);

            $stop->update([
                'status' => StatusStop::Selesai,
                'total_dus_terkirim' => $total,
                'foto_nota' => $pathFotoNota,
                'catatan_driver' => $catatan,
                'selesai_at' => now(),
            ]);

            $this->segarkanKendaraan($stop->kendaraan);
        });
    }

    /**
     * Sisa muatan yang boleh diampaskan, dirinci per produk.
     *
     * Dihitung per produk, bukan sekadar totalnya, karena yang ada di mobil
     * adalah barang tertentu. Dua toko batal masing-masing 5 dus air mineral
     * dan 5 dus teh berarti driver membawa 5 air dan 5 teh — bukan 10 dus
     * bebas pilih.
     *
     * @return Collection<int, array{produk: Produk, sisa: int, terpakai: int, tersedia: int}>
     */
    public function jatahKampas(Kendaraan $kendaraan): Collection
    {
        $kendaraan->loadMissing(['stops.pesanan.items.produk']);

        $sisa = [];

        foreach ($kendaraan->stops as $stop) {
            if ($stop->isKampas() || $stop->pesanan === null) {
                continue;
            }

            foreach ($stop->pesanan->items as $item) {
                if ($stop->status === StatusStop::Dibatalkan) {
                    // Seluruh isinya kembali menjadi sisa.
                    $sisa[$item->produk_id] = ($sisa[$item->produk_id] ?? 0) + $item->jumlah_dus;
                } elseif ($stop->status === StatusStop::Selesai) {
                    $sisa[$item->produk_id] = ($sisa[$item->produk_id] ?? 0) + $item->sisa;
                }
            }
        }

        // Yang sudah terlanjur diampaskan dikurangkan dari jatah.
        $terpakai = [];

        foreach ($kendaraan->stops as $stop) {
            if (! $stop->isKampas() || $stop->pesanan === null) {
                continue;
            }

            foreach ($stop->pesanan->items as $item) {
                $terpakai[$item->produk_id] = ($terpakai[$item->produk_id] ?? 0) + $item->jumlah_dus;
            }
        }

        $produkIds = array_keys($sisa + $terpakai);
        $produks = Produk::whereIn('id', $produkIds)->get()->keyBy('id');

        return collect($produkIds)
            ->map(function (int $id) use ($sisa, $terpakai, $produks): array {
                $jumlahSisa = (int) ($sisa[$id] ?? 0);
                $jumlahTerpakai = (int) ($terpakai[$id] ?? 0);

                return [
                    'produk' => $produks->get($id),
                    'sisa' => $jumlahSisa,
                    'terpakai' => $jumlahTerpakai,
                    'tersedia' => max(0, $jumlahSisa - $jumlahTerpakai),
                ];
            })
            ->filter(fn (array $b) => $b['produk'] !== null && $b['tersedia'] > 0)
            ->sortBy(fn (array $b) => $b['produk']->nama)
            ->values();
    }

    /**
     * Mencatat penjualan sisa muatan ke toko lain di jalan.
     *
     * Barangnya sudah ada di mobil dan notanya diserahkan saat itu juga, jadi
     * pesanan ini langsung tercatat selesai — tidak melewati tahap ORDER dan
     * PROCESS seperti pesanan biasa.
     *
     * Aturan pesanan biasa yang sengaja tidak berlaku di sini:
     *  - toko boleh dipilih walau masih punya pesanan berjalan, karena ini
     *    penjualan terpisah yang barangnya sudah di tangan;
     *  - tidak ada batas minimal dus, karena yang dijual adalah sisa.
     *
     * @param  array<int, int>  $items  jumlah dus, dikunci pada id produk
     */
    public function kampas(
        Kendaraan $kendaraan,
        Toko $toko,
        array $items,
        string $pathFotoNota,
        User $driver,
        ?string $catatan = null,
    ): Pesanan {
        $diminta = collect($items)
            ->map(fn ($jumlah) => (int) $jumlah)
            ->filter(fn (int $jumlah) => $jumlah > 0);

        if ($diminta->isEmpty()) {
            throw new RuntimeException(__('pengiriman.galat_kampas_kosong'));
        }

        if (! $toko->aktif) {
            throw new RuntimeException(__('pesanan.galat_toko_nonaktif', ['nama' => $toko->nama]));
        }

        $jatah = $this->jatahKampas($kendaraan)->keyBy(fn (array $b) => $b['produk']->id);

        foreach ($diminta as $produkId => $jumlah) {
            $baris = $jatah->get($produkId);

            if ($baris === null || $jumlah > $baris['tersedia']) {
                $produk = $baris['produk'] ?? Produk::find($produkId);

                throw new RuntimeException(__('pengiriman.galat_kampas_melebihi', [
                    'produk' => $produk?->nama ?? '#'.$produkId,
                    'tersedia' => $baris['tersedia'] ?? 0,
                    'diminta' => $jumlah,
                ]));
            }
        }

        return DB::transaction(function () use ($kendaraan, $toko, $diminta, $pathFotoNota, $driver, $catatan): Pesanan {
            $produks = Produk::whereIn('id', $diminta->keys())->lockForUpdate()->get()->keyBy('id');

            $totalDus = 0;
            $totalNilai = 0.0;

            $pesanan = Pesanan::create([
                'kode' => $this->kodeKampas(),
                'toko_id' => $toko->id,
                'wilayah_id' => $toko->wilayah_id,
                'dibuat_oleh' => $driver->id,
                'status' => StatusPesanan::Selesai,
                'jenis' => JenisPesanan::Kampas,
                'tanggal' => today(),
                'total_dus' => 0,
                'total_nilai' => 0,
                'catatan' => $catatan,
                'dikirim_at' => now(),
                'selesai_at' => now(),
            ]);

            foreach ($diminta as $produkId => $jumlah) {
                $produk = $produks->get($produkId);
                $subtotal = (float) $produk->harga * $jumlah;

                $pesanan->items()->create([
                    'produk_id' => $produk->id,
                    'jumlah_dus' => $jumlah,
                    'jumlah_dus_terkirim' => $jumlah,
                    'harga_satuan' => (float) $produk->harga,
                    'subtotal' => $subtotal,
                ]);

                // Kunciannya sudah dilepas waktu toko asalnya dibatalkan atau
                // notanya dicoret, jadi di sini stok fisik saja yang berkurang.
                $this->keluarkanStok($produk, $jumlah, 0, $pesanan, $driver);

                $totalDus += $jumlah;
                $totalNilai += $subtotal;
            }

            $pesanan->update(['total_dus' => $totalDus, 'total_nilai' => $totalNilai]);

            $kendaraan->stops()->create([
                'pesanan_id' => $pesanan->id,
                'toko_id' => $toko->id,
                'urutan' => ((int) $kendaraan->stops()->max('urutan')) + 1,
                'jenis' => 'kampas',
                'total_dus' => $totalDus,
                'total_dus_terkirim' => $totalDus,
                'status' => StatusStop::Selesai,
                'foto_nota' => $pathFotoNota,
                'catatan_driver' => $catatan,
                'selesai_at' => now(),
            ]);

            $this->segarkanKendaraan($kendaraan->fresh(['stops']));

            return $pesanan;
        });
    }

    /**
     * Menyegarkan angka ringkas kendaraan setelah isinya berubah.
     *
     * `target_dus` sengaja tidak ikut disentuh: ia salinan muatan saat mobil
     * berangkat, dan menjadi penyebut persentase pengiriman.
     */
    private function segarkanKendaraan(Kendaraan $kendaraan): void
    {
        $stops = $kendaraan->stops()->get();

        $kendaraan->update([
            'total_toko' => $stops->count(),
            'total_dus' => (int) $stops->sum('total_dus'),
        ]);

        // Mobil dianggap selesai ketika tidak ada lagi kunjungan yang menunggu.
        if ($stops->where('status', StatusStop::Pending)->isEmpty()) {
            $kendaraan->update(['status' => 'selesai']);
        } elseif ($kendaraan->status === 'siap') {
            $kendaraan->update(['status' => 'jalan']);
        }
    }

    private function lepasKunci(Produk $produk, int $jumlah, Pesanan $pesanan, User $user): void
    {
        $produk->decrement('stok_reserved', min($jumlah, $produk->stok_reserved));
        $produk->refresh();

        StokMutasi::create([
            'produk_id' => $produk->id,
            'pesanan_id' => $pesanan->id,
            'tipe' => 'release',
            'jumlah' => $jumlah,
            'stok_sesudah' => $produk->stok,
            'reserved_sesudah' => $produk->stok_reserved,
            'keterangan' => __('pesanan.mutasi_dilepas', ['kode' => $pesanan->kode]),
            'user_id' => $user->id,
        ]);
    }

    /**
     * Mengeluarkan barang dari gudang.
     *
     * `$jumlahDikunci` adalah banyaknya kuncian yang perlu dilepas, yang tidak
     * selalu sama dengan yang keluar: pada nota yang dicoret, kunciannya
     * sebesar pesanan semula sedangkan yang keluar hanya sebagian. Pada
     * kampas, kunciannya sudah dilepas lebih dulu sehingga bernilai nol.
     */
    private function keluarkanStok(Produk $produk, int $jumlahKeluar, int $jumlahDikunci, Pesanan $pesanan, User $user): void
    {
        if ($jumlahKeluar > 0) {
            $produk->decrement('stok', min($jumlahKeluar, $produk->stok));
        }

        if ($jumlahDikunci > 0) {
            $produk->decrement('stok_reserved', min($jumlahDikunci, $produk->stok_reserved));
        }

        $produk->refresh();

        StokMutasi::create([
            'produk_id' => $produk->id,
            'pesanan_id' => $pesanan->id,
            'tipe' => 'keluar',
            'jumlah' => -$jumlahKeluar,
            'stok_sesudah' => $produk->stok,
            'reserved_sesudah' => $produk->stok_reserved,
            'keterangan' => __('pesanan.mutasi_keluar', ['kode' => $pesanan->kode]),
            'user_id' => $user->id,
        ]);
    }

    private function kodeKampas(): string
    {
        $prefix = 'KMP-'.now()->format('Ymd');
        $urutan = Pesanan::where('jenis', JenisPesanan::Kampas)->whereDate('created_at', today())->count() + 1;

        return sprintf('%s-%04d', $prefix, $urutan);
    }
}
