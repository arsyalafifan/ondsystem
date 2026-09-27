<?php

namespace App\Models;

use App\Enums\StatusTarikFreezer;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu pengajuan penarikan freezer dari toko yang berhenti jadi mitra,
 * kebalikan dari App\Models\Noo.
 *
 * Berbeda dari NOO, `toko_id` di sini wajib terisi sejak baris ini dibuat —
 * tokonya sudah ada, tidak ada yang perlu "lahir" saat disetujui. Toko baru
 * dinonaktifkan dan `asset_id`-nya baru dikosongkan saat driver menuntaskan
 * pengambilan (lihat App\Services\TarikFreezer\TarikFreezerService::selesaikan()),
 * bukan lebih awal — freezernya secara fisik masih di toko sampai saat itu.
 */
#[Fillable(['kode', 'status', 'toko_id', 'alasan', 'diajukan_oleh', 'diajukan_at', 'disetujui_oleh', 'disetujui_at', 'ditolak_oleh', 'ditolak_at', 'alasan_tolak', 'diselesaikan_oleh', 'selesai_at'])]
class TarikFreezer extends Model
{
    use BerDepot, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => StatusTarikFreezer::class,
            'diajukan_at' => 'datetime',
            'disetujui_at' => 'datetime',
            'ditolak_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    /** @return HasOne<KendaraanStop, $this> */
    public function stop(): HasOne
    {
        return $this->hasOne(KendaraanStop::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penolak(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditolak_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diselesaikan_oleh');
    }

    /** @return HasMany<TarikFreezerFoto, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(TarikFreezerFoto::class)->orderBy('id');
    }

    #[Scope]
    protected function berjalan(Builder $query): void
    {
        $query->whereIn('status', StatusTarikFreezer::berjalan());
    }

    #[Scope]
    protected function menungguPersetujuan(Builder $query): void
    {
        $query->where('status', StatusTarikFreezer::Order);
    }
}
