<?php

namespace App\Services\Kunjungan;

use App\Models\PenugasanSales;
use App\Models\Toko;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyusun daftar toko yang menjadi tanggungan tiap sales, per bulan.
 *
 * Dua aturan yang dijaga: satu toko hanya boleh dipegang satu sales pada bulan
 * yang sama, dan seorang sales tidak boleh memegang lebih dari batas yang
 * ditetapkan. Keduanya diperiksa di sini sekaligus dijamin oleh batasan unik
 * di basis data, agar dua admin yang bekerja bersamaan tidak bisa menembusnya.
 */
class PenugasanService
{
    public function maksToko(): int
    {
        return (int) config('visit.maks_toko_per_sales');
    }

    /** Bulan penugasan selalu ditulis sebagai tanggal 1. */
    public function bulan(?string $bulan = null): string
    {
        return $bulan === null
            ? CarbonImmutable::today()->startOfMonth()->toDateString()
            : CarbonImmutable::parse($bulan)->startOfMonth()->toDateString();
    }

    /**
     * Menetapkan daftar toko seorang sales untuk satu bulan, menggantikan
     * daftar sebelumnya.
     *
     * @param  array<int, int>  $tokoIds
     * @return array{ditambah: int, dihapus: int, ditolak: array<int, string>}
     *
     * @throws RuntimeException bila melebihi batas jumlah toko
     */
    public function tetapkan(User $sales, array $tokoIds, string $bulan, User $admin): array
    {
        $bulan = $this->bulan($bulan);
        $tokoIds = array_values(array_unique(array_map('intval', $tokoIds)));

        if (count($tokoIds) > $this->maksToko()) {
            throw new RuntimeException(__('kunjungan.galat_melebihi_batas', [
                'jumlah' => count($tokoIds),
                'batas' => $this->maksToko(),
            ]));
        }

        return DB::transaction(function () use ($sales, $tokoIds, $bulan, $admin): array {
            $sekarang = PenugasanSales::query()
                ->where('sales_id', $sales->id)
                ->whereDate('bulan', $bulan)
                ->pluck('toko_id')
                ->all();

            $dihapus = array_diff($sekarang, $tokoIds);
            $ditambah = array_diff($tokoIds, $sekarang);

            if ($dihapus !== []) {
                PenugasanSales::query()
                    ->where('sales_id', $sales->id)
                    ->whereDate('bulan', $bulan)
                    ->whereIn('toko_id', $dihapus)
                    ->delete();
            }

            $ditolak = [];
            $berhasil = 0;

            foreach ($ditambah as $tokoId) {
                try {
                    PenugasanSales::create([
                        'sales_id' => $sales->id,
                        'toko_id' => $tokoId,
                        'bulan' => $bulan,
                        'ditugaskan_oleh' => $admin->id,
                    ]);

                    $berhasil++;
                } catch (QueryException) {
                    // Batasan unik (toko_id, bulan) menolak: toko sudah jadi
                    // tanggungan sales lain pada bulan yang sama.
                    $pemilik = $this->pemegang($tokoId, $bulan);

                    $ditolak[] = __('kunjungan.galat_toko_sudah_dipegang', [
                        'toko' => Toko::whereKey($tokoId)->value('nama') ?? "#{$tokoId}",
                        'sales' => $pemilik?->name ?? '?',
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

    /** Menyalin seluruh penugasan satu bulan ke bulan berikutnya. */
    public function salinKeBulan(string $dariBulan, string $keBulan, User $admin): int
    {
        $dariBulan = $this->bulan($dariBulan);
        $keBulan = $this->bulan($keBulan);

        if ($dariBulan === $keBulan) {
            throw new RuntimeException(__('kunjungan.galat_bulan_sama'));
        }

        $sumber = PenugasanSales::query()->whereDate('bulan', $dariBulan)->get();

        if ($sumber->isEmpty()) {
            throw new RuntimeException(__('kunjungan.galat_bulan_sumber_kosong'));
        }

        return DB::transaction(function () use ($sumber, $keBulan, $admin): int {
            $sudahAda = PenugasanSales::query()
                ->whereDate('bulan', $keBulan)
                ->pluck('toko_id')
                ->all();

            $baris = $sumber
                ->reject(fn (PenugasanSales $p) => in_array($p->toko_id, $sudahAda, true))
                ->map(fn (PenugasanSales $p) => [
                    'sales_id' => $p->sales_id,
                    'toko_id' => $p->toko_id,
                    'bulan' => $keBulan,
                    'ditugaskan_oleh' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

            if ($baris !== []) {
                PenugasanSales::insert($baris);
            }

            return count($baris);
        });
    }

    /** Sales yang memegang sebuah toko pada bulan tertentu. */
    public function pemegang(int $tokoId, string $bulan): ?User
    {
        $penugasan = PenugasanSales::query()
            ->with('sales:id,name')
            ->where('toko_id', $tokoId)
            ->whereDate('bulan', $this->bulan($bulan))
            ->first();

        return $penugasan?->sales;
    }

    /**
     * Jumlah toko yang dipegang tiap sales pada satu bulan.
     *
     * @return Collection<int, int>
     */
    public function jumlahPerSales(string $bulan): Collection
    {
        return PenugasanSales::query()
            ->whereDate('bulan', $this->bulan($bulan))
            ->selectRaw('sales_id, count(*) as jumlah')
            ->groupBy('sales_id')
            ->pluck('jumlah', 'sales_id');
    }

    /**
     * Toko yang belum dipegang siapa pun pada bulan tersebut, jadi masih bisa
     * ditugaskan.
     */
    public function tokoTersedia(string $bulan, ?int $kecualiSalesId = null)
    {
        $bulan = $this->bulan($bulan);

        return Toko::query()
            ->aktif()
            ->whereDoesntHave('penugasans', function ($q) use ($bulan, $kecualiSalesId): void {
                $q->whereDate('bulan', $bulan)
                    ->when($kecualiSalesId !== null, fn ($w) => $w->where('sales_id', '!=', $kecualiSalesId));
            });
    }
}
