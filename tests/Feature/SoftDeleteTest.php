<?php

use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarWilayah;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\RoutingBatch;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * Lima tabel yang tadinya memakai ->delete() sungguhan (wilayahs,
 * routing_batches, kendaraans, kendaraan_stops, pesanans) sekarang soft
 * delete: baris bertahan di basis data, tersembunyi dari kueri biasa. Dua
 * tabel lain (penugasan_sales, kunjungan_fotos) hanya dapat kolomnya lebih
 * dulu — trait SoftDeletes sengaja belum dipasang karena batasan uniknya
 * dipakai sebagai bagian dari logika aplikasi (lihat komentar migrasinya).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

/** Satu pesanan berstatus ORDER, dengan toko baru tiap panggilan. */
function buatPesananUji(): Pesanan
{
    static $n = 0;
    $n++;

    $wilayah = Wilayah::firstOrCreate(['kode' => 'W-PSN'], ['nama' => 'Wilayah Pesanan Uji']);
    $produk = Produk::firstOrCreate(
        ['kode' => 'P-PSN'],
        ['nama' => 'Produk Uji Pesanan', 'stok' => 100_000, 'harga' => 10_000],
    );
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $toko = Toko::create([
        'kode' => "TK-PSN{$n}", 'nama' => "Toko Pesanan Uji {$n}", 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Pesanan Uji', 'latitude' => -6.20 + $n * 0.001, 'longitude' => 106.80 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);

    return app(PesananService::class)->buat(
        $toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $sales,
    );
}

describe('wilayah', function () {
    it('bertahan di basis data setelah dihapus, tapi hilang dari kueri biasa', function () {
        $wilayah = Wilayah::create(['kode' => 'W-SD', 'nama' => 'Wilayah Uji']);

        $wilayah->delete();

        expect(Wilayah::find($wilayah->id))->toBeNull()
            ->and(Wilayah::withTrashed()->find($wilayah->id))->not->toBeNull()
            ->and(Wilayah::onlyTrashed()->find($wilayah->id)->deleted_at)->not->toBeNull();
    });

    it('muncul di daftar terhapus dan bisa dipulihkan lewat layar admin', function () {
        $wilayah = Wilayah::create(['kode' => 'W-SD2', 'nama' => 'Wilayah Dipulihkan']);

        $component = Livewire::actingAs($this->admin)->test(DaftarWilayah::class);

        $component->call('hapus', $wilayah->id);

        expect($component->get('wilayahs')->pluck('id'))->not->toContain($wilayah->id)
            ->and($component->get('wilayahsTerhapus')->pluck('id'))->toContain($wilayah->id);

        $component->call('pulihkan', $wilayah->id);

        expect($component->get('wilayahs')->pluck('id'))->toContain($wilayah->id)
            ->and($component->get('wilayahsTerhapus')->pluck('id'))->not->toContain($wilayah->id)
            ->and($wilayah->fresh()->deleted_at)->toBeNull();
    });

    it('menolak menghapus wilayah yang masih dipakai toko', function () {
        $wilayah = Wilayah::create(['kode' => 'W-DIPAKAI', 'nama' => 'Wilayah Dipakai']);
        Toko::create([
            'kode' => 'TK-SD01', 'nama' => 'Toko Uji', 'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. Uji', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        ]);

        Livewire::actingAs($this->admin)->test(DaftarWilayah::class)->call('hapus', $wilayah->id);

        expect($wilayah->fresh()->deleted_at)->toBeNull();
    });

    /**
     * Batasan unik pada `kode` tetap menghitung baris yang di-soft-delete —
     * ini keterbatasan yang sengaja diterima, bukan bug: kode yang sudah
     * dihapus baru bisa dipakai lagi setelah wilayahnya dipulihkan atau
     * dihapus permanen dari basis data.
     */
    it('kode yang sudah dihapus belum bisa dipakai wilayah baru', function () {
        Wilayah::create(['kode' => 'W-PAKAI', 'nama' => 'Yang Lama'])->delete();

        expect(fn () => Wilayah::create(['kode' => 'W-PAKAI', 'nama' => 'Yang Baru']))
            ->toThrow(Exception::class);
    });
});

describe('draft routing dihapus', function () {
    /**
     * Sebelum soft delete, ON DELETE CASCADE di basis data yang membereskan
     * kendaraan dan kunjungannya begitu batch dihapus. Soft delete hanyalah
     * UPDATE kolom deleted_at, jadi cascade itu tidak lagi terpicu —
     * RoutingService::hapusDraft() sekarang membereskannya sendiri secara
     * eksplisit, dan sengaja forceDelete (bukan soft delete): draf yang
     * belum pernah disetujui tidak punya nilai audit, dan soft delete di
     * sini akan membentur batasan unik kendaraan_stops.pesanan_id begitu
     * pesanannya dirutekan ulang. Tes ini mengunci perilaku itu.
     */
    it('membereskan kendaraan dan kunjungannya, dan pesanan kembali ke antrean', function () {
        $wilayah = Wilayah::create(['kode' => 'W-SD3', 'nama' => 'Wilayah Draft']);
        $produk = Produk::create(['kode' => 'P-SD', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 10_000]);
        $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
        $pesananService = app(PesananService::class);
        $routingService = app(RoutingService::class);

        foreach (range(1, 3) as $i) {
            $toko = Toko::create([
                'kode' => "TK-SD{$i}", 'nama' => "Toko Draft {$i}", 'wilayah_id' => $wilayah->id,
                'alamat' => 'Jl. Draft', 'latitude' => -6.20 + $i * 0.01, 'longitude' => 106.80 + $i * 0.01,
                'sumber_koordinat' => 'manual',
            ]);
            $pesanan = $pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $sales);
            $pesananService->setujui($pesanan, $this->admin);
        }

        $batch = $routingService->generate($this->admin);
        $kendaraanIds = $batch->kendaraans->pluck('id');
        $stopIds = KendaraanStop::whereIn('kendaraan_id', $kendaraanIds)->pluck('id');

        expect($kendaraanIds)->not->toBeEmpty()->and($stopIds)->not->toBeEmpty();

        $routingService->hapusDraft($batch);

        expect(Kendaraan::whereIn('id', $kendaraanIds)->count())->toBe(0)
            ->and(KendaraanStop::whereIn('id', $stopIds)->count())->toBe(0)
            // Draf yang belum pernah disetujui benar-benar hilang
            // (forceDelete), bukan cuma disembunyikan — tidak ada nilai
            // audit yang dikorbankan, dan menghindari bentrok batasan unik
            // begitu pesanannya dirutekan ulang.
            ->and(Kendaraan::withTrashed()->whereIn('id', $kendaraanIds)->count())->toBe(0)
            ->and(KendaraanStop::withTrashed()->whereIn('id', $stopIds)->count())->toBe(0)
            // Batch-nya sendiri tetap soft delete — catatan bahwa sebuah
            // draf pernah dibuat lalu dibuang.
            ->and(RoutingBatch::withTrashed()->find($batch->id)->deleted_at)->not->toBeNull()
            ->and($routingService->pesananSiapRouting())->toHaveCount(3);
    });
});

describe('penomoran tidak dipakai ulang setelah dihapus', function () {
    /**
     * withTrashed() dipasang di tambahKendaraan() dan kodeBatch() supaya
     * angka/kode yang sempat dipakai baris yang sudah di-soft-delete tidak
     * ditawarkan lagi — batasan unik di basis data tetap menghitungnya,
     * sekalipun baris itu sudah tak terlihat lewat kueri biasa.
     */
    it('tidak mengulang nomor kendaraan kosong yang sudah dihapus', function () {
        $batch = RoutingBatch::create([
            'kode' => 'RTG-UJI-01', 'tanggal' => today(), 'status' => 'draft',
            'dibuat_oleh' => $this->admin->id,
        ]);
        $routingService = app(RoutingService::class);

        $mobil1 = $routingService->tambahKendaraan($batch);
        $mobil2 = $routingService->tambahKendaraan($batch);

        expect($mobil1->nomor)->toBe(1)->and($mobil2->nomor)->toBe(2);

        $mobil2->delete();

        $mobil3 = $routingService->tambahKendaraan($batch);

        expect($mobil3->nomor)->toBe(3);
    });

    it('tidak mengulang kode batch draft yang sudah dihapus di hari yang sama', function () {
        $routingService = app(RoutingService::class);
        $wilayah = Wilayah::create(['kode' => 'W-SD4', 'nama' => 'Wilayah Kode']);
        $produk = Produk::create(['kode' => 'P-SD2', 'nama' => 'Produk Kode', 'stok' => 1000, 'harga' => 10_000]);
        $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
        $pesananService = app(PesananService::class);

        $buatBatch = function () use ($wilayah, $produk, $sales, $pesananService, $routingService) {
            static $n = 0;
            $n++;

            $toko = Toko::create([
                'kode' => "TK-SDK{$n}", 'nama' => "Toko Kode {$n}", 'wilayah_id' => $wilayah->id,
                'alamat' => 'Jl. Kode', 'latitude' => -6.20 + $n * 0.01, 'longitude' => 106.80 + $n * 0.01,
                'sumber_koordinat' => 'manual',
            ]);
            $pesanan = $pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $sales);
            $pesananService->setujui($pesanan, test()->admin);

            return $routingService->generate(test()->admin);
        };

        $batch1 = $buatBatch();
        $routingService->hapusDraft($batch1);

        $batch2 = $buatBatch();

        expect($batch2->kode)->not->toBe($batch1->kode);
    });
});

