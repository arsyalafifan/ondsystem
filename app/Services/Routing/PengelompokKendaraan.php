<?php

namespace App\Services\Routing;

use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;

/**
 * Membagi pesanan satu wilayah menjadi beberapa muatan kendaraan.
 *
 * Memakai algoritma sweep: setiap toko diberi sudut dilihat dari titik pusat,
 * lalu diurutkan memutar dan dipotong menjadi juring-juring. Hasilnya sejalan
 * dengan cara admin membagi manual di peta — satu mobil memegang satu area
 * yang menyatu, bukan toko-toko yang berjauhan.
 *
 * Dua hal yang dijaga:
 *
 *  - Jumlah mobil ditekan sesedikit mungkin. Satu mobil tambahan berarti satu
 *    driver dan satu tangki solar tambahan.
 *  - Isinya dibuat sebanding. Mobil yang cuma mengangkut dua toko itu
 *    pemborosan, dan biasanya muncul karena pemotongan juring yang ceroboh.
 *
 * Untuk itu pemotongan dicoba pada banyak titik awal yang berbeda, lalu
 * dipilih yang kelompoknya paling rapat secara geografis.
 */
class PengelompokKendaraan
{
    /** Batas jumlah titik awal yang dicoba, penjaga waktu proses. */
    private const MAKS_PERCOBAAN_AWAL = 180;

    /**
     * @param  array<int, TitikPengiriman>  $titik
     * @return array{kelompok: array<int, array<int, TitikPengiriman>>, peringatan: array<int, string>}
     */
    public function bagi(array $titik, int $maxToko, int $maxDus, ?Koordinat $depot = null): array
    {
        $peringatan = [];

        if ($titik === []) {
            return ['kelompok' => [], 'peringatan' => $peringatan];
        }

        // Pesanan yang sendirian saja sudah melebihi kapasitas satu mobil
        // tidak bisa digabung dengan siapa pun. Dipisahkan lebih dulu dan
        // dilaporkan supaya admin bisa memecah pesanannya.
        $terlaluBesar = [];
        $normal = [];

        foreach ($titik as $t) {
            if ($t->dus > $maxDus) {
                $terlaluBesar[] = $t;
                $peringatan[] = __('routing.peringatan_pesanan_besar', [
                    'toko' => $t->namaToko,
                    'dus' => $t->dus,
                    'maks' => $maxDus,
                ]);
            } else {
                $normal[] = $t;
            }
        }

        $kelompok = array_map(fn (TitikPengiriman $t) => [$t], $terlaluBesar);

        if ($normal === []) {
            return ['kelompok' => $kelompok, 'peringatan' => $peringatan];
        }

        $pusat = $depot ?? Geo::centroid(array_map(fn (TitikPengiriman $t) => $t->koordinat, $normal));

        usort($normal, function (TitikPengiriman $a, TitikPengiriman $b) use ($pusat): int {
            return Geo::sudut($pusat, $a->koordinat) <=> Geo::sudut($pusat, $b->koordinat);
        });

        foreach ($this->cariPembagianTerbaik($normal, $maxToko, $maxDus) as $bagian) {
            $kelompok[] = $bagian;
        }

        return ['kelompok' => $kelompok, 'peringatan' => $peringatan];
    }

    /**
     * Mencari pembagian dengan jumlah mobil paling sedikit. Pada jumlah mobil
     * yang sama, dipilih yang kelompoknya paling rapat.
     *
     * @param  array<int, TitikPengiriman>  $titik  sudah terurut menurut sudut
     * @return array<int, array<int, TitikPengiriman>>
     */
    private function cariPembagianTerbaik(array $titik, int $maxToko, int $maxDus): array
    {
        $n = count($titik);
        $totalDus = array_sum(array_map(fn (TitikPengiriman $t) => $t->dus, $titik));

        $kMin = max(1, (int) ceil($n / $maxToko), (int) ceil($totalDus / $maxDus));

        // Titik awal pemotongan digeser mengelilingi lingkaran. Memotong di
        // celah yang berbeda bisa menghasilkan kelompok yang jauh lebih rapat,
        // dan kadang membuat jumlah mobil minimum jadi tercapai.
        $awal = $this->titikAwalYangDicoba($n);

        for ($k = $kMin; $k <= $n; $k++) {
            $terbaik = null;
            $skorTerbaik = INF;

            foreach ($awal as $offset) {
                $putar = $this->putar($titik, $offset);
                $partisi = $this->potong($putar, $k, $maxToko, $maxDus);

                if ($partisi === null) {
                    continue;
                }

                $skor = $this->skorKerapatan($partisi);

                if ($skor < $skorTerbaik) {
                    $skorTerbaik = $skor;
                    $terbaik = $partisi;
                }
            }

            if ($terbaik !== null) {
                return $terbaik;
            }
        }

        // Tidak akan tercapai untuk masukan yang wajar, tapi tetap disiapkan
        // agar tidak ada pesanan yang hilang: satu mobil satu toko.
        return array_map(fn (TitikPengiriman $t) => [$t], $titik);
    }

