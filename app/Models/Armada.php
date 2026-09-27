<?php

namespace App\Models;

use App\Enums\JenisArmada;
use App\Enums\StatusArmada;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Katalog unit kendaraan fisik (plat, jenis, status perawatan) untuk SEMUA
 * gudang — sengaja tanpa BerDepot, sama seperti App\Models\Freezer.
 *
 * BELUM dipakai mesin routing. `App\Models\Kendaraan` ("Mobil 1", "Mobil 2",
 * dst.) tetap dibentuk apa adanya oleh App\Services\RoutingService sampai
 * data di sini cukup lengkap untuk dijadikan sumber pilihan mobil — lihat
 * migrasi create_armadas_table.
 *
 * `status` diisi manual lewat dropdown untuk saat ini. Rencananya akan ada
 * menu Maintenance Kendaraan terpisah yang mengubah status ini otomatis
 * (mis. mulai servis → "sedang_diperbaiki", selesai → "normal") — sampai
 * menu itu ada, admin yang menjaganya tetap akurat sendiri.
 */
#[Fillable(['id', 'plat', 'jenis', 'status'])]
class Armada extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'normal',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisArmada::class,
            'status' => StatusArmada::class,
        ];
    }

    #[Scope]
    protected function cari(Builder $query, string $cari): void
    {
        $cari = trim($cari);

        if ($cari !== '') {
            $query->where('plat', 'like', "%{$cari}%");
        }
    }
}
