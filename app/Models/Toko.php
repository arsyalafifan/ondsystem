<?php

namespace App\Models;

use App\Enums\StatusPesanan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'id',
    'kode', 'asset_id', 'freezer_tipe', 'freezer_pelanggan', 'nama', 'wilayah_id',
    'alamat', 'kelurahan', 'kecamatan', 'kota', 'kode_pos', 'telepon',
    'nama_pemilik', 'latitude', 'longitude', 'sumber_koordinat', 'geocoded_at',
    'geocode_catatan', 'aktif',
])]
class Toko extends Model
{
    use HasFactory;

    /**
     * Disamakan dengan nilai bawaan kolomnya. Tanpa ini, model yang baru
     * dibuat tanpa menyebut kolom tersebut akan membacanya sebagai null
     * sampai diambil ulang dari basis data.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'aktif' => true,
        'sumber_koordinat' => 'belum',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geocoded_at' => 'datetime',
            'aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    /** @return HasMany<Pesanan, $this> */
    public function pesanans(): HasMany
    {
        return $this->hasMany(Pesanan::class);
    }

    /** @return HasMany<PenugasanSales, $this> */
    public function penugasans(): HasMany
    {
        return $this->hasMany(PenugasanSales::class);
    }

    /** @return HasMany<Kunjungan, $this> */
    public function kunjungans(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    /** Pesanan yang sedang berjalan. Maksimal satu per toko. */
    /** @return HasOne<Pesanan, $this> */
    public function pesananAktif(): HasOne
    {
        return $this->hasOne(Pesanan::class)->whereIn('status', StatusPesanan::aktif());
    }

    protected function punyaKoordinat(): Attribute
    {
        return Attribute::get(fn (): bool => $this->latitude !== null && $this->longitude !== null);
    }

    protected function alamatLengkap(): Attribute
    {
        return Attribute::get(fn (): string => collect([
            $this->alamat, $this->kelurahan, $this->kecamatan, $this->kota, $this->kode_pos,
        ])->filter()->implode(', '));
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }

    /** Toko yang freezernya sudah punya nomor aset, jadi bisa dipindai sales. */
    #[Scope]
    protected function berassetId(Builder $query): void
    {
        $query->whereNotNull('asset_id');
    }

    /** Toko yang siap ikut routing. */
    #[Scope]
    protected function berkoordinat(Builder $query): void
    {
        $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * Dibungkus dalam satu kelompok agar "atau" di dalamnya tidak melebar
     * dan membatalkan syarat lain yang sudah dipasang pemanggil.
     */
    #[Scope]
    protected function tanpaKoordinat(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('latitude')->orWhereNull('longitude'));
    }

    /** Toko yang saat ini tidak punya pesanan berjalan, jadi boleh dipesan. */
    #[Scope]
    protected function bisaDipesan(Builder $query): void
    {
        $query->where('aktif', true)->whereDoesntHave('pesanans', function (Builder $q): void {
            $q->whereIn('status', StatusPesanan::aktif());
        });
    }
}
