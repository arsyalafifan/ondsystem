<?php

namespace App\Models;

use App\Enums\JenisAbsensi;
use App\Enums\LokasiAbsensi;
use App\Enums\StatusAbsensi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu kejadian absen — lihat catatan lengkap di migrasi
 * `create_absensis_table`.
 */
#[Fillable([
    'karyawan_id', 'tanggal', 'jenis', 'waktu', 'status', 'telat_menit', 'jam_acuan',
    'posisi_id', 'shift_id', 'foto', 'latitude', 'longitude', 'akurasi_m', 'jarak_m',
    'lokasi_jenis', 'depot_id', 'toko_id',
])]
class Absensi extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu' => 'datetime',
            'jenis' => JenisAbsensi::class,
            'status' => StatusAbsensi::class,
            'lokasi_jenis' => LokasiAbsensi::class,
            'telat_menit' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'akurasi_m' => 'integer',
            'jarak_m' => 'integer',
        ];
    }

    /** @return BelongsTo<Karyawan, $this> */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    /** @return BelongsTo<Posisi, $this> */
    public function posisi(): BelongsTo
    {
        return $this->belongsTo(Posisi::class);
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** @return BelongsTo<Depot, $this> */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    protected function urlFoto(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('visit.foto.disk'))->url($this->foto));
    }

    #[Scope]
    protected function tanggal(Builder $query, string $tanggal): void
    {
        $query->whereDate('tanggal', $tanggal);
    }
}
