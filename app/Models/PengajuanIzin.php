<?php

namespace App\Models;

use App\Enums\JenisIzin;
use App\Enums\PorsiIzin;
use App\Enums\StatusPengajuan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lihat catatan lengkap di migrasi `create_pengajuan_izins_table`. */
#[Fillable([
    'karyawan_id', 'jenis', 'porsi', 'tanggal_mulai', 'tanggal_selesai', 'jumlah_hari',
    'dibayar', 'alasan', 'lampiran', 'lampiran_nama', 'status', 'diajukan_oleh',
    'diputuskan_oleh', 'diputuskan_at', 'catatan_keputusan',
])]
class PengajuanIzin extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'menunggu',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisIzin::class,
            'porsi' => PorsiIzin::class,
            'status' => StatusPengajuan::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jumlah_hari' => 'float',
            'dibayar' => 'boolean',
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

    /** Menunggu atau disetujui — yang masih memegang tanggalnya. */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->whereIn('status', [StatusPengajuan::Menunggu, StatusPengajuan::Disetujui]);
    }

    #[Scope]
    protected function mencakup(Builder $query, CarbonInterface|string $tanggal): void
    {
        $tanggal = $tanggal instanceof CarbonInterface ? $tanggal->toDateString() : $tanggal;

        $query->whereDate('tanggal_mulai', '<=', $tanggal)->whereDate('tanggal_selesai', '>=', $tanggal);
    }

    #[Scope]
    protected function beririsan(Builder $query, CarbonInterface $mulai, CarbonInterface $selesai): void
    {
        $query->whereDate('tanggal_mulai', '<=', $selesai->toDateString())
            ->whereDate('tanggal_selesai', '>=', $mulai->toDateString());
    }

    public function beririsanDengan(CarbonInterface $mulai, CarbonInterface $selesai): bool
    {
        return $this->tanggal_mulai->lte($selesai) && $this->tanggal_selesai->gte($mulai);
    }

    /**
     * Kunci status harian di Attendance Monitoring (hr.status_harian_*) untuk
     * hari yang dicakup pengajuan ini.
     */
    public function statusHarian(): string
    {
        return match (true) {
            $this->jenis === JenisIzin::Sakit => 'sakit',
            $this->porsi === PorsiIzin::ParuhPertama => 'izin_paruh_pertama',
            $this->porsi === PorsiIzin::ParuhKedua => 'izin_paruh_kedua',
            default => 'izin',
        };
    }

    public function tanggalTeks(): string
    {
        return $this->tanggal_mulai->equalTo($this->tanggal_selesai)
            ? $this->tanggal_mulai->isoFormat('D MMM Y')
            : $this->tanggal_mulai->isoFormat('D MMM').' – '.$this->tanggal_selesai->isoFormat('D MMM Y');
    }
}
