<?php

use App\Enums\JenisBuktiNoo;
use App\Enums\JenisRouting;
use App\Enums\PeranPengguna;
use App\Enums\StatusNoo;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Livewire\Driver\DaftarKunjungan;
use App\Livewire\Noo\RoutingFreezer;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Livewire\Routing\RiwayatRouting;
use App\Models\Freezer;
use App\Models\Noo;
use App\Models\PaketNoo;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\RoutingBatch;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Noo\BuktiNooService;
use App\Services\Noo\NooService;
use App\Services\RoutingService;
use App\Support\DepotContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Ujung alur NOO: freezer dirutekan, diantar, dipasang, lalu pesanan
 * perdananya terbentuk sendiri.
 *
 * Dua hal yang paling dijaga di sini. Pertama, rute freezer dan rute pesanan
 * tidak pernah saling melihat meski berbagi tabel yang sama. Kedua,
 * kegagalan membuat pesanan perdana (mis. stok gudang kurang) TIDAK boleh
 * membatalkan pemasangan yang fisiknya sudah terjadi di lapangan.
 */
beforeEach(function () {
    Storage::fake(BuktiNooService::DISK);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Es Krim Cokelat', 'stok' => 500, 'harga' => 40_000]);

    $this->paket = PaketNoo::create(['nama' => '15+2', 'dus_reguler' => 15, 'dus_bonus' => 2, 'urutan' => 1, 'aktif' => true]);
    $this->paket->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => 15, 'is_bonus' => false]);
    $this->paket->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => 2, 'is_bonus' => true]);
});

