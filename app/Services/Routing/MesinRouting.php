<?php

namespace App\Services\Routing;

use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use App\Services\Peta\MatriksJarak;
use App\Services\Peta\OsrmClient;

/**
 * Menyusun rute lengkap dari sekumpulan pesanan.
 *
 * Alurnya mengikuti cara kerja admin selama ini, hanya dikerjakan mesin:
 *   1. pesanan dipisah menurut wilayah;
 *   2. tiap wilayah dibagi menjadi muatan mobil sesuai batas 25 toko / 220 dus;
 *   3. isi tiap mobil diurutkan supaya jarak tempuhnya paling pendek;
 *   4. jarak dan garis rute diambil dari OSRM agar mengikuti jalan sebenarnya.
 *
 * Bila OSRM tidak bisa dihubungi, perhitungan tetap jalan memakai jarak garis
 * lurus. Hasilnya sedikit kurang presisi tapi urutan kunjungannya tetap layak
 * pakai, jadi operasional tidak pernah berhenti karena layanan luar mati.
 */
class MesinRouting
{
    public function __construct(
        private readonly OsrmClient $osrm,
        private readonly PengelompokKendaraan $pengelompok,
        private readonly PengurutKunjungan $pengurut,
    ) {}

    /**
     * @param  array<int, TitikPengiriman>  $titik
     */
    public function susun(
        array $titik,
        Koordinat $depot,
        int $maxToko,
        int $maxDus,
        bool $pisahPerWilayah = true,
    ): HasilRouting {
        $peringatan = [];
        $rute = [];
        $sumberJarak = 'haversine';

        if ($titik === []) {
            return new HasilRouting([], [__('routing.tidak_ada_pesanan')], $sumberJarak);
        }

        // Pesanan dipisah per wilayah supaya satu mobil tidak melintasi
        // dua wilayah sekaligus, sesuai pembagian tanggung jawab lapangan.
        $perWilayah = $pisahPerWilayah
            ? $this->kelompokkanPerWilayah($titik)
            : [0 => $titik];

        foreach ($perWilayah as $wilayahId => $titikWilayah) {
            $hasilBagi = $this->pengelompok->bagi($titikWilayah, $maxToko, $maxDus, $depot);
            $peringatan = array_merge($peringatan, $hasilBagi['peringatan']);

            foreach ($hasilBagi['kelompok'] as $muatan) {
                $ruteMobil = $this->susunSatuKendaraan($muatan, $depot, $pisahPerWilayah ? (int) $wilayahId : null);

                if ($ruteMobil->sumberJarak === 'osrm') {
                    $sumberJarak = 'osrm';
                }

                $rute[] = $ruteMobil;
            }
        }

        // Mobil dengan muatan terbanyak diberi nomor lebih dulu supaya
        // penomoran di layar terasa wajar bagi admin.
        usort($rute, fn (RuteKendaraan $a, RuteKendaraan $b) => [$a->wilayahId, -$a->totalToko()] <=> [$b->wilayahId, -$b->totalToko()]);

        if ($sumberJarak === 'haversine' && $this->osrm->aktif()) {
            $peringatan[] = __('routing.peringatan_osrm_mati');
        }

        return new HasilRouting($rute, $peringatan, $sumberJarak);
    }

    /**
     * @param  array<int, TitikPengiriman>  $muatan
     */
    private function susunSatuKendaraan(array $muatan, Koordinat $depot, ?int $wilayahId): RuteKendaraan
    {
        // Indeks 0 depot, sisanya toko sesuai urutan daftar masuk.
        $koordinat = array_merge(
            [$depot],
            array_map(fn (TitikPengiriman $t) => $t->koordinat, $muatan),
        );

        $matriks = $this->osrm->matriks($koordinat) ?? MatriksJarak::dariHaversine($koordinat);

        $urutan = $this->pengurut->urutkan($matriks, count($muatan));

        // Indeks matriks 1..n dipetakan balik ke pesanannya.
        $titikTerurut = array_map(fn (int $i) => $muatan[$i - 1], $urutan);

        $koordinatTerurut = array_merge(
            [$depot],
            array_map(fn (TitikPengiriman $t) => $t->koordinat, $titikTerurut),
            [$depot],
        );

        $ruteAsli = $this->osrm->rute($koordinatTerurut);

        if ($ruteAsli !== null) {
            return new RuteKendaraan(
                titik: $titikTerurut,
                legs: $ruteAsli['legs'],
                totalJarakM: $ruteAsli['jarak_m'],
                totalDurasiS: $ruteAsli['durasi_s'],
                geometry: $ruteAsli['geometry'],
                wilayahId: $wilayahId,
                sumberJarak: 'osrm',
            );
        }

        return $this->dariMatriks($titikTerurut, $urutan, $matriks, $koordinatTerurut, $wilayahId);
    }

