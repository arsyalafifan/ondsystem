<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use Livewire\Livewire;

/**
 * "Order Ulang" / "Batalkan" untuk pesanan yang dibatalkan DRIVER di
 * lapangan (bukan oleh admin dari Daftar Pesanan) dengan alasan SELAIN
 * "toko membatalkan pesanan" — mis. toko tutup, stok tidak mencukupi,
 * alamat tidak ditemukan. Situasi ini masih ambigu (bukan penolakan
 * final toko), jadi admin diberi dua pilihan: coba lagi dengan item yang
 * sama (Order Ulang), atau menganggapnya final (Batalkan — cuma mengubah
 * catatan alasan, sama sekali tidak menyentuh stok).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100, 'harga' => 20_000]);

    $this->pesananService = app(PesananService::class);
    $this->routingService = app(RoutingService::class);
    $this->pengirimanService = app(PengirimanService::class);
});

function buatTokoOrderUlang(string $nama = 'Toko Uji OU'): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-OU%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Order Ulang No. '.$n,
        'latitude' => -6.20 + $n * 0.001,
        'longitude' => 106.82 + $n * 0.001,
        'sumber_koordinat' => 'manual',
    ]);
}

/**
 * Menyiapkan satu pesanan yang sudah dibatalkan DRIVER di lapangan (lewat
 * PengirimanService::batalkanDiLapangan(), bukan PesananService::batalkan()
 * admin) — jalur satu-satunya yang membuat Pesanan::bisa_order_ulang bisa
 * bernilai true. Mengembalikan pesanan yang sudah di-refresh berikut
 * relasi item/toko/stop-nya.
 */
function pesananDibatalkanDriver(
    string $alasan,
    int $jumlahDus = 10,
    ?Toko $toko = null,
): Pesanan {
    $toko ??= buatTokoOrderUlang();

    $pesanan = test()->pesananService->buat(
        $toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => $jumlahDus]], test()->sales,
    );
    test()->pesananService->setujui($pesanan, test()->admin);

    $batch = test()->routingService->generate(test()->admin);
    test()->routingService->setujui($batch, test()->admin);

    $kendaraan = $batch->fresh()->kendaraans->first();
    $kendaraan->update(['driver_id' => test()->driver->id]);

    $stop = $kendaraan->fresh(['stops'])->stops->first();

    test()->pengirimanService->batalkanDiLapangan($stop, test()->driver, $alasan);

    return $pesanan->fresh(['items', 'toko', 'stop', 'pembuat']);
}

// =====================================================================
describe('Pesanan::bisa_order_ulang', function () {
    it('benar untuk pesanan yang dibatalkan driver dengan alasan selain toko membatalkan', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_stok'));

        expect($pesanan->bisa_order_ulang)->toBeTrue();
    });

    it('salah kalau alasannya sudah "toko membatalkan pesanan"', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_toko_batal'));

        expect($pesanan->bisa_order_ulang)->toBeFalse();
    });

    it('salah untuk pesanan yang dibatalkan ADMIN (bukan driver), karena stop-nya dihapus', function () {
        $toko = buatTokoOrderUlang();
        $pesanan = $this->pesananService->buat(
            $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales,
        );

        $this->pesananService->batalkan($pesanan, $this->admin, __('pesanan.alasan_stok'));

        expect($pesanan->fresh(['stop'])->bisa_order_ulang)->toBeFalse();
    });

    it('salah untuk pesanan yang belum/tidak dibatalkan sama sekali', function () {
        $toko = buatTokoOrderUlang();
        $pesanan = $this->pesananService->buat(
            $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales,
        );

        expect($pesanan->fresh(['stop'])->bisa_order_ulang)->toBeFalse();
    });
});

// =====================================================================
describe('PesananService::tandaiBatalKarenaToko()', function () {
    it('mengubah alasan_cancel jadi "toko membatalkan pesanan", TIDAK menyentuh stok', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_stok'), jumlahDus: 10);
        $stokRervedSebelum = $this->produk->fresh()->stok_reserved;

        $this->pesananService->tandaiBatalKarenaToko($pesanan, $this->admin);

        $pesanan->refresh();
        expect($pesanan->alasan_cancel)->toBe(__('pesanan.alasan_toko_batal'))
            ->and($pesanan->catatan_cancel)->toContain(__('pesanan.alasan_stok'))
            // Siapa yang SUNGGUH membatalkan (driver, di lapangan) tetap apa
            // adanya — bukan diganti jadi admin yang cuma menandai final.
            ->and($pesanan->dibatalkan_oleh)->toBe($this->driver->id);

        expect($this->produk->fresh()->stok_reserved)->toBe($stokRervedSebelum);
    });

    it('menolak kalau alasannya sudah "toko membatalkan pesanan"', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_toko_batal'));

        expect(fn () => $this->pesananService->tandaiBatalKarenaToko($pesanan, $this->admin))
            ->toThrow(RuntimeException::class);
    });

    it('menolak untuk pesanan yang dibatalkan admin, bukan driver', function () {
        $toko = buatTokoOrderUlang();
        $pesanan = $this->pesananService->buat(
            $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales,
        );
        $this->pesananService->batalkan($pesanan, $this->admin, __('pesanan.alasan_stok'));

        expect(fn () => $this->pesananService->tandaiBatalKarenaToko($pesanan->fresh(), $this->admin))
            ->toThrow(RuntimeException::class);
    });
});

