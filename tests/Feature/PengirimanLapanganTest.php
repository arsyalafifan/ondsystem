<?php

use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Livewire\Driver\DaftarKunjungan;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Tiga tindakan driver di lapangan, dan satu pertanyaan yang mengikat
 * ketiganya: stok gudang hanya boleh berkurang sebanyak barang yang benar-benar
 * diterima toko. Dus yang tidak terkirim dan tidak diampaskan pulang bersama
 * mobilnya, jadi angka stoknya harus tetap utuh.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->air = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 50_000]);
    $this->teh = Produk::create(['kode' => 'P2', 'nama' => 'Teh Kotak', 'stok' => 1000, 'harga' => 40_000]);

    $this->pesananService = app(PesananService::class);
    $this->service = app(PengirimanService::class);
});

function buatTokoKirim(string $nama, ?string $asset = null): Toko
{
    static $nomor = 0;
    $nomor++;

    return Toko::create([
        'kode' => sprintf('TK-%04d', $nomor),
        'asset_id' => $asset ?? sprintf('IDNAH2025280%05d', $nomor),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Kirim No. '.$nomor,
        'latitude' => -6.20 + $nomor * 0.001,
        'longitude' => 106.82 + $nomor * 0.001,
        'sumber_koordinat' => 'manual',
    ]);
}

/**
 * Menyiapkan satu mobil berisi pesanan yang sudah disetujui dan siap jalan.
 *
 * @param  array<int, array<int, array{produk: Produk, dus: int}>>  $muatan  isi tiap toko
 */
function siapkanMobil(array $muatan): Kendaraan
{
    foreach ($muatan as $i => $isi) {
        $toko = buatTokoKirim('Toko '.($i + 1));

        $pesanan = test()->pesananService->buat(
            $toko,
            array_map(fn (array $b) => ['produk_id' => $b['produk']->id, 'jumlah_dus' => $b['dus']], $isi),
            test()->sales,
        );

        test()->pesananService->setujui($pesanan, test()->admin);
    }

    $batch = app(RoutingService::class)->generate(test()->admin);
    app(RoutingService::class)->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id]);

    return $kendaraan->fresh(['stops.pesanan.items']);
}

function stopUntuk(Kendaraan $kendaraan, string $namaToko): KendaraanStop
{
    return $kendaraan->stops()->with('pesanan.items', 'toko')->get()
        ->first(fn (KendaraanStop $s) => $s->toko->nama === $namaToko);
}

function gambarNota(): string
{
    $g = imagecreatetruecolor(200, 150);
    ob_start();
    imagejpeg($g, null, 70);
    $isi = (string) ob_get_clean();
    imagedestroy($g);

    return Storage::disk('public')->put('nota/uji.jpg', $isi) ? 'nota/uji.jpg' : 'nota/uji.jpg';
}

// =====================================================================
describe('membatalkan toko di lapangan', function () {
    it('menandai toko selesai tanpa menambah dus terkirim', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 20]],
            [['produk' => $this->air, 'dus' => 30]],
        ]);

        $stop = stopUntuk($kendaraan, 'Toko 1');

        $this->service->batalkanDiLapangan($stop, $this->driver, 'Toko tutup', 'Rolling door terkunci');

        $kendaraan = $kendaraan->fresh(['stops']);
        $stop->refresh();

        expect($stop->status)->toBe(StatusStop::Dibatalkan)
            ->and($stop->total_dus_terkirim)->toBe(0)
            // Tanggung jawab driver atas toko itu tuntas...
            ->and($kendaraan->total_selesai)->toBe(1)
            // ...tapi dusnya tidak ikut terhitung terkirim.
            ->and($kendaraan->dus_terkirim)->toBe(0);
    });

    it('mengembalikan kuncian stok tanpa memotong stok fisik', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 20]]]);

        $sebelum = $this->air->fresh();

        expect($sebelum->stok_reserved)->toBe(20);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        $sesudah = $this->air->fresh();

        // Barangnya masih di mobil: kunciannya dilepas, stoknya utuh.
        expect($sesudah->stok)->toBe($sebelum->stok)
            ->and($sesudah->stok_reserved)->toBe(0);
    });

    it('membatalkan pesanannya dengan alasan yang dipilih driver', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 20]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');

        $this->service->batalkanDiLapangan($stop, $this->driver, 'Toko tutup', 'Tetangga bilang pindah');

        $pesanan = $stop->pesanan()->first();

        expect($pesanan->status)->toBe(StatusPesanan::Cancel)
            ->and($pesanan->alasan_cancel)->toBe('Toko tutup')
            ->and($pesanan->dibatalkan_oleh)->toBe($this->driver->id);
    });

    it('menolak pembatalan tanpa alasan', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 20]]]);

        expect(fn () => $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, '  '))
            ->toThrow(RuntimeException::class);
    });

    it('menolak membatalkan kunjungan yang sudah tuntas', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 20]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');

        $this->service->batalkanDiLapangan($stop, $this->driver, 'Toko tutup');

        expect(fn () => $this->service->batalkanDiLapangan($stop->fresh(), $this->driver, 'Toko tutup'))
            ->toThrow(RuntimeException::class);
    });
});

