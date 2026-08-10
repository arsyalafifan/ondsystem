<?php

namespace App\Models;

use App\Enums\JenisFotoKunjungan;
use App\Enums\StatusKunjungan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'periode_kunjungan_id', 'periode_sales_id', 'sales_id', 'toko_id', 'status',
    'asset_id_terpindai', 'mulai_at', 'selesai_at', 'latitude', 'longitude',
    'akurasi_m', 'jarak_dari_toko_m', 'catatan_sales', 'catatan_admin',
    'ditinjau_oleh', 'ditinjau_at',
])]
class Kunjungan extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'berjalan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusKunjungan::class,
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
            'ditinjau_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** @return BelongsTo<PeriodeKunjungan, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeKunjungan::class, 'periode_kunjungan_id');
    }

    /** @return BelongsTo<PeriodeSales, $this> */
    public function periodeSales(): BelongsTo
    {
        return $this->belongsTo(PeriodeSales::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    /** @return BelongsTo<User, $this> */
    public function peninjau(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh');
    }

    /** @return HasMany<KunjunganFoto, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(KunjunganFoto::class);
    }

    /** Jenis foto yang belum diambil, sesuai urutan pengerjaan di lapangan. */
    protected function fotoKurang(): Attribute
    {
        return Attribute::get(function (): array {
            // Kolom jenis sudah di-cast menjadi enum, jadi yang dibandingkan
            // adalah nilainya. Membandingkan objek enum dengan string di sini
            // membuat semua foto selalu terbaca belum ada.
            $sudah = $this->fotos
                ->map(fn (KunjunganFoto $f) => $f->jenis->value)
                ->all();

            return array_values(array_filter(
                JenisFotoKunjungan::urut(),
                fn (JenisFotoKunjungan $j) => ! in_array($j->value, $sudah, true),
            ));
        });
    }

    protected function fotoLengkap(): Attribute
    {
        return Attribute::get(fn (): bool => $this->foto_kurang === []);
    }

    protected function jumlahFotoWajib(): Attribute
    {
        return Attribute::get(fn (): int => count(JenisFotoKunjungan::urut()));
    }

    /**
     * Titik pengambilan foto yang jauh dari koordinat toko patut diperiksa.
     * Bukan bukti kecurangan — GPS bisa meleset ratusan meter di antara
     * bangunan — tapi cukup untuk menandai kunjungan yang perlu dilihat lagi.
     */
    protected function lokasiMencurigakan(): Attribute
    {
        return Attribute::get(fn (): bool => $this->jarak_dari_toko_m !== null
            && $this->jarak_dari_toko_m > (int) config('visit.lokasi.jarak_wajar_m'));
    }

    #[Scope]
    protected function status(Builder $query, StatusKunjungan|string $status): void
    {
        $query->where('status', $status instanceof StatusKunjungan ? $status->value : $status);
    }

    #[Scope]
    protected function menungguTinjauan(Builder $query): void
    {
        $query->where('status', StatusKunjungan::TutupDiajukan);
    }
}
