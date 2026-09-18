<?php

namespace App\Models;

use App\Enums\JenisCatatanBbm;
use App\Enums\LevelBahanBakar;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'kendaraan_id', 'jenis', 'foto', 'foto_sebelum', 'foto_sesudah',
    'km', 'level_bbm', 'liter', 'biaya', 'catatan', 'dicatat_oleh',
])]
class CatatanBbm extends Model
{
    use BerDepot, HasFactory;

    protected function casts(): array
    {
        return [
            'jenis' => JenisCatatanBbm::class,
            'level_bbm' => LevelBahanBakar::class,
            'km' => 'integer',
            'liter' => 'decimal:2',
            'biaya' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Kendaraan, $this> */
    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    protected function urlFoto(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('visit.foto.disk'))->url($this->foto));
    }

    protected function urlFotoSebelum(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->foto_sebelum !== null
            ? Storage::disk(config('visit.foto.disk'))->url($this->foto_sebelum)
            : null);
    }

    protected function urlFotoSesudah(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->foto_sesudah !== null
            ? Storage::disk(config('visit.foto.disk'))->url($this->foto_sesudah)
            : null);
    }
}
