<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Models\Concerns\BerDepot;
use App\Models\Concerns\DisaringDepotSendiri;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'aktif', 'locale', 'no_hp', 'depot_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements DisaringDepotSendiri
{
    /** @use HasFactory<UserFactory> */
    use BerDepot, HasFactory, Notifiable;

    /**
     * Akses gudang disimpan di tabel `depot_user` (satu akun bisa dipakai di
     * beberapa gudang). Kolom `depot_id` sekarang berarti GUDANG DEFAULT
     * setelah login — tetap diisi otomatis BerDepot dari depot aktif saat
     * akun dibuat, dan gudang itu langsung ikut didaftarkan sebagai akses,
     * supaya kode lama yang membuat user dengan depot_id saja tetap benar.
     */
    protected static function booted(): void
    {
        static::created(function (User $pengguna): void {
            if ($pengguna->depot_id !== null && ! $pengguna->isSuperadmin()) {
                $pengguna->depots()->syncWithoutDetaching([$pengguna->depot_id]);
            }
        });
    }

    /**
     * Dipanggil DepotScope: pengguna "milik" sebuah gudang kalau punya akses
     * ke gudang itu (tabel depot_user), bukan kalau depot_id-nya sama.
     * Superadmin (tanpa baris akses) tetap tidak ikut terlihat di daftar
     * pengguna per gudang, sama seperti sebelumnya.
     */
    public function saringDepot(Builder $query, int $depotId): void
    {
        $query->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('depot_user')
            ->whereColumn('depot_user.user_id', $this->qualifyColumn('id'))
            ->where('depot_user.depot_id', $depotId));
    }

    /** @return BelongsToMany<Depot, $this> */
    public function depots(): BelongsToMany
    {
        return $this->belongsToMany(Depot::class)->withTimestamps();
    }

    /**
     * Gudang aktif yang boleh dimasuki pengguna ini, urut nomor urut depot.
     * Superadmin: semua gudang aktif. Pengguna tanpa akses sama sekali
     * jatuh ke gudang urutan pertama — sesuai aturan "depot nomor pertama
     * menjadi default kalau pengguna tidak diset akses".
     *
     * @return Collection<int, Depot>
     */
    public function depotYangBisaDiakses(): Collection
    {
        if ($this->isSuperadmin()) {
            return Depot::query()->aktif()->berurutan()->get();
        }

        $depots = Depot::query()->aktif()->berurutan()
            ->whereIn('id', fn ($q) => $q->select('depot_id')->from('depot_user')->where('user_id', $this->id))
            ->get();

        return $depots->isNotEmpty()
            ? $depots
            : Depot::query()->aktif()->berurutan()->limit(1)->get();
    }

    /** Gudang tujuan setelah login: gudang default kalau masih boleh, selain itu gudang pertama. */
    public function depotAwal(): ?Depot
    {
        $depots = $this->depotYangBisaDiakses();

        return $depots->firstWhere('id', $this->depot_id) ?? $depots->first();
    }

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

    /** Jadwal kunjungan mingguan sales ini — lihat dokumentasi PenugasanToko. */
    /** @return HasMany<PenugasanToko, $this> */
    public function penugasanTokos(): HasMany
    {
        return $this->hasMany(PenugasanToko::class, 'sales_id');
    }

    /**
     * Profil HR yang ditautkan ke akun ini, kalau ada. Karyawan sengaja
     * tidak di-scope per depot (lihat dokumentasi App\Models\Karyawan),
     * jadi relasi ini selalu bisa ditemukan terlepas dari konteks depot
     * yang sedang aktif di sesi mana pun.
     */
    /** @return HasOne<Karyawan, $this> */
    public function karyawan(): HasOne
    {
        return $this->hasOne(Karyawan::class);
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

    public function isHr(): bool
    {
        return $this->role === PeranPengguna::Hr;
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
