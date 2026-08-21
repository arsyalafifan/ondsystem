<?php

namespace App\Models;

use App\Enums\StatusStop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'kendaraan_id', 'pesanan_id', 'toko_id', 'urutan', 'jenis', 'total_dus',
    'total_dus_terkirim', 'jarak_dari_sebelumnya_m', 'durasi_dari_sebelumnya_s',
    'eta', 'status', 'foto_nota', 'catatan_driver', 'alasan_batal',
    'catatan_batal', 'dibatalkan_at', 'selesai_at',
])]
class KendaraanStop extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'pending',
        'jenis' => 'rute',
        'total_dus_terkirim' => 0,
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'total_dus' => 'integer',
            'total_dus_terkirim' => 'integer',
            'jarak_dari_sebelumnya_m' => 'integer',
            'durasi_dari_sebelumnya_s' => 'integer',
            'status' => StatusStop::class,
            'selesai_at' => 'datetime',
            'dibatalkan_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Kendaraan, $this> */
    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class);
    }

    /** @return BelongsTo<Pesanan, $this> */
    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class);
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    public function isKampas(): bool
    {
        return $this->jenis === 'kampas';
    }

    /** Dus yang tidak jadi terkirim, entah karena dibatalkan atau notanya dicoret. */
    protected function dusTersisa(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->total_dus - $this->total_dus_terkirim));
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->foto_nota === null
            ? null
            : Storage::disk('public')->url($this->foto_nota));
    }

    #[Scope]
    protected function belumTuntas(Builder $query): void
    {
        $query->where('status', StatusStop::Pending);
    }
}
