<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pesanan_id', 'produk_id', 'jumlah_dus', 'jumlah_dus_terkirim', 'harga_satuan', 'subtotal'])]
class PesananItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'jumlah_dus' => 'integer',
            'jumlah_dus_terkirim' => 'integer',
            'harga_satuan' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Pesanan, $this> */
    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class);
    }

    /**
     * Jumlah yang benar-benar diterima toko. Kosong berarti terkirim penuh
     * sesuai pesanan; terisi ketika notanya dicoret di lapangan.
     */
    protected function terkirim(): Attribute
    {
        return Attribute::get(fn (): int => $this->jumlah_dus_terkirim ?? $this->jumlah_dus);
    }

    /** Sisa yang tidak jadi diterima toko, jadi bisa diampaskan. */
    protected function sisa(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->jumlah_dus - $this->terkirim));
    }

    /** @return BelongsTo<Produk, $this> */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}
