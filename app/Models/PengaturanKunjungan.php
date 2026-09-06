<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris tunggal (singleton) — pengaturan kunjungan yang bisa admin ubah
 * langsung dari layar Penugasan Toko, bukan lewat deploy/`.env` seperti
 * `config/visit.php`. Baris pertamanya sudah disisipkan oleh migrasi
 * `create_pengaturan_kunjungans_table`, jadi kode di sini tidak pernah
 * perlu `firstOrCreate()` — selalu tinggal ambil baris yang sudah pasti
 * ada lewat `ambil()`.
 */
#[Fillable(['maks_toko_per_hari'])]
class PengaturanKunjungan extends Model
{
    protected function casts(): array
    {
        return ['maks_toko_per_hari' => 'integer'];
    }

    public static function ambil(): self
    {
        return static::query()->firstOrFail();
    }
}
