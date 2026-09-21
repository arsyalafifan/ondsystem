<?php

namespace App\Models;

use App\Enums\JenisBuktiPengiriman;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Lihat catatan lengkap di migrasi `create_stop_fotos_table`. */
#[Fillable(['kendaraan_stop_id', 'jenis', 'path', 'catatan'])]
class StopFoto extends Model
{
    use BerDepot;

    protected function casts(): array
    {
        return [
            'jenis' => JenisBuktiPengiriman::class,
        ];
    }

    /** @return BelongsTo<KendaraanStop, $this> */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(KendaraanStop::class, 'kendaraan_stop_id');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('visit.foto.disk'))->url($this->path));
    }
}
