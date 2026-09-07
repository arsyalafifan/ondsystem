<?php

namespace App\Models;

use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris per depot — pengaturan kunjungan yang bisa admin ubah
 * langsung dari layar Penugasan Toko, bukan lewat deploy/`.env` seperti
 * `config/visit.php`. Sebelum multi-depot ini singleton global; sekarang
 * `ambil()` otomatis terbatas ke baris milik depot aktif lewat DepotScope
 * (dipasang trait BerDepot), tanpa perlu ubah logikanya sama sekali.
 * Depot baru dapat baris pengaturannya sendiri lewat DepotService::buat().
 */
#[Fillable(['maks_toko_per_hari'])]
class PengaturanKunjungan extends Model
{
    use BerDepot;

    protected function casts(): array
    {
        return ['maks_toko_per_hari' => 'integer'];
    }

    public static function ambil(): self
    {
        return static::query()->firstOrFail();
    }
}
