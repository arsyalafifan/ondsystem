<?php

namespace App\Models;

use App\Enums\StatusTransferStok;
use App\Models\Concerns\DisaringDepotSendiri;
use App\Models\Scopes\DepotScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu pengiriman stok dari satu gudang penyimpanan ke gudang penyimpanan
 * lain. Lihat docblock migrasinya untuk alasan tidak memakai BerDepot —
 * baris ini "milik" dua depot sekaligus, keanggotaannya diputuskan lewat
 * saringDepot() di bawah (DisaringDepotSendiri), persis seperti App\Models\User.
 */
#[Fillable([
    'kode', 'status', 'depot_asal_id', 'depot_tujuan_id',
    'dikirim_oleh', 'dikirim_at', 'catatan_kirim',
    'diterima_oleh', 'diterima_at', 'catatan_terima',
    'dibatalkan_oleh', 'dibatalkan_at', 'alasan_batal',
])]
class TransferStok extends Model implements DisaringDepotSendiri
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new DepotScope);
    }

    protected function casts(): array
    {
        return [
            'status' => StatusTransferStok::class,
            'dikirim_at' => 'datetime',
            'diterima_at' => 'datetime',
            'dibatalkan_at' => 'datetime',
        ];
    }

    /** Terlihat dari gudang asal MAUPUN gudang tujuan — dua-duanya berkepentingan. */
    public function saringDepot(Builder $query, int $depotId): void
    {
        $query->where(fn (Builder $q) => $q
            ->where($this->qualifyColumn('depot_asal_id'), $depotId)
            ->orWhere($this->qualifyColumn('depot_tujuan_id'), $depotId));
    }

    /** @return BelongsTo<Depot, $this> */
    public function depotAsal(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'depot_asal_id');
    }

    /** @return BelongsTo<Depot, $this> */
    public function depotTujuan(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'depot_tujuan_id');
    }

    /**
     * Tanpa DepotScope dengan sengaja, sama alasannya dengan
     * TransferStokItem::produkAsal(): pengirim bisa jadi pengguna yang
     * aksesnya cuma ke gudang asal, dilihat dari konteks gudang tujuan
     * (atau sebaliknya) — tanpa ini namanya akan kosong di sisi seberang.
     *
     * @return BelongsTo<User, $this>
     */
    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikirim_oleh')->withoutGlobalScope(DepotScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh')->withoutGlobalScope(DepotScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh')->withoutGlobalScope(DepotScope::class);
    }

    /** @return HasMany<TransferStokItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TransferStokItem::class);
    }

    /** @return HasMany<TransferStokFoto, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(TransferStokFoto::class)->orderBy('id');
    }

    #[Scope]
    protected function dikirim(Builder $query): void
    {
        $query->where('status', StatusTransferStok::Dikirim);
    }
}
