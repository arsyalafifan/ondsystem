<?php

namespace App\Models;

use App\Enums\StatusStop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'routing_batch_id', 'wilayah_id', 'nomor', 'nama', 'warna', 'total_toko',
    'total_dus', 'target_dus', 'total_jarak_m', 'total_durasi_s', 'jam_berangkat',
    'estimasi_selesai', 'geometry', 'driver_id', 'diambil_at', 'status',
])]
class Kendaraan extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'total_toko' => 'integer',
            'total_dus' => 'integer',
            'target_dus' => 'integer',
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

    /**
     * Toko yang tidak lagi menunggu tindakan driver. Toko yang dibatalkan
     * ikut dihitung: tanggung jawab driver atasnya sudah tuntas, hanya dusnya
     * yang tidak terkirim.
     */
    protected function totalSelesai(): Attribute
    {
        return Attribute::get(fn (): int => $this->stops
            ->filter(fn (KendaraanStop $s) => $s->status->tuntas())
            ->count());
    }

    protected function totalBelum(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->stops->count() - $this->total_selesai));
    }

    protected function totalDibatalkan(): Attribute
    {
        return Attribute::get(fn (): int => $this->stops
            ->where('status', StatusStop::Dibatalkan)
            ->count());
    }

    /** Dus yang benar-benar keluar dari mobil, termasuk lewat kampas. */
    protected function dusTerkirim(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->stops->sum('total_dus_terkirim'));
    }

    /**
     * Dus yang masih benar-benar ada di mobil.
     *
     * Yang tidak jadi diterima toko dikurangi yang sudah diampaskan ke toko
     * lain — tanpa pengurangan itu, barang yang sudah keluar dari mobil masih
     * terhitung sebagai sisa dan driver terlihat seperti belum menuntaskan
     * apa pun.
     */
    protected function dusTersisa(): Attribute
    {
        return Attribute::get(function (): int {
            $belumTerkirim = (int) $this->stops
                ->filter(fn (KendaraanStop $s) => ! $s->isKampas())
                ->sum(fn (KendaraanStop $s) => $s->dus_tersisa);

            $sudahDiampaskan = (int) $this->stops
                ->filter(fn (KendaraanStop $s) => $s->isKampas())
                ->sum('total_dus_terkirim');

            return max(0, $belumTerkirim - $sudahDiampaskan);
        });
    }

    /**
     * Kemajuan pengiriman dihitung dari dus, bukan dari jumlah toko.
     *
     * Penyebutnya adalah muatan saat mobil berangkat, dan tidak ikut menyusut
     * ketika ada toko yang dibatalkan. Dengan begitu 100% benar-benar berarti
     * seluruh dus yang dibawa sudah keluar dari mobil — sisa yang tidak
     * diampaskan tetap terlihat sebagai kekurangan, bukan tersembunyi oleh
     * penyebut yang ikut mengecil.
     */
    protected function persenSelesai(): Attribute
    {
        return Attribute::get(function (): int {
            $target = $this->target_dus > 0 ? $this->target_dus : $this->total_dus;

            if ($target <= 0) {
                return 0;
            }

            return (int) min(100, round($this->dus_terkirim / $target * 100));
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

    /**
     * Toko yang sungguh dimuat mobil ini saat berangkat, untuk packing list.
     * Kampas sengaja tidak ikut — itu terjadi di lapangan setelah mobil
     * berangkat, bukan bagian dari muatan yang dipacking di gudang.
     */
    protected function stopsPacking(): Attribute
    {
        return Attribute::get(fn () => $this->stops->reject(fn (KendaraanStop $s) => $s->isKampas())->values());
    }

    /**
     * Total dus per produk dari seluruh toko yang dimuat mobil ini, untuk
     * tabel "Nama Barang" pada packing list. Dipakai bersama oleh versi HTML
     * dan ESC/P supaya keduanya tidak bisa diam-diam berbeda hasil.
     *
     * @return Collection<int, array{produk: string, qty: int}>
     */
    protected function ringkasanProdukPacking(): Attribute
    {
        return Attribute::get(function () {
            return $this->stops_packing
                ->flatMap(fn (KendaraanStop $s) => $s->pesanan?->items ?? collect())
                ->groupBy(fn (PesananItem $item) => $item->produk->nama)
                ->map(fn ($items, $nama) => ['produk' => $nama, 'qty' => (int) $items->sum('jumlah_dus')])
                ->sortKeys()
                ->values();
        });
    }
}
