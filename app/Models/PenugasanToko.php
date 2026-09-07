<?php

namespace App\Models;

use App\Enums\HariKunjungan;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal kunjungan MINGGUAN yang berdiri sendiri: satu toko = satu slot
 * hari untuk satu sales, berlaku terus sampai admin sendiri yang
 * mengubahnya — bukan disusun ulang tiap bulan seperti `PenugasanSales`
 * (tabel lama, masih ada untuk riwayat tapi tidak lagi dipakai fitur
 * manapun). `unique('toko_id')` di migrasinya-lah yang menjaga "sekali
 * toko dijadwalkan di satu hari, tidak bisa masuk hari lain lagi".
 */
#[Fillable(['toko_id', 'sales_id', 'hari', 'ditugaskan_oleh'])]
class PenugasanToko extends Model
{
    use BerDepot, HasFactory;

    protected function casts(): array
    {
        return ['hari' => HariKunjungan::class];
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditugaskan_oleh');
    }

    #[Scope]
    protected function hari(Builder $query, HariKunjungan|int $hari): void
    {
        $query->where('hari', $hari instanceof HariKunjungan ? $hari->value : $hari);
    }
}
