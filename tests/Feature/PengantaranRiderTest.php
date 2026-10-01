<?php

use App\Enums\JenisBuktiPengiriman;
use App\Enums\PeranPengguna;
use App\Enums\StatusPengantaranRider;
use App\Enums\StatusPesanan;
use App\Livewire\PengantaranRider\DaftarPengantaranRider;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Models\Depot;
use App\Models\PengantaranRider;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengantaranRider\PengantaranRiderService;
use App\Services\PesananService;
use App\Support\DepotContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Toko dekat gudang (dalam Depot::radius_rider_km) diantar rider — bukan
 * rute kendaraan — begitu admin memilihnya saat approve. Lihat
 * App\Services\PengantaranRider\PengantaranRiderService.
 *
 * Koordinat gudang uji (lihat database/factories/DepotFactory.php): tetap
 * di lat -6.2, lng 106.816666. 0.05° lintang ≈ 5.6km (dalam radius 13km
 * bawaan), 0.3° lintang ≈ 33km (di luar radius).
 */
beforeEach(function () {
    Storage::fake('public');

    // TestCase::setUp() membuat $this->depot lewat Depot::factory()->create()
    // SEBELUM kolom radius_rider_km ada di factory manapun — nilai bawaan
    // migrasi (13) memang tersimpan di DB, tapi instans PHP di memori belum
    // tentu tahu itu sampai di-refresh. update() di sini sekaligus menjamin
    // nilainya eksplisit DAN menyinkronkan instans $this->depot.
    $this->depot->update(['radius_rider_km' => 13]);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->rider = User::factory()->create(['role' => PeranPengguna::Rider]);
    $this->riderLain = User::factory()->create(['role' => PeranPengguna::Rider]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 10_000]);

    $this->pesananService = app(PesananService::class);
    $this->riderService = app(PengantaranRiderService::class);
});

function gambarPengantaranUji(): string
{
    $gambar = imagecreatetruecolor(60, 60);
    ob_start();
    imagejpeg($gambar);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

function lengkapiBuktiPengantaranUji($komponen)
{
    foreach (JenisBuktiPengiriman::wajibFoto() as $jenis) {
        $komponen = $komponen->call('terimaBuktiFoto', $jenis->value, gambarPengantaranUji());
    }

    return $komponen->call('terimaBuktiFoto', JenisBuktiPengiriman::FreezerDisusun->value, gambarPengantaranUji());
}

/** Toko dalam radius rider gudang (≈5.6km). */
function tokoDekatUji(string $kode = 'TK-DEKAT'): Toko
{
    return Toko::create([
        'kode' => $kode, 'nama' => 'Toko Dekat', 'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Dekat', 'latitude' => -6.25, 'longitude' => 106.816666, 'sumber_koordinat' => 'manual',
    ]);
}

/** Toko di luar radius rider gudang (≈33km). */
function tokoJauhUji(string $kode = 'TK-JAUH'): Toko
{
    return Toko::create([
        'kode' => $kode, 'nama' => 'Toko Jauh', 'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Jauh', 'latitude' => -6.5, 'longitude' => 106.816666, 'sumber_koordinat' => 'manual',
    ]);
}

/** Pesanan status Order siap approve, untuk $toko, 10 dus produk uji. */
function pesananUji(Toko $toko, int $dus = 10): Pesanan
{
    return test()->pesananService->buat($toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => $dus]], test()->sales);
}

describe('deteksi radius', function () {
    it('toko dekat gudang masuk radius rider', function () {
        $pesanan = pesananUji(tokoDekatUji());

        expect($this->riderService->dalamRadius($pesanan))->toBeTrue();
    });

    it('toko jauh dari gudang tidak masuk radius rider', function () {
        $pesanan = pesananUji(tokoJauhUji());

        expect($this->riderService->dalamRadius($pesanan))->toBeFalse();
    });

    it('toko tanpa koordinat tidak dianggap masuk radius', function () {
        $toko = Toko::create(['kode' => 'TK-NOKOORD', 'nama' => 'Toko Tanpa Koordinat', 'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. X']);
        $pesanan = pesananUji($toko);

        expect($this->riderService->dalamRadius($pesanan))->toBeFalse();
    });

    it('radius 0 di gudang mematikan deteksi rider sama sekali', function () {
        $this->depot->update(['radius_rider_km' => 0]);
        $pesanan = pesananUji(tokoDekatUji());

        expect($this->riderService->dalamRadius($pesanan))->toBeFalse();
    });
});

