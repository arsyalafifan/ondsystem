<?php

use App\Enums\PeranPengguna;
use App\Models\Produk;
use App\Models\RoutingBatch;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
});

function buatTokoPL(string $nama): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-PL%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Packing List No. '.$n,
        'latitude' => -6.20 + $n * 0.001,
        'longitude' => 106.82 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);
}

/**
 * Menyiapkan satu batch routing yang sudah disetujui.
 *
 * @param  array<int, array<int, array{produk_id: int, jumlah_dus: int}>>  $muatan  isi tiap toko
 */
function siapkanBatchDisetujuiPL(array $muatan, ?CarbonImmutable $tanggal = null): RoutingBatch
{
    foreach ($muatan as $i => $items) {
        $toko = buatTokoPL('Toko PL '.($i + 1));
        $pesanan = test()->pesananService->buat($toko, $items, test()->sales);
        test()->pesananService->setujui($pesanan, test()->admin);
    }

    $batch = test()->routingService->generate(test()->admin, tanggalKeberangkatan: $tanggal);
    test()->routingService->setujui($batch, test()->admin);

    return $batch->fresh();
}

it('menampilkan packing list untuk kendaraan yang routingnya sudah disetujui', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $this->actingAs($this->admin)->get(route('routing.packing-list', $kendaraan))->assertOk();
});

it('menolak packing list selama routingnya masih draft', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $toko = buatTokoPL('Toko Draft');
    $pesanan = $this->pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $this->sales);
    $this->pesananService->setujui($pesanan, $this->admin);

    $batch = $this->routingService->generate($this->admin);
    $kendaraan = $batch->kendaraans->first();

    $this->actingAs($this->admin)->get(route('routing.packing-list', $kendaraan))->assertForbidden();
});

it('menolak sales dan driver mengakses packing list', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $this->actingAs($this->sales)->get(route('routing.packing-list', $kendaraan))->assertForbidden();
    $this->actingAs($this->driver)->get(route('routing.packing-list', $kendaraan))->assertForbidden();
});

it('menampilkan nama mobil, jumlah faktur, jumlah dus, dan tanggal keberangkatan', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $tanggal = today()->addDays(3);

    $batch = siapkanBatchDisetujuiPL([
        [['produk_id' => $produk->id, 'jumlah_dus' => 10]],
        [['produk_id' => $produk->id, 'jumlah_dus' => 15]],
    ], $tanggal);
    $kendaraan = $batch->kendaraans->first();

    // Tanggal keberangkatan datang dari tanggal yang dipilih admin saat
    // generate routing, bukan tanggal batch dibuat (created_at).
    expect($kendaraan->batch->tanggal->isSameDay($tanggal))->toBeTrue();

    $this->actingAs($this->admin)->get(route('routing.packing-list', $kendaraan))
        ->assertOk()
        ->assertSee($kendaraan->nama)
        ->assertSee((string) $kendaraan->stops_packing->count())
        ->assertSee((string) $kendaraan->total_dus)
        ->assertSee($tanggal->format('d/m/Y'));
});

it('menjumlahkan qty produk yang sama dari toko-toko berbeda dalam satu mobil', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Bersama', 'stok' => 1000, 'harga' => 10_000]);

    $batch = siapkanBatchDisetujuiPL([
        [['produk_id' => $produk->id, 'jumlah_dus' => 10]],
        [['produk_id' => $produk->id, 'jumlah_dus' => 15]],
    ]);
    $kendaraan = $batch->kendaraans->first()->load(['stops.pesanan.items.produk']);

    $ringkasan = $kendaraan->ringkasan_produk_packing;

    expect($ringkasan)->toHaveCount(1)
        ->and($ringkasan->first()['produk'])->toBe('Produk Bersama')
        ->and($ringkasan->first()['qty'])->toBe(25);
});

it('daftar toko menampilkan nama dari master toko dan dus per toko', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);

    $batch = siapkanBatchDisetujuiPL([
        [['produk_id' => $produk->id, 'jumlah_dus' => 10]],
        [['produk_id' => $produk->id, 'jumlah_dus' => 15]],
    ]);
    $kendaraan = $batch->kendaraans->first();

    $this->actingAs($this->admin)->get(route('routing.packing-list', $kendaraan))
        ->assertOk()
        ->assertSeeInOrder(['Toko PL 1', 'Toko PL 2']);
});

/**
 * Kampas terjadi di lapangan setelah mobil berangkat — bukan bagian dari
 * muatan yang sungguh dipacking di gudang. Toko yang dibatalkan TETAP
 * tampil (dusnya memang sudah dimuat, itulah gunanya kolom "Dus Pulang"
 * untuk rekonsiliasi), tapi toko tujuan kampas tidak boleh ikut terhitung
 * seolah-olah dimuat sejak awal.
 */
