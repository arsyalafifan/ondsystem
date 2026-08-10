<?php

namespace App\Services\Kunjungan;

/** Isi QR code freezer yang sudah diurai menjadi bagian-bagiannya. */
final readonly class HasilPindaiQr
{
    public function __construct(
        public string $assetId,
        public ?string $namaPelanggan = null,
        public ?string $model = null,
        public string $mentah = '',
    ) {}
}
