<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->produk = Produk::create([
        'kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100_000, 'harga' => 50_000,
    ]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
});

/**
 * Membuat sejumlah toko berkoordinat beserta pesanan yang sudah berstatus
 * PROCESS, yaitu keadaan tepat sebelum admin menekan Generate Routing.
 *
 * @return array<int, Pesanan>
 */
function siapkanPesananSiapRouting(int $jumlah, int $dusPerToko = 20, string $kodeWilayah = 'W1'): array
{
    $wilayah = Wilayah::firstOrCreate(['kode' => $kodeWilayah], ['nama' => "Wilayah {$kodeWilayah}"]);

    // Sebaran melingkar agar rutenya punya bentuk yang masuk akal.
    $pesanans = [];
    $mulai = Toko::count();

    for ($i = 0; $i < $jumlah; $i++) {
        $sudut = $i / $jumlah * 2 * M_PI;

        $toko = Toko::create([
            'kode' => sprintf('TK-%04d', $mulai + $i + 1),
            'nama' => 'Toko '.($mulai + $i + 1),
            'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. Uji No. '.($i + 1),
            'latitude' => -6.20 + cos($sudut) * 0.03,
            'longitude' => 106.82 + sin($sudut) * 0.03,
            'sumber_koordinat' => 'manual',
        ]);

        $pesanan = test()->pesananService->buat(
            $toko,
            [['produk_id' => test()->produk->id, 'jumlah_dus' => $dusPerToko]],
            test()->sales,
        );

        test()->pesananService->setujui($pesanan, test()->admin);

        $pesanans[] = $pesanan->fresh();
    }

    return $pesanans;
}

it('membentuk kendaraan dan urutan kunjungan dari pesanan PROCESS', function () {
    siapkanPesananSiapRouting(12, dusPerToko: 15);

    $batch = $this->routingService->generate($this->admin, maxToko: 25, maxDus: 220);

    expect($batch->status)->toBe('draft')
        ->and($batch->total_toko)->toBe(12)
        ->and($batch->total_dus)->toBe(180)
        ->and($batch->kendaraans)->toHaveCount(1);

    $kendaraan = $batch->kendaraans->first();

    expect($kendaraan->nama)->toBe('Mobil 1')
        ->and($kendaraan->stops)->toHaveCount(12);

    // Urutan kunjungan harus rapat 1..n tanpa nomor yang bolong.
    expect($kendaraan->stops->pluck('urutan')->all())->toBe(range(1, 12));

    // Setiap kunjungan punya perkiraan jam tiba.
    expect($kendaraan->stops->whereNull('eta'))->toBeEmpty();
});

it('memecah menjadi beberapa kendaraan saat batas dus terlampaui', function () {
    // 20 toko x 30 dus = 600 dus, dengan batas 220 dus perlu minimal 3 mobil.
    siapkanPesananSiapRouting(20, dusPerToko: 30);

    $batch = $this->routingService->generate($this->admin, maxToko: 25, maxDus: 220);

    expect($batch->kendaraans->count())->toBeGreaterThanOrEqual(3);

    foreach ($batch->kendaraans as $kendaraan) {
        expect($kendaraan->total_dus)->toBeLessThanOrEqual(220)
            ->and($kendaraan->total_toko)->toBeLessThanOrEqual(25);
    }

    expect($batch->total_toko)->toBe(20)
        ->and($batch->total_dus)->toBe(600);
});

it('memecah menjadi beberapa kendaraan saat batas toko terlampaui', function () {
    siapkanPesananSiapRouting(30, dusPerToko: 5);

    $batch = $this->routingService->generate($this->admin, maxToko: 25, maxDus: 220);

    expect($batch->kendaraans->count())->toBeGreaterThanOrEqual(2);

    foreach ($batch->kendaraans as $kendaraan) {
        expect($kendaraan->total_toko)->toBeLessThanOrEqual(25);
    }
});

