<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusBayar;
use App\Livewire\Pembayaran\Pendapatan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Services\RoutingService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Menu Pendapatan mengelompokkan pesanan kategori "driver" (rute biasa +
 * kampas) menurut tanggal KEBERANGKATAN kendaraannya
 * (`RoutingBatch::tanggal`), BUKAN tanggal pelunasan — toko yang
 * berangkat dikirim tanggal 20 tapi baru lunas tanggal 22 tetap terhitung
 * sebagai pendapatan tanggal 20. Kategori "pos" (tidak pernah lewat
 * kendaraan) tetap memakai tanggal_lunas seperti biasa.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 20_000]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
});

/**
 * Menyiapkan satu pesanan LUNAS berkategori driver, yang kendaraannya
 * berangkat pada $tanggalKeberangkatan tapi baru dilunasi belakangan pada
 * $tanggalLunas — men-simulasikan skenario di permintaan: berangkat
 * tanggal 20, baru lunas tanggal 22.
 */
function pesananDriverLunasBelakangan(
    CarbonImmutable $tanggalKeberangkatan,
    CarbonImmutable $tanggalLunas,
    int $totalDus = 5,
): Pesanan {
    static $n = 0;
    $n++;

    $toko = Toko::create([
        'kode' => sprintf('TK-TGL%04d', $n),
        'nama' => 'Toko Tanggal '.$n,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Tanggal No. '.$n,
        'latitude' => -6.20 + $n * 0.001,
        'longitude' => 106.82 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = test()->pesananService->buat(
        $toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => $totalDus]], test()->sales,
    );
    test()->pesananService->setujui($pesanan, test()->admin);

    $batch = test()->routingService->generate(test()->admin, tanggalKeberangkatan: $tanggalKeberangkatan);
    test()->routingService->setujui($batch, test()->admin);

    // Lunas belakangan — tandaiLunas() (PelunasanService) selalu memakai
    // today(), jadi diset langsung di sini untuk mensimulasikan pelunasan
    // yang sungguh terjadi di tanggal lain.
    $pesanan->fresh()->update([
        'status_bayar' => StatusBayar::Lunas,
        'tanggal_lunas' => $tanggalLunas,
        'dilunasi_oleh' => test()->admin->id,
        'nominal_cash' => $totalDus * (float) test()->produk->harga,
        'nominal_transfer' => 0,
    ]);

    return $pesanan->fresh();
}

it('pesanan yang berangkat tanggal 20 tapi baru lunas tanggal 22 masuk pendapatan tanggal 20', function () {
    $keberangkatan = CarbonImmutable::parse('2026-08-20');
    $lunas = CarbonImmutable::parse('2026-08-22');

    $pesanan = pesananDriverLunasBelakangan($keberangkatan, $lunas);

    expect($pesanan->tanggal_pendapatan->toDateString())->toBe($keberangkatan->toDateString());

    // Mode "hari" pada tanggal KEBERANGKATAN (20) menemukannya...
    $adaDiTanggal20 = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'hari')
        ->set('tanggal', $keberangkatan->toDateString())
        ->instance()->pesanans()
        ->contains('id', $pesanan->id);

    // ...tapi mode "hari" pada tanggal PELUNASAN (22) TIDAK — dus-nya
    // sudah dihitung masuk pendapatan tanggal 20, tidak dihitung dua kali.
    $adaDiTanggal22 = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'hari')
        ->set('tanggal', $lunas->toDateString())
        ->instance()->pesanans()
        ->contains('id', $pesanan->id);

    expect($adaDiTanggal20)->toBeTrue()
        ->and($adaDiTanggal22)->toBeFalse();
});

it('ringkasanHarian mengelompokkan pesanan driver menurut tanggal keberangkatan', function () {
    $keberangkatan = CarbonImmutable::parse('2026-08-20');
    $lunas = CarbonImmutable::parse('2026-08-22');

    pesananDriverLunasBelakangan($keberangkatan, $lunas, totalDus: 5);

    $ringkasan = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->instance()->ringkasanHarian();

    expect($ringkasan->keys()->all())->toBe([$keberangkatan->toDateString()])
        ->and($ringkasan->get($keberangkatan->toDateString()))->toEqualWithDelta(100_000.0, 0.01);
});

