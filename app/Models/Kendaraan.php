<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'routing_batch_id', 'wilayah_id', 'nomor', 'nama', 'warna', 'total_toko',
    'total_dus', 'total_jarak_m', 'total_durasi_s', 'jam_berangkat',
    'estimasi_selesai', 'geometry', 'driver_id', 'diambil_at', 'status',
])]
class Kendaraan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total_toko' => 'integer',
            'total_dus' => 'integer',
            'total_jarak_m' => 'integer',
            'total_durasi_s' => 'integer',
            'diambil_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RoutingBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(RoutingBatch::class, 'routing_batch_id');
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    /** @return BelongsTo<User, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** @return HasMany<KendaraanStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(KendaraanStop::class)->orderBy('urutan');
    }

    /**
     * Nama tampilan dibentuk ulang dari nomornya setiap kali dibaca, memakai
     * bahasa yang sedang aktif.
     *
     * Kolom `nama` di basis data tetap menyimpan bentuk kanonis berbahasa
     * Indonesia untuk keperluan kueri mentah dan ekspor. Kalau nilai kolom itu
     * yang dipakai untuk tampilan, kendaraan yang dibuat admin berbahasa
     * Indonesia akan tetap terbaca "Mobil 1" oleh pengguna berbahasa Mandarin.
     */
    protected function nama(): Attribute
    {
        return Attribute::get(function (?string $tersimpan): string {
            // Kolom nomor bisa saja belum terambil kalau pemanggil membatasi
            // kolom yang dipilih. Dalam keadaan itu nilai kolom dipakai apa
            // adanya, daripada menggagalkan seluruh halaman.
            if (! array_key_exists('nomor', $this->attributes) || $this->attributes['nomor'] === null) {
                return (string) $tersimpan;
            }

            return __('umum.mobil').' '.$this->attributes['nomor'];
        });
    }

    protected function totalSelesai(): Attribute
    {
        return Attribute::get(fn (): int => $this->stops->where('status', 'selesai')->count());
    }

    protected function totalBelum(): Attribute
    {
        return Attribute::get(fn (): int => $this->total_toko - $this->total_selesai);
    }

    protected function persenSelesai(): Attribute
    {
        return Attribute::get(function (): int {
            if ($this->total_toko === 0) {
                return 0;
            }

            return (int) round($this->total_selesai / $this->total_toko * 100);
        });
    }

    protected function jarakKm(): Attribute
    {
        return Attribute::get(fn (): float => round($this->total_jarak_m / 1000, 1));
    }

    protected function durasiJam(): Attribute
    {
        return Attribute::get(function (): string {
            $menit = (int) round($this->total_durasi_s / 60);

            return $menit >= 60
                ? intdiv($menit, 60).' j '.($menit % 60).' m'
                : $menit.' m';
        });
    }
}
