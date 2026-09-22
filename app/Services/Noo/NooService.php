<?php

namespace App\Services\Noo;

use App\Enums\JenisBuktiNoo;
use App\Enums\StatusNoo;
use App\Enums\StatusStop;
use App\Models\Noo;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Models\User;
use App\Services\PesananService;
use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Aturan main NOO: pengajuan sales, keputusan admin, dan lahirnya toko.
 *
 * Titik paling menentukan di sini adalah setujui(): di situlah calon berubah
 * jadi baris `tokos` yang sungguhan. Toko itu sengaja lahir dalam keadaan
 * BELUM aktif dan tanpa `asset_id` — freezernya belum terpasang, nomornya
 * belum ada, dan toko tanpa freezer belum boleh dipesani. Keduanya baru
 * terisi saat driver menuntaskan pengantaran.
 */
class NooService
{
    /** Jarak minimal yang dianggap aman dari toko aktif yang sudah ada. */
    public const RADIUS_PERINGATAN_M = 200;

    public function __construct(private readonly BuktiNooService $bukti) {}

    /**
     * Mencatat pengajuan sales beserta ketiga foto wajibnya.
     *
     * Barisnya dibuat lebih dulu di dalam transaksi karena watermark dan
     * nama berkasnya memakai kode NOO — jadi berkas hanya bisa ditulis
     * setelah barisnya ada. Kalau ada yang gagal di tengah, barisnya ikut
     * hilang bersama transaksi tapi berkasnya TIDAK, karena disk bukan
     * bagian dari transaksi basis data — karena itu berkasnya dibersihkan
     * sendiri di catch.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $gambar  data URL, dikunci pada JenisBuktiNoo->value
     */
    public function ajukan(array $data, array $gambar, User $sales): Noo
    {
        $tersimpan = [];

        try {
            return DB::transaction(function () use ($data, $gambar, $sales, &$tersimpan): Noo {
                $noo = Noo::create([
                    ...$data,
                    'kode' => $this->kodeBerikutnya(),
                    'status' => StatusNoo::Order,
                    'diajukan_oleh' => $sales->id,
                    'diajukan_at' => now(),
                ]);

                foreach (JenisBuktiNoo::wajibSales() as $jenis) {
                    $tersimpan[] = $this->bukti->simpanFoto($noo, $jenis, $gambar[$jenis->value], $sales);
                }

                $this->bukti->simpanSemua($noo, $tersimpan);

                return $noo;
            });
        } catch (Throwable $e) {
            $this->bukti->hapusBerkas($tersimpan);

            throw $e;
        }
    }

    /**
     * Menyetujui calon: lahirlah tokonya, masih nonaktif dan tanpa IDN.
     *
     * @throws RuntimeException bila statusnya bukan lagi ORDER, atau identitas
     *                          pemiliknya ternyata sudah terdaftar sebagai toko lain
     */
    public function setujui(Noo $noo, User $admin): Toko
    {
        if ($noo->status !== StatusNoo::Order) {
            throw new RuntimeException(__('noo.galat_bukan_order', ['kode' => $noo->kode]));
        }

        $this->pastikanIdentitasBelumTerdaftar($noo);

        return DB::transaction(function () use ($noo, $admin): Toko {
            $toko = Toko::create([
                'kode' => Toko::kodeBerikutnya(),
                'nama' => $noo->nama,
                'wilayah_id' => $noo->wilayah_id,
                'alamat' => $noo->alamat,
                'kelurahan' => $noo->kelurahan,
                'kecamatan' => $noo->kecamatan,
                'kota' => $noo->kota,
                'provinsi' => $noo->provinsi,
                'kode_pos' => $noo->kode_pos,
                'telepon' => $noo->telepon,
                'nama_pemilik' => $noo->nama_pemilik,
                'nik_pemilik' => $noo->nik_pemilik,
                'kategori' => $noo->kategori,
                'freezer_tipe' => $noo->freezer_tipe,
                'latitude' => $noo->latitude,
                'longitude' => $noo->longitude,
                'sumber_koordinat' => $noo->sumber_koordinat,
                'geocoded_at' => now(),
                // Belum aktif dan tanpa nomor freezer: dua-duanya baru benar
                // setelah driver memasang freezernya di toko. Sampai saat itu
                // toko ini tidak muncul di pencarian Input Pesanan maupun
                // ikut routing reguler.
                'aktif' => false,
                'asset_id' => null,
            ]);

            $noo->update([
                'status' => StatusNoo::Process,
                'toko_id' => $toko->id,
                'disetujui_oleh' => $admin->id,
                'disetujui_at' => now(),
            ]);

            return $toko;
        });
    }

