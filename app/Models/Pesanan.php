<?php

namespace App\Models;

use App\Enums\StatusPesanan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'kode', 'toko_id', 'wilayah_id', 'dibuat_oleh', 'status', 'tanggal',
    'total_dus', 'total_nilai', 'catatan', 'diproses_oleh', 'diproses_at',
    'dikirim_at', 'selesai_at', 'alasan_cancel', 'catatan_cancel',
    'dibatalkan_oleh', 'dibatalkan_at',
])]
class Pesanan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => StatusPesanan::class,
            'tanggal' => 'date',
            'total_dus' => 'integer',
            'total_nilai' => 'decimal:2',
            'diproses_at' => 'datetime',
            'dikirim_at' => 'datetime',
            'selesai_at' => 'datetime',
            'dibatalkan_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    /** @return HasMany<PesananItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PesananItem::class);
    }

    /** @return HasOne<KendaraanStop, $this> */
    public function stop(): HasOne
    {
        return $this->hasOne(KendaraanStop::class);
    }

    #[Scope]
    protected function status(Builder $query, StatusPesanan|string $status): void
    {
        $query->where('status', $status instanceof StatusPesanan ? $status->value : $status);
    }

    /** Pesanan yang siap masuk proses routing. */
    #[Scope]
    protected function siapRouting(Builder $query): void
    {
        $query->where('status', StatusPesanan::Process)->whereDoesntHave('stop');
    }

    #[Scope]
    protected function hariIni(Builder $query): void
    {
        $query->whereDate('tanggal', today());
    }
}
