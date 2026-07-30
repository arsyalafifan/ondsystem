<?php

namespace App\Services\Routing;

use App\Services\Peta\Koordinat;

/** Satu pesanan yang harus diantar ke satu toko. */
final readonly class TitikPengiriman
{
    public function __construct(
        public int $pesananId,
        public int $tokoId,
        public int $wilayahId,
        public string $namaToko,
        public string $alamat,
        public int $dus,
        public Koordinat $koordinat,
    ) {}
}