// =====================================================================
describe('coret nota', function () {
    it('mencatat hanya yang diterima toko dan menandai kurang kirim', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        $this->service->coretNota($stop, [$item->id => 5], gambarNota(), $this->driver, 'Toko hanya mau 5');

        $stop->refresh();
        $pesanan = $stop->pesanan()->with('items')->first();

        expect($stop->status)->toBe(StatusStop::Selesai)
            ->and($stop->total_dus_terkirim)->toBe(5)
            ->and($stop->dus_tersisa)->toBe(5)
            ->and($pesanan->status)->toBe(StatusPesanan::Selesai)
            ->and($pesanan->kurang_kirim)->toBeTrue()
            ->and($pesanan->items->first()->jumlah_dus_terkirim)->toBe(5);
    });

    it('memotong stok hanya sebanyak yang diterima, dan melepas seluruh kuncian', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        $stokAwal = $this->air->fresh()->stok;

        $this->service->coretNota($stop, [$item->id => 5], gambarNota(), $this->driver);

        $air = $this->air->fresh();

        expect($air->stok)->toBe($stokAwal - 5)
            ->and($air->stok_reserved)->toBe(0);
    });

    it('menolak jumlah yang melebihi pesanan', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        expect(fn () => $this->service->coretNota($stop, [$item->id => 12], gambarNota(), $this->driver))
            ->toThrow(RuntimeException::class);
    });

    it('menolak coret yang tidak mengurangi apa pun', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        expect(fn () => $this->service->coretNota($stop, [$item->id => 10], gambarNota(), $this->driver))
            ->toThrow(RuntimeException::class);
    });

    it('menolak sisa di bawah batas minimal pesanan', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        // Toko yang hanya mau 3 dus sebenarnya menolak; yang tepat membatalkan.
        expect(fn () => $this->service->coretNota($stop, [$item->id => 3], gambarNota(), $this->driver))
            ->toThrow(RuntimeException::class);
    });
});

