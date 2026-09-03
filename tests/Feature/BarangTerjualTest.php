<?php

use App\Enums\PeranPengguna;
use App\Livewire\Penjualan\BarangTerjual;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Padanan packing list, tapi kebalikannya: packing list menunjukkan dus
 * yang BERANGKAT, layar ini menunjukkan dus yang BENAR-BENAR terjual
 * (PesananItem::terkirim, bukan jumlah_dus) — dan cuma dari pesanan
 * berstatus SELESAI, dikelompokkan menurut tanggal keberangkatan kendaraan
 * (rute biasa/kampas) atau tanggal_lunas (POS), sama seperti Pendapatan
 * dan Insentif Sales.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 20_000]);

    $this->pesananService = app(PesananService::class);
    $this->pengirimanService = app(PengirimanService::class);
    $this->routingService = app(RoutingService::class);
});

function tokoBarangTerjual(string $nama): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-BT%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Terjual No. '.$n,
        'latitude' => -6.20 + $n * 0.001,
        'longitude' => 106.82 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);
}

function gambarNotaBarangTerjual(): string
{
    $g = imagecreatetruecolor(200, 150);
    ob_start();
    imagejpeg($g, null, 70);
    $isi = (string) ob_get_clean();
    imagedestroy($g);

    Storage::disk('public')->put('nota/uji-bt.jpg', $isi);

    return 'nota/uji-bt.jpg';
}

/**
 * Satu pesanan rute biasa, disetujui, dirutekan untuk berangkat pada
 * $tanggal, lalu diselesaikan driver TANPA coret (seluruh isi nota
 * diterima). Mengembalikan [Pesanan, KendaraanStop, Kendaraan].
 *
 * @return array{0: Pesanan, 1: KendaraanStop, 2: Kendaraan}
 */
function siapkanPesananTerkirim(CarbonImmutable $tanggal, Produk $produk, int $jumlahDus = 5, ?string $namaToko = null): array
{
    $toko = tokoBarangTerjual($namaToko ?? 'Toko Terjual');

    $pesanan = test()->pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => $jumlahDus]], test()->sales);
    test()->pesananService->setujui($pesanan, test()->admin);

    $batch = test()->routingService->generate(test()->admin, tanggalKeberangkatan: $tanggal);
    test()->routingService->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id]);

    $stop = $kendaraan->stops()->with('pesanan.items', 'toko')->get()
        ->first(fn ($s) => $s->toko->nama === $toko->nama);

    test()->pesananService->selesaikanPengiriman($stop, gambarNotaBarangTerjual(), test()->driver);

    return [$pesanan->fresh(), $stop->fresh(), $kendaraan->fresh()];
}

