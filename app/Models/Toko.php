<?php

namespace App\Models;

use App\Enums\StatusPesanan;
use App\Models\Concerns\BerDepot;
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
    'alamat', 'kelurahan', 'kecamatan', 'kota', 'provinsi', 'kode_pos', 'telepon',
    'nama_pemilik', 'nik_pemilik', 'latitude', 'longitude', 'sumber_koordinat', 'geocoded_at',
    'geocode_catatan', 'aktif',
])]
class Toko extends Model
{
    use BerDepot, HasFactory;

    /**
     * Kode toko semu untuk transaksi POS "Tanpa Toko" — dipakai saat
     * transaksi tidak perlu diikat ke toko/perusahaan pelanggan tertentu
     * (mis. pembeli perorangan, atau pemberian ke karyawan). Bukan jenis
     * transaksi khusus: produk, bonus, maupun pembayarannya tetap persis
     * sama seperti transaksi POS yang memilih toko sungguhan — satu-satunya
     * beda adalah toko tidak wajib diisi.
     */
    public const KODE_INTERNAL = 'INTERNAL-POS';

    /**
     * Toko semu untuk transaksi POS "Tanpa Toko". Sengaja tetap berupa satu
     * baris Toko sungguhan (bukan `toko_id` yang benar-benar NULL) supaya
     * SELURUH kode di aplikasi yang mengandalkan `$pesanan->toko` selalu ada
     * (faktur, Daftar Pesanan, Pendapatan, peta dashboard, dsb.) tidak perlu
     * diaudit ulang satu per satu untuk null-safety. `aktif = false`
     * membuatnya otomatis tersembunyi dari SEMUA pencarian/listing toko
     * biasa yang sudah men-scope `Toko::aktif()` (pencarian toko di POS
     * maupun Input Pesanan, kandidat routing, peta, dsb.) — satu-satunya
     * tempat ia bisa muncul secara sengaja adalah Master Toko (yang memang
     * menampilkan toko nonaktif juga).
     */
    public static function internal(): self
    {
        return static::firstOrCreate(
            ['kode' => self::KODE_INTERNAL],
            [
                'nama' => 'Tanpa Toko',
                'alamat' => '-',
                'wilayah_id' => Wilayah::query()->value('id'),
                'aktif' => false,
            ],
        );
    }

    /**
     * Apakah ini toko semu "Tanpa Toko" — dipakai `PesananService::buatPos()`
     * untuk mengecualikannya dari pemeriksaan "toko harus aktif" yang
     * berlaku untuk toko sungguhan. Ia memang SENGAJA dibuat `aktif=false`
     * (lihat internal()) supaya tersembunyi dari pencarian toko biasa, jadi
     * pemeriksaan itu perlu tahu untuk tidak menolaknya.
     */
    public function isInternal(): bool
    {
        return $this->kode === self::KODE_INTERNAL;
    }

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

    /**
     * Slot jadwal mingguan toko ini (satu hari, satu sales) — lihat
     * dokumentasi PenugasanToko. HasOne, bukan HasMany: `unique('toko_id')`
     * di migrasinya menjamin paling banyak satu baris per toko.
     *
     * @return HasOne<PenugasanToko, $this>
     */
    public function penugasanToko(): HasOne
    {
        return $this->hasOne(PenugasanToko::class);
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

    /**
     * Apakah kelima field profil yang WAJIB dilengkapi sales (lihat
     * `App\Livewire\Toko\LengkapiData`) sudah semuanya terisi — dipakai
     * sebagai penanda "belum lengkap" di daftar pencarian layar itu, supaya
     * sales tahu toko mana yang masih perlu disentuh tanpa membuka satu
     * per satu. Kecamatan/kota/provinsi SENGAJA tidak ikut dihitung di
     * sini — ketiganya boleh kosong di layar itu (tidak memengaruhi rute
     * pengantaran maupun transaksi lain), jadi kosongnya bukan tanda toko
     * ini "belum lengkap".
     */
    protected function profilLengkap(): Attribute
    {
        return Attribute::get(fn (): bool => collect([
            $this->nama_pemilik, $this->nik_pemilik, $this->alamat,
            $this->asset_id, $this->telepon,
        ])->every(fn ($v) => $v !== null && $v !== ''));
    }

    protected function alamatLengkap(): Attribute
    {
        return Attribute::get(fn (): string => collect([
            $this->alamat, $this->kelurahan, $this->kecamatan, $this->kota, $this->provinsi, $this->kode_pos,
        ])->filter()->implode(', '));
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }

    /** Toko yang belum diketahui wilayahnya — belum bisa dipesan atau ikut routing. */
    #[Scope]
    protected function tanpaWilayah(Builder $query): void
    {
        $query->whereNull('wilayah_id');
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