    /** @throws RuntimeException bila statusnya bukan lagi ORDER */
    public function tolak(Noo $noo, User $admin, string $alasan): void
    {
        if ($noo->status !== StatusNoo::Order) {
            throw new RuntimeException(__('noo.galat_bukan_order', ['kode' => $noo->kode]));
        }

        if (trim($alasan) === '') {
            throw new RuntimeException(__('noo.galat_alasan_tolak_wajib'));
        }

        $noo->update([
            'status' => StatusNoo::Ditolak,
            'ditolak_oleh' => $admin->id,
            'ditolak_at' => now(),
            'alasan_tolak' => $alasan,
        ]);
    }

    /**
     * Menuntaskan pengantaran freezer di lapangan.
     *
     * Ini titik saat calon benar-benar jadi mitra: tokonya diaktifkan dan
     * diberi nomor freezer (IDN), bukti pemasangannya tersimpan, dan pesanan
     * perdananya dibuat.
     *
     * Pesanan perdana SENGAJA dibuat di luar transaksi utama. Ia bisa gagal
     * karena sebab yang wajar — stok gudang kurang, atau paketnya di bawah
     * minimal dus depot — dan kegagalan di gudang tidak boleh membatalkan
     * pengantaran yang fisiknya sudah terjadi. Driver tidak akan pernah
     * tersangkut di depan toko karena urusan stok; alasannya disimpan dan
     * admin bisa membuatnya ulang (lihat buatPesananPerdana()).
     *
     * @param  array<string, mixed>  $data  assetId, freezerTipe, dan data toko yang masih kosong
     * @param  array<string, string>  $gambar  data URL, dikunci pada JenisBuktiNoo->value
     */
    public function selesaikan(Noo $noo, array $data, array $gambar, User $driver): void
    {
        if ($noo->status !== StatusNoo::Delivery) {
            throw new RuntimeException(__('noo.galat_bukan_delivery', ['kode' => $noo->kode]));
        }

        $tersimpan = [];

        try {
            DB::transaction(function () use ($noo, $data, $gambar, $driver, &$tersimpan): void {
                $toko = $noo->toko()->lockForUpdate()->firstOrFail();

                $toko->update([
                    ...$data,
                    // Sejak baris ini toko resmi jadi mitra: muncul di
                    // pencarian Input Pesanan dan ikut routing reguler.
                    'aktif' => true,
                ]);

                foreach (JenisBuktiNoo::wajibDriver() as $jenis) {
                    $tersimpan[] = $this->bukti->simpanFoto($noo, $jenis, $gambar[$jenis->value], $driver);
                }

                $this->bukti->simpanSemua($noo, $tersimpan);

                $noo->stop?->update([
                    'status' => StatusStop::Selesai,
                    'total_dus_terkirim' => 1,
                    'selesai_at' => now(),
                ]);

                $noo->update([
                    'status' => StatusNoo::Selesai,
                    'freezer_tipe' => $toko->freezer_tipe,
                    'diselesaikan_oleh' => $driver->id,
                    'selesai_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $this->bukti->hapusBerkas($tersimpan);

            throw $e;
        }

        $this->buatPesananPerdana($noo->fresh(), $driver);
    }

    /**
     * Membuat pesanan perdana dari paket yang dipilih pemilik toko.
     *
     * Tidak pernah melempar: kegagalannya dicatat di `catatan_pesanan_gagal`
     * supaya admin bisa menanganinya belakangan (isi ulang stok, lalu coba
     * lagi) tanpa mengganggu siapa pun yang sedang di lapangan.
     */
    public function buatPesananPerdana(Noo $noo, User $pembuat): ?Pesanan
    {
        if ($noo->pesanan_id !== null) {
            return $noo->pesanan;
        }

        $noo->loadMissing(['paket.items', 'toko', 'pengaju']);

        $baris = fn (bool $bonus): array => $noo->paket->items
            ->where('is_bonus', $bonus)
            ->map(fn ($item): array => ['produk_id' => $item->produk_id, 'jumlah_dus' => $item->jumlah_dus])
            ->values()
            ->all();

        try {
            $pesanan = app(PesananService::class)->buat(
                toko: $noo->toko,
                items: $baris(false),
                pembuat: $pembuat,
                catatan: __('noo.catatan_pesanan_perdana', ['kode' => $noo->kode]),
                bonusItems: $baris(true),
                // Atribusi penjualannya tetap milik sales yang menemukan
                // toko ini, bukan driver yang kebetulan menekan tombolnya.
                atasNamaSales: $noo->pengaju?->isSales() ? $noo->pengaju : null,
            );
        } catch (Throwable $e) {
            $noo->update(['catatan_pesanan_gagal' => $e->getMessage()]);

            return null;
        }

        $pesanan->update(['noo_id' => $noo->id]);
        $noo->update(['pesanan_id' => $pesanan->id, 'catatan_pesanan_gagal' => null]);

        return $pesanan;
    }

    /**
     * Toko AKTIF terdekat dari sebuah titik, kalau ada yang dalam radius.
     *
     * Dipakai untuk memperingatkan admin bahwa calon ini berdiri terlalu
     * dekat dengan mitra yang sudah ada — peringatan, bukan larangan:
     * dua toko bersebelahan memang mungkin, dan yang tahu duduk perkaranya
     * adalah admin, bukan aplikasi.
     *
     * Toko yang lahir dari NOO lain tapi freezernya belum terpasang sengaja
     * TIDAK ikut terhitung: ia belum aktif, jadi belum jadi mitra yang
     * wilayah jualannya perlu dilindungi.
     *
     * @return array{toko: Toko, jarak: int}|null
     */
    public function tokoAktifTerdekat(float $lat, float $lng, int $radiusMeter = self::RADIUS_PERINGATAN_M): ?array
    {
        // Disaring kotak koordinat dulu (memakai indeks [latitude, longitude]
        // di `tokos`) supaya haversine tidak dihitung untuk seluruh toko
        // sedepot. 1 derajat lintang ≈ 111.320 m; sepanjang bujur jaraknya
        // menyempit mengikuti kosinus lintang.
        $deltaLat = $radiusMeter / 111_320;
        $deltaLng = $radiusMeter / (111_320 * max(0.01, cos(deg2rad($lat))));

        $titik = new Koordinat($lat, $lng);

        return Toko::query()
            ->aktif()
            ->berkoordinat()
            ->whereBetween('latitude', [$lat - $deltaLat, $lat + $deltaLat])
            ->whereBetween('longitude', [$lng - $deltaLng, $lng + $deltaLng])
            ->get()
            ->map(fn (Toko $toko): array => [
                'toko' => $toko,
                'jarak' => (int) round(Geo::haversine($titik, new Koordinat((float) $toko->latitude, (float) $toko->longitude))),
            ])
            ->filter(fn (array $baris): bool => $baris['jarak'] <= $radiusMeter)
            ->sortBy('jarak')
            ->first();
    }

    /**
     * NIK dan nomor telepon pemilik unik per depot di `tokos` (lihat
     * Toko\LengkapiData::simpan()). Diperiksa lagi di sini, bukan cuma saat
     * sales mengajukan: admin boleh menyunting datanya sebelum menyetujui,
     * dan toko dengan identitas yang sama bisa saja terdaftar di sela-sela
     * kedua waktu itu.
     *
     * @throws RuntimeException
     */
    private function pastikanIdentitasBelumTerdaftar(Noo $noo): void
    {
        if (Toko::query()->where('nik_pemilik', $noo->nik_pemilik)->exists()) {
            throw new RuntimeException(__('toko.galat_nik_dipakai'));
        }

        if (Toko::query()->where('telepon', $noo->telepon)->exists()) {
            throw new RuntimeException(__('toko.galat_hp_dipakai'));
        }
    }

    /**
     * Kode NOO harian: NOO-Ymd-####. Bentuk dan cara hitungnya sama dengan
     * kode pesanan (PesananService::kodePesanan()) — termasuk ikut menghitung
     * baris yang sudah dihapus, supaya nomor yang pernah terpakai tidak
     * pernah dipakai ulang.
     */
    private function kodeBerikutnya(): string
    {
        $hariIni = now()->format('Ymd');
        $urutan = Noo::withTrashed()->whereDate('created_at', today())->count() + 1;

        return sprintf('NOO-%s-%04d', $hariIni, $urutan);
    }
}
