<?php

namespace App\Console\Commands;

use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use App\Services\RoutingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Memperbaiki kendaraan yang garis rutenya (geometry) sudah tidak lagi
 * melewati toko-tokonya sendiri.
 *
 * Ini membereskan data lama dari sebelum koreksi koordinat toko otomatis
 * memicu hitung ulang (lihat RoutingService::hitungUlangUntukToko). Rute
 * yang digenerate sebelum koordinat tokonya diperbaiki tetap menyimpan
 * garis rute versi koordinat lama, sementara peta menggambar penandanya
 * dari koordinat toko yang sekarang — garis dan titiknya jadi tidak
 * nyambung walau sopir belum melakukan aksi lapangan apa pun.
 *
 * Kendaraan yang garis rutenya masih cocok dilewati, jadi perintah ini
 * aman dijalankan berulang dan tidak membebani OSRM tanpa perlu.
 */
class PerbaikiGeometryRute extends Command
{
    protected $signature = 'rute:perbaiki-geometry
        {--urutkan-ulang : Sekalian optimalkan urutan kunjungan, bukan cuma garis rutenya}
        {--toleransi=300 : Jarak maksimum (meter) antara toko dan garis rute sebelum dianggap tidak nyambung}
        {--dry-run : Hanya melaporkan, tanpa mengubah apa pun}';

    protected $description = 'Menghitung ulang garis rute kendaraan yang sudah tidak lagi melewati toko-tokonya';

    public function handle(RoutingService $service): int
    {
        $toleransi = (float) $this->option('toleransi');
        $kering = (bool) $this->option('dry-run');
        $urutkanUlang = (bool) $this->option('urutkan-ulang');

        // batch ikut diambil di depan: hitungUlang() memperbarui total batch
        // lewat $kendaraan->batch, dan memuatnya belakangan satu per satu
        // melanggar mode ketat (lazy loading) begitu kendaraannya lebih dari
        // satu — persis keadaan nyata di lapangan.
        $kendaraans = Kendaraan::query()
            ->where('status', '!=', 'selesai')
            ->with(['batch', 'stops.toko:id,nama,latitude,longitude'])
            ->orderBy('id')
            ->get();

        $this->info("Memeriksa {$kendaraans->count()} kendaraan yang belum selesai (toleransi {$toleransi} m).");
        $this->newLine();

        $perlu = $kendaraans->filter(fn (Kendaraan $k) => $this->tidakNyambung($k, $toleransi));

        if ($perlu->isEmpty()) {
            $this->info('Semua garis rute sudah cocok dengan koordinat tokonya. Tidak ada yang perlu diperbaiki.');

            return self::SUCCESS;
        }

        $baris = [];

        $adaYangSudahJalan = false;

        foreach ($perlu as $kendaraan) {
            $jarakSebelum = $kendaraan->total_jarak_m;

            // Rute yang sudah dijalani TIDAK boleh diurutkan ulang: sopirnya
            // sudah menyelesaikan sebagian kunjungan, dan mengacak urutannya
            // sekarang akan memindahkan toko yang sudah dilewati. Garis
            // rutenya tetap diperbaiki — itu memang yang bikin peta salah.
            $sudahJalan = $kendaraan->stops->contains(fn (KendaraanStop $s) => $s->status->tuntas());
            $adaYangSudahJalan = $adaYangSudahJalan || $sudahJalan;

            if (! $kering) {
                $urutkanUlang && ! $sudahJalan
                    ? $service->optimalkanUlang($kendaraan)
                    : $service->hitungUlang($kendaraan);

                $kendaraan->refresh();
            }

            $baris[] = [
                $kendaraan->id,
                $kendaraan->nama,
                $kendaraan->stops->count(),
                number_format($jarakSebelum / 1000, 1).' km',
                $kering ? '-' : number_format($kendaraan->total_jarak_m / 1000, 1).' km',
                $sudahJalan ? 'sudah jalan — urutan dipertahankan' : '',
            ];
        }

        $this->table(['ID', 'Mobil', 'Toko', 'Jarak sebelum', 'Jarak sesudah', 'Catatan'], $baris);

        if ($kering) {
            $this->warn($perlu->count().' kendaraan perlu diperbaiki. Jalankan tanpa --dry-run untuk menyimpannya.');

            return self::SUCCESS;
        }

        $this->info($perlu->count().' kendaraan diperbaiki.');

        if (! $urutkanUlang) {
            $this->newLine();
            $this->line('Catatan: hanya garis rute dan jarak/ETA yang dihitung ulang — urutan kunjungannya');
            $this->line('tetap seperti semula. Urutan yang dulu efisien untuk koordinat lama bisa jadi');
            $this->line('sudah tidak efisien untuk koordinat yang sekarang (terlihat dari jaraknya yang');
            $this->line('melonjak). Pakai --urutkan-ulang kalau urutannya juga boleh berubah.');
        }

        if ($urutkanUlang && $adaYangSudahJalan) {
            $this->newLine();
            $this->warn('Sebagian kendaraan sudah dijalani sopirnya, jadi urutan kunjungannya sengaja');
            $this->warn('dipertahankan — hanya garis rutenya yang diperbaiki. Lihat kolom Catatan.');
        }

        $this->laporkanKoordinatMencurigakan($perlu, $toleransi);

        return self::SUCCESS;
    }

