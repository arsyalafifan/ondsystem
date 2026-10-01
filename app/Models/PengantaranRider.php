<?php

namespace App\Models;

use App\Enums\StatusPengantaranRider;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pesanan yang ditandai untuk diantar rider (bukan rute kendaraan) —
 * lihat App\Services\PengantaranRider\PengantaranRiderService. Berbeda dari
 * KendaraanStop, tidak ada kendaraan/urutan sama sekali: baris ini "milik"
 * satu gudang (BerDepot biasa, bukan DisaringDepotSendiri — pesanan dan
 * rider-nya selalu berada di gudang yang sama), mulai tersedia di pool
 * sampai rider menyelesaikannya.
 */
#[Fillable([
    'pesanan_id', 'status', 'ditandai_oleh', 'ditandai_at',
    'rider_id', 'diambil_at', 'dilepas_at',
    'foto_nota', 'catatan_rider', 'selesai_at',
])]
class PengantaranRider extends Model
{
    use BerDepot;

    protected function casts(): array
    {
        return [
            'status' => StatusPengantaranRider::class,
            'ditandai_at' => 'datetime',
            'diambil_at' => 'datetime',
            'dilepas_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pesanan, $this> */
    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penanda(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditandai_oleh');
    }

    /** @return HasMany<PengantaranRiderFoto, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(PengantaranRiderFoto::class)->orderBy('id');
    }

    #[Scope]
    protected function tersedia(Builder $query): void
    {
        $query->where('status', StatusPengantaranRider::Tersedia);
    }
}
