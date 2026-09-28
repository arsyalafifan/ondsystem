<?php

namespace App\Models;

use App\Models\Scopes\DepotScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Katalog freezer untuk SEMUA gudang (General Master Freezer) — sengaja
 * tanpa BerDepot. Gudang sebuah IDN diketahui lewat toko yang memegangnya.
 */
#[Fillable(['id', 'idn', 'tipe', 'keterangan', 'aktif', 'depot_simpan_id', 'gudang_dicatat_oleh', 'gudang_dicatat_at'])]
class Freezer extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'gudang_dicatat_at' => 'datetime',
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

    /**
     * Cari juga lewat nama/kode toko pemegangnya, supaya "freezer ini ada di
     * toko apa?" bisa dijawab dari kotak pencarian yang sama.
     */
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
                ->orWhere('keterangan', 'like', "%{$cari}%")
                ->orWhereHas('toko', fn (Builder $t) => $t
                    ->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%"));
        });
    }

    /** Freezer yang belum dipasang di toko mana pun (di gudang mana pun). */
    #[Scope]
    protected function tersedia(Builder $query): void
    {
        $query->whereDoesntHave('toko');
    }

    /**
     * Satu-satunya toko yang memegang freezer ini (dicocokkan lewat
     * asset_id = idn; IDN unik global, jadi paling banyak satu). Filter
     * gudang sengaja dilepas — Master Freezer berlaku lintas gudang.
     *
     * @return HasOne<Toko, $this>
     */
    public function toko(): HasOne
    {
        return $this->hasOne(Toko::class, 'asset_id', 'idn')->withoutGlobalScope(DepotScope::class);
    }

    /** @return BelongsTo<Depot, $this> */
    public function depotSimpan(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'depot_simpan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pencatatGudang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gudang_dicatat_oleh');
    }

    /**
     * Gudang freezer ini SAAT INI, seperti COALESCE: kalau terpasang di
     * toko, ikut gudang tokonya; kalau tidak, gudang tempat ia dicatat
     * disimpan (menu Freezer ke Gudang); kalau dua-duanya kosong, null —
     * lokasinya belum diketahui. `toko.depot` dan `depotSimpan` sebaiknya
     * sudah dimuat oleh pemanggil yang menampilkan banyak baris.
     */
    protected function gudangSaatIni(): Attribute
    {
        return Attribute::get(fn (): ?Depot => $this->toko?->depot ?? $this->depotSimpan);
    }

    /** 'toko' | 'gudang' | null — dari mana gudangSaatIni() berasal. */
    protected function sumberGudang(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->toko !== null ? 'toko' : ($this->depotSimpan !== null ? 'gudang' : null));
    }

    /** Freezer yang gudangnya (menurut COALESCE di atas) adalah $depotId. */
    #[Scope]
    protected function digudang(Builder $query, int $depotId): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereHas('toko', fn (Builder $t) => $t->where('depot_id', $depotId))
            ->orWhere(fn (Builder $l) => $l->whereDoesntHave('toko')->where('depot_simpan_id', $depotId)));
    }
}
