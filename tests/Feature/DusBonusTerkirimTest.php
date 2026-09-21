<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Statistik\DusBonusTerkirim;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Barat']);
    $this->produkUtama = Produk::create(['kode' => 'P1', 'nama' => 'Kopi Robusta', 'stok' => 1000, 'harga' => 25_000]);
    $this->produkBonus = Produk::create(['kode' => 'P2', 'nama' => 'Gula Pasir', 'stok' => 1000, 'harga' => 12_000]);
    $this->produkVarian = Produk::create(['kode' => 'P3', 'nama' => 'Sirup Vanila', 'stok' => 1000, 'harga' => 15_000]);

    $this->pesananService = app(PesananService::class);
    $this->pengirimanService = app(PengirimanService::class);
    $this->routingService = app(RoutingService::class);
});

function tokoUjiBonus(string $nama): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-BN%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Bonus No. '.$n,
        'latitude' => -6.20 + $n * 0.001,
        'longitude' => 106.82 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);
}

function gambarNotaUjiBonus(): string
{
    $g = imagecreatetruecolor(200, 150);
    ob_start();
    imagejpeg($g, null, 70);
    $isi = (string) ob_get_clean();
    imagedestroy($g);

    Storage::disk('public')->put('nota/uji-bonus.jpg', $isi);

    return 'nota/uji-bonus.jpg';
}

/**
 * Membuat pesanan rute pengantaran driver dengan bonus manual atau promo, lalu menyelesaikan pengiriman.
 */
function siapkanPesananDriverBonus(
    CarbonImmutable $tanggal,
    Produk $produk,
    Produk $produkBonus,
    int $jumlahReguler = 5,
    int $jumlahBonus = 2,
    ?string $namaToko = null
): array {
    $toko = tokoUjiBonus($namaToko ?? 'Toko Driver Bonus');

    $pesanan = test()->pesananService->buat(
        toko: $toko,
        items: [['produk_id' => $produk->id, 'jumlah_dus' => $jumlahReguler]],
        pembuat: test()->sales,
        bonusItems: [['produk_id' => $produkBonus->id, 'jumlah_dus' => $jumlahBonus]],
    );
    test()->pesananService->setujui($pesanan, test()->admin);

    $batch = test()->routingService->generate(test()->admin, tanggalKeberangkatan: $tanggal);
    test()->routingService->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id]);

    $stop = $kendaraan->stops()->with('pesanan.items', 'toko')->get()
        ->first(fn ($s) => $s->toko->nama === $toko->nama);

    test()->pesananService->selesaikanPengiriman($stop, gambarNotaUjiBonus(), test()->driver);

    return [$pesanan->fresh(), $stop->fresh(), $kendaraan->fresh()];
}

