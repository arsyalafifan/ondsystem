<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'aktif', 'locale', 'no_hp'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Disamakan dengan nilai bawaan kolomnya, supaya pengguna yang baru dibuat
     * tanpa menyebut kolom ini tetap bisa dibaca tanpa perlu diambil ulang
     * dari basis data. `locale` kosong berarti mengikuti bahasa bawaan.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => null,
        'aktif' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => PeranPengguna::class,
            'aktif' => 'boolean',
        ];
    }

    /** @return HasMany<Pesanan, $this> */
    public function pesanans(): HasMany
    {
        return $this->hasMany(Pesanan::class, 'dibuat_oleh');
    }

    /** @return HasMany<Kendaraan, $this> */
    public function kendaraans(): HasMany
    {
        return $this->hasMany(Kendaraan::class, 'driver_id');
    }

    /** Toko yang menjadi tanggungan kunjungan sales ini. */
    /** @return HasMany<PenugasanSales, $this> */
    public function penugasans(): HasMany
    {
        return $this->hasMany(PenugasanSales::class, 'sales_id');
    }

    /** @return HasMany<Kunjungan, $this> */
    public function kunjungans(): HasMany
    {
        return $this->hasMany(Kunjungan::class, 'sales_id');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [PeranPengguna::Admin, PeranPengguna::Superadmin], true);
    }

    public function isSales(): bool
    {
        return $this->role === PeranPengguna::Sales;
    }

    public function isDriver(): bool
    {
        return $this->role === PeranPengguna::Driver;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === PeranPengguna::Superadmin;
    }

    #[Scope]
    protected function driver(Builder $query): void
    {
        $query->where('role', PeranPengguna::Driver)->where('aktif', true);
    }

    #[Scope]
    protected function sales(Builder $query): void
    {
        $query->where('role', PeranPengguna::Sales)->where('aktif', true);
    }
}