    /** @return array<int, int> */
    private function titikAwalYangDicoba(int $n): array
    {
        if ($n <= self::MAKS_PERCOBAAN_AWAL) {
            return range(0, $n - 1);
        }

        $langkah = (int) ceil($n / self::MAKS_PERCOBAAN_AWAL);

        return range(0, $n - 1, $langkah);
    }

    /**
     * @param  array<int, TitikPengiriman>  $titik
     * @return array<int, TitikPengiriman>
     */
    private function putar(array $titik, int $offset): array
    {
        if ($offset === 0) {
            return $titik;
        }

        return array_merge(array_slice($titik, $offset), array_slice($titik, 0, $offset));
    }

    /**
     * Memotong daftar terurut menjadi tepat $k bagian berurutan.
     *
     * Mengembalikan null bila $k mobil tidak cukup menampung semuanya, supaya
     * pemanggil bisa mencoba dengan jumlah mobil yang lebih banyak.
     *
     * @param  array<int, TitikPengiriman>  $titik
     * @return array<int, array<int, TitikPengiriman>>|null
     */
    private function potong(array $titik, int $k, int $maxToko, int $maxDus): ?array
    {
        $total = count($titik);

        if ($k * $maxToko < $total) {
            return null;
        }

        // Jumlah dus kumulatif dari belakang, supaya pemeriksaan "sisanya
        // masih muat?" bisa dilakukan seketika di dalam perulangan.
        $sisaDus = array_fill(0, $total + 1, 0);

        for ($i = $total - 1; $i >= 0; $i--) {
            $sisaDus[$i] = $sisaDus[$i + 1] + $titik[$i]->dus;
        }

        if ($sisaDus[0] > $k * $maxDus) {
            return null;
        }

        $kelompok = [];
        $indeks = 0;

        for ($m = 0; $m < $k; $m++) {
            $sisaKendaraan = $k - $m;
            $sisaTitik = $total - $indeks;

            $targetToko = (int) ceil($sisaTitik / $sisaKendaraan);
            $targetDus = (int) ceil($sisaDus[$indeks] / $sisaKendaraan);

            $muatan = [];
            $dus = 0;

            while ($indeks < $total) {
                $calon = $titik[$indeks];

                if ($muatan !== []) {
                    if (count($muatan) >= $maxToko || $dus + $calon->dus > $maxDus) {
                        break;
                    }

                    if ($sisaKendaraan > 1) {
                        $sudahCukup = count($muatan) >= $targetToko || $dus + $calon->dus > $targetDus;

                        // Berhenti di target hanya kalau mobil-mobil berikutnya
                        // memang sanggup menghabiskan sisanya. Tanpa penjagaan
                        // ini, pemotongan berhenti terlalu dini dan menyisakan
                        // mobil terakhir yang isinya cuma beberapa toko.
                        if ($sudahCukup && $this->sisanyaMuat($sisaDus, $indeks, $total, $sisaKendaraan - 1, $maxToko, $maxDus)) {
                            break;
                        }
                    }
                }

                $muatan[] = $calon;
                $dus += $calon->dus;
                $indeks++;
            }

            if ($muatan === []) {
                break;
            }

            $kelompok[] = $muatan;
        }

        // Masih ada yang tertinggal berarti $k mobil tidak cukup.
        return $indeks < $total ? null : $kelompok;
    }

    /**
     * @param  array<int, int>  $sisaDus
     */
    private function sisanyaMuat(array $sisaDus, int $indeks, int $total, int $kendaraanTersisa, int $maxToko, int $maxDus): bool
    {
        return ($total - $indeks) <= $kendaraanTersisa * $maxToko
            && $sisaDus[$indeks] <= $kendaraanTersisa * $maxDus;
    }

    /**
     * Ukuran kerapatan sebuah pembagian: total jarak setiap toko ke titik
     * tengah kelompoknya. Semakin kecil, semakin berdempetan tokonya, dan
     * semakin pendek rute yang nanti terbentuk.
     *
     * @param  array<int, array<int, TitikPengiriman>>  $partisi
     */
    private function skorKerapatan(array $partisi): float
    {
        $skor = 0.0;

        foreach ($partisi as $muatan) {
            $koordinat = array_map(fn (TitikPengiriman $t) => $t->koordinat, $muatan);
            $tengah = Geo::centroid($koordinat);

            if ($tengah === null) {
                continue;
            }

            foreach ($koordinat as $k) {
                $skor += Geo::haversine($tengah, $k);
            }
        }

        return $skor;
    }
}