// =====================================================================
describe('DaftarPesanan (Livewire): Order Ulang', function () {
    it('tombol Order Ulang dan Batalkan hanya tampil untuk pesanan yang dibatalkan driver, bukan admin', function () {
        $dibatalkanDriver = pesananDibatalkanDriver(__('pesanan.alasan_stok'));

        $tokoAdmin = buatTokoOrderUlang();
        $dibatalkanAdmin = $this->pesananService->buat(
            $tokoAdmin, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales,
        );
        $this->pesananService->batalkan($dibatalkanAdmin, $this->admin, __('pesanan.alasan_stok'));

        $html = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->html();

        expect(substr_count($html, 'wire:click="bukaOrderUlang('))->toBe(1)
            ->and(substr_count($html, "wire:click=\"tandaiBatalKarenaToko({$dibatalkanDriver->id})\""))->toBe(1)
            ->and($html)->not->toContain("tandaiBatalKarenaToko({$dibatalkanAdmin->id})");
    });

    it('membuka modal Order Ulang mengisi baris produk dan sales sesuai pesanan lama', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_stok'), jumlahDus: 7);

        Livewire::actingAs($this->admin)
            ->test(DaftarPesanan::class)
            ->call('bukaOrderUlang', $pesanan->id)
            ->assertSet('pesananOrderUlang', $pesanan->id)
            ->assertSet('barisOrderUlang', [['produk_id' => $this->produk->id, 'jumlah_dus' => 7]])
            ->assertSet('salesOrderUlang', $this->sales->id);
    });

    it('admin bisa menyimpan order ulang dengan item yang sama seperti pesanan lama', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_stok'), jumlahDus: 7);

        Livewire::actingAs($this->admin)
            ->test(DaftarPesanan::class)
            ->call('bukaOrderUlang', $pesanan->id)
            ->call('simpanOrderUlang')
            ->assertHasNoErrors();

        $baru = Pesanan::where('id', '!=', $pesanan->id)->with('items')->firstOrFail();

        expect($baru->toko_id)->toBe($pesanan->toko_id)
            ->and($baru->status)->toBe(StatusPesanan::Order)
            ->and($baru->items)->toHaveCount(1)
            ->and($baru->items->first()->produk_id)->toBe($this->produk->id)
            ->and($baru->items->first()->jumlah_dus)->toBe(7)
            ->and($baru->sales_id)->toBe($this->sales->id);
    });

    /**
     * Stok sisa hanya 5 (100 total, 7 masih terkunci di pesanan lama yang
     * batal di lapangan — belum dilepas, masih fisik di mobil). Mencoba
     * order ulang 7 dus lagi harus ditolak dengan notifikasi stok, modal
     * TETAP terbuka supaya admin bisa langsung menyesuaikan.
     */
    it('order ulang ditolak kalau stok tidak mencukupi, dan modal tetap terbuka untuk disesuaikan', function () {
        $produkTerbatas = Produk::create(['kode' => 'P-TERBATAS', 'nama' => 'Stok Terbatas', 'stok' => 7, 'harga' => 10_000]);
        $toko = buatTokoOrderUlang();

        $pesanan = $this->pesananService->buat($toko, [['produk_id' => $produkTerbatas->id, 'jumlah_dus' => 7]], $this->sales);
        $this->pesananService->setujui($pesanan, $this->admin);
        $batch = $this->routingService->generate($this->admin);
        $this->routingService->setujui($batch, $this->admin);
        $kendaraan = $batch->fresh()->kendaraans->first();
        $kendaraan->update(['driver_id' => $this->driver->id]);
        $stop = $kendaraan->fresh(['stops'])->stops->first();
        $this->pengirimanService->batalkanDiLapangan($stop, $this->driver, __('pesanan.alasan_stok'));

        // Semua 7 dus masih terkunci (belum diampaskan/dilepas), jadi
        // stok_tersedia tinggal 0 — order ulang 7 dus lagi pasti gagal.
        $komponen = Livewire::actingAs($this->admin)
            ->test(DaftarPesanan::class)
            ->call('bukaOrderUlang', $pesanan->fresh()->id)
            ->call('simpanOrderUlang');

        $komponen->assertHasErrors('barisOrderUlang');
        expect($komponen->get('pesananOrderUlang'))->not->toBeNull();

        expect(Pesanan::count())->toBe(1);

        // Menyesuaikan ke jumlah yang muat (produk lain, stok penuh)
        // berhasil tanpa perlu menutup modal.
        $komponen->set('barisOrderUlang.0.produk_id', $this->produk->id)
            ->set('barisOrderUlang.0.jumlah_dus', 5)
            ->call('simpanOrderUlang')
            ->assertHasNoErrors();

        expect(Pesanan::count())->toBe(2);
    });

    it('menandai batal karena toko lewat komponen', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_alamat'));

        Livewire::actingAs($this->admin)
            ->test(DaftarPesanan::class)
            ->call('tandaiBatalKarenaToko', $pesanan->id)
            ->assertHasNoErrors();

        expect($pesanan->fresh()->alasan_cancel)->toBe(__('pesanan.alasan_toko_batal'));
    });

    it('sales tidak bisa membuka atau menyimpan order ulang maupun menandai batal final', function () {
        $pesanan = pesananDibatalkanDriver(__('pesanan.alasan_stok'));

        Livewire::actingAs($this->sales)
            ->test(DaftarPesanan::class)
            ->call('bukaOrderUlang', $pesanan->id)
            ->assertStatus(403);

        Livewire::actingAs($this->sales)
            ->test(DaftarPesanan::class)
            ->call('tandaiBatalKarenaToko', $pesanan->id)
            ->assertStatus(403);
    });
});
