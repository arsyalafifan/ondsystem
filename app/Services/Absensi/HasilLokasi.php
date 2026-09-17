<?php

namespace App\Services\Absensi;

/** Tempat sebuah absen diterima, beserta jaraknya dari titik acuan. */
final readonly class HasilLokasi
{
    public function __construct(
        public ?int $jarakM,
        public ?int $depotId,
        public ?int $tokoId,
        /** Nama tempat untuk watermark & tampilan; kosong untuk posisi bebas lokasi. */
        public string $nama,
    ) {}
}
