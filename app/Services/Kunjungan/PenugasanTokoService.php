<?php

namespace App\Services\Kunjungan;

use App\Enums\HariKunjungan;
use App\Models\PengaturanKunjungan;
use App\Models\PenugasanToko;
use App\Models\PenugasanTokoDefault;
use App\Models\Toko;
use App\Models\User;
use App\Support\DepotContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyusun jadwal kunjungan MINGGUAN tiap sales — satu toko selamanya
 * berada di satu slot hari untuk satu sales, sampai admin sendiri yang
 * mengubahnya (lihat dokumentasi `PenugasanToko`). Pengganti
 * `PenugasanService` lama yang menyusun ulang penugasan tiap bulan —
 * tabel lamanya (`PenugasanSales`) dibiarkan apa adanya untuk riwayat,
 * tidak lagi dibaca/ditulis di sini.
 *
 * Dua aturan yang dijaga: satu toko hanya boleh dipegang satu sales pada
 * satu hari (berlaku GLOBAL, bukan cuma dalam satu hari yang sama —
 * begitu toko masuk Senin, ia tidak bisa masuk Selasa dkk sampai
 * dilepas), dan satu hari tidak boleh diisi lebih dari batas yang bisa
 * admin atur sendiri (`PengaturanKunjungan::maks_toko_per_hari`).
 */
class PenugasanTokoService
{
    public function maksPerHari(): int
    {
        return PengaturanKunjungan::ambil()->maks_toko_per_hari;
    }

    public function ubahMaksPerHari(int $baru): void
    {
        if ($baru < 1) {
            throw new RuntimeException(__('kunjungan.galat_maks_tidak_valid'));
        }

        PengaturanKunjungan::ambil()->update(['maks_toko_per_hari' => $baru]);
    }

    /**
     * Menetapkan daftar toko seorang sales untuk SATU hari, menggantikan
     * daftar hari itu saja — hari lain untuk sales yang sama tidak
     * tersentuh sama sekali.
     *
     * @param  array<int, int>  $tokoIds
     * @return array{ditambah: int, dihapus: int, ditolak: array<int, string>}
     *
     * @throws RuntimeException bila melebihi batas jumlah toko per hari
     */
    public function tetapkan(User $sales, HariKunjungan $hari, array $tokoIds, User $admin): array
    {
        $tokoIds = array_values(array_unique(array_map('intval', $tokoIds)));

        if (count($tokoIds) > $this->maksPerHari()) {
            throw new RuntimeException(__('kunjungan.galat_melebihi_batas', [
                'jumlah' => count($tokoIds),
                'batas' => $this->maksPerHari(),
            ]));
        }

        return DB::transaction(function () use ($sales, $hari, $tokoIds, $admin): array {
            $sekarang = PenugasanToko::query()
                ->where('sales_id', $sales->id)
                ->where('hari', $hari->value)
                ->pluck('toko_id')
                ->all();

            $dihapus = array_diff($sekarang, $tokoIds);
            $ditambah = array_diff($tokoIds, $sekarang);

            if ($dihapus !== []) {
                PenugasanToko::query()
                    ->where('sales_id', $sales->id)
                    ->where('hari', $hari->value)
                    ->whereIn('toko_id', $dihapus)
                    ->delete();
            }

            $ditolak = [];
            $berhasil = 0;

            foreach ($ditambah as $tokoId) {
                try {
                    PenugasanToko::create([
                        'toko_id' => $tokoId,
                        'sales_id' => $sales->id,
                        'hari' => $hari,
                        'ditugaskan_oleh' => $admin->id,
                    ]);

                    $berhasil++;
                } catch (QueryException) {
                    // Batasan unik toko_id menolak: toko sudah dijadwalkan
                    // di hari/sales lain.
                    $pemegang = $this->pemegang($tokoId);

                    $ditolak[] = __('kunjungan.galat_toko_sudah_dipegang', [
                        'toko' => Toko::whereKey($tokoId)->value('nama') ?? "#{$tokoId}",
                        'sales' => $pemegang?->sales?->name ?? '?',
                    ]);
                }
            }

            return [
                'ditambah' => $berhasil,
                'dihapus' => count($dihapus),
                'ditolak' => $ditolak,
            ];
        });
    }