// =====================================================================
describe('kampas', function () {
    it('menghitung jatah per produk dari toko yang dibatalkan', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->teh, 'dus' => 5]],
            [['produk' => $this->air, 'dus' => 20]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 2'), $this->driver, 'Toko tutup');

        $jatah = $this->service->jatahKampas($kendaraan->fresh(['stops']))
            ->keyBy(fn (array $b) => $b['produk']->id);

        expect($jatah->get($this->air->id)['tersedia'])->toBe(5)
            ->and($jatah->get($this->teh->id)['tersedia'])->toBe(5)
            ->and($jatah->sum('tersedia'))->toBe(10);
    });

    it('menolak kampas melebihi sisa produk itu, meski total jatahnya cukup', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->teh, 'dus' => 5]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 2'), $this->driver, 'Toko tutup');

        $tujuan = buatTokoKirim('Toko Kampas');

        // Total jatah 10 dus, tapi air mineral hanya 5 yang ada di mobil.
        expect(fn () => $this->service->kampas(
            $kendaraan->fresh(['stops']), $tujuan, [$this->air->id => 10], gambarNota(), $this->driver,
        ))->toThrow(RuntimeException::class);
    });

    it('mencatat pesanan kampas yang langsung selesai dan menambah kunjungan', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->air, 'dus' => 20]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        $tujuan = buatTokoKirim('Toko Kampas');
        $stokAwal = $this->air->fresh()->stok;

        $pesanan = $this->service->kampas(
            $kendaraan->fresh(['stops']), $tujuan, [$this->air->id => 5], gambarNota(), $this->driver, 'Dijual di jalan',
        );

        $kendaraan = $kendaraan->fresh(['stops']);

        expect($pesanan->jenis)->toBe(JenisPesanan::Kampas)
            ->and($pesanan->status)->toBe(StatusPesanan::Selesai)
            ->and($pesanan->total_dus)->toBe(5)
            // Kunjungan baru ikut menambah jumlah toko pada rute mobil.
            ->and($kendaraan->stops)->toHaveCount(3)
            ->and($kendaraan->dus_terkirim)->toBe(5)
            // Stok fisik berkurang; kunciannya sudah dilepas saat pembatalan.
            ->and($this->air->fresh()->stok)->toBe($stokAwal - 5)
            ->and($this->air->fresh()->stok_reserved)->toBe(20);
    });

    it('mengurangi jatah setelah sebagian diampaskan', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        $tujuan = buatTokoKirim('Toko Kampas');

        $this->service->kampas($kendaraan->fresh(['stops']), $tujuan, [$this->air->id => 4], gambarNota(), $this->driver);

        $jatah = $this->service->jatahKampas($kendaraan->fresh(['stops']))
            ->keyBy(fn (array $b) => $b['produk']->id);

        expect($jatah->get($this->air->id)['tersedia'])->toBe(6);
    });

    it('boleh dikampaskan ke toko yang masih punya pesanan berjalan', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->air, 'dus' => 20]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        // Toko 2 masih punya pesanan DELIVERY yang belum dikirim.
        $tokoSibuk = stopUntuk($kendaraan, 'Toko 2')->toko;

        $pesanan = $this->service->kampas(
            $kendaraan->fresh(['stops']), $tokoSibuk, [$this->air->id => 5], gambarNota(), $this->driver,
        );

        expect($pesanan->toko_id)->toBe($tokoSibuk->id);
    });

    it('menerima sisa dari nota yang dicoret, tanpa batas minimal dus', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        $this->service->coretNota($stop, [$item->id => 5], gambarNota(), $this->driver);

        $tujuan = buatTokoKirim('Toko Kampas');

        // Hanya 2 dus — jauh di bawah batas minimal pesanan biasa.
        $pesanan = $this->service->kampas(
            $kendaraan->fresh(['stops']), $tujuan, [$this->air->id => 2], gambarNota(), $this->driver,
        );

        expect($pesanan->total_dus)->toBe(2);
    });

    it('menolak kampas kosong', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        expect(fn () => $this->service->kampas(
            $kendaraan->fresh(['stops']), buatTokoKirim('Toko Kampas'), [], gambarNota(), $this->driver,
        ))->toThrow(RuntimeException::class);
    });

    /**
     * Bug nyata: toko kampas ditambahkan ke urutan kunjungan, tapi garis
     * rute (geometry) kendaraan tidak pernah dihitung ulang -- di peta,
     * penanda toko kampas jadi terlihat lepas dari garis rute yang sudah
     * digambar sebelumnya, seolah tidak berhubungan sama sekali. Kampas
     * satu-satunya tindakan lapangan yang MENAMBAH toko baru ke urutan
     * kunjungan (batal dan coret nota tidak mengubah daftar sama sekali),
     * jadi ini satu-satunya yang butuh RoutingService::hitungUlang().
     */
    it('menghitung ulang garis rute dan ETA setelah toko kampas ditambahkan', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        $geometrySebelum = $kendaraan->fresh()->geometry;
        $tokoKampas = buatTokoKirim('Toko Kampas');

        $this->service->kampas(
            $kendaraan->fresh(['stops']), $tokoKampas, [$this->air->id => 10], gambarNota(), $this->driver,
        );

        $kendaraan = $kendaraan->fresh(['stops']);
        $stopKampas = $kendaraan->stops->firstWhere('jenis', 'kampas');

        expect($kendaraan->geometry)->not->toBeNull()
            ->and($kendaraan->geometry)->not->toBe($geometrySebelum)
            ->and($kendaraan->total_jarak_m)->toBeGreaterThan(0)
            // Sebelum diperbaiki, stop kampas dibuat tanpa jarak/ETA sama
            // sekali -- kolom-kolom ini tetap null selamanya.
            ->and($stopKampas->jarak_dari_sebelumnya_m)->not->toBeNull()
            ->and($stopKampas->eta)->not->toBeNull();
    });

    it('memperbarui total jarak dan dus pada batch routing setelah kampas', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $batch = $kendaraan->batch;
        $jarakBatchSebelum = $batch->total_jarak_m;

        $this->service->kampas(
            $kendaraan->fresh(['stops']), buatTokoKirim('Toko Kampas'), [$this->air->id => 10], gambarNota(), $this->driver,
        );

        expect($batch->fresh()->total_jarak_m)->not->toBe($jarakBatchSebelum);
    });
});

