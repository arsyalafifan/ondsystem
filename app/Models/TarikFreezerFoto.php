<?php

namespace App\Models;

use App\Enums\JenisBuktiTarikFreezer;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bukti foto satu penarikan freezer — foto freezernya dan foto nomor IDN-nya,
 * diambil driver saat pengambilan. Sama seperti App\Models\NooFoto, disk
 * privat dan hanya bisa dibuka lewat rute `tarik-freezer.foto` yang
 * memeriksa dulu siapa yang meminta.
 */
#[Fillable(['tarik_freezer_id', 'jenis', 'path'])]
class TarikFreezerFoto extends Model
{
    use BerDepot;

    protected function casts(): array
    {
        return ['jenis' => JenisBuktiTarikFreezer::class];
    }

    /** @return BelongsTo<TarikFreezer, $this> */
    public function tarikFreezer(): BelongsTo
    {
        return $this->belongsTo(TarikFreezer::class);
    }
}
