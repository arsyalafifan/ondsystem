<?php

namespace App\Models;

use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'depot_id', 'idn', 'tipe', 'keterangan', 'aktif'])]
class Freezer extends Model
{
    use BerDepot, HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }

    /**
     * Label ringkas untuk pemilih IDN (Master Toko, Lengkapi Data Toko,
     * pemasangan freezer NOO) — satu bentuk yang sama dipakai di mana pun
     * IDN perlu ditampilkan sambil menyebut tipenya sekalian.
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->idn.' · '.$this->tipe);
    }

    #[Scope]
    protected function cari(Builder $query, string $cari): void
    {
        $cari = trim($cari);
        if ($cari === '') {
            return;
        }

        $query->where(function (Builder $q) use ($cari): void {
            $q->where('idn', 'like', "%{$cari}%")
                ->orWhere('tipe', 'like', "%{$cari}%")
                ->orWhere('keterangan', 'like', "%{$cari}%");
        });
    }

    /**
     * Toko yang saat ini dipasangi freezer ini (dicocokkan lewat asset_id = idn).
     *
     * @return HasMany<Toko, $this>
     */
    public function tokos(): HasMany
    {
        return $this->hasMany(Toko::class, 'asset_id', 'idn');
    }
}