it('memisahkan kendaraan menurut wilayah', function () {
    siapkanPesananSiapRouting(6, dusPerToko: 10, kodeWilayah: 'W1');
    siapkanPesananSiapRouting(6, dusPerToko: 10, kodeWilayah: 'W2');

    $batch = $this->routingService->generate($this->admin);

    expect($batch->kendaraans)->toHaveCount(2);

    foreach ($batch->kendaraans as $kendaraan) {
        $wilayahStop = $kendaraan->stops->map(fn (KendaraanStop $s) => $s->toko->wilayah_id)->unique();

        expect($wilayahStop)->toHaveCount(1)
            ->and($kendaraan->wilayah_id)->toBe($wilayahStop->first());
    }
});

it('melewati toko tanpa koordinat dan tetap merutekan sisanya', function () {
    siapkanPesananSiapRouting(5, dusPerToko: 10);

    $wilayah = Wilayah::first();
    $tokoTanpaTitik = Toko::create([
        'kode' => 'TK-9999', 'nama' => 'Toko Tanpa Titik',
        'wilayah_id' => $wilayah->id, 'alamat' => 'Jl. Entah',
    ]);

    $pesanan = $this->pesananService->buat(
        $tokoTanpaTitik,
        [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]],
        $this->sales,
    );
    $this->pesananService->setujui($pesanan, $this->admin);

    $batch = $this->routingService->generate($this->admin);

    expect($batch->total_toko)->toBe(5)
        ->and($pesanan->fresh()->status)->toBe(StatusPesanan::Process)
        ->and(KendaraanStop::where('pesanan_id', $pesanan->id)->exists())->toBeFalse();
});

it('tidak merutekan ulang pesanan yang sudah masuk draft', function () {
    siapkanPesananSiapRouting(5, dusPerToko: 10);

    $this->routingService->generate($this->admin);

    expect($this->routingService->pesananSiapRouting())->toBeEmpty()
        ->and(fn () => $this->routingService->generate($this->admin))
        ->toThrow(RuntimeException::class);
});

