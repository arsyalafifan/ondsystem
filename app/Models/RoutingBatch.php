<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'kode', 'tanggal', 'status', 'total_kendaraan', 'total_toko', 'total_dus',
    'total_jarak_m', 'total_durasi_s', 'max_toko', 'max_dus', 'sumber_jarak',
    'dibuat_oleh', 'disetujui_oleh', 'disetujui_at',
])]
class RoutingBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'disetujui_at' => 'datetime',
        ];
    }

    /** @return HasMany<Kendaraan, $this> */
    public function kendaraans(): HasMany
    {
        return $this->hasMany(Kendaraan::class)->orderBy('nomor');
    }

    /** @return HasManyThrough<KendaraanStop, Kendaraan, $this> */
    public function stops(): HasManyThrough
    {
        return $this->hasManyThrough(KendaraanStop::class, Kendaraan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isDisetujui(): bool
    {
        return $this->status === 'disetujui';
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    #[Scope]
    protected function disetujui(Builder $query): void
    {
        $query->where('status', 'disetujui');
    }
}
