<?php

namespace App\Models;

use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Promo periode "beli N dus gratis M dus". Aktif/tidaknya murni ditentukan
 * `tanggal_mulai`/`tanggal_selesai` (lihat aktifPada()) — begitu periodenya
 * lewat, promo otomatis berhenti muncul di Input Pesanan tanpa admin perlu
 * menonaktifkannya manual.
 */
#[Fillable(['id', 'nama', 'tanggal_mulai', 'tanggal_selesai', 'minimal_dus', 'bonus_dus'])]
class Promo extends Model
{
    use BerDepot, HasFactory;

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'minimal_dus' => 'integer',
            'bonus_dus' => 'integer',
        ];
    }

    /** @return BelongsToMany<Produk, $this> */
    public function produks(): BelongsToMany
    {
        // ->using(PromoProduk::class): lihat docblock PromoProduk — supaya
        // sync()/attach() di manapun (termasuk fixture test) otomatis
        // mengisi depot_id pivot, bukan cuma di satu titik pemanggilan.
        return $this->belongsToMany(Produk::class, 'promo_produk')->using(PromoProduk::class);
    }

    /** @return HasMany<Pesanan, $this> */
    public function pesanans(): HasMany
    {
        return $this->hasMany(Pesanan::class);
    }

    /**
     * Promo yang periodenya mencakup $tanggal (format 'Y-m-d'). whereDate(),
     * bukan whereBetween() dengan string tanggal polos — kolom `date` bisa
     * saja tersimpan dengan sisa waktu "00:00:00" di baliknya tergantung
     * driver basis data, sama seperti alasan di
     * Pesanan::tanggalPendapatanAntara().
     */
    #[Scope]
    protected function aktifPada(Builder $query, string $tanggal): void
    {
        $query->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal);
    }
}
