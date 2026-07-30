<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kendaraan_id', 'pesanan_id', 'toko_id', 'urutan', 'total_dus',
    'jarak_dari_sebelumnya_m', 'durasi_dari_sebelumnya_s', 'eta',
    'status', 'foto_nota', 'catatan_driver', 'selesai_at',
])]
class KendaraanStop extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'total_dus' => 'integer',
            'jarak_dari_sebelumnya_m' => 'integer',
            'durasi_dari_sebelumnya_s' => 'integer',
            'selesai_at' => 'datetime',
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
}