describe('pesanan', function () {
    /**
     * Pesanan dulunya masuk kelompok "kolom disiapkan, belum dipakai" —
     * tidak ada jalur kode yang menghapusnya. Sejak ada kebutuhan nyata
     * membuang pesanan dummy tanpa kehilangan barisnya, trait SoftDeletes
     * dipasang beserta dua penjagaan yang menyertainya.
     */
    it('bertahan di basis data setelah dihapus, tapi hilang dari kueri biasa', function () {
        $pesanan = buatPesananUji();

        $pesanan->delete();

        expect(Pesanan::find($pesanan->id))->toBeNull()
            ->and(Pesanan::withTrashed()->find($pesanan->id))->not->toBeNull();
    });

    /**
     * kodePesanan() memakai withTrashed() supaya kode tidak dipakai ulang
     * — batasan unik pada `kode` tetap menghitung baris yang di-soft-delete,
     * persis pola yang sama dengan Wilayah, RoutingBatch, dan Kendaraan.
     */
    it('tidak mengulang kode pesanan yang sudah dihapus di hari yang sama', function () {
        $p1 = buatPesananUji();
        $kode1 = $p1->kode;
        $p1->delete();

        $p2 = buatPesananUji();

        expect($p2->kode)->not->toBe($kode1);
    });

    /**
     * Banyak layar driver mengakses $stop->pesanan->... tanpa null-safe
     * karena selama ini pesanan pada sebuah stop dijamin selalu ada.
     * Menghapus pesanan yang sudah masuk rute akan mematahkan asumsi itu
     * dan membuat layar-layar tersebut error, jadi ->delete() Eloquent
     * menolaknya. Ini TIDAK melindungi dari mengedit kolom deleted_at
     * langsung lewat basis data — hanya penghapusan lewat aplikasi.
     */
    it('menolak dihapus lewat Eloquent kalau sudah masuk rute pengiriman', function () {
        $admin = User::factory()->create(['role' => PeranPengguna::Admin]);
        $pesanan = buatPesananUji();
        app(PesananService::class)->setujui($pesanan, $admin);
        $batch = app(RoutingService::class)->generate($admin);

        $pesanan->refresh();
        expect($pesanan->stop)->not->toBeNull();

        expect(fn () => $pesanan->delete())->toThrow(RuntimeException::class);
        expect(Pesanan::find($pesanan->id))->not->toBeNull();
    });

    it('boleh dihapus kalau belum pernah masuk rute', function () {
        $pesanan = buatPesananUji();

        $pesanan->delete();

        expect(Pesanan::find($pesanan->id))->toBeNull();
    });
});

