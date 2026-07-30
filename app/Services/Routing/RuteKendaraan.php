<?php

namespace App\Services\Routing;

/** Hasil perhitungan satu kendaraan: toko mana saja dan urutan kunjungannya. */
final class RuteKendaraan
{
    /**
     * @param  array<int, TitikPengiriman>  $titik  sudah terurut sesuai kunjungan
     * @param  array<int, array{jarak_m: float, durasi_s: float}>  $legs
     *                                                                    Ruas perjalanan. Indeks 0 = depot ke toko pertama,
     *                                                                    indeks n = toko terakhir kembali ke depot.
     */
    public function __construct(
        public array $titik,
        public array $legs,
        public float $totalJarakM,
        public float $totalDurasiS,
        public string $geometry,
        public ?int $wilayahId,
        public string $sumberJarak,
    ) {}

    public function totalToko(): int
    {
        return count($this->titik);
    }

    public function totalDus(): int
    {
        return array_sum(array_map(fn (TitikPengiriman $t) => $t->dus, $this->titik));
    }
}