    /**
     * Menyusun hasil dari matriks ketika permintaan rute detail gagal.
     *
     * @param  array<int, TitikPengiriman>  $titikTerurut
     * @param  array<int, int>  $urutan
     * @param  array<int, Koordinat>  $koordinatTerurut
     */
    private function dariMatriks(
        array $titikTerurut,
        array $urutan,
        MatriksJarak $matriks,
        array $koordinatTerurut,
        ?int $wilayahId,
    ): RuteKendaraan {
        $jalur = array_merge([0], $urutan, [0]);
        $legs = [];
        $totalJarak = 0.0;
        $totalDurasi = 0.0;

        for ($i = 0; $i < count($jalur) - 1; $i++) {
            $jarak = $matriks->jarak($jalur[$i], $jalur[$i + 1]);
            $durasi = $matriks->durasi($jalur[$i], $jalur[$i + 1]);

            $legs[] = ['jarak_m' => $jarak, 'durasi_s' => $durasi];
            $totalJarak += $jarak;
            $totalDurasi += $durasi;
        }

        return new RuteKendaraan(
            titik: $titikTerurut,
            legs: $legs,
            totalJarakM: $totalJarak,
            totalDurasiS: $totalDurasi,
            geometry: Geo::encodePolyline($koordinatTerurut),
            wilayahId: $wilayahId,
            sumberJarak: $matriks->sumber,
        );
    }

    /**
     * @param  array<int, TitikPengiriman>  $titik
     * @return array<int, array<int, TitikPengiriman>>
     */
    private function kelompokkanPerWilayah(array $titik): array
    {
        $hasil = [];

        foreach ($titik as $t) {
            $hasil[$t->wilayahId][] = $t;
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * Menghitung ulang jarak, durasi dan garis rute untuk satu urutan toko
     * yang sudah ditentukan. Dipakai setelah admin menggeser toko antar mobil
     * atau mengubah urutan secara manual.
     *
     * @param  array<int, Koordinat>  $koordinatToko  sesuai urutan kunjungan
     * @return array{jarak_m: float, durasi_s: float, geometry: string, legs: array<int, array{jarak_m: float, durasi_s: float}>}
     */
    public function hitungUlang(array $koordinatToko, Koordinat $depot): array
    {
        if ($koordinatToko === []) {
            return ['jarak_m' => 0.0, 'durasi_s' => 0.0, 'geometry' => '', 'legs' => []];
        }

        $jalur = array_merge([$depot], $koordinatToko, [$depot]);

        $hasil = $this->osrm->rute($jalur);

        if ($hasil !== null) {
            return $hasil;
        }

        $legs = [];
        $totalJarak = 0.0;
        $totalDurasi = 0.0;
        $meterPerDetik = 25 * 1000 / 3600;

        for ($i = 0; $i < count($jalur) - 1; $i++) {
            $m = Geo::haversine($jalur[$i], $jalur[$i + 1]) * 1.35;
            $legs[] = ['jarak_m' => $m, 'durasi_s' => $m / $meterPerDetik];
            $totalJarak += $m;
            $totalDurasi += $m / $meterPerDetik;
        }

        return [
            'jarak_m' => $totalJarak,
            'durasi_s' => $totalDurasi,
            'geometry' => Geo::encodePolyline($jalur),
            'legs' => $legs,
        ];
    }
}
