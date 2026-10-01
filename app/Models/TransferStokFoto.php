<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto barang diterima, diunggah gudang TUJUAN saat accept. Opsional dan
 * bebas jumlahnya (bukan daftar jenis tetap seperti bukti NOO/Tarik Freezer)
 * — lihat App\Livewire\TransferStok\DaftarTransferStok.
 */
#[Fillable(['transfer_stok_id', 'path', 'diunggah_oleh'])]
class TransferStokFoto extends Model
{
    /** @return BelongsTo<TransferStok, $this> */
    public function transferStok(): BelongsTo
    {
        return $this->belongsTo(TransferStok::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }
}
