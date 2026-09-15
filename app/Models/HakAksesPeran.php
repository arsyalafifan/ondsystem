<?php

namespace App\Models;

use App\Enums\CakupanData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu pengecualian hak akses (peran × menu) terhadap bawaan di
 * App\Akses\DaftarAkses. Sengaja tidak BerDepot — berlaku lintas depot.
 */
#[Fillable(['peran', 'menu', 'boleh', 'cakupan', 'diubah_oleh'])]
class HakAksesPeran extends Model
{
    protected function casts(): array
    {
        return [
            'boleh' => 'boolean',
            'cakupan' => CakupanData::class,
        ];
    }
}
