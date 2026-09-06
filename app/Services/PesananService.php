<?php

namespace App\Services;

use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use App\Models\StokMutasi;
use App\Models\Toko;
use App\Models\User;
use App\Support\Bahasa;
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
     * @param  array<int, array{produk_id: int, jumlah_dus: int}>  $items  item biasa, harga penuh
     * @param  array<int, array{produk_id: int, jumlah_dus: int}>  $bonusItems  item bonus — harga SELALU 0
     *                                                                          berapa pun jumlah dus-nya,
     *                                                                          dan tidak pernah digabung
     *                                                                          dengan item biasa untuk
     *                                                                          produk yang sama (dua baris
     *                                                                          terpisah, bukan satu baris
     *                                                                          bertambah)
     * @param  ?User  $atasNamaSales  wajib diisi kalau $pembuat admin/superadmin — dicatat di
     *                                Pesanan::sales_id supaya faktur tetap menampilkan nama sales
     *                                yang sebenarnya, bukan nama admin yang mengetik
     * @param  array<int, array{produk_id: int, jumlah_dus: int}>  $promoBonusItems  item bonus dari
     *                                                                               promo aktif (`Promo::aktifPada()`) — berbeda dari $bonusItems:
     *                                                                               terbuka untuk SEMUA peran (bukan cuma admin/superadmin), dan
     *                                                                               divalidasi terhadap aturan promo yang SUNGGUH aktif saat ini
     *                                                                               (bukan dipercaya dari klien): total dus $items harus sudah
     *                                                                               mencapai `Promo::minimal_dus`, jumlahnya tidak boleh melebihi
     *                                                                               `Promo::bonus_dus`, dan produknya harus ada di daftar berhak
     *                                                                               promo itu. Digabung dengan $bonusItems sebelum disimpan (lihat
     *                                                                               di bawah) — keduanya sama-sama berakhir sebagai baris
     *                                                                               `is_bonus = true`, dan `pesanan_items` punya batasan unik
     *                                                                               (pesanan_id, produk_id, is_bonus) yang akan bentrok kalau
     *                                                                               produk yang sama muncul sebagai DUA baris bonus terpisah.
     *
     * @throws ValidationException
     */
    public function buat(
        Toko $toko,
        array $items,
        User $pembuat,
        ?string $catatan = null,
        array $bonusItems = [],
        ?User $atasNamaSales = null,
        array $promoBonusItems = [],
    ): Pesanan {
        $items = $this->bersihkanItems($items);
        $bonusItems = $this->bersihkanItems($bonusItems);
        $promoBonusItems = $this->bersihkanItems($promoBonusItems);

        if ($items === [] && $bonusItems === [] && $promoBonusItems === []) {
            throw ValidationException::withMessages([
                'items' => __('pesanan.galat_minimal_satu'),
            ]);
        }

        // Admin/superadmin mengetik pesanan ini, bukan sales — faktur tetap
        // perlu menampilkan nama sales yang sebenarnya bertanggung jawab,
        // jadi field ini wajib diisi untuk mereka. Sales yang menginput
        // pesanannya sendiri tidak perlu mengisi apa-apa di sini.
        if ($pembuat->isAdmin() && $atasNamaSales === null) {
            throw ValidationException::withMessages([
                'atasNamaSales' => __('pesanan.galat_sales_wajib'),
            ]);
        }

        if ($atasNamaSales !== null && $atasNamaSales->role !== PeranPengguna::Sales) {
            throw ValidationException::withMessages([
                'atasNamaSales' => __('pesanan.galat_bukan_sales'),
            ]);
        }

        $promo = null;

        if ($promoBonusItems !== []) {
            // Server SELALU mencari promo aktifnya sendiri — TIDAK PERNAH
            // menerima atau mempercayai id promo dari pemanggil, persis
            // seperti $bisaBonus di BuatPesanan::simpan() tidak pernah
            // dipercaya dari state komponen.
            $promo = Promo::query()->with('produks')->aktifPada(today()->toDateString())->first();

            if ($promo === null) {
                throw ValidationException::withMessages([
                    'promoBonusItems' => __('pesanan.galat_promo_tidak_aktif'),
                ]);
            }

            $totalDusReguler = array_sum(array_column($items, 'jumlah_dus'));

            if ($totalDusReguler < $promo->minimal_dus) {
                throw ValidationException::withMessages([
                    'promoBonusItems' => __('pesanan.galat_promo_syarat_belum', [
                        'min' => $promo->minimal_dus,
                        'sekarang' => $totalDusReguler,
                    ]),
                ]);
            }

            $totalPromoBonus = array_sum(array_column($promoBonusItems, 'jumlah_dus'));

            if ($totalPromoBonus > $promo->bonus_dus) {
                throw ValidationException::withMessages([
                    'promoBonusItems' => __('pesanan.galat_promo_melebihi_batas', ['maks' => $promo->bonus_dus]),
                ]);
            }

            $produkLayak = $promo->produks->pluck('id')->all();

            foreach ($promoBonusItems as $item) {
                if (! in_array($item['produk_id'], $produkLayak, true)) {
                    throw ValidationException::withMessages([
                        'promoBonusItems' => __('pesanan.galat_promo_produk_tak_layak'),
                    ]);
                }
            }
        }

        // Bonus manual dan bonus promo digabung jadi SATU pool sebelum
        // disimpan — lihat docblock $promoBonusItems di atas soal batasan
        // unik yang akan bentrok kalau keduanya tetap dua array terpisah.
        $semuaBonus = $this->bersihkanItems([...$bonusItems, ...$promoBonusItems]);

        $minDus = (int) config('ond.min_dus_per_toko');
        // Dus bonus (manual maupun promo) tetap dus fisik yang sungguh
        // dimuat ke mobil — ikut dihitung ke batas minimal dan total_dus,
        // walau harganya 0.
        $totalDus = array_sum(array_column($items, 'jumlah_dus'))
            + array_sum(array_column($semuaBonus, 'jumlah_dus'));

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

        // Toko boleh diimpor tanpa wilayah dan dilengkapi belakangan, tapi
        // pesanan mewarisi wilayah_id dari tokonya (dipakai untuk routing) —
        // kolom itu wajib terisi di tabel pesanans, jadi harus dicegat di
        // sini, bukan menunggu gagal di lapisan basis data.
        if ($toko->wilayah_id === null) {
            throw ValidationException::withMessages([
                'toko_id' => __('pesanan.galat_toko_tanpa_wilayah', ['nama' => $toko->nama]),
            ]);
        }

        return DB::transaction(function () use ($toko, $items, $semuaBonus, $pembuat, $catatan, $totalDus, $atasNamaSales, $promo): Pesanan {
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

            // Stok diperiksa atas permintaan GABUNGAN (biasa + bonus) per
            // produk — produk yang sama boleh muncul di kedua daftar (lihat
            // docblock method ini), dan totalnya tetap harus muat di stok
            // yang tersedia, bukan diperiksa dua kali secara terpisah
            // seolah-olah keduanya tidak berbagi rak yang sama.
            $gabunganPermintaan = [];

            foreach ([...$items, ...$semuaBonus] as $item) {
                $gabunganPermintaan[$item['produk_id']] = ($gabunganPermintaan[$item['produk_id']] ?? 0) + $item['jumlah_dus'];
            }

            $produks = Produk::whereIn('id', array_keys($gabunganPermintaan))->lockForUpdate()->get()->keyBy('id');

            foreach ($gabunganPermintaan as $produkId => $jumlahDiminta) {
                $produk = $produks->get($produkId);

                if ($produk === null || ! $produk->aktif) {
                    throw ValidationException::withMessages([
                        'items' => __('pesanan.galat_produk_hilang'),
                    ]);
                }

                if ($jumlahDiminta > $produk->stok_tersedia) {
                    throw ValidationException::withMessages([
                        'items' => __('pesanan.galat_stok_kurang', [
                            'nama' => $produk->nama,
                            'diminta' => $jumlahDiminta,
                            'tersedia' => $produk->stok_tersedia,
                        ]),
                    ]);
                }
            }

            $pesanan = Pesanan::create([
                'kode' => $this->kodePesanan(),
                'toko_id' => $toko->id,
                'wilayah_id' => $toko->wilayah_id,
                'dibuat_oleh' => $pembuat->id,
                'sales_id' => $atasNamaSales?->id,
                'promo_id' => $promo?->id,
                'status' => StatusPesanan::Order,
                'tanggal' => today(),
                'total_dus' => $totalDus,
                'total_nilai' => 0,
                'catatan' => $catatan,
            ]);

            $totalNilai = 0.0;

            foreach ($items as $item) {
                $produk = $produks->get($item['produk_id']);
                $subtotal = (float) $produk->harga * $item['jumlah_dus'];
                $totalNilai += $subtotal;

                $pesanan->items()->create([
                    'produk_id' => $produk->id,
                    'jumlah_dus' => $item['jumlah_dus'],
                    'harga_satuan' => (float) $produk->harga,
                    'subtotal' => $subtotal,
                    'is_bonus' => false,
                ]);

                $this->kunciStok($produk, $item['jumlah_dus'], $pesanan, $pembuat);
            }

            // Item bonus (manual maupun promo, sudah digabung jadi
            // $semuaBonus di atas): harga & subtotal SELALU 0, berapa pun
            // jumlah dus-nya atau berapa pun harga produk sebenarnya —
            // inilah "perlakuan khusus" bonus. Stoknya tetap dikunci sama
            // seperti item biasa (baris kunciStok() di bawah tidak beda
            // sama sekali dari baris biasa di atas): dus bonus tetap fisik
            // keluar dari gudang saat pengiriman, cuma tidak ditagihkan.
            foreach ($semuaBonus as $item) {
                $produk = $produks->get($item['produk_id']);

                $pesanan->items()->create([
                    'produk_id' => $produk->id,
                    'jumlah_dus' => $item['jumlah_dus'],
                    'harga_satuan' => 0,
                    'subtotal' => 0,
                    'is_bonus' => true,
                ]);

                $this->kunciStok($produk, $item['jumlah_dus'], $pesanan, $pembuat);
            }

            $pesanan->update(['total_nilai' => $totalNilai]);

            return $pesanan->fresh(['items.produk', 'toko']);
        });
    }

    /**
     * Penjualan langsung di tempat (POS) — tidak pernah melalui rute
     * pengantaran driver sama sekali. Mengikuti pola yang sama dengan
     * PengirimanService::kampas(): barangnya berpindah tangan seketika,
     * jadi pesanan langsung tercatat SELESAI dan lunas begitu dibuat,
     * tanpa fase reservasi/DELIVERY seperti pesanan biasa. Bedanya dari
     * kampas: POS tidak pernah membuat KendaraanStop sama sekali, karena
     * memang tidak ada kendaraan yang terlibat.
     *
     * Beda lain dari pesanan biasa:
     *  - tidak ada batas minimal dus per toko (mulai dari 1);
     *  - toko yang masih punya pesanan pengantaran aktif tetap boleh
     *    dilayani, karena POS tidak bersinggungan dengan routing sama
     *    sekali.
     *
     * Stok TETAP diperiksa terhadap `stok_tersedia` (`stok - stok_reserved`),
     * SAMA seperti buat() — bukan `stok` fisik mentah. Dus yang sedang
     * terkunci untuk pesanan pengantaran lain (termasuk sisa kampas yang
     * masih di mobil setelah toko batal/dicoret, lihat
     * `PengirimanService::batalkanDiLapangan()`/`coretNota()`) memang belum
     * pernah lepas dari mobil — kalau POS dibiarkan menjualnya lagi karena
     * cuma melihat `stok` mentah (yang belum berkurang sampai barang
     * benar-benar diserahkan), dus yang sama akan terjanjikan dua kali:
     * sekali ke pesanan yang menguncinya, sekali lagi ke pembeli POS.
     * Karena POS tidak pernah lewat `kunciStok()` sama sekali (tidak ada
     * fase reservasi, barangnya langsung keluar), stok yang benar-benar
     * dikeluarkannya dipotong lewat `keluarkanStokLangsung()` — hanya
     * menyentuh `stok` fisik, SENGAJA TIDAK ikut memotong `stok_reserved`
     * seperti `keluarkanStok()` biasa, supaya kuncian milik pesanan lain
     * yang sama sekali tidak berkaitan dengan transaksi POS ini tidak ikut
     * terpotong.
     *
     * Sama seperti buat(): admin/superadmin boleh menyertakan item bonus
     * (harga & subtotal SELALU 0), stoknya tetap keluar fisik seketika
     * seperti item biasa. Bedanya dari buat(), POS tidak punya parameter
     * "atas nama sales" — notanya tetap boleh dicetak (statusnya langsung
     * SELESAI seketika dibuat, dan `Pesanan::bisa_dicetak` sengaja
     * mengecualikan jenis POS dari syarat status PROCESS/DELIVERY yang
     * dipakai pesanan biasa, lihat dokumentasinya di `Pesanan.php`), tapi
     * atribusi penjualnya otomatis memakai `dibuat_oleh` — tidak perlu
     * dipilih manual seperti langkah "atas nama sales" di buat().
     *
     * @param  array<int, array{produk_id: int, jumlah_dus: int}>  $items  item biasa, harga penuh
     * @param  array<int, array{produk_id: int, jumlah_dus: int}>  $bonusItems  item bonus — harga SELALU 0
     *
     * @throws ValidationException
     */
    public function buatPos(
        Toko $toko,
        array $items,
        User $penjual,
        float $nominalCash,
        float $nominalTransfer,
        ?string $catatan = null,
        array $bonusItems = [],
    ): Pesanan {
        $items = $this->bersihkanItems($items);
        $bonusItems = $this->bersihkanItems($bonusItems);

        if ($items === [] && $bonusItems === []) {
            throw ValidationException::withMessages([
                'items' => __('pesanan.galat_minimal_satu'),
            ]);
        }

        // Toko::internal() (transaksi "Tanpa Toko") sengaja dibuat
        // aktif=false supaya tersembunyi dari pencarian toko biasa —
        // dikecualikan di sini, bukan dianggap toko nonaktif sungguhan.
        if (! $toko->aktif && ! $toko->isInternal()) {
            throw ValidationException::withMessages([
                'toko_id' => __('pesanan.galat_toko_nonaktif', ['nama' => $toko->nama]),
            ]);
        }

        if ($toko->wilayah_id === null) {
            throw ValidationException::withMessages([
                'toko_id' => __('pesanan.galat_toko_tanpa_wilayah', ['nama' => $toko->nama]),
            ]);
        }

        if ($nominalCash < 0 || $nominalTransfer < 0) {
            throw ValidationException::withMessages([
                'nominalCash' => __('pembayaran.galat_nominal_negatif'),
            ]);
        }

        return DB::transaction(function () use ($toko, $items, $bonusItems, $penjual, $nominalCash, $nominalTransfer, $catatan): Pesanan {
            // Stok diperiksa atas permintaan GABUNGAN (biasa + bonus) per
            // produk — sama seperti buat(), produk yang sama boleh muncul
            // di kedua daftar dan totalnya harus muat di stok fisik yang
            // sama, bukan diperiksa dua kali secara terpisah.
            $gabunganPermintaan = [];

            foreach ([...$items, ...$bonusItems] as $item) {
                $gabunganPermintaan[$item['produk_id']] = ($gabunganPermintaan[$item['produk_id']] ?? 0) + $item['jumlah_dus'];
            }

            $produks = Produk::whereIn('id', array_keys($gabunganPermintaan))->lockForUpdate()->get()->keyBy('id');

            foreach ($gabunganPermintaan as $produkId => $jumlahDiminta) {
                $produk = $produks->get($produkId);

                if ($produk === null || ! $produk->aktif) {
                    throw ValidationException::withMessages([
                        'items' => __('pesanan.galat_produk_hilang'),
                    ]);
                }

                if ($jumlahDiminta > $produk->stok_tersedia) {
                    throw ValidationException::withMessages([
                        'items' => __('pesanan.galat_stok_kurang', [
                            'nama' => $produk->nama,
                            'diminta' => $jumlahDiminta,
                            'tersedia' => $produk->stok_tersedia,
                        ]),
                    ]);
                }
            }

            // Dus bonus tetap dus fisik yang sungguh keluar dari rak, jadi
            // ikut dihitung ke total_dus — tapi tidak pernah ke total_nilai
            // (harganya selalu 0, lihat perulangan penyimpanan di bawah).
            $totalDus = array_sum(array_column($items, 'jumlah_dus'))
                + array_sum(array_column($bonusItems, 'jumlah_dus'));

            $totalNilai = 0.0;

            foreach ($items as $item) {
                $totalNilai += (float) $produks->get($item['produk_id'])->harga * $item['jumlah_dus'];
            }

            // Toleransi 1 rupiah untuk pembulatan, sama seperti
            // PelunasanService::tandaiLunas().
            if (abs(($nominalCash + $nominalTransfer) - $totalNilai) > 1.0) {
                throw ValidationException::withMessages([
                    'nominalCash' => __('pembayaran.galat_nominal_tidak_sesuai', [
                        'total' => Bahasa::rupiah($nominalCash + $nominalTransfer),
                        'tagihan' => Bahasa::rupiah($totalNilai),
                    ]),
                ]);
            }

            $pesanan = Pesanan::create([
                'kode' => $this->kodePos(),
                'toko_id' => $toko->id,
                'wilayah_id' => $toko->wilayah_id,
                'dibuat_oleh' => $penjual->id,
                'status' => StatusPesanan::Selesai,
                'jenis' => JenisPesanan::Pos,
                'tanggal' => today(),
                'total_dus' => $totalDus,
                'total_nilai' => $totalNilai,
                'catatan' => $catatan,
                'dikirim_at' => now(),
                'selesai_at' => now(),
                'status_bayar' => StatusBayar::Lunas,
                'tanggal_lunas' => today(),
                'dilunasi_oleh' => $penjual->id,
                'nominal_cash' => $nominalCash,
                'nominal_transfer' => $nominalTransfer,
            ]);

            foreach ($items as $item) {
                $produk = $produks->get($item['produk_id']);
                $subtotal = (float) $produk->harga * $item['jumlah_dus'];

                $pesanan->items()->create([
                    'produk_id' => $produk->id,
                    'jumlah_dus' => $item['jumlah_dus'],
                    'jumlah_dus_terkirim' => $item['jumlah_dus'],
                    'harga_satuan' => (float) $produk->harga,
                    'subtotal' => $subtotal,
                    'is_bonus' => false,
                ]);

                $this->keluarkanStokLangsung($produk, $item['jumlah_dus'], $pesanan, $penjual);
            }

            // Item bonus: harga & subtotal SELALU 0 — sama seperti buat(),
            // stoknya tetap keluar fisik seketika seperti item biasa (baris
            // keluarkanStokLangsung() di bawah tidak beda sama sekali dari
            // baris biasa di atas).
            foreach ($bonusItems as $item) {
                $produk = $produks->get($item['produk_id']);

                $pesanan->items()->create([
                    'produk_id' => $produk->id,
                    'jumlah_dus' => $item['jumlah_dus'],
                    'jumlah_dus_terkirim' => $item['jumlah_dus'],
                    'harga_satuan' => 0,
                    'subtotal' => 0,
                    'is_bonus' => true,
                ]);

                $this->keluarkanStokLangsung($produk, $item['jumlah_dus'], $pesanan, $penjual);
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

    /**
     * Menandai FINAL pesanan yang sebelumnya dibatalkan DRIVER di lapangan
     * (lihat `Pesanan::bisa_order_ulang`) sebagai batal karena toko —
     * dipakai admin ketika memutuskan TIDAK akan order ulang lagi.
     *
     * Cuma mengubah `alasan_cancel` (alasan aslinya dari driver disalin ke
     * `catatan_cancel` supaya tidak hilang) — SENGAJA TIDAK menyentuh stok
     * maupun `dibatalkan_oleh`/`dibatalkan_at` sama sekali. Dus yang masih
     * fisik di mobil driver tetap mengikuti alur kampas/selesaikanKendaraan
     * yang sudah ada (PengirimanService) — lepas sama sekali dari tindakan
     * ini, yang murni soal pencatatan alasan akhir, bukan soal stok.
     * Siapa yang sungguh membatalkan (driver, di lapangan) dan kapan tetap
     * apa adanya, supaya jejak auditnya tidak berubah jadi seolah-olah
     * admin sendiri yang membatalkan.
     */
    public function tandaiBatalKarenaToko(Pesanan $pesanan, User $admin): void
    {
        if (! $pesanan->bisa_order_ulang) {
            throw new RuntimeException(__('pesanan.galat_bukan_batal_lapangan'));
        }

        $catatan = __('pesanan.catatan_alasan_awal', ['alasan' => $pesanan->alasan_cancel]);

        if ($pesanan->catatan_cancel) {
            $catatan .= "\n".$pesanan->catatan_cancel;
        }

        $pesanan->update([
            'alasan_cancel' => __('pesanan.alasan_toko_batal'),
            'catatan_cancel' => $catatan,
        ]);
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

    /**
     * Dipakai HANYA untuk item yang sebelumnya lewat `kunciStok()` untuk
     * pesanan yang SAMA (alur buat() → selesaikanPengiriman()/coretNota()) —
     * `$jumlah` di sini diasumsikan persis sama dengan yang dikunci
     * sebelumnya, supaya `stok_reserved` (kuncian GLOBAL per produk, bukan
     * per pesanan) terkurangi tepat sebesar kuncian milik pesanan ini saja.
     * Untuk stok yang keluar TANPA pernah dikunci lebih dulu (POS), pakai
     * `keluarkanStokLangsung()` — memotong `stok_reserved` di sini akan
     * salah sasaran, mengurangi kuncian pesanan lain yang tidak ada
     * hubungannya sama sekali.
     */
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
     * Mengeluarkan stok fisik untuk transaksi yang TIDAK PERNAH lewat fase
     * kuncian (POS — lihat buatPos()): barangnya langsung dari rak lalu
     * langsung ke pembeli dalam satu langkah, tidak pernah melalui
     * `kunciStok()` lebih dulu. Hanya `stok` fisik yang berkurang; SENGAJA
     * TIDAK menyentuh `stok_reserved` sama sekali — beda dari
     * `keluarkanStok()` yang melepas kuncian milik pesanan ITU SENDIRI,
     * di sini tidak ada kuncian yang dimiliki transaksi ini yang perlu
     * dilepas. Availabilitasnya sudah dipastikan aman lewat pemeriksaan
     * `stok_tersedia` di buatPos() sebelum method ini dipanggil.
     */
    private function keluarkanStokLangsung(Produk $produk, int $jumlah, Pesanan $pesanan, User $user): void
    {
        $produk->decrement('stok', min($jumlah, $produk->stok));
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
        // withTrashed(): kode tidak boleh dipakai ulang meskipun pesanan
        // sebelumnya di hari yang sama sudah di-soft-delete — kode punya
        // batasan unik yang tetap menghitung baris yang di-soft-delete.
        $urutan = Pesanan::withTrashed()->whereDate('created_at', today())->count() + 1;

        return sprintf('%s-%04d', $prefix, $urutan);
    }

    private function kodePos(): string
    {
        $prefix = 'POS-'.now()->format('Ymd');
        $urutan = Pesanan::withTrashed()->where('jenis', JenisPesanan::Pos)->whereDate('created_at', today())->count() + 1;

        return sprintf('%s-%04d', $prefix, $urutan);
    }
}
