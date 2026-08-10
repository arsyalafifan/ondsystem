<?php

namespace App\Services\Kunjungan;

use App\Models\PenugasanSales;
use App\Models\PeriodeKunjungan;
use App\Models\PeriodeSales;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mengurus periode kunjungan mingguan.
 *
 * Satu periode berjalan Senin sampai Sabtu. Begitu masuk Senin berikutnya,
 * periode baru dibuka dan hitungan dimulai dari nol — periode lama tidak
 * dihapus, hanya ditutup, sehingga riwayat minggu-minggu sebelumnya tetap
 * bisa dibuka.
 *
 * Periode dibuka sendiri saat pertama kali dibutuhkan, jadi sistem tetap
 * berjalan benar meski penjadwal tugas berkala kebetulan tidak aktif.
 */
class PeriodeKunjunganService
{
    /** Periode untuk tanggal tertentu; dibuat bila belum ada. */
    public function periodeUntuk(?CarbonImmutable $tanggal = null): PeriodeKunjungan
    {
        $tanggal = $tanggal ?? CarbonImmutable::today();

        $mulai = $this->awalPekan($tanggal);
        $selesai = $mulai->addDays((int) config('visit.hari_selesai') - (int) config('visit.hari_mulai'));

        $periode = PeriodeKunjungan::query()
            ->whereDate('tanggal_mulai', $mulai->toDateString())
            ->first();

        if ($periode !== null) {
            return $periode;
        }

        return DB::transaction(function () use ($mulai, $selesai): PeriodeKunjungan {
            // Dikunci lewat firstOrCreate agar dua sales yang membuka aplikasi
            // bersamaan pada Senin pagi tidak membuat dua periode kembar.
            $periode = PeriodeKunjungan::firstOrCreate(
                ['tahun' => $mulai->isoWeekYear, 'minggu' => $mulai->isoWeek],
                [
                    'kode' => sprintf('VST-%d-W%02d', $mulai->isoWeekYear, $mulai->isoWeek),
                    'tanggal_mulai' => $mulai->toDateString(),
                    'tanggal_selesai' => $selesai->toDateString(),
                    'status' => 'berjalan',
                ],
            );

            if ($periode->wasRecentlyCreated) {
                $this->tutupPeriodeLama($periode);
                $this->siapkanSales($periode);
            }

            return $periode;
        });
    }

    /** Periode yang sedang berjalan hari ini. */
    public function periodeBerjalan(): PeriodeKunjungan
    {
        return $this->periodeUntuk();
    }

    /**
     * Menyiapkan baris progres untuk setiap sales aktif, lengkap dengan
     * salinan jumlah tanggungannya bulan ini.
     */
    public function siapkanSales(PeriodeKunjungan $periode): void
    {
        $bulan = $periode->tanggal_mulai->copy()->startOfMonth()->toDateString();

        $jumlahPerSales = PenugasanSales::query()
            ->whereDate('bulan', $bulan)
            ->selectRaw('sales_id, count(*) as jumlah')
            ->groupBy('sales_id')
            ->pluck('jumlah', 'sales_id');

        foreach (User::sales()->get() as $sales) {
            PeriodeSales::firstOrCreate(
                ['periode_kunjungan_id' => $periode->id, 'sales_id' => $sales->id],
                ['target_toko' => (int) ($jumlahPerSales[$sales->id] ?? 0)],
            );
        }
    }

    /**
     * Menyegarkan angka target periode berjalan setelah admin mengubah
     * penugasan. Dipakai supaya perubahan langsung terlihat, tanpa menunggu
     * minggu berikutnya.
     */
    public function segarkanTarget(PeriodeKunjungan $periode): void
    {
        $bulan = $periode->tanggal_mulai->copy()->startOfMonth()->toDateString();

        $jumlahPerSales = PenugasanSales::query()
            ->whereDate('bulan', $bulan)
            ->selectRaw('sales_id, count(*) as jumlah')
            ->groupBy('sales_id')
            ->pluck('jumlah', 'sales_id');

        $this->siapkanSales($periode);

        foreach ($periode->periodeSales()->get() as $baris) {
            $baru = (int) ($jumlahPerSales[$baris->sales_id] ?? 0);

            if ($baris->target_toko !== $baru) {
                $baris->update(['target_toko' => $baru]);
            }
        }
    }

    /** Menutup semua periode yang tanggal selesainya sudah lewat. */
    public function tutupPeriodeLama(?PeriodeKunjungan $kecuali = null): int
    {
        return PeriodeKunjungan::query()
            ->where('status', 'berjalan')
            ->when($kecuali !== null, fn ($q) => $q->whereKeyNot($kecuali->id))
            ->whereDate('tanggal_selesai', '<', CarbonImmutable::today()->toDateString())
            ->update(['status' => 'selesai', 'updated_at' => now()]);
    }

    /** Senin pada pekan tanggal tersebut. */
    private function awalPekan(CarbonImmutable $tanggal): CarbonImmutable
    {
        return $tanggal->startOfWeek(CarbonImmutable::MONDAY);
    }
}