it('menolak sales dan driver, mengizinkan admin', function () {
    $this->actingAs($this->sales)->get(route('penjualan.barang-terjual'))->assertForbidden();
    $this->actingAs($this->driver)->get(route('penjualan.barang-terjual'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('penjualan.barang-terjual'))->assertOk();
});

describe('hitungan dus terjual', function () {
    it('menghitung dus yang benar-benar terkirim, bukan yang dipesan — nota yang dicoret ikut dikurangi', function () {
        $tanggal = CarbonImmutable::parse('2026-08-20');

        siapkanPesananTerkirim($tanggal, $this->produk, 10, 'Toko Coret');

        // Batalkan efek selesaikanPengiriman() di atas dengan memesan toko
        // BARU yang dicoret, supaya skenarionya bersih: 10 dus dipesan,
        // cuma 6 yang diterima toko.
        $toko = tokoBarangTerjual('Toko Coret Nyata');
        $pesananCoret = $this->pesananService->buat($toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales);
        $this->pesananService->setujui($pesananCoret, $this->admin);

        $batch = $this->routingService->generate($this->admin, tanggalKeberangkatan: $tanggal);
        $this->routingService->setujui($batch, $this->admin);
        $kendaraan = $batch->fresh()->kendaraans->first();

        $stopCoret = $kendaraan->stops()->with('pesanan.items', 'toko')->get()
            ->first(fn ($s) => $s->toko->nama === 'Toko Coret Nyata');
        $item = $stopCoret->pesanan->items->first();

        $this->pengirimanService->coretNota($stopCoret, [$item->id => 6], gambarNotaBarangTerjual(), $this->driver);

        $komponen = Livewire::actingAs($this->admin)->test(BarangTerjual::class)
            ->set('mode', 'hari')
            ->set('tanggal', $tanggal->toDateString());

        // 10 (Toko Terjual, penuh) + 6 (Toko Coret Nyata, dicoret dari 10) = 16.
        expect($komponen->instance()->totalDusTerjual)->toBe(16);
    });

    it('pesanan yang dibatalkan driver di lapangan tidak ikut terhitung terjual', function () {
        $tanggal = CarbonImmutable::parse('2026-08-21');

        siapkanPesananTerkirim($tanggal, $this->produk, 8, 'Toko Terjual Batal');

        $toko = tokoBarangTerjual('Toko Batal Lapangan');
        $pesananBatal = $this->pesananService->buat($toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 5]], $this->sales);
        $this->pesananService->setujui($pesananBatal, $this->admin);

        $batch = $this->routingService->generate($this->admin, tanggalKeberangkatan: $tanggal);
        $this->routingService->setujui($batch, $this->admin);
        $kendaraan = $batch->fresh()->kendaraans->first();

        $stopBatal = $kendaraan->stops()->with('pesanan.items', 'toko')->get()
            ->first(fn ($s) => $s->toko->nama === 'Toko Batal Lapangan');

        // Stop-nya TETAP ada (tidak dihapus), tanggal kendaraannya tetap
        // masuk rentang — satu-satunya yang mencegah 5 dus ini ikut
        // terhitung "terjual" adalah pemeriksaan status SELESAI secara
        // eksplisit, karena tanggalPendapatanAntara() sendiri tidak
        // menyaring status pesanan sama sekali untuk kategori driver.
        $this->pengirimanService->batalkanDiLapangan($stopBatal, $this->driver, 'Toko tutup');

        $komponen = Livewire::actingAs($this->admin)->test(BarangTerjual::class)
            ->set('mode', 'hari')
            ->set('tanggal', $tanggal->toDateString());

        expect($komponen->instance()->totalDusTerjual)->toBe(8);
    });

    it('mengelompokkan per produk, termasuk memisahkan jumlah dus bonus', function () {
        $tanggal = CarbonImmutable::today();
        $produkBonus = Produk::create(['kode' => 'P2', 'nama' => 'Sirup Bonus', 'stok' => 1000, 'harga' => 15_000]);

        $toko = tokoBarangTerjual('Toko POS Bonus');
        $this->pesananService->buatPos(
            toko: $toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 4]],
            penjual: $this->admin,
            nominalCash: 80_000,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $produkBonus->id, 'jumlah_dus' => 2]],
        );

        $komponen = Livewire::actingAs($this->admin)->test(BarangTerjual::class)->set('mode', 'semua');

        $ringkasan = $komponen->instance()->ringkasanProduk->keyBy('nama');

        expect($ringkasan['Air Mineral']['qty'])->toBe(4)
            ->and($ringkasan['Air Mineral']['qty_bonus'])->toBe(0)
            ->and($ringkasan['Sirup Bonus']['qty'])->toBe(2)
            ->and($ringkasan['Sirup Bonus']['qty_bonus'])->toBe(2)
            ->and($komponen->instance()->totalBonus)->toBe(2)
            ->and($komponen->instance()->totalDusTerjual)->toBe(6);
    });

    it('dus terjual kategori driver dan pos dihitung terpisah dalam satuan dus', function () {
        $tanggal = CarbonImmutable::today();

        siapkanPesananTerkirim($tanggal, $this->produk, 7, 'Toko Driver');

        $tokoPos = tokoBarangTerjual('Toko POS');
        $this->pesananService->buatPos(
            toko: $tokoPos,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 3]],
            penjual: $this->admin,
            nominalCash: 60_000,
            nominalTransfer: 0,
        );

        $komponen = Livewire::actingAs($this->admin)->test(BarangTerjual::class)->set('mode', 'semua');

        expect($komponen->instance()->totalPerKategori)->toBe(['driver' => 7, 'pos' => 3]);
    });

    it('mengelompokkan menurut tanggal keberangkatan kendaraan, bukan tanggal pesanan dibuat', function () {
        $keberangkatan = CarbonImmutable::parse('2026-08-20');

        [$pesanan] = siapkanPesananTerkirim($keberangkatan, $this->produk, 5);

        // Tanggal pesanan dibuat (created_at) sama sekali BUKAN tanggal
        // keberangkatan — mode "hari" harus mengikuti keberangkatan.
        expect($pesanan->tanggal_pendapatan->toDateString())->toBe($keberangkatan->toDateString());

        $adaDiTanggalBerangkat = Livewire::actingAs($this->admin)->test(BarangTerjual::class)
            ->set('mode', 'hari')
            ->set('tanggal', $keberangkatan->toDateString());

        expect($adaDiTanggalBerangkat->instance()->totalDusTerjual)->toBe(5);

        $tidakAdaDiTanggalDibuat = Livewire::actingAs($this->admin)->test(BarangTerjual::class)
            ->set('mode', 'hari')
            ->set('tanggal', $pesanan->created_at->toDateString());

        if ($pesanan->created_at->toDateString() !== $keberangkatan->toDateString()) {
            expect($tidakAdaDiTanggalDibuat->instance()->totalDusTerjual)->toBe(0);
        }
    });
});