// =====================================================================
describe('progres berbasis dus', function () {
    it('memakai muatan berangkat sebagai penyebut, bukan target yang menyusut', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 30]],
            [['produk' => $this->air, 'dus' => 70]],
        ]);

        expect($kendaraan->target_dus)->toBe(100);

        // Toko 30 dus dibatalkan, toko 70 dus dikirim penuh.
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $this->pesananService->selesaikanPengiriman(
            stopUntuk($kendaraan, 'Toko 2'), gambarNota(), $this->driver,
        );

        $kendaraan = $kendaraan->fresh(['stops']);

        // Penyebutnya tetap 100, jadi sisa 30 yang belum diampaskan terlihat
        // sebagai kekurangan alih-alih tersembunyi.
        expect($kendaraan->dus_terkirim)->toBe(70)
            ->and($kendaraan->persen_selesai)->toBe(70)
            ->and($kendaraan->dus_tersisa)->toBe(30);
    });

    it('mencapai seratus persen setelah sisanya diampaskan', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 30]],
            [['produk' => $this->air, 'dus' => 70]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $this->pesananService->selesaikanPengiriman(stopUntuk($kendaraan, 'Toko 2'), gambarNota(), $this->driver);

        $this->service->kampas(
            $kendaraan->fresh(['stops']), buatTokoKirim('Toko Kampas'),
            [$this->air->id => 30], gambarNota(), $this->driver,
        );

        $kendaraan = $kendaraan->fresh(['stops']);

        expect($kendaraan->dus_terkirim)->toBe(100)
            ->and($kendaraan->persen_selesai)->toBe(100)
            ->and($kendaraan->dus_tersisa)->toBe(0);
    });

    it('tidak memotong stok untuk dus yang tidak terkirim ke mana pun', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 30]],
            [['produk' => $this->air, 'dus' => 70]],
        ]);

        $stokAwal = $this->air->fresh()->stok;

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $this->pesananService->selesaikanPengiriman(stopUntuk($kendaraan, 'Toko 2'), gambarNota(), $this->driver);

        $air = $this->air->fresh();

        // 30 dus pulang bersama mobil, jadi stoknya harus tetap utuh.
        expect($air->stok)->toBe($stokAwal - 70)
            ->and($air->stok_reserved)->toBe(0);
    });
});

// =====================================================================
it('menampilkan halaman driver dengan aksi batalkan dan upload/konfirmasi', function () {
    $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk()
        ->assertSee(__('pengiriman.aksi_batalkan'))
        ->assertSee(__('driver.upload_nota'));
});

it('menampilkan tombol kampas hanya setelah ada sisa muatan', function () {
    $kendaraan = siapkanMobil([
        [['produk' => $this->air, 'dus' => 10]],
        [['produk' => $this->air, 'dus' => 20]],
    ]);

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk()
        ->assertDontSee(__('pengiriman.aksi_kampas'));

    $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

    $this->actingAs($this->driver)
        ->get(route('driver.kunjungan', $kendaraan))
        ->assertOk()
        ->assertSee(__('pengiriman.aksi_kampas'));
});