    /**
     * Sebuah kendaraan dianggap tidak nyambung kalau ada tokonya yang
     * letaknya jauh dari garis rute tersimpan. Garis rute dari OSRM sangat
     * rapat (ratusan titik), jadi toko yang sungguh dilewati pasti punya
     * titik garis di dekatnya; kalau tidak ada, garis itu memang milik
     * koordinat yang lama.
     */
    private function tidakNyambung(Kendaraan $kendaraan, float $toleransi): bool
    {
        return $this->tokoJauhDariRute($kendaraan, $toleransi) !== [];
    }

    /**
     * Toko yang letaknya jauh dari garis rute kendaraannya sendiri, beserta
     * jaraknya dalam meter.
     *
     * @return array<int, array{nama: string, lat: float, lng: float, jarak: float}>
     */
    private function tokoJauhDariRute(Kendaraan $kendaraan, float $toleransi): array
    {
        $berkoordinat = $kendaraan->stops
            ->filter(fn (KendaraanStop $s) => $s->toko?->latitude !== null && $s->toko?->longitude !== null)
            ->values();

        if ($berkoordinat->isEmpty()) {
            return [];
        }

        $garis = Geo::decodePolyline((string) $kendaraan->geometry);

        $jauh = [];

        foreach ($berkoordinat as $stop) {
            $titikToko = new Koordinat((float) $stop->toko->latitude, (float) $stop->toko->longitude);

            // Ada toko tapi garis rutenya kosong/rusak — jelas perlu dihitung.
            $terdekat = INF;

            foreach ($garis as $titikGaris) {
                $terdekat = min($terdekat, Geo::haversine($titikToko, $titikGaris));

                if ($terdekat <= $toleransi) {
                    break;
                }
            }

            if ($terdekat > $toleransi) {
                $jauh[] = [
                    'nama' => (string) $stop->toko->nama,
                    'lat' => $titikToko->lat,
                    'lng' => $titikToko->lng,
                    'jarak' => $terdekat,
                ];
            }
        }

        return $jauh;
    }

    /**
     * Kendaraan yang SETELAH dihitung ulang pun tokonya masih jauh dari
     * garis rute. Ini bukan lagi soal garis rute basi: koordinat tokonya
     * memang tidak berada di dekat jalan mana pun (mis. jatuh di laut atau
     * di tengah blok kosong), jadi OSRM menempelkannya ke jalan terdekat
     * yang jauh. Menghitung ulang berapa kali pun tidak akan menutup jarak
     * itu — yang perlu diperbaiki koordinat tokonya.
     */
    private function laporkanKoordinatMencurigakan(Collection $kendaraans, float $toleransi): void
    {
        $baris = [];

        // Diambil ulang dengan relasinya lengkap: refresh() setelah hitung
        // ulang hanya memuat kembali relasi tingkat pertama, jadi stops.toko
        // yang bersarang sudah tidak ikut termuat lagi di sini.
        $segar = Kendaraan::query()
            ->whereIn('id', $kendaraans->pluck('id'))
            ->with(['stops.toko:id,nama,latitude,longitude'])
            ->orderBy('id')
            ->get();

        foreach ($segar as $kendaraan) {
            foreach ($this->tokoJauhDariRute($kendaraan, $toleransi) as $toko) {
                $baris[] = [
                    $kendaraan->nama,
                    $toko['nama'],
                    number_format($toko['lat'], 6).', '.number_format($toko['lng'], 6),
                    number_format($toko['jarak'] / 1000, 2).' km',
                ];
            }
        }

        if ($baris === []) {
            return;
        }

        $this->newLine();
        $this->warn('Toko berikut tetap jauh dari jalan terdekat walau rutenya sudah dihitung ulang.');
        $this->warn('Koordinatnya patut dicurigai salah — perbaiki titiknya lewat Master Toko.');
        $this->newLine();

        $this->table(['Mobil', 'Toko', 'Koordinat', 'Jarak ke jalan'], $baris);
    }
}
