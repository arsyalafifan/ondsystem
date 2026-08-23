<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Livewire\Pembayaran\BelumLunas;
use App\Livewire\Pembayaran\Pelunasan;
use App\Livewire\Pembayaran\Pendapatan;
use App\Models\Kendaraan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PelunasanService;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use App\Support\Bahasa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->produk = Produk::create([
        'kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100_000, 'harga' => 50_000,
    ]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
    $this->pengirimanService = app(PengirimanService::class);
    $this->pelunasanService = app(PelunasanService::class);
});

/**
 * Membuat sejumlah toko berkoordinat beserta pesanan yang sudah berstatus
 * PROCESS, yaitu keadaan tepat sebelum admin menekan Generate Routing.
 *
 * @return array<int, Pesanan>
 */
function siapkanPesananPembayaran(int $jumlah, int $dusPerToko = 20, string $kodeWilayah = 'W1'): array
{
    $wilayah = Wilayah::firstOrCreate(['kode' => $kodeWilayah], ['nama' => "Wilayah {$kodeWilayah}"]);

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

/** Membentuk satu kendaraan berisi $jumlah toko, semuanya sampai berstatus SELESAI. */
function kendaraanSelesai(int $jumlah, int $dusPerToko = 20): Kendaraan
{
    Storage::fake('public');

    siapkanPesananPembayaran($jumlah, $dusPerToko);

    $batch = test()->routingService->generate(test()->admin);
    test()->routingService->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();

    foreach ($kendaraan->stops as $stop) {
        $path = UploadedFile::fake()->image("nota-{$stop->id}.jpg")->store('nota', 'public');
        test()->pesananService->selesaikanPengiriman($stop, $path, test()->driver);
    }

    return $kendaraan->fresh();
}

/** Melunasi satu pesanan penuh sebagai cash, jalan pintas dipakai oleh tes lain yang tidak menguji split-nya sendiri. */
function lunasiCashPenuh(Pesanan $pesanan, User $admin): void
{
    test()->pelunasanService->tandaiLunas($pesanan->fresh(), $admin, (float) $pesanan->fresh()->tagihan, 0);
}

it('menolak menandai lunas atau belum lunas selain pesanan SELESAI', function () {
    $pesanan = $this->pesananService->buat(
        Toko::create([
            'kode' => 'TK-1', 'nama' => 'Toko 1', 'wilayah_id' => Wilayah::create(['kode' => 'W1', 'nama' => 'W1'])->id,
            'alamat' => 'Jl. Uji',
        ]),
        [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]],
        $this->sales,
    );

    expect($pesanan->status)->toBe(StatusPesanan::Order);

    expect(fn () => $this->pelunasanService->tandaiLunas($pesanan, $this->admin, 500_000, 0))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $this->pelunasanService->tandaiBelumLunas($pesanan, $this->admin))
        ->toThrow(RuntimeException::class);

    $this->pesananService->setujui($pesanan, $this->admin);
    expect(fn () => $this->pelunasanService->tandaiLunas($pesanan->fresh(), $this->admin, 500_000, 0))
        ->toThrow(RuntimeException::class);
});

describe('rincian sumber pembayaran', function () {
    it('menerima seluruhnya cash', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        $this->pelunasanService->tandaiLunas($pesanan, $this->admin, $tagihan, 0);

        $segar = $pesanan->fresh();
        expect($segar->status_bayar)->toBe(StatusBayar::Lunas)
            ->and((float) $segar->nominal_cash)->toBe($tagihan)
            ->and((float) $segar->nominal_transfer)->toBe(0.0);
    });

    it('menerima seluruhnya transfer', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        $this->pelunasanService->tandaiLunas($pesanan, $this->admin, 0, $tagihan);

        $segar = $pesanan->fresh();
        expect((float) $segar->nominal_cash)->toBe(0.0)
            ->and((float) $segar->nominal_transfer)->toBe($tagihan);
    });

    it('menerima campuran cash dan transfer asalkan jumlahnya pas', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        $this->pelunasanService->tandaiLunas($pesanan, $this->admin, $tagihan - 50_000, 50_000);

        $segar = $pesanan->fresh();
        expect((float) $segar->nominal_cash)->toBe($tagihan - 50_000)
            ->and((float) $segar->nominal_transfer)->toBe(50_000.0);
    });

    it('menolak jumlah yang kurang dari tagihan', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        expect(fn () => $this->pelunasanService->tandaiLunas($pesanan, $this->admin, $tagihan - 50_000, 0))
            ->toThrow(RuntimeException::class);

        expect($pesanan->fresh()->status_bayar)->toBe(StatusBayar::Pending);
    });

    it('menolak jumlah yang melebihi tagihan', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        expect(fn () => $this->pelunasanService->tandaiLunas($pesanan, $this->admin, $tagihan + 10_000, 0))
            ->toThrow(RuntimeException::class);
    });

    it('menolak nominal negatif', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        expect(fn () => $this->pelunasanService->tandaiLunas($pesanan, $this->admin, $tagihan + 10_000, -10_000))
            ->toThrow(RuntimeException::class);
    });

    it('mengosongkan rincian sumber saat ditandai belum lunas lagi', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();

        lunasiCashPenuh($pesanan, $this->admin);
        $this->pelunasanService->tandaiBelumLunas($pesanan->fresh(), $this->admin);

        $segar = $pesanan->fresh();
        expect($segar->status_bayar)->toBe(StatusBayar::BelumLunas)
            ->and($segar->nominal_cash)->toBeNull()
            ->and($segar->nominal_transfer)->toBeNull();
    });
});

