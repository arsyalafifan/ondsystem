<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jabatan — lintas-gudang, sengaja TIDAK memakai trait BerDepot. Sifatnya
 * sama persis dengan Department, lihat catatan lengkap di migrasi
 * `create_jabatans_table`.
 */
#[Fillable(['id', 'kode', 'nama'])]
class Jabatan extends Model
{
    use HasFactory, SoftDeletes;

    /** @return HasMany<Karyawan, $this> */
    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