describe('konfirmasi rider/driver saat approve', function () {
    it('approve satu pesanan di luar radius tetap langsung Process seperti biasa', function () {
        $pesanan = pesananUji(tokoJauhUji());

        Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
            ->call('setujui', $pesanan->id)
            ->assertSet('modalRiderDriverTerbuka', false);

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Process);
    });

    it('approve satu pesanan dalam radius membuka modal, bukan langsung disetujui', function () {
        $pesanan = pesananUji(tokoDekatUji());

        Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
            ->call('setujui', $pesanan->id)
            ->assertSet('modalRiderDriverTerbuka', true);

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Order);
    });

    it('approve massal campuran: yang luar radius langsung jalan, yang dalam radius menunggu modal', function () {
        $dekat = pesananUji(tokoDekatUji());
        $jauh = pesananUji(tokoJauhUji('TK-JAUH2'));

        Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
            ->set('terpilih', [$dekat->id, $jauh->id])
            ->call('setujuiTerpilih')
            ->assertSet('modalRiderDriverTerbuka', true)
            ->assertSet('pesananRadiusIds', [$dekat->id]);

        expect($jauh->fresh()->status)->toBe(StatusPesanan::Process)
            ->and($dekat->fresh()->status)->toBe(StatusPesanan::Order);
    });

    it('memilih driver di modal menyetujui seperti biasa (Process)', function () {
        $pesanan = pesananUji(tokoDekatUji());

        Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
            ->call('setujui', $pesanan->id)
            ->set("konfirmasiRiderDriver.{$pesanan->id}", 'driver')
            ->call('submitKonfirmasiRiderDriver')
            ->assertSet('modalRiderDriverTerbuka', false);

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Process)
            ->and(PengantaranRider::where('pesanan_id', $pesanan->id)->exists())->toBeFalse();
    });

    it('memilih rider di modal langsung Delivery dan membuat baris pool Tersedia', function () {
        $pesanan = pesananUji(tokoDekatUji());

        Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
            ->call('setujui', $pesanan->id)
            ->set("konfirmasiRiderDriver.{$pesanan->id}", 'rider')
            ->call('submitKonfirmasiRiderDriver');

        $pengantaran = PengantaranRider::where('pesanan_id', $pesanan->id)->first();

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Delivery)
            ->and($pengantaran)->not->toBeNull()
            ->and($pengantaran->status)->toBe(StatusPengantaranRider::Tersedia)
            ->and($pengantaran->ditandai_oleh)->toBe($this->admin->id);
    });
});

describe('alihkan ke driver', function () {
    it('membatalkan penandaan rider dan mengembalikan ke Process selama belum diambil', function () {
        $pesanan = pesananUji(tokoDekatUji());
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);

        $this->riderService->alihkanKeDriver($pengantaran, $this->admin);

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Process)
            ->and(PengantaranRider::find($pengantaran->id))->toBeNull();
    });

    it('menolak mengalihkan yang sudah diambil rider', function () {
        $pesanan = pesananUji(tokoDekatUji());
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        expect(fn () => $this->riderService->alihkanKeDriver($pengantaran->fresh(), $this->admin))
            ->toThrow(RuntimeException::class);

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Delivery);
    });

    it('bisa dialihkan lewat layar Pesanan oleh admin', function () {
        $pesanan = pesananUji(tokoDekatUji());
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);

        Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
            ->call('alihkanKeDriver', $pengantaran->id)
            ->assertHasNoErrors();

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Process);
    });
});

