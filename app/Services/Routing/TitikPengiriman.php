<?php

namespace App\Services\Routing;

use App\Services\Peta\Koordinat;

/**
 * Satu titik yang harus disinggahi: satu toko, dengan sekian muatan.
 *
 * Mesin routing sengaja tidak tahu muatannya berupa apa — dus es krim untuk
 * rute reguler, unit freezer untuk rute NOO. `rujukanId` pun tidak pernah
 * dibacanya; ia cuma dibawa pulang-pergi supaya pemanggil bisa mencocokkan
 * hasil susunan dengan sumbernya (id pesanan saat merutekan pesanan, id NOO
 * saat merutekan freezer, id stop saat sekadar mengurutkan ulang rute yang
 * sudah jadi).
 */
final readonly class TitikPengiriman
{
    public function __construct(
        public int $rujukanId,
        public int $tokoId,
        public int $wilayahId,
        public string $namaToko,
        public string $alamat,
        public int $dus,
        public Koordinat $koordinat,
    ) {}
}