describe('tabel riwayat', function () {
    it('bisa disaring lewat kata kunci, kategori, dan produk', function () {
        $tanggal = CarbonImmutable::today();
        $produkLain = Produk::create(['kode' => 'P3', 'nama' => 'Teh Kotak', 'stok' => 1000, 'harga' => 10_000]);

        siapkanPesananTerkirim($tanggal, $this->produk, 5, 'Toko Saring Driver');

        $tokoPos = tokoBarangTerjual('Toko Saring POS');
        $this->pesananService->buatPos(
            toko: $tokoPos,
            items: [['produk_id' => $produkLain->id, 'jumlah_dus' => 2]],
            penjual: $this->admin,
            nominalCash: 20_000,
            nominalTransfer: 0,
        );

        $komponen = Livewire::actingAs($this->admin)->test(BarangTerjual::class)->set('mode', 'semua');

        expect($komponen->instance()->riwayat->total())->toBe(2);

        $komponen->set('riwayatKategori', 'pos');
        expect($komponen->instance()->riwayat->total())->toBe(1);

        $komponen->set('riwayatKategori', '');
        $komponen->set('riwayatCari', 'Saring POS');
        expect($komponen->instance()->riwayat->total())->toBe(1);

        $komponen->set('riwayatCari', '');
        $komponen->set('riwayatProduk', (string) $this->produk->id);
        expect($komponen->instance()->riwayat->total())->toBe(1);

        $komponen->call('bersihkanFilterRiwayat');
        expect($komponen->instance()->riwayat->total())->toBe(2)
            ->and($komponen->get('riwayatKategori'))->toBe('')
            ->and($komponen->get('riwayatProduk'))->toBe('');
    });

    it('dipaging 15 baris per halaman supaya tidak perlu discroll panjang', function () {
        $tanggal = CarbonImmutable::today();

        for ($i = 0; $i < 17; $i++) {
            siapkanPesananTerkirim($tanggal, $this->produk, 5, 'Toko Paging '.$i);
        }

        $komponen = Livewire::actingAs($this->admin)->test(BarangTerjual::class)->set('mode', 'semua');

        expect($komponen->instance()->riwayat->total())->toBe(17)
            ->and($komponen->instance()->riwayat->count())->toBe(15)
            ->and($komponen->instance()->riwayat->hasPages())->toBeTrue();
    });
});

describe('bahasa', function () {
    it('menampilkan halaman tanpa kunci terjemahan yang bocor', function (string $kode) {
        $this->admin->update(['locale' => $kode]);

        $tanggal = CarbonImmutable::today();
        siapkanPesananTerkirim($tanggal, $this->produk, 5, 'Toko Bahasa');

        $respons = $this->actingAs($this->admin)->get(route('penjualan.barang-terjual'));

        $respons->assertOk();

        $teks = strip_tags($respons->getContent());
        preg_match_all('/\bpenjualan\.[a-z_]+\b/', $teks, $cocok);

        expect(array_unique($cocok[0]))->toBe([]);
    })->with(['id', 'en', 'zh_CN', 'zh_TW']);
});
