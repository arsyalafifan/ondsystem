<?php

namespace App\Services\Routing;

use App\Services\Peta\MatriksJarak;

/**
 * Menentukan urutan kunjungan sebuah kendaraan agar total perjalanan
 * sependek mungkin (persoalan travelling salesman).
 *
 * Dua tahap:
 *  1. Tetangga terdekat, untuk mendapat urutan awal yang masuk akal.
 *  2. Perbaikan 2-opt dan Or-opt, untuk membongkar jalur yang saling
 *     menyilang dan memindahkan toko yang kebetulan terselip di posisi salah.
 *
 * Indeks 0 pada matriks selalu depot. Rute berangkat dari depot dan kembali
 * lagi ke depot.
 */
class PengurutKunjungan
{
    private const BATAS_PUTARAN = 200;

    /**
     * @return array<int, int> urutan indeks titik (1..n), tanpa depot
     */
    public function urutkan(MatriksJarak $matriks, int $jumlahTitik): array
    {
        if ($jumlahTitik <= 1) {
            return $jumlahTitik === 1 ? [1] : [];
        }

        $urutan = $this->tetanggaTerdekat($matriks, $jumlahTitik);

        if ($jumlahTitik <= 2) {
            return $urutan;
        }

        $urutan = $this->duaOpt($matriks, $urutan);
        $urutan = $this->orOpt($matriks, $urutan);

        return $urutan;
    }

    /**
     * Selalu lanjut ke toko terdekat yang belum dikunjungi.
     *
     * @return array<int, int>
     */
    private function tetanggaTerdekat(MatriksJarak $matriks, int $jumlahTitik): array
    {
        $belum = range(1, $jumlahTitik);
        $urutan = [];
        $sekarang = 0;

        while ($belum !== []) {
            $terbaik = null;
            $jarakTerbaik = INF;

            foreach ($belum as $posisi => $kandidat) {
                $jarak = $matriks->durasi($sekarang, $kandidat);

                if ($jarak < $jarakTerbaik) {
                    $jarakTerbaik = $jarak;
                    $terbaik = $posisi;
                }
            }

            $sekarang = $belum[$terbaik];
            $urutan[] = $sekarang;
            unset($belum[$terbaik]);
            $belum = array_values($belum);
        }

        return $urutan;
    }

    /**
     * Membalik potongan rute selama hasilnya lebih pendek. Ini yang
     * menghilangkan jalur menyilang — bentuk pemborosan paling kentara
     * pada rute yang disusun manual.
     *
     * @param  array<int, int>  $urutan
     * @return array<int, int>
     */
    private function duaOpt(MatriksJarak $matriks, array $urutan): array
    {
        $tur = $this->bungkusDepot($urutan);
        $n = count($tur);
        $putaran = 0;

        $membaik = true;

        while ($membaik && $putaran < self::BATAS_PUTARAN) {
            $membaik = false;
            $putaran++;

            for ($i = 1; $i < $n - 2; $i++) {
                for ($j = $i + 1; $j < $n - 1; $j++) {
                    $selisih = $matriks->durasi($tur[$i - 1], $tur[$j])
                        + $matriks->durasi($tur[$i], $tur[$j + 1])
                        - $matriks->durasi($tur[$i - 1], $tur[$i])
                        - $matriks->durasi($tur[$j], $tur[$j + 1]);

                    if ($selisih < -0.01) {
                        $potongan = array_reverse(array_slice($tur, $i, $j - $i + 1));
                        array_splice($tur, $i, $j - $i + 1, $potongan);
                        $membaik = true;
                    }
                }
            }
        }

        return array_slice($tur, 1, -1);
    }

    /**
     * Memindahkan satu sampai tiga toko berurutan ke posisi lain dalam rute.
     * Berguna untuk toko yang letaknya sebenarnya searah tapi terlanjur
     * masuk di urutan yang jauh.
     *
     * @param  array<int, int>  $urutan
     * @return array<int, int>
     */
    private function orOpt(MatriksJarak $matriks, array $urutan): array
    {
        $tur = $this->bungkusDepot($urutan);
        $putaran = 0;
        $membaik = true;

        while ($membaik && $putaran < self::BATAS_PUTARAN) {
            $membaik = false;
            $putaran++;
            $n = count($tur);

            for ($panjang = 1; $panjang <= 3 && ! $membaik; $panjang++) {
                for ($i = 1; $i + $panjang <= $n - 1 && ! $membaik; $i++) {
                    $sebelum = $tur[$i - 1];
                    $awal = $tur[$i];
                    $akhir = $tur[$i + $panjang - 1];
                    $sesudah = $tur[$i + $panjang];

                    // Keuntungan dari mencabut potongan ini.
                    $hemat = $matriks->durasi($sebelum, $awal)
                        + $matriks->durasi($akhir, $sesudah)
                        - $matriks->durasi($sebelum, $sesudah);

                    if ($hemat <= 0.01) {
                        continue;
                    }

                    $potongan = array_slice($tur, $i, $panjang);
                    $sisa = array_merge(array_slice($tur, 0, $i), array_slice($tur, $i + $panjang));

                    for ($j = 1; $j < count($sisa) && ! $membaik; $j++) {
                        $kiri = $sisa[$j - 1];
                        $kanan = $sisa[$j];

                        foreach ([false, true] as $dibalik) {
                            $p = $dibalik ? array_reverse($potongan) : $potongan;

                            $biaya = $matriks->durasi($kiri, $p[0])
                                + $matriks->durasi($p[count($p) - 1], $kanan)
                                - $matriks->durasi($kiri, $kanan);

                            if ($biaya < $hemat - 0.01) {
                                array_splice($sisa, $j, 0, $p);
                                $tur = $sisa;
                                $membaik = true;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return array_slice($tur, 1, -1);
    }

    /**
     * @param  array<int, int>  $urutan
     * @return array<int, int>
     */
    private function bungkusDepot(array $urutan): array
    {
        return array_merge([0], array_values($urutan), [0]);
    }

    /**
     * Total durasi sebuah urutan, termasuk berangkat dari dan kembali ke depot.
     *
     * @param  array<int, int>  $urutan
     */
    public function totalDurasi(MatriksJarak $matriks, array $urutan): float
    {
        $tur = $this->bungkusDepot($urutan);
        $total = 0.0;

        for ($i = 0; $i < count($tur) - 1; $i++) {
            $total += $matriks->durasi($tur[$i], $tur[$i + 1]);
        }

        return $total;
    }
}
