<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lihat catatan lengkap di migrasi `create_pengajuan_lemburs_table`. */
#[Fillable([
    'karyawan_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'lintas_hari', 'menit_diajukan',
    'menit_maks', 'tugas', 'status', 'diajukan_oleh', 'diputuskan_oleh', 'diputuskan_at',
    'catatan_keputusan',
])]
class PengajuanLembur extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'menunggu',
        'lintas_hari' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPengajuan::class,
            'tanggal' => 'date',
            'lintas_hari' => 'boolean',
            'menit_diajukan' => 'integer',
            'menit_maks' => 'integer',
            'diputuskan_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Karyawan, $this> */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function diajukanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function diputuskanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    #[Scope]
    protected function disetujui(Builder $query): void
    {
        $query->where('status', StatusPengajuan::Disetujui);
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->whereIn('status', [StatusPengajuan::Menunggu, StatusPengajuan::Disetujui]);
    }

    /** Tanggal kerja lemburnya di dalam rentang — sama bentuknya dengan PengajuanIzin. */
    #[Scope]
    protected function beririsan(Builder $query, CarbonInterface $mulai, CarbonInterface $selesai): void
    {
        $query->whereDate('tanggal', '>=', $mulai->toDateString())
            ->whereDate('tanggal', '<=', $selesai->toDateString());
    }

    public function beririsanDengan(CarbonInterface $mulai, CarbonInterface $selesai): bool
    {
        return $this->tanggal->betweenIncluded($mulai->copy()->startOfDay(), $selesai->copy()->startOfDay());
    }

    public function mulaiAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->tanggal->toDateString().' '.$this->jam_mulai);
    }

    public function selesaiAt(): CarbonImmutable
    {
        $selesai = CarbonImmutable::parse($this->tanggal->toDateString().' '.$this->jam_selesai);

        return $this->lintas_hari ? $selesai->addDay() : $selesai;
    }

    public function jamTeks(): string
    {
        return substr((string) $this->jam_mulai, 0, 5).'–'.substr((string) $this->jam_selesai, 0, 5)
            .($this->lintas_hari ? ' (+1)' : '');
    }

    /** "1 j 30 m" — dipakai semua tampilan lembur. */
    public static function formatMenit(?int $menit): string
    {
        if ($menit === null) {
            return '—';
        }

        $jam = intdiv($menit, 60);
        $sisa = $menit % 60;

        return trim(($jam > 0 ? $jam.' '.__('umum.jam_singkat') : '').($sisa > 0 || $jam === 0 ? ' '.$sisa.' '.__('umum.menit_singkat') : ''));
    }
}
