<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu gudang/tenant fisik (Perawang, Dumai, Bengkalis, ...).
 * Model ini sendiri TIDAK di-scope oleh App\Models\Scopes\DepotScope —
 * ini tabel tenant-nya sendiri, bukan data milik salah satu tenant.
 */
#[Fillable(['kode', 'nama', 'lat', 'lng', 'service_minutes', 'jam_berangkat', 'max_toko', 'max_dus', 'min_dus_per_toko', 'aktif'])]
class Depot extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'service_minutes' => 'integer',
            'max_toko' => 'integer',
            'max_dus' => 'integer',
            'min_dus_per_toko' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }
}
