<?php

namespace App\Services\TransferStok;

use App\Enums\JenisMutasiStok;
use App\Enums\StatusTransferStok;
use App\Models\Depot;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\TransferStok;
use App\Models\TransferStokFoto;
use App\Models\User;
use App\Support\DepotContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aturan main Transfer Stok: memindahkan stok fisik antar gudang
 * penyimpanan (App\Models\Depot::$gudang_penyimpanan).
 *
 * Beda dari NOO/Tarik Freezer, tidak ada tahap persetujuan admin terpisah —
 * begitu gudang asal menekan kirim, stoknya LANGSUNG berpindah (dikurangi
 * dari asal). Gudang tujuan tinggal menerima (boleh merevisi jumlah kalau
 * barang yang sampai kurang) atau gudang asal membatalkannya selama belum
 * diterima.
 */
class TransferStokService
{
    /**
     * @param  array<int, array{produk_id: int, jumlah: int}>  $baris  produk_id merujuk katalog gudang ASAL
     *
     * @throws RuntimeException
     */
    public function kirim(Depot $asal, Depot $tujuan, array $baris, ?string $catatan, User $pengirim): TransferStok
    {
        if ($asal->is($tujuan)) {
            throw new RuntimeException(__('transfer_stok.galat_asal_tujuan_sama'));
        }

        if (! $asal->gudang_penyimpanan || ! $tujuan->gudang_penyimpanan) {
            throw new RuntimeException(__('transfer_stok.galat_bukan_gudang_penyimpanan'));
        }

        $baris = $this->gabungkanBaris($baris);

        if ($baris === []) {
            throw new RuntimeException(__('transfer_stok.galat_item_kosong'));
        }

        return DB::transaction(function () use ($asal, $tujuan, $baris, $catatan, $pengirim): TransferStok {
            $transfer = TransferStok::create([
                'kode' => $this->kodeBerikutnya(),
                'status' => StatusTransferStok::Dikirim,
                'depot_asal_id' => $asal->id,
                'depot_tujuan_id' => $tujuan->id,
                'dikirim_oleh' => $pengirim->id,
                'dikirim_at' => now(),
                'catatan_kirim' => $catatan,
            ]);

            foreach ($baris as $satu) {
                $produkAsal = Produk::query()->whereKey($satu['produk_id'])->where('depot_id', $asal->id)->first();

                if ($produkAsal === null) {
                    throw new RuntimeException(__('transfer_stok.galat_produk_tidak_ditemukan'));
                }

                if ($satu['jumlah'] <= 0) {
                    throw new RuntimeException(__('transfer_stok.galat_jumlah_tidak_valid', ['produk' => $produkAsal->nama]));
                }

                if ($satu['jumlah'] > $produkAsal->stok_tersedia) {
                    throw new RuntimeException(__('transfer_stok.galat_stok_kurang', [
                        'produk' => $produkAsal->nama,
                        'tersedia' => $produkAsal->stok_tersedia,
                    ]));
                }

                $produkTujuan = $this->carikanAtauBuatkanProdukTujuan($produkAsal, $tujuan);

                $transfer->items()->create([
                    'produk_asal_id' => $produkAsal->id,
                    'produk_tujuan_id' => $produkTujuan->id,
                    'jumlah_kirim' => $satu['jumlah'],
                ]);

                $produkAsal->decrement('stok', $satu['jumlah']);
                $produkAsal->refresh();

                StokMutasi::create([
                    'produk_id' => $produkAsal->id,
                    'tipe' => JenisMutasiStok::TransferKeluar,
                    'jumlah' => -$satu['jumlah'],
                    'stok_sesudah' => $produkAsal->stok,
                    'reserved_sesudah' => $produkAsal->stok_reserved,
                    'keterangan' => __('transfer_stok.mutasi_keluar', ['kode' => $transfer->kode, 'gudang' => $tujuan->nama]),
                    'user_id' => $pengirim->id,
                ]);
            }

            return $transfer;
        });
    }

    /** @throws RuntimeException bila statusnya bukan lagi Dikirim */
    public function batalkan(TransferStok $transfer, User $admin, ?string $alasan): void
    {
        if ($transfer->status !== StatusTransferStok::Dikirim) {
            throw new RuntimeException(__('transfer_stok.galat_bukan_dikirim', ['kode' => $transfer->kode]));
        }

        DB::transaction(function () use ($transfer, $admin, $alasan): void {
            foreach ($transfer->items()->with('produkAsal')->get() as $item) {
                $produkAsal = $item->produkAsal;

                $produkAsal->increment('stok', $item->jumlah_kirim);
                $produkAsal->refresh();

                StokMutasi::create([
                    'produk_id' => $produkAsal->id,
                    'tipe' => JenisMutasiStok::TransferMasuk,
                    'jumlah' => $item->jumlah_kirim,
                    'stok_sesudah' => $produkAsal->stok,
                    'reserved_sesudah' => $produkAsal->stok_reserved,
                    'keterangan' => __('transfer_stok.mutasi_batal', ['kode' => $transfer->kode]),
                    'user_id' => $admin->id,
                ]);
            }

            $transfer->update([
                'status' => StatusTransferStok::Dibatalkan,
                'dibatalkan_oleh' => $admin->id,
                'dibatalkan_at' => now(),
                'alasan_batal' => $alasan,
            ]);
        });
    }