// =====================================================================
describe('cegatan jatah di layar driver', function () {
    /**
     * Atribut `max` pada input angka hanya berlaku saat form disubmit, dan
     * tombol simpan kampas dipanggil lewat wire:click. Tanpa penjaga di
     * komponen, driver baru tahu isiannya kelebihan setelah memilih toko dan
     * mengunggah foto nota.
     */
    it('memotong isian yang melebihi jatah produknya dan memberi tahu driver', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->air, 'dus' => 20]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
            ->call('bukaKampas')
            ->set("jumlahKampas.{$this->air->id}", 10)
            ->assertSet("jumlahKampas.{$this->air->id}", 5)
            ->assertDispatched('notifikasi');
    });

    it('membiarkan isian yang pas dengan jatah', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->air, 'dus' => 20]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
            ->call('bukaKampas')
            ->set("jumlahKampas.{$this->air->id}", 5)
            ->assertSet("jumlahKampas.{$this->air->id}", 5)
            ->assertSet('kampasMelebihiJatah', false)
            ->assertNotDispatched('notifikasi');
    });

    it('menolak angka negatif', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->air, 'dus' => 20]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
            ->call('bukaKampas')
            ->set("jumlahKampas.{$this->air->id}", -3)
            ->assertSet("jumlahKampas.{$this->air->id}", 0);
    });

    it('membatasi tiap produk sendiri-sendiri, bukan hanya totalnya', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->teh, 'dus' => 5]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 2'), $this->driver, 'Toko tutup');

        // Total jatah 10 dus, tapi 8 dus air saja sudah melewati batasnya.
        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
            ->call('bukaKampas')
            ->set("jumlahKampas.{$this->air->id}", 8)
            ->assertSet("jumlahKampas.{$this->air->id}", 5);
    });

    it('menampilkan sisa jatah pada tiap baris produk', function () {
        $kendaraan = siapkanMobil([
            [['produk' => $this->air, 'dus' => 5]],
            [['produk' => $this->teh, 'dus' => 7]],
        ]);

        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 1'), $this->driver, 'Toko tutup');
        $this->service->batalkanDiLapangan(stopUntuk($kendaraan, 'Toko 2'), $this->driver, 'Toko tutup');

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan->fresh()])
            ->call('bukaKampas')
            ->assertSee(__('pengiriman.tersedia'))
            ->assertSeeInOrder([$this->air->nama, '5', $this->teh->nama, '7']);
    });
});

