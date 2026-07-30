<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'satuan', 'stok', 'stok_reserved', 'harga', 'aktif'])]
class Produk extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'satuan' => 'dus',
        'stok' => 0,
        'stok_reserved' => 0,
        'harga' => 0,
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'stok_reserved' => 'integer',
            'harga' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    /**
     * Stok yang benar-benar bisa dipesan: stok fisik dikurangi yang sudah
     * dikunci oleh pesanan berjalan.
     */
    protected function stokTersedia(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->stok - $this->stok_reserved));
    }

    /** @return HasMany<PesananItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PesananItem::class);
    }

    /** @return HasMany<StokMutasi, $this> */
    public function mutasis(): HasMany
    {
        return $this->hasMany(StokMutasi::class);
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }
}
