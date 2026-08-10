<?php

namespace App\Models;

use App\Enums\StatusKunjungan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Progres seorang sales pada satu periode mingguan.
 *
 * `target_toko` adalah salinan jumlah tanggungannya saat periode dibuka.
 * Disalin, bukan dihitung ulang, supaya angka target minggu yang sudah lewat
 * tidak ikut berubah ketika admin menyusun ulang penugasan bulan berikutnya.
 */
#[Fillable(['periode_kunjungan_id', 'sales_id', 'target_toko'])]
class PeriodeSales extends Model
{
    use HasFactory;

    protected $attributes = [
        'target_toko' => 0,
    ];

    protected function casts(): array
    {
        return ['target_toko' => 'integer'];
    }

    /** @return BelongsTo<PeriodeKunjungan, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeKunjungan::class, 'periode_kunjungan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    /** @return HasMany<Kunjungan, $this> */
    public function kunjungans(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    /**
     * Angka-angka progres. Toko yang laporan tutupnya disetujui admin
     * dikeluarkan dari penyebut, karena tidak bisa dikunjungi dan bukan
     * kesalahan sales.
     */
    protected function progres(): Attribute
    {
        return Attribute::get(function (): array {
            $perStatus = $this->kunjungans
                ->groupBy(fn (Kunjungan $k) => $k->status->value)
                ->map->count();

            $selesai = (int) ($perStatus[StatusKunjungan::Selesai->value] ?? 0);
            $tutup = (int) ($perStatus[StatusKunjungan::TutupDisetujui->value] ?? 0);
            $menunggu = (int) ($perStatus[StatusKunjungan::TutupDiajukan->value] ?? 0);
            $berjalan = (int) ($perStatus[StatusKunjungan::Berjalan->value] ?? 0);

            $efektif = max(0, $this->target_toko - $tutup);

            return [
                'target' => $this->target_toko,
                'target_efektif' => $efektif,
                'selesai' => $selesai,
                'tutup' => $tutup,
                'menunggu' => $menunggu,
                'berjalan' => $berjalan,
                'belum' => max(0, $efektif - $selesai),
                'persen' => $efektif === 0 ? 0 : (int) round($selesai / $efektif * 100),
            ];
        });
    }
}
