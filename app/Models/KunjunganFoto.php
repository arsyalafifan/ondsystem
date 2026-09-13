<?php

namespace App\Models;

use App\Enums\JenisFotoKunjungan;
use App\Enums\SumberFotoKunjungan;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'kunjungan_id', 'jenis', 'sumber', 'path', 'diambil_at', 'exif_diambil_at',
    'disinkronkan_at', 'latitude', 'longitude', 'akurasi_m', 'lebar', 'tinggi',
    'ukuran_byte',
])]
class KunjunganFoto extends Model
{
    use BerDepot, HasFactory;

    protected function casts(): array
    {
        return [
            'jenis' => JenisFotoKunjungan::class,
            'sumber' => SumberFotoKunjungan::class,
            'diambil_at' => 'datetime',
            'exif_diambil_at' => 'datetime',
            'disinkronkan_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** @return BelongsTo<Kunjungan, $this> */
    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('visit.foto.disk'))->url($this->path));
    }

    protected function punyaLokasi(): Attribute
    {
        return Attribute::get(fn (): bool => $this->latitude !== null && $this->longitude !== null);
    }

    /** Berkas unggahan yang sama sekali tidak membawa keterangan waktu. */
    protected function waktuTidakDiketahui(): Attribute
    {
        return Attribute::get(fn (): bool => $this->sumber === SumberFotoKunjungan::Unggah
            && $this->exif_diambil_at === null);
    }
}