it('mode rentang yang mencakup tanggal keberangkatan menemukannya walau tanggal lunasnya di luar rentang', function () {
    $keberangkatan = CarbonImmutable::parse('2026-08-20');
    $lunas = CarbonImmutable::parse('2026-08-25');

    $pesanan = pesananDriverLunasBelakangan($keberangkatan, $lunas);

    $ditemukan = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'rentang')
        ->set('dariTanggal', '2026-08-19')
        ->set('sampaiTanggal', '2026-08-21')
        ->instance()->pesanans()
        ->contains('id', $pesanan->id);

    expect($ditemukan)->toBeTrue();
});

it('pesanan POS tetap memakai tanggal_lunas, tidak terpengaruh perubahan ini', function () {
    $toko = Toko::create([
        'kode' => 'TK-POSTGL', 'nama' => 'Toko POS Tanggal', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. POS Tanggal', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $pesanan = $this->pesananService->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 2]],
        penjual: $this->sales,
        nominalCash: 40_000,
        nominalTransfer: 0,
    );

    // POS langsung lunas seketika saat dibuat — tanggal_lunas = hari ini,
    // dan tidak pernah punya kendaraan/batch sama sekali.
    expect($pesanan->fresh(['stop'])->tanggal_pendapatan->toDateString())
        ->toBe($pesanan->tanggal_lunas->toDateString())
        ->toBe(today()->toDateString());
});

/**
 * Tanggal keberangkatan sekarang milik KENDARAAN, bukan batch — dua mobil
 * dari satu `generate()` yang sama boleh diedit ke tanggal berbeda-beda
 * lewat `RoutingService::ubahTanggal()` (lihat `RoutingDriverTest.php`).
 * `tanggal_pendapatan` tiap pesanan harus ikut tanggal KENDARAANNYA
 * sendiri, bukan tanggal default batch saat digenerate.
 */
it('dua kendaraan dari batch yang sama diedit ke tanggal berbeda — pendapatan ikut tanggal kendaraan masing-masing', function () {
    $wilayah = Wilayah::create(['kode' => 'W-PTK', 'nama' => 'Wilayah PTK']);
    $produk = Produk::create(['kode' => 'PTK1', 'nama' => 'Produk PTK', 'stok' => 1000, 'harga' => 25_000]);

    foreach ([1, 2] as $i) {
        $toko = Toko::create([
            'kode' => "TK-PTK{$i}", 'nama' => "Toko PTK {$i}", 'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. PTK', 'latitude' => -6.20 + $i * 0.05, 'longitude' => 106.80 + $i * 0.05,
            'sumber_koordinat' => 'manual',
        ]);
        $pesanan = $this->pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 5]], $this->sales);
        $this->pesananService->setujui($pesanan, $this->admin);
    }

    // maxToko: 1 supaya kedua toko dipecah ke dua kendaraan berbeda.
    $batch = $this->routingService->generate($this->admin, maxToko: 1, pisahPerWilayah: false);
    $this->routingService->setujui($batch, $this->admin);

    [$kendaraan1, $kendaraan2] = $batch->fresh()->kendaraans->all();

    $tanggal1 = CarbonImmutable::parse('2026-08-20');
    $tanggal2 = CarbonImmutable::parse('2026-08-25');

    $this->routingService->ubahTanggal($kendaraan1, $tanggal1);
    $this->routingService->ubahTanggal($kendaraan2, $tanggal2);

    $pesanan1 = $kendaraan1->fresh()->stops->first()->pesanan()->first();
    $pesanan2 = $kendaraan2->fresh()->stops->first()->pesanan()->first();

    expect($pesanan1->fresh(['stop.kendaraan'])->tanggal_pendapatan->toDateString())->toBe($tanggal1->toDateString())
        ->and($pesanan2->fresh(['stop.kendaraan'])->tanggal_pendapatan->toDateString())->toBe($tanggal2->toDateString());
});
