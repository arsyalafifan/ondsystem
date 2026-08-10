<?php

namespace App\Models;

use App\Enums\StatusKunjungan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'tanggal_mulai', 'tanggal_selesai', 'tahun', 'minggu', 'status'])]
class PeriodeKunjungan extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'berjalan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tahun' => 'integer',
            'minggu' => 'integer',
        ];
    }

    /** @return HasMany<PeriodeSales, $this> */
    public function periodeSales(): HasMany
    {
        return $this->hasMany(PeriodeSales::class);
    }

    /** @return HasMany<Kunjungan, $this> */
    public function kunjungans(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function berjalan(): bool
    {
        return $this->status === 'berjalan';
    }

    protected function rentang(): Attribute
    {
        return Attribute::get(fn (): string => $this->tanggal_mulai->isoFormat('ll').' – '.$this->tanggal_selesai->isoFormat('ll'));
    }

    /** Rekap seluruh sales pada periode ini. */
    protected function ringkasan(): Attribute
    {
        return Attribute::get(function (): array {
            $target = (int) $this->periodeSales->sum('target_toko');

            $perStatus = $this->kunjungans
                ->groupBy(fn (Kunjungan $k) => $k->status->value)
                ->map->count();

            $tutup = (int) ($perStatus[StatusKunjungan::TutupDisetujui->value] ?? 0);
            $selesai = (int) ($perStatus[StatusKunjungan::Selesai->value] ?? 0);
            $efektif = max(0, $target - $tutup);

            return [
                'target' => $target,
                'target_efektif' => $efektif,
                'selesai' => $selesai,
                'tutup' => $tutup,
                'menunggu' => (int) ($perStatus[StatusKunjungan::TutupDiajukan->value] ?? 0),
                'belum' => max(0, $efektif - $selesai),
                'persen' => $efektif === 0 ? 0 : (int) round($selesai / $efektif * 100),
            ];
        });
    }

    #[Scope]
    protected function berjalanSaja(Builder $query): void
    {
        $query->where('status', 'berjalan');
    }
}