it('tidak menghitung toko tujuan kampas, tapi tetap menghitung toko yang dibatalkan di lapangan', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);

    $batch = siapkanBatchDisetujuiPL([
        [['produk_id' => $produk->id, 'jumlah_dus' => 5]],
        [['produk_id' => $produk->id, 'jumlah_dus' => 20]],
    ]);
    $kendaraan = $batch->kendaraans->first();
    $kendaraan->update(['driver_id' => $this->driver->id]);

    $stopDibatalkan = $kendaraan->stops()->whereHas('toko', fn ($q) => $q->where('nama', 'Toko PL 1'))->first();

    app(PengirimanService::class)->batalkanDiLapangan($stopDibatalkan, $this->driver, 'Toko tutup');

    $tokoKampas = buatTokoPL('Toko Kampas');
    $gambar = UploadedFile::fake()->image('nota.jpg')->store('nota', 'public');

    app(PengirimanService::class)->kampas(
        $kendaraan->fresh(['stops']), $tokoKampas, [$produk->id => 5], $gambar, $this->driver,
    );

    $kendaraan = $kendaraan->fresh(['stops.toko']);
    $namaToko = $kendaraan->stops_packing->map(fn ($s) => $s->toko->nama);

    expect($namaToko)->toContain('Toko PL 1')
        ->and($namaToko)->not->toContain('Toko Kampas');
});

it('selalu mencetak dengan ukuran kertas besar 24x28cm', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $this->actingAs($this->admin)->get(route('routing.packing-list', $kendaraan))
        ->assertOk()
        ->assertSee('size: 24cm 28cm;', false);
});

it('mengunduh packing list sebagai berkas PDF sungguhan', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $respons = $this->actingAs($this->admin)->get(route('routing.packing-list.pdf', $kendaraan));

    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toBe('application/pdf');
    expect(str_starts_with($respons->getContent(), '%PDF-'))->toBeTrue();
});

it('menolak unduh PDF selama routingnya masih draft', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $toko = buatTokoPL('Toko Draft PDF');
    $pesanan = $this->pesananService->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $this->sales);
    $this->pesananService->setujui($pesanan, $this->admin);

    $batch = $this->routingService->generate($this->admin);
    $kendaraan = $batch->kendaraans->first();

    $this->actingAs($this->admin)->get(route('routing.packing-list.pdf', $kendaraan))->assertForbidden();
});

it('mengunduh packing list sebagai perintah ESC/P mentah', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $respons = $this->actingAs($this->admin)->get(route('routing.packing-list.escp', $kendaraan));

    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toBe('application/octet-stream');
    expect($respons->headers->get('content-disposition'))->toContain('.prn');

    $isi = $respons->getContent();
    expect(str_starts_with($isi, "\x1B@"))->toBeTrue() // ESC @ : inisialisasi printer
        ->and($isi)->toContain($kendaraan->nama)
        ->and($isi)->toContain('Toko PL 1')
        ->and($isi)->toContain('Produk A')
        ->and(str_ends_with($isi, "\x0C"))->toBeTrue(); // form feed di akhir
});

it('halaman cetak menyertakan link ondprint:// untuk OND Print Helper', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $this->actingAs($this->admin)->get(route('routing.packing-list', $kendaraan))
        ->assertOk()
        ->assertSee('ondprint://print?url=', false);
});

it('OND Print Helper bisa mengambil ESC/P lewat link bertanda tangan tanpa sesi login', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $url = URL::temporarySignedRoute(
        'routing.packing-list.escp.signed',
        now()->addMinutes(5),
        ['kendaraan' => $kendaraan->id],
    );

    // Sengaja TANPA actingAs — inilah inti pengujiannya.
    $respons = $this->get($url);

    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toBe('application/octet-stream');
    expect($respons->getContent())->toContain($kendaraan->nama);
});

it('menolak link ondprint yang tanda tangannya sudah tidak sah', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $url = URL::temporarySignedRoute(
        'routing.packing-list.escp.signed',
        now()->addMinutes(5),
        ['kendaraan' => $kendaraan->id],
    );

    $this->get($url.'&diubah=1')->assertForbidden();
});

it('menolak link ondprint yang sudah kedaluwarsa', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 10_000]);
    $batch = siapkanBatchDisetujuiPL([[['produk_id' => $produk->id, 'jumlah_dus' => 10]]]);
    $kendaraan = $batch->kendaraans->first();

    $url = URL::temporarySignedRoute(
        'routing.packing-list.escp.signed',
        now()->subMinute(),
        ['kendaraan' => $kendaraan->id],
    );

    $this->get($url)->assertForbidden();
});