    /**
     * Menerima transfer: boleh merevisi jumlah per baris (barang yang
     * sampai kurang dari yang dikirim), lalu stok fisik gudang TUJUAN
     * baru bertambah sebesar jumlah yang BENAR-BENAR diterima — bukan
     * jumlah yang dikirim.
     *
     * @param  array<int, int>  $jumlahTerima  dikunci pada id TransferStokItem
     * @param  array<int, string>  $fotoPaths  path berkas yang sudah tersimpan di disk
     *
     * @throws RuntimeException bila statusnya bukan lagi Dikirim
     */
    public function terima(TransferStok $transfer, array $jumlahTerima, ?string $catatan, array $fotoPaths, User $admin): void
    {
        if ($transfer->status !== StatusTransferStok::Dikirim) {
            throw new RuntimeException(__('transfer_stok.galat_bukan_dikirim', ['kode' => $transfer->kode]));
        }

        DB::transaction(function () use ($transfer, $jumlahTerima, $catatan, $fotoPaths, $admin): void {
            foreach ($transfer->items()->with('produkTujuan')->get() as $item) {
                // Diklem ke jumlah kirim di server — tidak pernah percaya
                // begitu saja angka yang datang dari klien bisa melebihi
                // yang sungguh dikirim.
                $diterima = max(0, min((int) ($jumlahTerima[$item->id] ?? $item->jumlah_kirim), $item->jumlah_kirim));

                $item->update(['jumlah_terima' => $diterima]);

                if ($diterima === 0) {
                    continue;
                }

                $produkTujuan = $item->produkTujuan;

                $produkTujuan->increment('stok', $diterima);
                $produkTujuan->refresh();

                StokMutasi::create([
                    'produk_id' => $produkTujuan->id,
                    'tipe' => JenisMutasiStok::TransferMasuk,
                    'jumlah' => $diterima,
                    'stok_sesudah' => $produkTujuan->stok,
                    'reserved_sesudah' => $produkTujuan->stok_reserved,
                    'keterangan' => __('transfer_stok.mutasi_masuk', ['kode' => $transfer->kode, 'gudang' => $transfer->depotAsal->nama]),
                    'user_id' => $admin->id,
                ]);
            }

            foreach ($fotoPaths as $path) {
                TransferStokFoto::create([
                    'transfer_stok_id' => $transfer->id,
                    'path' => $path,
                    'diunggah_oleh' => $admin->id,
                ]);
            }

            $transfer->update([
                'status' => StatusTransferStok::Diterima,
                'diterima_oleh' => $admin->id,
                'diterima_at' => now(),
                'catatan_terima' => $catatan,
            ]);
        });
    }

    /**
     * Produk padanan di gudang tujuan, dicocokkan lewat kode. Belum ada?
     * Dibuatkan otomatis (nama/satuan/harga disalin dari gudang asal,
     * stok mulai dari nol) — supaya gudang tujuan tidak perlu mendaftarkan
     * produknya sendiri dulu sebelum bisa menerima kiriman pertamanya.
     */
    private function carikanAtauBuatkanProdukTujuan(Produk $produkAsal, Depot $tujuan): Produk
    {
        return DepotContext::jalankanSebagai($tujuan, function () use ($produkAsal): Produk {
            $produkTujuan = Produk::where('kode', $produkAsal->kode)->first();

            if ($produkTujuan !== null) {
                return $produkTujuan;
            }

            return Produk::create([
                'kode' => $produkAsal->kode,
                'barcode' => $produkAsal->barcode,
                'nama' => $produkAsal->nama,
                'satuan' => $produkAsal->satuan,
                'harga' => $produkAsal->harga,
                'aktif' => true,
            ]);
        });
    }

    /** Baris dengan produk_id sama digabung, supaya tidak ada dua baris transfer untuk produk yang sama. */
    private function gabungkanBaris(array $baris): array
    {
        $jumlahPerProduk = [];

        foreach ($baris as $satu) {
            $produkId = (int) ($satu['produk_id'] ?? 0);
            $jumlah = (int) ($satu['jumlah'] ?? 0);

            if ($produkId <= 0 || $jumlah <= 0) {
                continue;
            }

            $jumlahPerProduk[$produkId] = ($jumlahPerProduk[$produkId] ?? 0) + $jumlah;
        }

        return collect($jumlahPerProduk)
            ->map(fn (int $jumlah, int $produkId): array => ['produk_id' => $produkId, 'jumlah' => $jumlah])
            ->values()
            ->all();
    }

    /** Kode transfer: TRF-Ymd-####, unik GLOBAL (lihat docblock migrasi — tidak ada depot tunggal yang memilikinya). */
    private function kodeBerikutnya(): string
    {
        $hariIni = now()->format('Ymd');
        $urutan = TransferStok::withTrashed()->whereDate('created_at', today())->count() + 1;

        return sprintf('TRF-%s-%04d', $hariIni, $urutan);
    }
}