it('menolak sales dan driver, mengizinkan admin', function () {
    $this->actingAs($this->sales)->get(route('statistik.dus-bonus-terkirim'))->assertForbidden();
    $this->actingAs($this->driver)->get(route('statistik.dus-bonus-terkirim'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('statistik.dus-bonus-terkirim'))->assertOk();
});

describe('penyaringan dus bonus terkirim', function () {
    it('hanya menghitung item bonus dari pesanan berstatus selesai', function () {
        $tanggal = CarbonImmutable::parse('2026-09-10');

        // Pesanan 1: Selesai dengan 3 dus bonus
        siapkanPesananDriverBonus($tanggal, $this->produkUtama, $this->produkBonus, 10, 3, 'Toko Selesai 1');

        // Pesanan 2: POS Selesai dengan 2 dus bonus sirup
        $tokoPos = tokoUjiBonus('Toko POS Selesai');
        $this->pesananService->buatPos(
            toko: $tokoPos,
            items: [['produk_id' => $this->produkUtama->id, 'jumlah_dus' => 4]],
            penjual: $this->admin,
            nominalCash: 100_000,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $this->produkVarian->id, 'jumlah_dus' => 2]],
        );

        // Pesanan 3: Masih Process / belum selesai
        $tokoPending = tokoUjiBonus('Toko Belum Selesai');
        $this->pesananService->buat(
            toko: $tokoPending,
            items: [['produk_id' => $this->produkUtama->id, 'jumlah_dus' => 5]],
            pembuat: $this->sales,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 4]],
        );

        $komponen = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class)->set('mode', 'semua');

        // Hanya pesanan 1 (3 dus) dan pesanan 2 (2 dus) = 5 dus bonus.
        // Item reguler (10 + 4 dus) dan pesanan belum selesai (4 dus) tidak dihitung.
        expect($komponen->instance()->totalDusBonus)->toBe(5)
            ->and($komponen->instance()->totalVarian)->toBe(2)
            ->and($komponen->instance()->totalPesanan)->toBe(2)
            ->and($komponen->instance()->totalToko)->toBe(2);
    });

    it('memperhitungkan nota yang dicoret di lapangan', function () {
        $tanggal = CarbonImmutable::parse('2026-09-12');

        $toko = tokoUjiBonus('Toko Coret Bonus');
        $pesanan = $this->pesananService->buat(
            toko: $toko,
            items: [['produk_id' => $this->produkUtama->id, 'jumlah_dus' => 10]],
            pembuat: $this->sales,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 5]],
        );
        $this->pesananService->setujui($pesanan, $this->admin);

        $batch = $this->routingService->generate($this->admin, tanggalKeberangkatan: $tanggal);
        $this->routingService->setujui($batch, $this->admin);
        $kendaraan = $batch->fresh()->kendaraans->first();

        $stop = $kendaraan->stops()->with('pesanan.items', 'toko')->get()
            ->first(fn ($s) => $s->toko->nama === $toko->nama);
        $itemBonus = $stop->pesanan->items->firstWhere('is_bonus', true);

        // Toko hanya menerima 2 dus dari 5 dus bonus yang dijadwalkan
        $this->pengirimanService->coretNota($stop, [$itemBonus->id => 2], gambarNotaUjiBonus(), $this->driver);

        $komponen = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class)
            ->set('mode', 'hari')
            ->set('tanggal', $tanggal->toDateString());

        expect($komponen->instance()->totalDusBonus)->toBe(2);
    });

    it('tidak menghitung item bonus yang dicoret habis (0 diterima toko)', function () {
        $tanggal = CarbonImmutable::parse('2026-09-13');

        $toko = tokoUjiBonus('Toko Tolak Bonus');
        $pesanan = $this->pesananService->buat(
            toko: $toko,
            items: [['produk_id' => $this->produkUtama->id, 'jumlah_dus' => 5]],
            pembuat: $this->sales,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 3]],
        );
        $this->pesananService->setujui($pesanan, $this->admin);

        $batch = $this->routingService->generate($this->admin, tanggalKeberangkatan: $tanggal);
        $this->routingService->setujui($batch, $this->admin);
        $kendaraan = $batch->fresh()->kendaraans->first();

        $stop = $kendaraan->stops()->with('pesanan.items', 'toko')->get()
            ->first(fn ($s) => $s->toko->nama === $toko->nama);
        $itemBonus = $stop->pesanan->items->firstWhere('is_bonus', true);

        // Dicoret menjadi 0
        $this->pengirimanService->coretNota($stop, [$itemBonus->id => 0], gambarNotaUjiBonus(), $this->driver);

        $komponen = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class)
            ->set('mode', 'hari')
            ->set('tanggal', $tanggal->toDateString());

        expect($komponen->instance()->totalDusBonus)->toBe(0)
            ->and($komponen->instance()->ringkasanProduk)->toBeEmpty();
    });

    it('mengabaikan pesanan yang dibatalkan driver di lapangan', function () {
        $tanggal = CarbonImmutable::parse('2026-09-14');

        $toko = tokoUjiBonus('Toko Batal');
        $pesanan = $this->pesananService->buat(
            toko: $toko,
            items: [['produk_id' => $this->produkUtama->id, 'jumlah_dus' => 5]],
            pembuat: $this->sales,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 3]],
        );
        $this->pesananService->setujui($pesanan, $this->admin);

        $batch = $this->routingService->generate($this->admin, tanggalKeberangkatan: $tanggal);
        $this->routingService->setujui($batch, $this->admin);
        $kendaraan = $batch->fresh()->kendaraans->first();

        $stop = $kendaraan->stops()->with('pesanan.items', 'toko')->get()
            ->first(fn ($s) => $s->toko->nama === $toko->nama);

        $this->pengirimanService->batalkanDiLapangan($stop, $this->driver, 'Toko Tutup');

        $komponen = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class)
            ->set('mode', 'hari')
            ->set('tanggal', $tanggal->toDateString());

        expect($komponen->instance()->totalDusBonus)->toBe(0);
    });
});

