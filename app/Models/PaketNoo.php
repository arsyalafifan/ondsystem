<?php

namespace App\Models;

use App\Models\Concerns\BerDepot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paket penjualan perdana untuk mitra baru: "15+2", "10+1", dan seterusnya.
 *
 * `dus_reguler`/`dus_bonus` adalah janji ke pemilik toko, sedangkan
 * `items` adalah es krim yang benar-benar dikirim. Keduanya bisa saja tidak
 * cocok — admin baru membuat paketnya dan belum sempat memilih produknya —
 * jadi `lengkap` yang menentukan apakah paket ini sudah boleh ditawarkan
 * sales, bukan sekadar `aktif`. Paket timpang lebih baik tidak muncul sama
 * sekali daripada terlanjur dipilih lalu gagal jadi pesanan di ujung alur,
 * saat driver sudah berdiri di depan tokonya.
 */
// `depot_id` ikut fillable karena DepotService::buat() menyemai paket bawaan
// untuk depot yang BARU dibuat, saat DepotContext masih menunjuk depot lain
// (atau belum ada sama sekali) — sama alasannya dengan PengaturanKunjungan.
#[Fillable(['id', 'nama', 'dus_reguler', 'dus_bonus', 'urutan', 'aktif', 'depot_id'])]
class PaketNoo extends Model
{
    use BerDepot, HasFactory;

    protected function casts(): array
    {
        return [
            'dus_reguler' => 'integer',
            'dus_bonus' => 'integer',
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    /** @return HasMany<PaketNooItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PaketNooItem::class);
    }

    /** Jumlah dus yang sudah dipilih produknya, reguler atau bonus. */
    public function dusTerpilih(bool $bonus): int
    {
        return (int) $this->items->where('is_bonus', $bonus)->sum('jumlah_dus');
    }

    /** Rincian produknya sudah genap persis sesuai dus yang dijanjikan. */
    protected function lengkap(): Attribute
    {
        return Attribute::get(fn (): bool => $this->dusTerpilih(false) === $this->dus_reguler
            && $this->dusTerpilih(true) === $this->dus_bonus);
    }

    /** Total dus yang keluar gudang kalau paket ini dipakai. */
    protected function totalDus(): Attribute
    {
        return Attribute::get(fn (): int => $this->dus_reguler + $this->dus_bonus);
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }
}
