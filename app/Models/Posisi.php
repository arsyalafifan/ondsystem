<?php

namespace App\Models;

use App\Enums\LokasiAbsensi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Posisi kerja + aturan absensinya — lihat catatan lengkap di migrasi
 * `create_posisis_table`. Lintas gudang (tanpa BerDepot), seperti master HR
 * lainnya.
 */
#[Fillable([
    'id', 'kode', 'nama', 'jam_masuk', 'jam_pulang', 'toleransi_telat_menit',
    'hari_kerja', 'lokasi_jenis', 'radius_meter', 'pakai_absen_istirahat',
    'istirahat_paling_lambat', 'aktif',
])]
class Posisi extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'aktif' => true,
        'lokasi_jenis' => 'depot',
        'radius_meter' => 100,
        'toleransi_telat_menit' => 0,
        'pakai_absen_istirahat' => false,
    ];

    protected function casts(): array
    {
        return [
            'hari_kerja' => 'array',
            'lokasi_jenis' => LokasiAbsensi::class,
            'toleransi_telat_menit' => 'integer',
            'radius_meter' => 'integer',
            'pakai_absen_istirahat' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    /** @return HasMany<Karyawan, $this> */
    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }

    /**
     * Hari kerja posisi ini dalam ISO-8601 (1 = Senin). Kosong/null berarti
     * Senin–Sabtu, mengikuti hari kerja perusahaan di `config('visit.hari_*')`
     * — bukan tujuh hari penuh, supaya posisi yang settingnya belum disentuh
     * tidak membuat Minggu terhitung alfa.
     *
     * @return list<int>
     */
    public function hariKerja(): array
    {
        $tersimpan = array_map('intval', $this->hari_kerja ?? []);

        return $tersimpan !== []
            ? $tersimpan
            : range((int) config('visit.hari_mulai'), (int) config('visit.hari_selesai'));
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }
}