it('pendapatan mengikuti tanggal pelunasan, bukan tanggal pengiriman', function () {
    $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
    $pesanan = $kendaraan->stops->first()->pesanan()->first();

    $this->pelunasanService->tandaiBelumLunas($pesanan, $this->admin);

    $tanggalAsli = $pesanan->fresh()->tanggal->toDateString();

    $this->travelTo(now()->addWeek());

    lunasiCashPenuh($pesanan, $this->admin);

    $segar = $pesanan->fresh();

    expect($segar->status_bayar)->toBe(StatusBayar::Lunas)
        ->and($segar->tanggal_lunas->toDateString())->toBe(today()->toDateString())
        ->and($segar->tanggal_lunas->toDateString())->not->toBe($tanggalAsli);
});

it('tagihan pesanan kurang kirim memakai jumlah yang benar-benar terkirim', function () {
    Storage::fake('public');

    siapkanPesananPembayaran(1, dusPerToko: 20);

    $batch = $this->routingService->generate($this->admin);
    $this->routingService->setujui($batch, $this->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $stop = $kendaraan->stops->first();
    $item = $stop->pesanan()->first()->items()->first();

    $path = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');
    $this->pengirimanService->coretNota($stop, [$item->id => 15], $path, $this->driver);

    $pesanan = $stop->pesanan()->with('items')->first();

    expect($pesanan->kurang_kirim)->toBeTrue()
        ->and($pesanan->tagihan)->toBe(15 * 50_000.0)
        ->and($pesanan->tagihan)->toBeLessThan((float) $pesanan->total_nilai);
});

it('tagihan pesanan normal sama persis dengan total_nilai', function () {
    $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
    $pesanan = $kendaraan->stops->first()->pesanan()->with('items')->first();

    expect($pesanan->kurang_kirim)->toBeFalse()
        ->and($pesanan->tagihan)->toBe((float) $pesanan->total_nilai);
});

it('menampilkan ketiga halaman pembayaran untuk admin', function () {
    kendaraanSelesai(6, dusPerToko: 10);

    $this->actingAs($this->admin)->get(route('pembayaran.pelunasan'))->assertOk();
    $this->actingAs($this->admin)->get(route('pembayaran.belum-lunas'))->assertOk();
    $this->actingAs($this->admin)->get(route('pembayaran.pendapatan'))->assertOk();
});

it('menolak sales dan driver mengakses menu pembayaran', function () {
    $this->actingAs($this->sales)->get(route('pembayaran.pelunasan'))->assertForbidden();
    $this->actingAs($this->driver)->get(route('pembayaran.pelunasan'))->assertForbidden();
});

describe('layar Pelunasan', function () {
    it('mengunci tombol Proses selama cash+transfer belum pas dengan tagihan', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        Livewire::actingAs($this->admin)
            ->test(Pelunasan::class)
            ->call('konfirmasiLunas', $pesanan->id)
            ->assertSet('selisihNominal', $tagihan)
            ->set('nominalCash', $tagihan - 10_000)
            ->assertSet('selisihNominal', 10_000.0)
            ->set('nominalTransfer', 10_000)
            ->assertSet('selisihNominal', 0.0);
    });

    it('menyimpan pelunasan dengan rincian cash dan transfer lewat layar sungguhan', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        Livewire::actingAs($this->admin)
            ->test(Pelunasan::class)
            ->call('konfirmasiLunas', $pesanan->id)
            ->set('nominalCash', $tagihan - 20_000)
            ->set('nominalTransfer', 20_000)
            ->call('proses')
            ->assertHasNoErrors();

        $segar = $pesanan->fresh();
        expect($segar->status_bayar)->toBe(StatusBayar::Lunas)
            ->and((float) $segar->nominal_cash)->toBe($tagihan - 20_000)
            ->and((float) $segar->nominal_transfer)->toBe(20_000.0);
    });

    it('menolak proses lewat layar kalau jumlahnya belum pas, tanpa mengubah status', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        Livewire::actingAs($this->admin)
            ->test(Pelunasan::class)
            ->call('konfirmasiLunas', $pesanan->id)
            ->set('nominalCash', $tagihan - 10_000)
            ->call('proses')
            ->assertDispatched('notifikasi');

        expect($pesanan->fresh()->status_bayar)->toBe(StatusBayar::Pending);
    });

    it('mengosongkan isian setiap kali modal lunas dibuka ulang', function () {
        $kendaraan = kendaraanSelesai(2, dusPerToko: 10);
        $pesanan1 = $kendaraan->stops[0]->pesanan()->first();
        $pesanan2 = $kendaraan->stops[1]->pesanan()->first();

        Livewire::actingAs($this->admin)
            ->test(Pelunasan::class)
            ->call('konfirmasiLunas', $pesanan1->id)
            ->set('nominalCash', 12_345)
            ->call('konfirmasiLunas', $pesanan2->id)
            ->assertSet('nominalCash', '')
            ->assertSet('nominalTransfer', '');
    });

    it('superadmin juga bisa memproses pelunasan, tidak ditolak oleh guard isAdmin', function () {
        $superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $tagihan = (float) $pesanan->tagihan;

        Livewire::actingAs($superadmin)
            ->test(Pelunasan::class)
            ->call('konfirmasiLunas', $pesanan->id)
            ->set('nominalCash', $tagihan)
            ->call('proses')
            ->assertHasNoErrors();

        expect($pesanan->fresh()->status_bayar)->toBe(StatusBayar::Lunas);
    });
});

