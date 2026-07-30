<?php

namespace App\Services\Routing;

/** Keluaran satu kali proses generate routing. */
final class HasilRouting
{
    /**
     * @param  array<int, RuteKendaraan>  $rute
     * @param  array<int, string>  $peringatan
     */
    public function __construct(
        public array $rute,
        public array $peringatan,
        public string $sumberJarak,
    ) {}

    public function totalToko(): int
    {
        return array_sum(array_map(fn (RuteKendaraan $r) => $r->totalToko(), $this->rute));
    }

    public function totalDus(): int
    {
        return array_sum(array_map(fn (RuteKendaraan $r) => $r->totalDus(), $this->rute));
    }

    public function totalJarakM(): int
    {
        return (int) round(array_sum(array_map(fn (RuteKendaraan $r) => $r->totalJarakM, $this->rute)));
    }

    public function totalDurasiS(): int
    {
        return (int) round(array_sum(array_map(fn (RuteKendaraan $r) => $r->totalDurasiS, $this->rute)));
    }
}
