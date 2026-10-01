<?php

namespace App\Models;

use App\Models\Scopes\DepotScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transfer_stok_id', 'produk_asal_id', 'produk_tujuan_id', 'jumlah_kirim', 'jumlah_terima'])]
class TransferStokItem extends Model
{
    protected function casts(): array
    {
        return [
            'jumlah_kirim' => 'integer',
            'jumlah_terima' => 'integer',
        ];
    }

    /** @return BelongsTo<TransferStok, $this> */
    public function transferStok(): BelongsTo
    {
        return $this->belongsTo(TransferStok::class);
    }

    /**
     * Tanpa DepotScope dengan sengaja: baris ini kadang dilihat dari konteks
     * gudang TUJUAN, di mana produk gudang ASAL normalnya tersaring habis
     * oleh DepotScope-nya Produk (begitu pula sebaliknya). Siapa pun yang
     * bisa melihat TransferStokItem ini (sudah tersaring lewat
     * TransferStok::saringDepot()) berhak melihat kedua produk yang
     * dirujuknya, dari gudang mana pun asalnya.
     *
     * @return BelongsTo<Produk, $this>
     */
    public function produkAsal(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_asal_id')->withoutGlobalScope(DepotScope::class);
    }

    /** @return BelongsTo<Produk, $this> */
    public function produkTujuan(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_tujuan_id')->withoutGlobalScope(DepotScope::class);
    }

    /** Selisih kurang dari yang diterima vs yang dikirim — null selama belum diterima. */
    protected function kekurangan(): Attribute
    {
        return Attribute::get(
            fn (): ?int => $this->jumlah_terima === null ? null : max(0, $this->jumlah_kirim - $this->jumlah_terima)
        );
    }
}
