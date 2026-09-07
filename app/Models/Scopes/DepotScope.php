<?php

namespace App\Models\Scopes;

use App\Exceptions\DepotTidakDiketahui;
use App\Support\DepotContext;
use App\Support\ModeDepot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Menjaga setiap query model yang pakai App\Models\Concerns\BerDepot
 * supaya hanya menyentuh baris milik depot yang sedang aktif.
 *
 * Kondisi default (BelumDitentukan) sengaja GAGAL KERAS lewat exception,
 * bukan diam-diam mengembalikan data tanpa filter — kalau ada kode yang
 * lupa menyiapkan konteks depot, itu harus langsung ketahuan di log
 * sebagai DepotTidakDiketahui, bukan berubah jadi kebocoran data yang
 * baru disadari belakangan.
 */
final class DepotScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        match (DepotContext::mode()) {
            ModeDepot::Terkunci => $builder->where(
                $model->qualifyColumn('depot_id'),
                DepotContext::currentOrFail()->id,
            ),
            // Satu-satunya cara melihat data lintas-depot, dan hanya bisa
            // dicapai lewat aksi eksplisit superadmin (login pilih "Semua
            // Depot", atau switcher) — bukan default diam-diam.
            ModeDepot::SemuaDepot => null,
            ModeDepot::BelumDitentukan => throw DepotTidakDiketahui::saatQuery($model::class),
        };
    }
}
