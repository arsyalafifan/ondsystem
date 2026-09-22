<?php

namespace App\Models;

use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris produk di dalam paket NOO. Bentuknya sengaja dibuat sama dengan
 * `PesananItem` (produk + jumlah dus + penanda bonus) karena baris-baris
 * inilah yang disalin apa adanya menjadi item pesanan perdana saat NOO
 * selesai diantar.
 */
#[Fillable(['paket_noo_id', 'produk_id', 'jumlah_dus', 'is_bonus'])]
class PaketNooItem extends Model
{
    use BerDepot, HasFactory;

    protected function casts(): array
    {
        return [
            'jumlah_dus' => 'integer',
            'is_bonus' => 'boolean',
        ];
    }

    /** @return BelongsTo<PaketNoo, $this> */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketNoo::class, 'paket_noo_id');
    }

    /** @return BelongsTo<Produk, $this> */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}
