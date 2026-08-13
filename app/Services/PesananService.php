<?php

namespace App\Services;

use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Aturan main pesanan: pembuatan, perpindahan status, dan pengembalian stok.
 *
 * Perlakuan stok memakai dua angka. `stok_reserved` naik begitu pesanan
 * dibuat, sehingga barang langsung terkunci dan tidak bisa dijanjikan ke toko
 * lain. `stok` fisik baru turun ketika barang benar-benar sampai di toko.
 * Dengan cara ini pembatalan cukup melepas kuncian, dan angka stok gudang
 * tetap cocok dengan barang yang nyata ada di rak.
 */
class PesananService
{
    /**
     * @param  array<int, array{produk_id: int, jumlah_dus: int}>  $items
     *
     * @throws ValidationException
     */
    public function buat(Toko $toko, array $items, User $pembuat, ?string $catatan = null): Pesanan
    {
        $items = $this->bersihkanItems($items);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('pesanan.galat_minimal_satu'),
            ]);
        }

        $minDus = (int) config('ond.min_dus_per_toko');
        $totalDus = array_sum(array_column($items, 'jumlah_dus'));

        if ($totalDus < $minDus) {
            throw ValidationException::withMessages([
                'items' => __('pesanan.galat_min_dus', ['min' => $minDus, 'sekarang' => $totalDus]),
            ]);
        }

        if (! $toko->aktif) {
            throw ValidationException::withMessages([
                'toko_id' => __('pesanan.galat_toko_nonaktif', ['nama' => $toko->nama]),
            ]);
        }

        return DB::transaction(function () use ($toko, $items, $pembuat, $catatan, $totalDus): Pesanan {
            // Dikunci di dalam transaksi supaya dua sales yang menekan simpan
            // bersamaan tidak sama-sama lolos pemeriksaan pesanan aktif.
            $adaPesananAktif = Pesanan::query()
                ->where('toko_id', $toko->id)
                ->whereIn('status', StatusPesanan::aktif())
                ->lockForUpdate()
                ->exists();

            if ($adaPesananAktif) {
                throw ValidationException::withMessages([
                    'toko_id' => __('pesanan.galat_pesanan_aktif', ['nama' => $toko->nama]),
                ]);
            }

            $produkIds = array_column($items, 'produk_id');
            $produks = Produk::whereIn('id', $produkIds)->lockForUpdate()->get()->keyBy('id');

            $totalNilai = 0;
            $baris = [];

            foreach ($items as $item) {
                $produk = $produks->get($item['produk_id']);

                if ($produk === null || ! $produk->aktif) {
                    throw ValidationException::withMessages([
                        'items' => __('pesanan.galat_produk_hilang'),
                    ]);
                }

                if ($item['jumlah_dus'] > $produk->stok_tersedia) {
                    throw ValidationException::withMessages([
                        'items' => __('pesanan.galat_stok_kurang', [
                            'nama' => $produk->nama,
                            'diminta' => $item['jumlah_dus'],
                            'tersedia' => $produk->stok_tersedia,
                        ]),
                    ]);
                }

                $subtotal = (float) $produk->harga * $item['jumlah_dus'];
                $totalNilai += $subtotal;

                $baris[] = [
                    'produk' => $produk,
                    'jumlah_dus' => $item['jumlah_dus'],
                    'harga_satuan' => (float) $produk->harga,
                    'subtotal' => $subtotal,
                ];
            }

            $pesanan = Pesanan::create([
                'kode' => $this->kodePesanan(),
                'toko_id' => $toko->id,
                'wilayah_id' => $toko->wilayah_id,
                'dibuat_oleh' => $pembuat->id,
                'status' => StatusPesanan::Order,
                'tanggal' => today(),
                'total_dus' => $totalDus,
                'total_nilai' => $totalNilai,
                'catatan' => $catatan,
            ]);

            foreach ($baris as $b) {
                $pesanan->items()->create([
                    'produk_id' => $b['produk']->id,
                    'jumlah_dus' => $b['jumlah_dus'],
                    'harga_satuan' => $b['harga_satuan'],
                    'subtotal' => $b['subtotal'],
                ]);

                $this->kunciStok($b['produk'], $b['jumlah_dus'], $pesanan, $pembuat);
            }

            return $pesanan->fresh(['items.produk', 'toko']);
        });
    }

    /** ORDER menjadi PROCESS setelah admin memeriksa. */
    public function setujui(Pesanan $pesanan, User $admin): void
    {
        if ($pesanan->status !== StatusPesanan::Order) {
            throw new RuntimeException(__('pesanan.galat_bukan_order', ['kode' => $pesanan->kode]));
        }

        $pesanan->update([
            'status' => StatusPesanan::Process,
            'diproses_oleh' => $admin->id,
            'diproses_at' => now(),
        ]);
    }

    /**
     * Menandai satu kunjungan selesai setelah driver mengunggah foto nota.
     * Stok fisik dipotong di sini, karena barangnya baru benar-benar keluar
     * gudang saat serah terima terjadi.
     */
    public function selesaikanPengiriman(KendaraanStop $stop, string $pathFotoNota, User $driver, ?string $catatan = null): void
    {
        if ($stop->status !== StatusStop::Pending) {
            throw new RuntimeException(__('pesanan.galat_sudah_selesai'));
        }

        DB::transaction(function () use ($stop, $pathFotoNota, $driver, $catatan): void {
            // Relasi dimuat di sini karena layanan ini bisa dipanggil dengan
            // model yang relasinya belum ikut terambil.
            $stop->loadMissing('kendaraan');

            $pesanan = $stop->pesanan()->lockForUpdate()->first();

            if ($pesanan->status !== StatusPesanan::Delivery) {
                throw new RuntimeException(__('pesanan.galat_bukan_delivery', ['kode' => $pesanan->kode]));
            }

            $stop->update([
                'status' => StatusStop::Selesai,
                // Pengiriman biasa berarti seluruh isi nota diterima toko.
                // Pengiriman sebagian ditangani PengirimanService::coretNota().
                'total_dus_terkirim' => $stop->total_dus,
                'foto_nota' => $pathFotoNota,
                'catatan_driver' => $catatan,
                'selesai_at' => now(),
            ]);

            $pesanan->update([
                'status' => StatusPesanan::Selesai,
                'selesai_at' => now(),
            ]);

            foreach ($pesanan->items()->with('produk')->get() as $item) {
                $this->keluarkanStok($item->produk, $item->jumlah_dus, $pesanan, $driver);
            }

            $kendaraan = $stop->kendaraan;

            if ($kendaraan->stops()->where('status', StatusStop::Pending)->doesntExist()) {
                $kendaraan->update(['status' => 'selesai']);
            } elseif ($kendaraan->status === 'siap') {
                $kendaraan->update(['status' => 'jalan']);
            }
        });
    }

    /**
     * Membatalkan pesanan dan melepas kuncian stoknya.
     *
     * Pesanan yang notanya sudah diunggah tidak bisa dibatalkan lewat sini,
     * karena barangnya sudah diterima toko.
     */
    public function batalkan(Pesanan $pesanan, User $admin, string $alasan, ?string $catatan = null): void
    {
        if (! $pesanan->status->bisaDibatalkan()) {
            throw new RuntimeException(__('pesanan.galat_tak_bisa_batal', ['status' => $pesanan->status->label()]));
        }

        DB::transaction(function () use ($pesanan, $admin, $alasan, $catatan): void {
            $pesanan->loadMissing('stop.kendaraan');

            $stop = $pesanan->stop;

            if ($stop !== null && $stop->status === StatusStop::Selesai) {
                throw new RuntimeException(__('pesanan.galat_sudah_diterima'));
            }

            foreach ($pesanan->items()->with('produk')->get() as $item) {
                $this->lepasKunciStok($item->produk, $item->jumlah_dus, $pesanan, $admin);
            }

            // Kunjungannya dikeluarkan dari rute supaya driver tidak
            // mendatangi toko yang pesanannya sudah batal.
            if ($stop !== null) {
                $kendaraan = $stop->kendaraan;
                $stop->delete();

                $kendaraan->update([
                    'total_toko' => $kendaraan->stops()->count(),
                    'total_dus' => (int) $kendaraan->stops()->sum('total_dus'),
                ]);
            }

            $pesanan->update([
                'status' => StatusPesanan::Cancel,
                'alasan_cancel' => $alasan,
                'catatan_cancel' => $catatan,
                'dibatalkan_oleh' => $admin->id,
                'dibatalkan_at' => now(),
            ]);
        });
    }

    private function kunciStok(Produk $produk, int $jumlah, Pesanan $pesanan, User $user): void
    {
        $produk->increment('stok_reserved', $jumlah);
        $produk->refresh();

        StokMutasi::create([
            'produk_id' => $produk->id,
            'pesanan_id' => $pesanan->id,
            'tipe' => 'reserve',
            'jumlah' => -$jumlah,
            'stok_sesudah' => $produk->stok,
            'reserved_sesudah' => $produk->stok_reserved,
            'keterangan' => __('pesanan.mutasi_dikunci', ['kode' => $pesanan->kode]),
            'user_id' => $user->id,
        ]);
    }

    private function lepasKunciStok(Produk $produk, int $jumlah, Pesanan $pesanan, User $user): void
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

    private function keluarkanStok(Produk $produk, int $jumlah, Pesanan $pesanan, User $user): void
    {
        $produk->decrement('stok', min($jumlah, $produk->stok));
        $produk->decrement('stok_reserved', min($jumlah, $produk->stok_reserved));
        $produk->refresh();

        StokMutasi::create([
            'produk_id' => $produk->id,
            'pesanan_id' => $pesanan->id,
            'tipe' => 'keluar',
            'jumlah' => -$jumlah,
            'stok_sesudah' => $produk->stok,
            'reserved_sesudah' => $produk->stok_reserved,
            'keterangan' => __('pesanan.mutasi_keluar', ['kode' => $pesanan->kode]),
            'user_id' => $user->id,
        ]);
    }

    /**
     * Menggabungkan baris produk yang sama dan membuang jumlah nol.
     *
     * @param  array<int, array{produk_id: int|string, jumlah_dus: int|string}>  $items
     * @return array<int, array{produk_id: int, jumlah_dus: int}>
     */
    private function bersihkanItems(array $items): array
    {
        $gabung = [];

        foreach ($items as $item) {
            $produkId = (int) ($item['produk_id'] ?? 0);
            $jumlah = (int) ($item['jumlah_dus'] ?? 0);

            if ($produkId <= 0 || $jumlah <= 0) {
                continue;
            }

            $gabung[$produkId] = ($gabung[$produkId] ?? 0) + $jumlah;
        }

        return array_map(
            fn (int $produkId, int $jumlah) => ['produk_id' => $produkId, 'jumlah_dus' => $jumlah],
            array_keys($gabung),
            array_values($gabung),
        );
    }

    private function kodePesanan(): string
    {
        $prefix = 'PSN-'.now()->format('Ymd');
        $urutan = Pesanan::whereDate('created_at', today())->count() + 1;

        return sprintf('%s-%04d', $prefix, $urutan);
    }
}