// =====================================================================
describe('koreksi item setelah pesanan terlanjur selesai', function () {
    /**
     * Kasus nyata: driver seharusnya coret nota (toko cuma ambil sebagian),
     * tapi malah mengunggah nota lewat jalur pengiriman penuh. Pesanan
     * langsung SELESAI dengan seluruh isinya dianggap terkirim, stoknya
     * sudah terlanjur keluar penuh, dan jumlahnya ikut masuk ke Pelunasan.
     * koreksiItemSetelahSelesai() adalah jalan perbaikannya belakangan.
     */
    it('mengembalikan stok dan menandai kurang_kirim, tanpa menyentuh stok_reserved', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');

        $stokAwal = $this->air->fresh()->stok;

        // Driver salah pencet: upload nota penuh padahal toko cuma ambil 7.
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $item = $stop->pesanan->items->first();
        expect($item->fresh()->terkirim)->toBe(10)
            ->and($this->air->fresh()->stok)->toBe($stokAwal - 10);

        $this->service->koreksiItemSetelahSelesai($item->fresh(), 7, $this->admin);

        $item->refresh();
        $pesanan = $stop->pesanan()->first();
        $air = $this->air->fresh();

        expect($item->jumlah_dus_terkirim)->toBe(7)
            ->and($item->sisa)->toBe(3)
            ->and($pesanan->status)->toBe(StatusPesanan::Selesai)
            ->and($pesanan->kurang_kirim)->toBeTrue()
            // 3 dus yang tidak jadi diambil toko kembali ke stok fisik.
            ->and($air->stok)->toBe($stokAwal - 7)
            // Kuncian sudah nol sejak pesanan ditutup, tidak ikut berubah.
            ->and($air->stok_reserved)->toBe(0);
    });

    it('mengurangi total_dus_terkirim pada stop, sehingga tagihan Pelunasan otomatis ikut terkoreksi', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $item = $stop->pesanan->items->first();
        $hargaSatuan = (float) $item->harga_satuan;

        $this->service->koreksiItemSetelahSelesai($item->fresh(), 7, $this->admin);

        $stop->refresh();
        $pesanan = $stop->pesanan()->with('items')->first();

        expect($stop->total_dus_terkirim)->toBe(7)
            // Pesanan::tagihan() memakai jumlah terkirim kalau kurang_kirim,
            // jadi menagih toko hanya sebesar 7 dus, bukan 10.
            ->and((float) $pesanan->tagihan)->toBe(round($hargaSatuan * 7, 2));
    });

    it('mencatat jejak mutasi stok bertipe penyesuaian', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $item = $stop->pesanan->items->first();
        $this->service->koreksiItemSetelahSelesai($item->fresh(), 7, $this->admin, 'Toko konfirmasi cuma ambil 7');

        $mutasi = StokMutasi::where('pesanan_id', $stop->pesanan_id)
            ->where('tipe', 'penyesuaian')->first();

        expect($mutasi)->not->toBeNull()
            ->and($mutasi->jumlah)->toBe(3)
            ->and($mutasi->keterangan)->toBe('Toko konfirmasi cuma ambil 7')
            ->and($mutasi->user_id)->toBe($this->admin->id);
    });

    it('menolak kalau pesanannya belum berstatus Selesai', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        expect(fn () => $this->service->koreksiItemSetelahSelesai($item, 7, $this->admin))
            ->toThrow(RuntimeException::class);
    });

    it('menolak jumlah yang melebihi pesanan semula', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $item = $stop->pesanan->items->first();

        expect(fn () => $this->service->koreksiItemSetelahSelesai($item->fresh(), 15, $this->admin))
            ->toThrow(RuntimeException::class);
    });

    it('menolak kalau tidak ada perubahan', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $item = $stop->pesanan->items->first();

        expect(fn () => $this->service->koreksiItemSetelahSelesai($item->fresh(), 10, $this->admin))
            ->toThrow(RuntimeException::class);
    });

    it('menolak jumlah negatif', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $item = $stop->pesanan->items->first();

        expect(fn () => $this->service->koreksiItemSetelahSelesai($item->fresh(), -1, $this->admin))
            ->toThrow(RuntimeException::class);
    });

    it('bisa dijalankan lewat perintah artisan pesanan:koreksi-item', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $pesanan = $stop->pesanan()->first();
        $stokAwal = $this->air->fresh()->stok;

        $this->artisan('pesanan:koreksi-item', [
            'kode_pesanan' => $pesanan->kode,
            'kode_produk' => $this->air->kode,
            'jumlah_diterima' => 7,
            '--admin' => $this->admin->email,
        ])->expectsConfirmation('Lanjutkan koreksi ini?', 'yes')
            ->assertExitCode(0);

        // $stokAwal diambil setelah pengiriman penuh (sudah dipotong 10),
        // jadi koreksi ke 7 mengembalikan selisihnya (3), bukan memotong lagi.
        expect($this->air->fresh()->stok)->toBe($stokAwal + 3)
            ->and($stop->pesanan()->first()->kurang_kirim)->toBeTrue();
    });

    it('perintah artisan tidak mengubah apa pun kalau konfirmasi ditolak', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $this->pesananService->selesaikanPengiriman($stop, gambarNota(), $this->driver);

        $pesanan = $stop->pesanan()->first();
        $stokAwal = $this->air->fresh()->stok;

        $this->artisan('pesanan:koreksi-item', [
            'kode_pesanan' => $pesanan->kode,
            'kode_produk' => $this->air->kode,
            'jumlah_diterima' => 7,
            '--admin' => $this->admin->email,
        ])->expectsConfirmation('Lanjutkan koreksi ini?', 'no')
            ->assertExitCode(0);

        expect($this->air->fresh()->stok)->toBe($stokAwal)
            ->and($stop->pesanan()->first()->kurang_kirim)->toBeFalse();
    });
});

