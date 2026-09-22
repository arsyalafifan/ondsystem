<?php

namespace App\Models;

use App\Enums\JenisBuktiNoo;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bukti foto satu NOO — KTP dan kartu keluarga pemilik, tampak depan toko,
 * sampai bukti pemasangan freezer oleh driver.
 *
 * Berbeda dari foto bukti lain di aplikasi ini, berkasnya TIDAK disimpan di
 * disk publik: di antaranya ada dokumen identitas, yang tidak boleh terbuka
 * bagi siapa pun yang kebetulan menebak URL-nya. Semuanya hanya bisa dibuka
 * lewat rute `noo.foto` yang memeriksa dulu siapa yang meminta — lihat
 * App\Services\Noo\BuktiNooService::DISK.
 */
#[Fillable(['noo_id', 'jenis', 'path'])]
class NooFoto extends Model
{
    use BerDepot;

    protected function casts(): array
    {
        return ['jenis' => JenisBuktiNoo::class];
    }

    /** @return BelongsTo<Noo, $this> */
    public function noo(): BelongsTo
    {
        return $this->belongsTo(Noo::class);
    }
}
