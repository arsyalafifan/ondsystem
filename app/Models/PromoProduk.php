<?php

namespace App\Models;

use App\Support\DepotContext;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot khusus (bukan Pivot bawaan Eloquent) SATU-SATUNYA alasannya: sync()/
 * attach() menulis lewat query builder mentah, bukan Model::create(), jadi
 * trait BerDepot yang biasa dipakai model lain tidak pernah jalan untuk baris
 * pivot. Kelas ini menambal celah yang sama seperti bug
 * PenugasanTokoDefault::insert() sebelumnya, tapi dipasang sekali di sini
 * lewat Promo::produks()->using(), bukan mengandalkan setiap pemanggil
 * sync()/attach() ingat menyebut depot_id manual.
 */
class PromoProduk extends Pivot
{
    public static function boot(): void
    {
        parent::boot();

        static::creating(function (self $pivot): void {
            if (array_key_exists('depot_id', $pivot->getAttributes())) {
                return;
            }
            $pivot->setAttribute('depot_id', DepotContext::currentOrFail()->id);
        });
    }
}