describe('layar Belum Lunas', function () {
    it('menyimpan pelunasan dengan rincian cash dan transfer', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $this->pelunasanService->tandaiBelumLunas($pesanan, $this->admin);
        $tagihan = (float) $pesanan->fresh()->tagihan;

        Livewire::actingAs($this->admin)
            ->test(BelumLunas::class)
            ->call('konfirmasi', $pesanan->id)
            ->set('nominalCash', $tagihan)
            ->call('tandaiLunas')
            ->assertHasNoErrors();

        $segar = $pesanan->fresh();
        expect($segar->status_bayar)->toBe(StatusBayar::Lunas)
            ->and((float) $segar->nominal_cash)->toBe($tagihan);
    });

    it('menolak tandaiLunas kalau jumlahnya belum pas', function () {
        $kendaraan = kendaraanSelesai(1, dusPerToko: 10);
        $pesanan = $kendaraan->stops->first()->pesanan()->first();
        $this->pelunasanService->tandaiBelumLunas($pesanan, $this->admin);

        Livewire::actingAs($this->admin)
            ->test(BelumLunas::class)
            ->call('konfirmasi', $pesanan->id)
            ->set('nominalCash', 1_000)
            ->call('tandaiLunas')
            ->assertDispatched('notifikasi');

        expect($pesanan->fresh()->status_bayar)->toBe(StatusBayar::BelumLunas);
    });
});

describe('rekap cash/transfer di layar Pendapatan', function () {
    it('menjumlahkan pendapatan menurut sumbernya', function () {
        $kendaraan = kendaraanSelesai(2, dusPerToko: 10);
        $pesanan1 = $kendaraan->stops[0]->pesanan()->first();
        $pesanan2 = $kendaraan->stops[1]->pesanan()->first();
        $tagihan1 = (float) $pesanan1->tagihan;
        $tagihan2 = (float) $pesanan2->tagihan;

        $this->pelunasanService->tandaiLunas($pesanan1, $this->admin, $tagihan1, 0);
        $this->pelunasanService->tandaiLunas($pesanan2, $this->admin, 0, $tagihan2);

        Livewire::actingAs($this->admin)
            ->test(Pendapatan::class)
            ->assertSet('totalCash', $tagihan1)
            ->assertSet('totalTransfer', $tagihan2)
            ->assertSee(Bahasa::rupiah($tagihan1))
            ->assertSee(Bahasa::rupiah($tagihan2));
    });
});
