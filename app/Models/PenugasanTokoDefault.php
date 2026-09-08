<?php

namespace App\Models;

use App\Enums\HariKunjungan;
use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cadangan "default" jadwal mingguan SATU sales — dipakai sebagai
 * checkpoint yang bisa dipulihkan kapan saja lewat "Restore ke Default"
 * (lihat `PenugasanTokoService::jadikanDefault()`/`restoreDefault()`).
 * Baris di sini murni tempat penyimpanan; tidak pernah dibaca
 * `KunjunganService`/`PeriodeKunjunganService` sama sekali — cuma
 * `PenugasanToko` (jadwal yang SUNGGUH berlaku) yang menentukan tanggungan
 * sales sehari-hari.
 */
#[Fillable(['sales_id', 'toko_id', 'hari'])]
class PenugasanTokoDefault extends Model
{
    use BerDepot, HasFactory;

    protected function casts(): array
    {
        return ['hari' => HariKunjungan::class];
    }

    /** @return BelongsTo<User, $this> */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }
}
