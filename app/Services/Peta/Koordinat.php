<?php

namespace App\Services\Peta;

use InvalidArgumentException;

/** Satu titik di peta. */
final readonly class Koordinat
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException("Latitude di luar jangkauan: {$lat}");
        }

        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException("Longitude di luar jangkauan: {$lng}");
        }
    }

    /** Format yang dipakai URL OSRM: bujur dulu, baru lintang. */
    public function untukOsrm(): string
    {
        return sprintf('%.6F,%.6F', $this->lng, $this->lat);
    }

    /** @return array{lat: float, lng: float} */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }
}