describe('ambil & lepas', function () {
    it('rider bisa mengambil pesanan dari pool', function () {
        $pengantaran = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji()), $this->admin);

        $this->riderService->ambil($pengantaran, $this->rider);

        expect($pengantaran->fresh()->status)->toBe(StatusPengantaranRider::Diambil)
            ->and($pengantaran->fresh()->rider_id)->toBe($this->rider->id);
    });

    it('menolak mengambil kalau rider sudah punya pengantaran aktif lain', function () {
        $p1 = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji('TK-A')), $this->admin);
        $p2 = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji('TK-B')), $this->admin);

        $this->riderService->ambil($p1, $this->rider);

        expect(fn () => $this->riderService->ambil($p2, $this->rider))->toThrow(RuntimeException::class);
        expect($p2->fresh()->status)->toBe(StatusPengantaranRider::Tersedia);
    });

    it('rider lain tetap bisa mengambil pesanan berbeda secara bersamaan', function () {
        $p1 = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji('TK-A')), $this->admin);
        $p2 = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji('TK-B')), $this->admin);

        $this->riderService->ambil($p1, $this->rider);
        $this->riderService->ambil($p2, $this->riderLain);

        expect($p1->fresh()->status)->toBe(StatusPengantaranRider::Diambil)
            ->and($p2->fresh()->status)->toBe(StatusPengantaranRider::Diambil);
    });

    it('melepas mengembalikan ke pool dan membersihkan rider_id', function () {
        $pengantaran = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji()), $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        $this->riderService->lepas($pengantaran->fresh(), $this->rider);

        expect($pengantaran->fresh()->status)->toBe(StatusPengantaranRider::Tersedia)
            ->and($pengantaran->fresh()->rider_id)->toBeNull();
    });

    it('menolak melepas pengantaran milik rider lain', function () {
        $pengantaran = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji()), $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        expect(fn () => $this->riderService->lepas($pengantaran->fresh(), $this->riderLain))
            ->toThrow(RuntimeException::class);
    });

    it('setelah melepas, rider boleh mengambil pesanan lain', function () {
        $p1 = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji('TK-A')), $this->admin);
        $p2 = $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji('TK-B')), $this->admin);

        $this->riderService->ambil($p1, $this->rider);
        $this->riderService->lepas($p1->fresh(), $this->rider);
        $this->riderService->ambil($p2->fresh(), $this->rider);

        expect($p2->fresh()->status)->toBe(StatusPengantaranRider::Diambil);
    });
});

describe('penyelesaian', function () {
    it('selesai penuh memotong stok sesuai jumlah dipesan', function () {
        $pesanan = pesananUji(tokoDekatUji(), 10);
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        $item = $pesanan->items->first();
        $stokAwal = $this->produk->fresh()->stok;

        $this->riderService->selesaikan($pengantaran->fresh(), [$item->id => 10], 'nota/uji.jpg', $this->rider, null, []);

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Selesai)
            ->and($pengantaran->fresh()->status)->toBe(StatusPengantaranRider::Selesai)
            ->and($this->produk->fresh()->stok)->toBe($stokAwal - 10)
            ->and($this->produk->fresh()->stok_reserved)->toBe(0);
    });

    it('kekurangan diterima langsung melepas kunci stok (beda dari kampas driver)', function () {
        $pesanan = pesananUji(tokoDekatUji(), 10);
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        $item = $pesanan->items->first();
        $stokAwal = $this->produk->fresh()->stok;

        $this->riderService->selesaikan($pengantaran->fresh(), [$item->id => 7], 'nota/uji.jpg', $this->rider, 'Kurang 3 dus', []);

        expect($this->produk->fresh()->stok)->toBe($stokAwal - 7)
            // Kuncian seluruhnya lepas — 7 lewat keluarkanStok, 3 lewat
            // lepasKunciStok langsung, bukan dibiarkan tertahan.
            ->and($this->produk->fresh()->stok_reserved)->toBe(0)
            ->and($item->fresh()->jumlah_dus_terkirim)->toBe(7)
            ->and($pesanan->fresh()->kurang_kirim)->toBeTrue();
    });

    it('menolak selesai di bawah minimal dus per toko gudang', function () {
        $this->depot->update(['min_dus_per_toko' => 5]);
        $pesanan = pesananUji(tokoDekatUji(), 10);
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        $item = $pesanan->items->first();

        expect(fn () => $this->riderService->selesaikan($pengantaran->fresh(), [$item->id => 2], 'nota/uji.jpg', $this->rider, null, []))
            ->toThrow(RuntimeException::class);

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Delivery);
    });

    it('menolak diselesaikan rider yang bukan pemegangnya', function () {
        $pesanan = pesananUji(tokoDekatUji());
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        $item = $pesanan->items->first();

        expect(fn () => $this->riderService->selesaikan($pengantaran->fresh(), [$item->id => 10], 'nota/uji.jpg', $this->riderLain, null, []))
            ->toThrow(RuntimeException::class);
    });

    it('bisa diselesaikan lewat layar dengan foto lengkap dan tanda tangan toko', function () {
        $pesanan = pesananUji(tokoDekatUji(), 10);
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);
        $item = $pesanan->items->first();

        $komponen = Livewire::actingAs($this->rider)->test(DaftarPengantaranRider::class)
            ->call('bukaKonfirmasi', $pengantaran->id)
            ->set("dicekKonfirmasi.{$item->id}", true)
            ->set('tokoSusunSendiri', true)
            ->set('namaPenandatanganToko', 'Budi')
            ->call('terimaTandaTangan', gambarPengantaranUji())
            ->set('fotoNota', UploadedFile::fake()->image('nota.jpg'));

        $komponen = lengkapiBuktiPengantaranUji($komponen);

        $komponen->call('simpanKonfirmasi')->assertHasNoErrors();

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Selesai)
            ->and($pengantaran->fresh()->status)->toBe(StatusPengantaranRider::Selesai)
            // Foto wajib + satu tanda tangan toko (pengganti foto freezer disusun).
            ->and($pengantaran->fresh()->fotos()->count())->toBe(count(JenisBuktiPengiriman::wajibFoto()) + 1);
    });

    it('layar menolak simpan sebelum semua item dicentang', function () {
        $pesanan = pesananUji(tokoDekatUji());
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        Livewire::actingAs($this->rider)->test(DaftarPengantaranRider::class)
            ->call('bukaKonfirmasi', $pengantaran->id)
            ->assertSet('semuaTercekKonfirmasi', false)
            ->call('simpanKonfirmasi')
            ->assertDispatched('notifikasi');

        expect($pengantaran->fresh()->status)->toBe(StatusPengantaranRider::Diambil);
    });
});