    /**
     * Menyalin seluruh jadwal MINGGUAN sales ini (semua hari) menjadi
     * "default"-nya — menimpa default lama sepenuhnya, bukan menambah.
     * Dipakai lewat tombol "Jadikan Default".
     */
    public function jadikanDefault(User $sales): void
    {
        DB::transaction(function () use ($sales): void {
            PenugasanTokoDefault::query()->where('sales_id', $sales->id)->delete();

            // insert() adalah bulk insert query builder — TIDAK memicu event
            // creating Eloquent, jadi auto-stamp depot_id di trait BerDepot
            // tidak pernah jalan untuk baris-baris ini. depot_id harus
            // disebut manual di sini, atau baris yang tersimpan tidak akan
            // pernah ketemu lagi lewat query manapun (semuanya di-scope).
            $depotId = DepotContext::currentOrFail()->id;

            $baris = PenugasanToko::query()
                ->where('sales_id', $sales->id)
                ->get(['toko_id', 'hari'])
                ->map(fn (PenugasanToko $p) => [
                    'depot_id' => $depotId,
                    'sales_id' => $sales->id,
                    'toko_id' => $p->toko_id,
                    'hari' => $p->hari->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($baris !== []) {
                PenugasanTokoDefault::insert($baris);
            }
        });
    }

    /**
     * Mengembalikan jadwal MINGGUAN sales ini (semua hari) ke default yang
     * tersimpan — full replace, sama seperti tetapkan() tapi mencakup
     * seluruh minggu sekaligus. Toko yang tersimpan di default tapi kini
     * sudah dipegang sales lain dilewati dan dilaporkan, bukan direbut
     * paksa.
     *
     * @return array{dipulihkan: int, dihapus: int, ditolak: array<int, string>}
     *
     * @throws RuntimeException bila belum pernah ada default tersimpan untuk sales ini
     */
    public function restoreDefault(User $sales, User $admin): array
    {
        $default = PenugasanTokoDefault::query()->where('sales_id', $sales->id)->get();

        if ($default->isEmpty()) {
            throw new RuntimeException(__('kunjungan.galat_belum_ada_default'));
        }

        return DB::transaction(function () use ($sales, $default, $admin): array {
            $dihapus = PenugasanToko::query()->where('sales_id', $sales->id)->delete();

            $ditolak = [];
            $berhasil = 0;

            foreach ($default as $baris) {
                try {
                    PenugasanToko::create([
                        'toko_id' => $baris->toko_id,
                        'sales_id' => $sales->id,
                        'hari' => $baris->hari,
                        'ditugaskan_oleh' => $admin->id,
                    ]);

                    $berhasil++;
                } catch (QueryException) {
                    $pemegang = $this->pemegang($baris->toko_id);

                    $ditolak[] = __('kunjungan.galat_toko_sudah_dipegang', [
                        'toko' => Toko::whereKey($baris->toko_id)->value('nama') ?? "#{$baris->toko_id}",
                        'sales' => $pemegang?->sales?->name ?? '?',
                    ]);
                }
            }

            return [
                'dipulihkan' => $berhasil,
                'dihapus' => $dihapus,
                'ditolak' => $ditolak,
            ];
        });
    }

    /** Baris jadwal (lengkap dengan sales-nya) yang sedang memegang sebuah toko, kalau ada. */
    public function pemegang(int $tokoId): ?PenugasanToko
    {
        return PenugasanToko::query()->with('sales:id,name')->where('toko_id', $tokoId)->first();
    }

    /**
     * Jumlah toko yang dipegang tiap sales, dijumlahkan dari SELURUH hari
     * — dipakai anotasi daftar sales di layar Penugasan Toko.
     *
     * @return Collection<int, int>
     */
    public function jumlahPerSales(): Collection
    {
        return PenugasanToko::query()
            ->selectRaw('sales_id, count(*) as jumlah')
            ->groupBy('sales_id')
            ->pluck('jumlah', 'sales_id');
    }

    /**
     * Progres kelengkapan data profil toko per sales, dihitung dari
     * SELURUH jadwal mingguannya (Senin-Minggu digabung, tidak memandang
     * hari) — dasar layar "Progres Lengkapi Data Toko". "Lengkap" di sini
     * memakai accessor `Toko::profil_lengkap` yang sama persis dengan
     * badge di layar Lengkapi Data Toko, supaya keduanya tidak pernah
     * bisa melenceng satu sama lain lewat dua kali logika yang beda.
     *
     * Dimuat lalu dihitung di memori (bukan lewat SQL agregat) justru
     * SUPAYA bisa memakai ulang accessor itu — skalanya (ratusan/ribuan
     * baris penugasan per depot, bukan puluhan ribu) membuat ini tetap
     * murah.
     *
     * @return Collection<int, array{total: int, lengkap: int}> dikunci sales_id
     */
    public function progresLengkapiData(): Collection
    {
        return PenugasanToko::query()
            ->with('toko:id,nama_pemilik,nik_pemilik,alamat,asset_id,telepon')
            ->get(['sales_id', 'toko_id'])
            ->groupBy('sales_id')
            ->map(fn (Collection $baris) => [
                'total' => $baris->count(),
                'lengkap' => $baris->filter(fn (PenugasanToko $p) => $p->toko?->profil_lengkap === true)->count(),
            ]);
    }

    /**
     * Jumlah toko per hari untuk SATU sales — dasar strip ringkasan
     * mingguan (Senin: 5, Selasa: 8, dst.) di layar Penugasan Toko.
     *
     * @return array<int, int> kunci = nilai HariKunjungan (1-7)
     */
    public function jumlahPerHariUntukSales(int $salesId): array
    {
        $jumlah = PenugasanToko::query()
            ->where('sales_id', $salesId)
            ->selectRaw('hari, count(*) as jumlah')
            ->groupBy('hari')
            ->pluck('jumlah', 'hari');

        return collect(HariKunjungan::cases())
            ->mapWithKeys(fn (HariKunjungan $h) => [$h->value => (int) ($jumlah[$h->value] ?? 0)])
            ->all();
    }

    /**
     * Toko yang boleh dipilih untuk slot (sales, hari) ini: belum
     * dijadwalkan sama sekali, ATAU kebetulan sudah jadi milik slot yang
     * SAMA PERSIS ini (supaya pilihan yang sudah tersimpan tetap tampil,
     * bukan menghilang begitu tabnya dibuka).
     */
    public function tokoTersedia(HariKunjungan $hari, int $salesId)
    {
        return Toko::query()
            ->aktif()
            ->whereDoesntHave('penugasanToko', function ($q) use ($hari, $salesId): void {
                $q->where(fn ($w) => $w->where('sales_id', '!=', $salesId)->orWhere('hari', '!=', $hari->value));
            });
    }
}
