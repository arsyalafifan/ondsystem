<?php

namespace App\Models;

use App\Enums\JenisPesanan;
use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use Carbon\CarbonInterface;
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
use Illuminate\Support\Carbon;
use RuntimeException;

#[Fillable([
    'kode', 'toko_id', 'wilayah_id', 'dibuat_oleh', 'sales_id', 'status', 'jenis',
    'kurang_kirim', 'status_bayar', 'tanggal_lunas', 'dilunasi_oleh',
    'nominal_cash', 'nominal_transfer',
    'tanggal', 'total_dus', 'total_nilai', 'catatan',
    'diproses_oleh', 'diproses_at', 'dikirim_at', 'selesai_at',
    'alasan_cancel', 'catatan_cancel', 'dibatalkan_oleh', 'dibatalkan_at',
])]
class Pesanan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Menolak penghapusan pesanan yang sudah pernah masuk rute.
     *
     * Banyak layar driver (unggah nota, coret nota, dashboard) mengakses
     * `$stop->pesanan->...` tanpa null-safe karena selama ini pesanan pada
     * sebuah stop dijamin selalu ada. Begitu pesanan boleh soft delete,
     * asumsi itu bisa pecah — stop yang masih menunjuk ke pesanan yang
     * dihapus akan membuat layar-layar itu error. Penjaga ini hanya berlaku
     * lewat ->delete() Eloquent (mis. tombol hapus di aplikasi); mengedit
     * kolom deleted_at langsung lewat basis data tetap melewatinya, jadi
     * hanya pesanan yang belum pernah dirutekan yang aman dihapus dengan
     * cara itu.
     */
    protected static function booted(): void
    {
        static::deleting(function (Pesanan $pesanan): void {
            if ($pesanan->stop()->exists()) {
                throw new RuntimeException(__('pesanan.galat_hapus_sudah_dirutekan', ['kode' => $pesanan->kode]));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => StatusPesanan::class,
            'jenis' => JenisPesanan::class,
            'kurang_kirim' => 'boolean',
            'status_bayar' => StatusBayar::class,
            'tanggal_lunas' => 'date',
            'tanggal' => 'date',
            'total_dus' => 'integer',
            'total_nilai' => 'decimal:2',
            'nominal_cash' => 'decimal:2',
            'nominal_transfer' => 'decimal:2',
            'diproses_at' => 'datetime',
            'dikirim_at' => 'datetime',
            'selesai_at' => 'datetime',
            'dibatalkan_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    /** @return BelongsTo<Wilayah, $this> */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * "Atas nama sales siapa" pesanan ini — beda dari pembuat()/dibuat_oleh,
     * yang selalu mencatat siapa yang SUNGGUH mengetiknya. Dipakai saat
     * admin/superadmin menginput pesanan (mis. sambil memberi bonus) atas
     * nama seorang sales, supaya faktur tetap menampilkan nama sales-nya.
     * Null untuk pesanan yang diinput sales sendiri — pembuat() sudah cukup.
     *
     * @return BelongsTo<User, $this>
     */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function dilunasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dilunasi_oleh');
    }

    /** @return HasMany<PesananItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PesananItem::class);
    }

    /** @return HasOne<KendaraanStop, $this> */
    public function stop(): HasOne
    {
        return $this->hasOne(KendaraanStop::class);
    }

    /**
     * Nilai yang benar-benar bisa ditagih ke toko.
     *
     * Pada pesanan yang notanya dicoret, total_nilai tetap menyimpan nilai
     * pesanan semula — hanya jumlah per baris (`PesananItem::terkirim`) yang
     * berubah. Tagihan pembayaran harus memakai yang benar-benar terkirim,
     * bukan nilai pesanan awal. Pemanggil wajib memuat relasi `items` lebih
     * dulu (mis. `->with('items')`) — mode ketat model melempar galat kalau
     * belum, alih-alih memicu kueri N+1 secara diam-diam.
     */
    protected function tagihan(): Attribute
    {
        return Attribute::get(function (): float {
            if (! $this->kurang_kirim) {
                return (float) $this->total_nilai;
            }

            return (float) $this->items->sum(
                fn (PesananItem $i) => $i->terkirim * (float) $i->harga_satuan
            );
        });
    }

    /**
     * Aktor dan waktu tindakan TERAKHIR yang tercatat pada pesanan ini,
     * dipakai kolom "Update By | Date" di daftar pesanan.
     *
     * Pesanan tidak punya satu kolom "diubah oleh" yang umum — tiap
     * tindakan (proses, batal, lunas) punya pasangan aktor+waktunya
     * sendiri. Yang terbaru di antaranya itulah yang ditampilkan; kalau
     * belum ada satu pun tindakan lanjutan, jatuh kembali ke penginput
     * dan waktu pesanan dibuat. Pemanggil wajib memuat relasi pembuat,
     * pemroses, pembatal, dan dilunasiOleh lebih dulu — mode ketat model
     * melempar galat kalau belum, alih-alih memicu kueri N+1 diam-diam.
     *
     * @return array{user: ?User, at: ?Carbon}
     */
    protected function pembaruTerakhir(): Attribute
    {
        return Attribute::get(function (): array {
            $kandidat = collect([
                ['user' => $this->pembatal, 'at' => $this->dibatalkan_at],
                ['user' => $this->dilunasiOleh, 'at' => $this->tanggal_lunas],
                ['user' => $this->pemroses, 'at' => $this->diproses_at],
                ['user' => $this->pembuat, 'at' => $this->created_at],
            ])->filter(fn (array $k) => $k['at'] !== null);

            return $kandidat->sortByDesc(fn (array $k) => $k['at'])->first()
                ?? ['user' => $this->pembuat, 'at' => $this->created_at];
        });
    }

    /**
     * Dibatalkan DRIVER di lapangan (bukan oleh admin dari Daftar Pesanan)
     * dengan alasan SELAIN "toko membatalkan pesanan" — situasi yang masih
     * bisa ditindaklanjuti admin lewat Order Ulang, atau ditandai final
     * lewat tombol Batalkan.
     *
     * Dibedakan dari pembatalan admin lewat keberadaan `stop`:
     * `PesananService::batalkan()` (admin) MENGHAPUS baris stop-nya,
     * sedangkan `PengirimanService::batalkanDiLapangan()` (driver)
     * membiarkannya ada dengan status Dibatalkan — dus-nya masih fisik di
     * mobil, jadi kunjungannya tetap perlu terlihat driver. Kalau
     * alasannya sudah "toko membatalkan pesanan", dianggap sudah final —
     * tidak ada lagi yang perlu ditindaklanjuti admin.
     *
     * Pemanggil wajib memuat relasi `stop` lebih dulu — mode ketat model
     * melempar galat kalau belum, alih-alih memicu kueri N+1 diam-diam.
     */
    protected function bisaOrderUlang(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === StatusPesanan::Cancel
            && $this->stop !== null
            && $this->stop->status === StatusStop::Dibatalkan
            && $this->alasan_cancel !== __('pesanan.alasan_toko_batal'));
    }

    /**
     * Tanggal yang dipakai untuk mengelompokkan pesanan ini di menu
     * Pendapatan — BUKAN selalu tanggal_lunas.
     *
     * Untuk kategori "driver" (rute biasa maupun kampas, keduanya lewat
     * kendaraan — lihat `JenisPesanan::kategoriPendapatan()`), pendapatan
     * mengikuti tanggal KEBERANGKATAN kendaraannya (`RoutingBatch::tanggal`),
     * bukan kapan tagihannya kebetulan dilunasi — toko yang berangkat
     * dikirim tanggal 20 tapi baru bayar tanggal 22 tetap terhitung sebagai
     * pendapatan tanggal 20, karena dus-nya memang sudah keluar gudang
     * tanggal itu. Kategori "pos" tidak pernah lewat kendaraan sama sekali
     * (langsung lunas seketika saat dibuat), jadi tanggal_lunas sudah tepat
     * dan satu-satunya tanggal yang bermakna baginya.
     *
     * Pemanggil wajib memuat relasi `stop.kendaraan.batch` lebih dulu untuk
     * kategori driver — mode ketat model melempar galat kalau belum, alih-
     * alih memicu kueri N+1 diam-diam. Jatuh kembali ke tanggal_lunas kalau
     * rantai relasinya ternyata putus (mis. kendaraan lama yang datanya
     * tidak lengkap), supaya menu Pendapatan tidak pernah pecah gara-gara
     * satu baris yang datanya tidak biasa.
     */
    protected function tanggalPendapatan(): Attribute
    {
        return Attribute::get(function (): CarbonInterface {
            if ($this->jenis->kategoriPendapatan() === 'driver') {
                $tanggal = $this->stop?->kendaraan?->batch?->tanggal;

                if ($tanggal !== null) {
                    return $tanggal;
                }
            }

            return $this->tanggal_lunas;
        });
    }

    #[Scope]
    protected function status(Builder $query, StatusPesanan|string $status): void
    {
        $query->where('status', $status instanceof StatusPesanan ? $status->value : $status);
    }

    #[Scope]
    protected function statusBayar(Builder $query, StatusBayar|string $status): void
    {
        $query->where('status_bayar', $status instanceof StatusBayar ? $status->value : $status);
    }

    /** Pesanan yang siap masuk proses routing. */
    #[Scope]
    protected function siapRouting(Builder $query): void
    {
        $query->where('status', StatusPesanan::Process)->whereDoesntHave('stop');
    }

    #[Scope]
    protected function hariIni(Builder $query): void
    {
        $query->whereDate('tanggal', today());
    }
}
