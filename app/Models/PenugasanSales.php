<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu toko yang menjadi tanggungan seorang sales pada satu bulan.
 */
#[Fillable(['sales_id', 'toko_id', 'bulan', 'ditugaskan_oleh'])]
class PenugasanSales extends Model
{
    use HasFactory;

    protected $table = 'penugasan_sales';

    protected function casts(): array
    {
        return ['bulan' => 'date'];
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

    /** @return BelongsTo<User, $this> */
    public function penugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditugaskan_oleh');
    }

    #[Scope]
    protected function bulan(Builder $query, string $bulan): void
    {
        $query->whereDate('bulan', $bulan);
    }
}