// =====================================================================
describe('konfirmasi penerimaan lewat layar driver (upload nota terpadu)', function () {
    /**
     * Ini persis kasus yang mau dicegah: dulu tombol "Upload Nota" langsung
     * menuntaskan pesanan dengan asumsi semuanya diterima penuh, tanpa
     * konfirmasi apa pun — persis lubang yang membuat toko yang cuma ambil
     * sebagian tetap tercatat terjual penuh. Sekarang bukaKonfirmasi() +
     * simpanKonfirmasi() adalah SATU-SATUNYA jalan menuntaskan pengiriman,
     * dan selalu menampilkan checklist per produk lebih dulu.
     */
    it('mengisi checklist dengan jumlah pesanan penuh secara bawaan', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasi', $stop->id)
            ->assertSet("jumlahKonfirmasi.{$item->id}", 10);
    });

    it('menuntaskan sebagai pengiriman penuh kalau checklist dibiarkan apa adanya', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();
        $stokAwal = $this->air->fresh()->stok;

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasi', $stop->id)
            ->set("dicekKonfirmasi.{$item->id}", true)
            ->set('fotoNota', UploadedFile::fake()->image('nota.jpg'))
            ->call('simpanKonfirmasi')
            ->assertHasNoErrors()
            ->assertDispatched('notifikasi');

        $stop->refresh();
        $pesanan = $stop->pesanan()->first();

        expect($stop->status)->toBe(StatusStop::Selesai)
            ->and($stop->total_dus_terkirim)->toBe(10)
            ->and($pesanan->kurang_kirim)->toBeFalse()
            ->and((float) $pesanan->tagihan)->toBe((float) $pesanan->total_nilai)
            ->and($this->air->fresh()->stok)->toBe($stokAwal - 10);
    });

    it('menuntaskan sebagai kurang kirim begitu satu baris dikurangi, dan sisanya jadi jatah kampas', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();
        $stokAwal = $this->air->fresh()->stok;

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasi', $stop->id)
            ->set("jumlahKonfirmasi.{$item->id}", 7)
            ->set("dicekKonfirmasi.{$item->id}", true)
            ->set('fotoNota', UploadedFile::fake()->image('nota.jpg'))
            ->call('simpanKonfirmasi')
            ->assertHasNoErrors();

        $stop->refresh();
        $pesanan = $stop->pesanan()->with('items')->first();

        expect($stop->status)->toBe(StatusStop::Selesai)
            ->and($stop->total_dus_terkirim)->toBe(7)
            ->and($pesanan->kurang_kirim)->toBeTrue()
            ->and($pesanan->items->first()->sisa)->toBe(3)
            // Stok fisik cuma berkurang sebanyak yang benar-benar diambil.
            ->and($this->air->fresh()->stok)->toBe($stokAwal - 7)
            // 3 dus yang tidak diambil sekarang tersedia untuk dikampaskan.
            ->and(app(PengirimanService::class)->jatahKampas($kendaraan->fresh())->firstWhere('produk.id', $this->air->id)['tersedia'])->toBe(3);
    });

    it('menolak isian yang melebihi jumlah pesanan dan tidak mengunggah apa pun', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasi', $stop->id)
            ->set("jumlahKonfirmasi.{$item->id}", 15)
            ->set("dicekKonfirmasi.{$item->id}", true)
            ->set('fotoNota', UploadedFile::fake()->image('nota.jpg'))
            ->call('simpanKonfirmasi')
            ->assertDispatched('notifikasi');

        expect($stop->fresh()->status)->toBe(StatusStop::Pending)
            ->and(Storage::disk('public')->allFiles())->toBeEmpty();
    });

    it('mengarahkan ke pembatalan kalau seluruh isian di bawah batas minimal', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasi', $stop->id)
            ->set("jumlahKonfirmasi.{$item->id}", 2)
            ->set("dicekKonfirmasi.{$item->id}", true)
            ->set('fotoNota', UploadedFile::fake()->image('nota.jpg'))
            ->call('simpanKonfirmasi')
            ->assertDispatched('notifikasi');

        expect($stop->fresh()->status)->toBe(StatusStop::Pending);
    });

    it('menolak mengunggah tanpa foto nota', function () {
        $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
        $stop = stopUntuk($kendaraan, 'Toko 1');
        $item = $stop->pesanan->items->first();

        Livewire::actingAs($this->driver)
            ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasi', $stop->id)
            ->set("dicekKonfirmasi.{$item->id}", true)
            ->call('simpanKonfirmasi')
            ->assertHasErrors(['fotoNota']);

        expect($stop->fresh()->status)->toBe(StatusStop::Pending);
    });

    /**
     * Inti permintaannya: ceklis di sebelah kiri memaksa driver benar-benar
     * melihat tiap baris produk, bukan sekadar mengandalkan angka bawaan
     * yang sudah terisi penuh tanpa pernah dilihat.
     */
    describe('ceklis wajib sebelum bisa disimpan', function () {
        it('membuka modal dengan seluruh ceklis kosong', function () {
            $kendaraan = siapkanMobil([[['produk' => $this->air, 'dus' => 10]]]);
            $stop = stopUntuk($kendaraan, 'Toko 1');
            $item = $stop->pesanan->items->first();

            Livewire::actingAs($this->driver)
                ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
                ->call('bukaKonfirmasi', $stop->id)
                ->assertSet("dicekKonfirmasi.{$item->id}", false)
                ->assertSet('semuaTercekKonfirmasi', false);
        });

        it('menolak simpan kalau ada baris yang belum dicentang, meski foto sudah diisi', function () {
            $kendaraan = siapkanMobil([
                [['produk' => $this->air, 'dus' => 6], ['produk' => $this->teh, 'dus' => 4]],
            ]);
            $stop = stopUntuk($kendaraan, 'Toko 1');
            $itemAir = $stop->pesanan->items->firstWhere('produk_id', $this->air->id);

            Livewire::actingAs($this->driver)
                ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
                ->call('bukaKonfirmasi', $stop->id)
                // Cuma baris air yang dicentang, baris teh belum.
                ->set("dicekKonfirmasi.{$itemAir->id}", true)
                ->set('fotoNota', UploadedFile::fake()->image('nota.jpg'))
                ->call('simpanKonfirmasi')
                ->assertDispatched('notifikasi');

            expect($stop->fresh()->status)->toBe(StatusStop::Pending)
                ->and(Storage::disk('public')->allFiles())->toBeEmpty();
        });

        it('mengizinkan simpan begitu semua baris sudah dicentang', function () {
            $kendaraan = siapkanMobil([
                [['produk' => $this->air, 'dus' => 6], ['produk' => $this->teh, 'dus' => 4]],
            ]);
            $stop = stopUntuk($kendaraan, 'Toko 1');

            $component = Livewire::actingAs($this->driver)
                ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
                ->call('bukaKonfirmasi', $stop->id);

            foreach ($stop->pesanan->items as $item) {
                $component->set("dicekKonfirmasi.{$item->id}", true);
            }

            $component->assertSet('semuaTercekKonfirmasi', true)
                ->set('fotoNota', UploadedFile::fake()->image('nota.jpg'))
                ->call('simpanKonfirmasi')
                ->assertHasNoErrors();

            expect($stop->fresh()->status)->toBe(StatusStop::Selesai);
        });

        it('mengosongkan ceklis lagi setiap kali modal dibuka untuk toko baru', function () {
            $kendaraan = siapkanMobil([
                [['produk' => $this->air, 'dus' => 10]],
                [['produk' => $this->teh, 'dus' => 8]],
            ]);
            $stop1 = stopUntuk($kendaraan, 'Toko 1');
            $stop2 = stopUntuk($kendaraan, 'Toko 2');
            $item1 = $stop1->pesanan->items->first();
            $item2 = $stop2->pesanan->items->first();

            Livewire::actingAs($this->driver)
                ->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
                ->call('bukaKonfirmasi', $stop1->id)
                ->set("dicekKonfirmasi.{$item1->id}", true)
                ->call('tutupKonfirmasi')
                ->call('bukaKonfirmasi', $stop2->id)
                ->assertSet("dicekKonfirmasi.{$item2->id}", false);
        });
    });
});