describe('kolom disiapkan tapi belum dipakai sebagai soft delete', function () {
    /**
     * penugasan_sales dan kunjungan_fotos punya kolom deleted_at, tapi
     * modelnya sengaja TIDAK memakai trait SoftDeletes: keduanya masih
     * hard-delete seperti semula. Lihat komentar migrasi
     * 2026_08_21_000100_add_soft_deletes_columns.php untuk alasannya.
     */
    it('kolom deleted_at ada di skema penugasan_sales dan kunjungan_fotos', function () {
        expect(Schema::hasColumn('penugasan_sales', 'deleted_at'))->toBeTrue()
            ->and(Schema::hasColumn('kunjungan_fotos', 'deleted_at'))->toBeTrue();
    });

    it('penugasan_sales masih hard delete, bukan soft delete', function () {
        $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
        $wilayah = Wilayah::create(['kode' => 'W-SD5', 'nama' => 'Wilayah Tugas']);
        $toko = Toko::create([
            'kode' => 'TK-SDT1', 'nama' => 'Toko Tugas', 'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. Tugas', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        ]);

        $id = DB::table('penugasan_sales')->insertGetId([
            'sales_id' => $sales->id, 'toko_id' => $toko->id, 'bulan' => today()->startOfMonth(),
            'ditugaskan_oleh' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('penugasan_sales')->where('id', $id)->delete();

        expect(DB::table('penugasan_sales')->where('id', $id)->exists())->toBeFalse();
    });
});