it('mengembalikan pesanan ke antrean ketika draft dihapus', function () {
    siapkanPesananSiapRouting(5, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->hapusDraft($batch);

    expect($this->routingService->pesananSiapRouting())->toHaveCount(5)
        ->and(Kendaraan::count())->toBe(0)
        ->and(KendaraanStop::count())->toBe(0);
});

it('menaikkan semua pesanan menjadi DELIVERY saat routing disetujui', function () {
    siapkanPesananSiapRouting(6, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $batch->refresh();

    expect($batch->status)->toBe('disetujui')
        ->and($batch->disetujui_oleh)->toBe($this->admin->id)
        ->and(Pesanan::where('status', StatusPesanan::Delivery)->count())->toBe(6)
        ->and(Kendaraan::where('status', 'siap')->count())->toBe($batch->kendaraans->count());
});

it('menolak persetujuan routing yang sudah disetujui', function () {
    siapkanPesananSiapRouting(4, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    expect(fn () => $this->routingService->setujui($batch->fresh(), $this->admin))
        ->toThrow(RuntimeException::class);
});

it('memindahkan toko antar mobil dan merapikan urutan keduanya', function () {
    siapkanPesananSiapRouting(10, dusPerToko: 30);

    $batch = $this->routingService->generate($this->admin, maxToko: 5, maxDus: 220);

    expect($batch->kendaraans->count())->toBeGreaterThanOrEqual(2);

    $asal = $batch->kendaraans->first();
    $tujuan = $batch->kendaraans->skip(1)->first();

    $jumlahAsalAwal = $asal->stops->count();
    $jumlahTujuanAwal = $tujuan->stops->count();
    $stop = $asal->stops->first();

    $this->routingService->pindahStop($stop, $tujuan);

    $asal->refresh()->load('stops');
    $tujuan->refresh()->load('stops');

    expect($stop->fresh()->kendaraan_id)->toBe($tujuan->id)
        ->and($asal->stops)->toHaveCount($jumlahAsalAwal - 1)
        ->and($tujuan->stops)->toHaveCount($jumlahTujuanAwal + 1)
        // Nomor urut tidak boleh bolong setelah perpindahan.
        ->and($asal->stops->pluck('urutan')->all())->toBe(range(1, $jumlahAsalAwal - 1))
        ->and($tujuan->stops->pluck('urutan')->all())->toBe(range(1, $jumlahTujuanAwal + 1))
        // Totalnya ikut dihitung ulang.
        ->and($asal->total_toko)->toBe($jumlahAsalAwal - 1)
        ->and($tujuan->total_toko)->toBe($jumlahTujuanAwal + 1);
});

it('menyusun ulang urutan sesuai daftar yang diberikan admin', function () {
    siapkanPesananSiapRouting(6, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $kendaraan = $batch->kendaraans->first();

    $terbalik = $kendaraan->stops->pluck('id')->reverse()->values()->all();

    $this->routingService->ubahUrutan($kendaraan, $terbalik);

    expect($kendaraan->fresh()->stops->pluck('id')->all())->toBe($terbalik);
});

it('menyelesaikan pengiriman lewat unggahan foto nota dan memotong stok', function () {
    Storage::fake('public');

    siapkanPesananSiapRouting(3, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $stop = $kendaraan->stops->first();

    $stokSebelum = $this->produk->fresh()->stok;
    $reservedSebelum = $this->produk->fresh()->stok_reserved;

    $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');

    $this->pesananService->selesaikanPengiriman($stop, $path, $this->driver, 'Diterima Pak Budi');

    $stop->refresh();
    $this->produk->refresh();

    expect($stop->status)->toBe(StatusStop::Selesai)
        ->and($stop->foto_nota)->toBe($path)
        ->and($stop->catatan_driver)->toBe('Diterima Pak Budi')
        ->and($stop->pesanan()->first()->status)->toBe(StatusPesanan::Selesai)
        // Stok fisik baru berkurang di titik ini, bukan saat pesanan dibuat.
        ->and($this->produk->stok)->toBe($stokSebelum - 10)
        ->and($this->produk->stok_reserved)->toBe($reservedSebelum - 10)
        // Mobil berpindah ke status jalan karena masih ada toko tersisa.
        ->and($kendaraan->fresh()->status)->toBe('jalan');
});

it('menandai mobil selesai setelah toko terakhir dikirim', function () {
    Storage::fake('public');

    siapkanPesananSiapRouting(3, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();

    foreach ($kendaraan->stops as $stop) {
        $path = UploadedFile::fake()->image("nota-{$stop->id}.jpg")->store('nota', 'public');
        $this->pesananService->selesaikanPengiriman($stop, $path, $this->driver);
    }

    expect($kendaraan->fresh()->status)->toBe('selesai')
        ->and(Pesanan::where('status', StatusPesanan::Selesai)->count())->toBe(3);
});

it('menolak penyelesaian ganda pada kunjungan yang sama', function () {
    Storage::fake('public');

    siapkanPesananSiapRouting(2, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $stop = $batch->fresh()->kendaraans->first()->stops->first();
    $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');

    $this->pesananService->selesaikanPengiriman($stop, $path, $this->driver);

    expect(fn () => $this->pesananService->selesaikanPengiriman($stop->fresh(), $path, $this->driver))
        ->toThrow(RuntimeException::class);
});

it('mengeluarkan kunjungan dari rute ketika pesanannya dibatalkan', function () {
    siapkanPesananSiapRouting(5, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $stop = $kendaraan->stops->first();
    $pesanan = $stop->pesanan()->first();

    $this->pesananService->batalkan($pesanan, $this->admin, 'Toko tutup');

    expect($pesanan->fresh()->status)->toBe(StatusPesanan::Cancel)
        ->and(KendaraanStop::find($stop->id))->toBeNull()
        ->and($kendaraan->fresh()->total_toko)->toBe(4)
        ->and($this->produk->fresh()->stok_reserved)->toBe(40);
});

it('menolak pembatalan pesanan yang notanya sudah diunggah', function () {
    Storage::fake('public');

    siapkanPesananSiapRouting(2, dusPerToko: 10);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $stop = $batch->fresh()->kendaraans->first()->stops->first();
    $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');

    $this->pesananService->selesaikanPengiriman($stop, $path, $this->driver);

    expect(fn () => $this->pesananService->batalkan($stop->pesanan()->first(), $this->admin, 'Terlambat'))
        ->toThrow(RuntimeException::class);
});
