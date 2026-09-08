<?php

namespace App\Models\Concerns;

use App\Models\Depot;
use App\Models\Scopes\DepotScope;
use App\Support\DepotContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dipasang di setiap model yang datanya harus terisolasi per depot.
 * Memasang App\Models\Scopes\DepotScope ke setiap query model ini, dan
 * otomatis mengisi depot_id saat baris baru dibuat.
 *
 * array_key_exists (bukan `!== null`) dipakai supaya pemanggil bisa
 * mengirim depot_id => null secara sengaja (satu-satunya kasus nyata:
 * User untuk akun superadmin) tanpa kena auto-stamp, sementara model lain
 * yang tidak pernah menyebut depot_id sama sekali tetap otomatis terisi
 * tanpa perlu ditulis manual di manapun.
 */
trait BerDepot
{
    public static function bootBerDepot(): void
    {
        static::addGlobalScope(new DepotScope);

        static::creating(function (Model $model): void {
            if (array_key_exists('depot_id', $model->getAttributes())) {
                return;
            }

            $model->setAttribute('depot_id', DepotContext::currentOrFail()->id);
        });
    }

    /** @return BelongsTo<Depot, $this> */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }
}