describe('pembatalan pesanan', function () {
    it('membatalkan pesanan yang masih Tersedia di pool ikut membersihkan baris pool', function () {
        $pesanan = pesananUji(tokoDekatUji());
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);

        $this->pesananService->batalkan($pesanan->fresh(), $this->admin, 'Toko batal');

        expect($pesanan->fresh()->status)->toBe(StatusPesanan::Cancel)
            ->and(PengantaranRider::find($pengantaran->id))->toBeNull();
    });

    it('membatalkan pesanan yang sedang Diambil rider ikut membersihkan baris pool', function () {
        $pesanan = pesananUji(tokoDekatUji());
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);

        $this->pesananService->batalkan($pesanan->fresh(), $this->admin, 'Toko batal');

        expect(PengantaranRider::find($pengantaran->id))->toBeNull();
    });

    it('menolak membatalkan pesanan yang pengantaran ridernya sudah Selesai', function () {
        $pesanan = pesananUji(tokoDekatUji(), 10);
        $pengantaran = $this->riderService->tandaiUntukRider($pesanan, $this->admin);
        $this->riderService->ambil($pengantaran, $this->rider);
        $item = $pesanan->items->first();
        $this->riderService->selesaikan($pengantaran->fresh(), [$item->id => 10], 'nota/uji.jpg', $this->rider, null, []);

        expect(fn () => $this->pesananService->batalkan($pesanan->fresh(), $this->admin, 'Coba batal'))
            ->toThrow(RuntimeException::class);
    });
});

describe('visibilitas per gudang', function () {
    it('pool tersedia hanya menampilkan pengantaran milik gudang yang sedang aktif', function () {
        $this->riderService->tandaiUntukRider(pesananUji(tokoDekatUji()), $this->admin);

        $depotB = Depot::factory()->create(['kode' => 'DEPOTB']);

        DepotContext::jalankanSebagai($depotB, function () {
            $wilayahB = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah B']);
            $produkB = Produk::create(['kode' => 'P1', 'nama' => 'Produk B', 'stok' => 100, 'harga' => 1000]);
            $tokoB = Toko::create([
                'kode' => 'TK-B1', 'nama' => 'Toko B', 'wilayah_id' => $wilayahB->id, 'alamat' => 'Jl. B',
                'latitude' => -6.25, 'longitude' => 106.816666, 'sumber_koordinat' => 'manual',
            ]);
            $pesananB = test()->pesananService->buat($tokoB, [['produk_id' => $produkB->id, 'jumlah_dus' => 5]], test()->sales);

            expect(PengantaranRider::tersedia()->count())->toBe(0);

            test()->riderService->tandaiUntukRider($pesananB, test()->admin);

            expect(PengantaranRider::tersedia()->count())->toBe(1);
        });

        // Kembali ke konteks depot A (ambient) — cuma lihat 1 punya sendiri.
        expect(PengantaranRider::tersedia()->count())->toBe(1);
    });
});

describe('akses', function () {
    it('hanya rider dan admin yang bisa membuka menu pengantaran rider', function () {
        $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

        $this->actingAs($this->sales)->get(route('pengantaran-rider.daftar'))->assertForbidden();
        $this->actingAs($driver)->get(route('pengantaran-rider.daftar'))->assertForbidden();
        $this->actingAs($this->rider)->get(route('pengantaran-rider.daftar'))->assertOk();
        $this->actingAs($this->admin)->get(route('pengantaran-rider.daftar'))->assertOk();
    });
});