/** NOO yang sudah disetujui admin, siap dirutekan. */
function nooDisetujui(string $nik = '3201234567890123', string $telepon = '081234567890'): Noo
{
    $noo = Noo::create([
        'kode' => 'NOO-20260922-'.str_pad((string) (Noo::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
        'status' => StatusNoo::Order,
        'paket_noo_id' => test()->paket->id,
        'nama' => 'Toko Maju '.$nik,
        'alamat' => 'Jl. Merdeka No. 10',
        'wilayah_id' => test()->wilayah->id,
        'telepon' => $telepon,
        'nama_pemilik' => 'Budi Santoso',
        'nik_pemilik' => $nik,
        'latitude' => -6.2100000,
        'longitude' => 106.8300000,
        'sumber_koordinat' => 'manual',
        'diajukan_oleh' => test()->sales->id,
        'diajukan_at' => now(),
    ]);

    app(NooService::class)->setujui($noo, test()->admin);

    return $noo->fresh();
}

/**
 * IDN cuma boleh dipasang kalau sudah terdaftar di Master Freezer (depot
 * yang sedang aktif) — lihat PunyaPemilihFreezer::aturanIdn(). Idn dioper
 * apa adanya (belum dirapikan) supaya ikut menguji normalisasi komponen.
 */
function daftarkanFreezerNoo(string $idn): void
{
    $rapi = mb_strtoupper(preg_replace('/\s+/', '', $idn));

    if (! Freezer::where('idn', $rapi)->exists()) {
        Freezer::create(['depot_id' => DepotContext::currentOrFail()->id, 'idn' => $rapi, 'tipe' => 'SD-200']);
    }
}

/** Data URL kecil untuk mengisi foto bukti di tes. */
function gambarPasangUji(): string
{
    $gambar = imagecreatetruecolor(50, 50);
    ob_start();
    imagejpeg($gambar);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

/** Merutekan seluruh NOO yang siap, lalu menyetujui rutenya. */
function rutekanDanBerangkatkan(): Noo
{
    nooDisetujui();

    $service = app(RoutingService::class);
    $batch = $service->generateNoo(test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id]);

    $service->setujui($batch, test()->admin);

    catatBerangkatKendaraan($kendaraan->fresh(), test()->driver);

    return Noo::firstOrFail()->fresh();
}

it('menyusun rute freezer terpisah dari rute pesanan', function () {
    nooDisetujui();

    $batch = app(RoutingService::class)->generateNoo($this->admin);

    expect($batch->jenis)->toBe(JenisRouting::Noo)
        ->and($batch->total_dus)->toBe(1);

    $stop = $batch->fresh()->kendaraans->first()->stops->first();

    // Inti pemisahannya: stop freezer tidak punya pesanan sama sekali.
    expect($stop->jenis)->toBe('noo')
        ->and($stop->pesanan_id)->toBeNull()
        ->and($stop->noo_id)->toBe(Noo::firstOrFail()->id);
});

it('tidak menampilkan rute freezer di layar routing pesanan', function () {
    nooDisetujui();
    $batch = app(RoutingService::class)->generateNoo($this->admin);

    $terlihat = Livewire::actingAs($this->admin)->test(RiwayatRouting::class)
        ->instance()->batches->pluck('id')->all();

    expect($terlihat)->not->toContain($batch->id);
});

it('menaikkan NOO ke status Delivery begitu rutenya disetujui', function () {
    $noo = nooDisetujui();
    $batch = app(RoutingService::class)->generateNoo($this->admin);

    app(RoutingService::class)->setujui($batch, $this->admin);

    expect($noo->fresh()->status)->toBe(StatusNoo::Delivery);
});

it('tidak lagi menawarkan NOO yang sudah masuk rute', function () {
    nooDisetujui();
    app(RoutingService::class)->generateNoo($this->admin);

    expect(app(RoutingService::class)->nooSiapRouting())->toHaveCount(0);
});

it('mengaktifkan toko, mengisi IDN, dan membuat pesanan perdana saat driver selesai memasang', function () {
    $noo = rutekanDanBerangkatkan();
    $kendaraan = $noo->stop->kendaraan;

    daftarkanFreezerNoo('idn ah2025 280001');

    $komponen = Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->call('bukaKonfirmasiNoo', $noo->stop->id)
        ->set('assetIdNoo', 'idn ah2025 280001')
        ->set('freezerTipeNoo', '6 kaki');

    foreach (JenisBuktiNoo::wajibDriver() as $jenis) {
        $komponen = $komponen->call('terimaBuktiNoo', $jenis->value, gambarPasangUji());
    }

    $komponen->call('simpanKonfirmasiNoo')->assertHasNoErrors();

    $noo = $noo->fresh(['fotos']);
    $toko = $noo->toko;

    expect($noo->status)->toBe(StatusNoo::Selesai)
        ->and($noo->diselesaikan_oleh)->toBe($this->driver->id)
        ->and($toko->aktif)->toBeTrue()
        // Dirapikan sama seperti Master Toko supaya cocok dengan pindaian QR.
        ->and($toko->asset_id)->toBe('IDNAH2025280001')
        ->and($toko->freezer_tipe)->toBe('6 kaki')
        ->and($noo->stop->fresh()->status)->toBe(StatusStop::Selesai)
        // Seluruh bukti pemasangan tersimpan. (NOO di berkas tes ini dibuat
        // langsung, tanpa lewat layar sales, jadi foto sales-nya memang
        // tidak ada — itu diuji tersendiri di NooInputTest.)
        ->and($noo->fotos->pluck('jenis')->all())->toBe(JenisBuktiNoo::wajibDriver());

    $pesanan = Pesanan::firstOrFail();

    expect($noo->pesanan_id)->toBe($pesanan->id)
        ->and($pesanan->noo_id)->toBe($noo->id)
        ->and($pesanan->status)->toBe(StatusPesanan::Order)
        ->and($pesanan->total_dus)->toBe(17)
        // Atribusi penjualannya tetap milik sales yang menemukan toko ini.
        ->and($pesanan->sales_id)->toBe($this->sales->id)
        ->and($pesanan->items()->where('is_bonus', true)->value('jumlah_dus'))->toBe(2);
});

it('menolak menyelesaikan pemasangan sebelum seluruh fotonya lengkap', function () {
    $noo = rutekanDanBerangkatkan();
    $kendaraan = $noo->stop->kendaraan;

    daftarkanFreezerNoo('IDN-001');

    $komponen = Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->call('bukaKonfirmasiNoo', $noo->stop->id)
        ->set('assetIdNoo', 'IDN-001')
        ->call('terimaBuktiNoo', JenisBuktiNoo::QrCode->value, gambarPasangUji())
        ->call('simpanKonfirmasiNoo');

    expect($noo->fresh()->status)->toBe(StatusNoo::Delivery)
        ->and($noo->toko->fresh()->aktif)->toBeFalse();
});

it('menolak IDN yang sudah dipakai toko lain', function () {
    Toko::create([
        'kode' => 'TK-9000', 'nama' => 'Toko Lain', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Lain', 'asset_id' => 'IDNAH2025280001',
    ]);
    daftarkanFreezerNoo('IDNAH2025280001');

    $noo = rutekanDanBerangkatkan();
    $kendaraan = $noo->stop->kendaraan;

    $komponen = Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->call('bukaKonfirmasiNoo', $noo->stop->id)
        ->set('assetIdNoo', 'IDNAH2025280001');

    foreach (JenisBuktiNoo::wajibDriver() as $jenis) {
        $komponen = $komponen->call('terimaBuktiNoo', $jenis->value, gambarPasangUji());
    }

    $komponen->call('simpanKonfirmasiNoo')->assertHasErrors('assetIdNoo');

    expect($noo->fresh()->status)->toBe(StatusNoo::Delivery);
});

it('tetap menuntaskan pemasangan meski stok gudang tidak cukup untuk pesanan perdana', function () {
    $noo = rutekanDanBerangkatkan();
    $kendaraan = $noo->stop->kendaraan;

    // Gudang kosong: pesanan perdana pasti gagal dibuat.
    $this->produk->update(['stok' => 0]);

    daftarkanFreezerNoo('IDNAH2025280002');

    $komponen = Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->call('bukaKonfirmasiNoo', $noo->stop->id)
        ->set('assetIdNoo', 'IDNAH2025280002');

    foreach (JenisBuktiNoo::wajibDriver() as $jenis) {
        $komponen = $komponen->call('terimaBuktiNoo', $jenis->value, gambarPasangUji());
    }

    $komponen->call('simpanKonfirmasiNoo');

    $noo = $noo->fresh();

    // Pengantarannya tuntas — driver tidak tersangkut di depan toko karena
    // urusan gudang — tapi sebabnya tercatat supaya admin bisa menyusulnya.
    expect($noo->status)->toBe(StatusNoo::Selesai)
        ->and($noo->toko->fresh()->aktif)->toBeTrue()
        ->and($noo->pesanan_id)->toBeNull()
        ->and($noo->catatan_pesanan_gagal)->not->toBeNull()
        ->and(Pesanan::count())->toBe(0);

    // Setelah stok diisi ulang, admin bisa membuatnya lagi.
    $this->produk->update(['stok' => 500]);
    app(NooService::class)->buatPesananPerdana($noo, $this->admin);

    expect($noo->fresh()->pesanan_id)->not->toBeNull()
        ->and(Pesanan::firstOrFail()->total_dus)->toBe(17);
});

it('tidak pernah mengikutkan pesanan perdana NOO ke persetujuan massal', function () {
    $noo = rutekanDanBerangkatkan();
    $kendaraan = $noo->stop->kendaraan;

    daftarkanFreezerNoo('IDNAH2025280003');

    $komponen = Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
        ->call('bukaKonfirmasiNoo', $noo->stop->id)
        ->set('assetIdNoo', 'IDNAH2025280003');

    foreach (JenisBuktiNoo::wajibDriver() as $jenis) {
        $komponen = $komponen->call('terimaBuktiNoo', $jenis->value, gambarPasangUji());
    }

    $komponen->call('simpanKonfirmasiNoo');

    $pesanan = Pesanan::firstOrFail();

    $layar = Livewire::actingAs($this->admin)->test(DaftarPesanan::class);

    expect($layar->instance()->idBisaDisetujui)->not->toContain($pesanan->id);

    // Bahkan kalau id-nya dipaksakan dari klien, ia tetap tidak tersentuh.
    $layar->set('terpilih', [$pesanan->id])->call('setujuiTerpilih');

    expect($pesanan->fresh()->status)->toBe(StatusPesanan::Order);

    // Jalur manualnya tetap terbuka.
    Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->call('setujui', $pesanan->id);

    expect($pesanan->fresh()->status)->toBe(StatusPesanan::Process);
});

it('menyusun rute freezer lewat layarnya sendiri', function () {
    $noo = nooDisetujui();

    $komponen = Livewire::actingAs($this->admin)->test(RoutingFreezer::class);

    expect($komponen->instance()->siapRouting)->toHaveCount(1);

    $komponen->set('terpilih', [$noo->id])->call('generate');

    expect($komponen->instance()->batches)->toHaveCount(1)
        ->and($komponen->instance()->batches->first()->jenis)->toBe(JenisRouting::Noo)
        ->and($komponen->instance()->siapRouting)->toHaveCount(0)
        // Seleksi dibersihkan setelah rute tersusun.
        ->and($komponen->instance()->terpilih)->toBe([]);
});

it('membiarkan calon yang tidak dicentang tetap menunggu untuk tanggal lain', function () {
    $satu = nooDisetujui('3201234567890111', '081200000111');
    $dua = nooDisetujui('3201234567890222', '081200000222');

    $komponen = Livewire::actingAs($this->admin)->test(RoutingFreezer::class);

    expect($komponen->instance()->siapRouting)->toHaveCount(2);

    // Hanya toko pertama yang dirutekan untuk hari ini...
    $komponen->set('tanggalKeberangkatan', '2026-09-23')
        ->set('terpilih', [$satu->id])
        ->call('generate');

    expect($komponen->instance()->batches)->toHaveCount(1)
        ->and($komponen->instance()->siapRouting->pluck('id')->all())->toBe([$dua->id]);

    // ...lalu toko kedua dirutekan terpisah untuk tanggal yang berbeda,
    // dan batch pertama TETAP terlihat di layar yang sama.
    $komponen->set('tanggalKeberangkatan', '2026-09-24')
        ->set('terpilih', [$dua->id])
        ->call('generate');

    $batches = $komponen->instance()->batches;

    expect($batches)->toHaveCount(2)
        ->and($komponen->instance()->siapRouting)->toHaveCount(0)
        ->and($batches->pluck('tanggal')->map->toDateString()->sort()->values()->all())
        ->toBe(['2026-09-23', '2026-09-24']);
});

it('menolak menyusun rute tanpa memilih calon', function () {
    nooDisetujui();

    Livewire::actingAs($this->admin)->test(RoutingFreezer::class)
        ->call('generate');

    expect(RoutingBatch::noo()->count())->toBe(0);
});
