<?php

namespace App\Models;

use App\Enums\JenisKelamin;
use App\Enums\StatusKaryawan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Master data karyawan — modul HR System.
 *
 * Sengaja TIDAK memakai trait BerDepot: ini menu administrasi karyawan
 * lintas-perusahaan (Admin/Hr/Superadmin melihat seluruh karyawan dari
 * semua depot sekaligus), bukan data milik satu gudang. `depot_id`
 * ("Penempatan") tetap ada sebagai field form biasa yang diisi manual —
 * bukan hasil auto-stamp dari konteks depot yang sedang aktif — dan
 * nantinya jadi titik jangkar geofencing absensi (radius terhadap lat/lng
 * depot ybs), bukan sekadar label organisasi. Lihat catatan lengkap di
 * migrasi `create_karyawans_table`.
 *
 * `user_id` menautkan ke akun login yang SUDAH ADA (nullable — lihat
 * dokumentasi `App\Models\User::karyawan()`). Modul ini tidak membuat akun
 * baru, cuma menautkan.
 */
#[Fillable([
    'id', 'kode_karyawan', 'nama_lengkap', 'nik', 'jenis_kelamin', 'tanggal_lahir',
    'no_hp', 'alamat_domisili', 'department_id', 'jabatan_id', 'tanggal_masuk',
    'status_karyawan', 'tanggal_berakhir_kontrak', 'gaji_pokok', 'no_rekening',
    'npwp', 'catatan', 'depot_id', 'foto_karyawan', 'foto_ktp', 'user_id', 'aktif',
])]
class Karyawan extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'jenis_kelamin' => JenisKelamin::class,
            'status_karyawan' => StatusKaryawan::class,
            'tanggal_lahir' => 'date',
            'tanggal_masuk' => 'date',
            'tanggal_berakhir_kontrak' => 'date',
            'gaji_pokok' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /** @return BelongsTo<Depot, $this> */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function urlFotoKaryawan(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->foto_karyawan === null
            ? null
            : Storage::disk('public')->url($this->foto_karyawan));
    }

    protected function urlFotoKtp(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->foto_ktp === null
            ? null
            : Storage::disk('public')->url($this->foto_ktp));
    }
}