describe('pengelompokan varian & kpi', function () {
    it('mengelompokkan per varian produk dengan urutan dus terbanyak', function () {
        $tanggal = CarbonImmutable::today();

        // Varian Gula Pasir: 4 dus
        siapkanPesananDriverBonus($tanggal, $this->produkUtama, $this->produkBonus, 5, 4, 'Toko Rekap 1');

        // Varian Sirup Vanila: 7 dus
        siapkanPesananDriverBonus($tanggal, $this->produkUtama, $this->produkVarian, 5, 7, 'Toko Rekap 2');

        $komponen = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class)->set('mode', 'semua');

        $ringkasan = $komponen->instance()->ringkasanProduk;

        expect($ringkasan->count())->toBe(2)
            // Peringkat 1: Sirup Vanila (7 dus)
            ->and($ringkasan->first()['nama'])->toBe('Sirup Vanila')
            ->and($ringkasan->first()['total_dus'])->toBe(7)
            ->and($ringkasan->first()['porsi'])->toBe(63.6)
            // Peringkat 2: Gula Pasir (4 dus)
            ->and($ringkasan->last()['nama'])->toBe('Gula Pasir')
            ->and($ringkasan->last()['total_dus'])->toBe(4)
            ->and($ringkasan->last()['porsi'])->toBe(36.4);

        // Data Chart
        expect($komponen->instance()->dataChart['labels'])->toBe(['Sirup Vanila', 'Gula Pasir'])
            ->and($komponen->instance()->dataChart['data'])->toBe([7, 4]);
    });
});

describe('filter riwayat dan tanggal', function () {
    it('dapat menyaring riwayat berdasarkan kata kunci, produk, kategori, dan tipe bonus', function () {
        $tanggal = CarbonImmutable::today();

        // 1. Pesanan Driver dengan Gula Pasir (Bonus Manual)
        siapkanPesananDriverBonus($tanggal, $this->produkUtama, $this->produkBonus, 5, 3, 'Toko Driver Unik');

        // 2. Pesanan POS dengan Sirup Vanila
        $tokoPos = tokoUjiBonus('Toko POS Unik');
        $this->pesananService->buatPos(
            toko: $tokoPos,
            items: [['produk_id' => $this->produkUtama->id, 'jumlah_dus' => 2]],
            penjual: $this->admin,
            nominalCash: 50_000,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $this->produkVarian->id, 'jumlah_dus' => 2]],
        );

        $komponen = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class)->set('mode', 'semua');

        expect($komponen->instance()->riwayat->total())->toBe(2);

        // Filter cari nama toko
        $komponen->set('riwayatCari', 'Driver Unik');
        expect($komponen->instance()->riwayat->total())->toBe(1);

        // Reset cari, filter produk Gula Pasir
        $komponen->set('riwayatCari', '');
        $komponen->set('riwayatProduk', (string) $this->produkBonus->id);
        expect($komponen->instance()->riwayat->total())->toBe(1);

        // Filter kategori POS
        $komponen->set('riwayatProduk', '');
        $komponen->set('riwayatKategori', 'pos');
        expect($komponen->instance()->riwayat->total())->toBe(1);

        // Bersihkan filter
        $komponen->call('bersihkanFilterRiwayat');
        expect($komponen->instance()->riwayat->total())->toBe(2)
            ->and($komponen->get('riwayatCari'))->toBe('')
            ->and($komponen->get('riwayatProduk'))->toBe('')
            ->and($komponen->get('riwayatKategori'))->toBe('')
            ->and($komponen->get('riwayatTipeBonus'))->toBe('');
    });

    it('mendukung mode tanggal harian, bulanan, rentang, dan semua', function () {
        $tgl1 = CarbonImmutable::parse('2026-08-15');
        $tgl2 = CarbonImmutable::parse('2026-09-05');

        siapkanPesananDriverBonus($tgl1, $this->produkUtama, $this->produkBonus, 5, 3, 'Toko Agustus');
        siapkanPesananDriverBonus($tgl2, $this->produkUtama, $this->produkBonus, 5, 4, 'Toko September');

        $komponen = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class);

        // Mode Hari (Agustus 15)
        $komponen->set('mode', 'hari')->set('tanggal', '2026-08-15');
        expect($komponen->instance()->totalDusBonus)->toBe(3);

        // Mode Bulan (September 2026)
        $komponen->set('mode', 'bulan')->set('bulan', '2026-09');
        expect($komponen->instance()->totalDusBonus)->toBe(4);

        // Mode Rentang (Agustus sampai September)
        $komponen->set('mode', 'rentang')
            ->set('dariTanggal', '2026-08-01')
            ->set('sampaiTanggal', '2026-09-30');
        expect($komponen->instance()->totalDusBonus)->toBe(7);

        // Mode Semua
        $komponen->set('mode', 'semua');
        expect($komponen->instance()->totalDusBonus)->toBe(7);
    });
});

describe('ekspor excel', function () {
    it('mengunduh berkas excel dengan streaming response yang valid', function () {
        $tanggal = CarbonImmutable::today();
        siapkanPesananDriverBonus($tanggal, $this->produkUtama, $this->produkBonus, 5, 2, 'Toko Ekspor Excel');

        $respons = Livewire::actingAs($this->admin)->test(DusBonusTerkirim::class)
            ->set('mode', 'semua')
            ->call('unduhExcel');

        $respons->assertFileDownloaded();
    });
});
