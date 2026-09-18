<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Model ber-DepotScope yang keanggotaan depotnya TIDAK ditentukan oleh satu
 * kolom depot_id — saat ini hanya User, yang bisa punya akses ke beberapa
 * gudang sekaligus (tabel `depot_user`). DepotScope memanggil saringDepot()
 * sebagai ganti filter `depot_id = ?` biasa.
 */
interface DisaringDepotSendiri
{
    public function saringDepot(Builder $query, int $depotId): void;
}
