<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shift kerja khusus milik seorang karyawan — lihat catatan di migrasi
 * `create_shifts_table`. Karyawan tanpa shift mengikuti jam kerja posisinya.
 */
#[Fillable(['id', 'kode', 'nama', 'jam_masuk', 'jam_pulang', 'lintas_hari', 'aktif'])]
class Shift extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'aktif' => true,
        'lintas_hari' => false,
    ];

    protected function casts(): array
    {
        return [
            'lintas_hari' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    /** @return HasMany<Karyawan, $this> */
    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }
}
