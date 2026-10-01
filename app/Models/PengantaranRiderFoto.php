<?php

namespace App\Models;

use App\Enums\JenisBuktiPengiriman;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Bukti pengiriman rider — mirip persis App\Models\StopFoto. Lihat migrasinya. */
#[Fillable(['pengantaran_rider_id', 'jenis', 'path', 'catatan'])]
class PengantaranRiderFoto extends Model
{
    use BerDepot;

    protected function casts(): array
    {
        return [
            'jenis' => JenisBuktiPengiriman::class,
        ];
    }

    /** @return BelongsTo<PengantaranRider, $this> */
    public function pengantaran(): BelongsTo
    {
        return $this->belongsTo(PengantaranRider::class, 'pengantaran_rider_id');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('visit.foto.disk'))->url($this->path));
    }
}
