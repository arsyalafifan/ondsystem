<?php

use App\Support\Terbilang;

it('mengubah nilai rupiah menjadi kata', function (int $nilai, string $harapan) {
    expect(Terbilang::rupiah($nilai))->toBe($harapan);
})->with([
    [0, 'Nol rupiah'],
    [1, 'Satu rupiah'],
    [11, 'Sebelas rupiah'],
    [100, 'Seratus rupiah'],
    [1000, 'Seribu rupiah'],
    [21000, 'Dua puluh satu ribu rupiah'],
    [527000, 'Lima ratus dua puluh tujuh ribu rupiah'],
    [1000000, 'Satu juta rupiah'],
]);
