<?php

namespace App\Models;

use App\Enums\KategoriToko;
use App\Enums\StatusNoo;
use App\Models\Concerns\BerDepot;
use App\Services\Peta\Koordinat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu calon toko mitra baru, dari pendataan sales sampai freezernya
 * terpasang dan pesanan perdananya terbentuk.
 *
 * Selama statusnya belum `Process`, data toko di sini adalah SATU-SATUNYA
 * yang ada — `tokos` belum punya barisnya. Sesudah disetujui, `toko` sudah
 * terisi dan dialah yang berlaku; kolom-kolom di sini berhenti jadi sumber
 * kebenaran dan tinggal menjadi rekaman apa yang disetujui waktu itu.
 */
#[Fillable([
    'kode', 'status', 'paket_noo_id',
    'nama', 'alamat', 'wilayah_id', 'kelurahan', 'kecamatan', 'kota', 'provinsi', 'kode_pos',
    'telepon', 'nama_pemilik', 'nik_pemilik', 'kategori', 'freezer_tipe',
    'latitude', 'longitude', 'sumber_koordinat',
    'toko_id', 'pesanan_id', 'catatan_pesanan_gagal',
    'diajukan_oleh', 'diajukan_at', 'disetujui_oleh', 'disetujui_at',
    'ditolak_oleh', 'ditolak_at', 'alasan_tolak',
    'diubah_admin_oleh', 'diubah_admin_at', 'diselesaikan_oleh', 'selesai_at',
])]
class Noo extends Model
{
    use BerDepot, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => StatusNoo::class,
            'kategori' => KategoriToko::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'diajukan_at' => 'datetime',
            'disetujui_at' => 'datetime',
            'ditolak_at' => 'datetime',
            'diubah_admin_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PaketNoo, $this> */
    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketNoo::class, 'paket_noo_id');
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    /** @return BelongsTo<Pesanan, $this> */
    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class);
    }

    /**
     * Kunjungan pengantaran freezer untuk NOO ini, begitu ia masuk rute.
     *
     * @return HasOne<KendaraanStop, $this>
     */
    public function stop(): HasOne
    {
        return $this->hasOne(KendaraanStop::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penolak(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditolak_oleh');
    }

    /**
     * Urutan penyimpanan = urutan pengambilan (bukti sales dulu, baru bukti
     * driver) — orderBy eksplisit supaya urutan tampilnya tidak bergantung
     * kebetulan urutan baris di basis data.
     *
     * @return HasMany<NooFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(NooFoto::class)->orderBy('id');
    }

    protected function koordinat(): Attribute
    {
        return Attribute::get(fn (): Koordinat => new Koordinat($this->latitude, $this->longitude));
    }

    #[Scope]
    protected function berjalan(Builder $query): void
    {
        $query->whereIn('status', StatusNoo::berjalan());
    }

    #[Scope]
    protected function menungguPersetujuan(Builder $query): void
    {
        $query->where('status', StatusNoo::Order);
    }
}
