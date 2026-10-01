<?php

use App\Enums\JenisMutasiStok;
use App\Enums\PeranPengguna;
use App\Enums\StatusTransferStok;
use App\Livewire\TransferStok\DaftarTransferStok;
use App\Models\Depot;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\TransferStok;
use App\Models\User;
use App\Services\TransferStok\TransferStokService;
use App\Support\DepotContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Transfer stok antar gudang penyimpanan: kirim LANGSUNG memindahkan stok
 * fisik (tidak ada tahap persetujuan terpisah), gudang tujuan menerima
 * (boleh merevisi jumlah) atau gudang asal membatalkan selama belum
 * diterima. Lihat App\Services\TransferStok\TransferStokService.
 */
beforeEach(function () {
    $this->depot->update(['gudang_penyimpanan' => true, 'nama' => 'Gudang Dumai']);
    $this->depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Gudang Perawang', 'gudang_penyimpanan' => true]);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 100, 'harga' => 10_000]);
});

/** Mengirim transfer dari $asal ke $tujuan lewat service, mengembalikan baris TransferStok. */
function kirimTransferUji(Depot $asal, Depot $tujuan, ?array $baris = null): TransferStok
{
    $baris ??= [['produk_id' => test()->produk->id, 'jumlah' => 10]];

    return app(TransferStokService::class)->kirim($asal, $tujuan, $baris, 'Catatan uji', test()->admin);
}

describe('kirim', function () {
    it('memindahkan stok langsung dan mencatat mutasi keluar', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);

        expect($transfer->status)->toBe(StatusTransferStok::Dikirim)
            ->and($transfer->kode)->toStartWith('TRF-')
            ->and($this->produk->fresh()->stok)->toBe(90);

        $mutasi = StokMutasi::where('produk_id', $this->produk->id)->latest('id')->first();
        expect($mutasi->tipe)->toBe(JenisMutasiStok::TransferKeluar)
            ->and($mutasi->jumlah)->toBe(-10)
            ->and($mutasi->stok_sesudah)->toBe(90);
    });

    it('membuat otomatis produk padanan di gudang tujuan jika belum ada', function () {
        kirimTransferUji($this->depot, $this->depotB);

        $produkTujuan = DepotContext::jalankanSebagai($this->depotB, fn () => Produk::where('kode', 'P1')->first());

        expect($produkTujuan)->not->toBeNull()
            ->and($produkTujuan->nama)->toBe('Produk Uji')
            ->and($produkTujuan->stok)->toBe(0);
    });

    it('memakai produk tujuan yang sudah ada jika kodenya sudah terdaftar di sana', function () {
        $produkTujuanLama = DepotContext::jalankanSebagai(
            $this->depotB,
            fn () => Produk::create(['kode' => 'P1', 'nama' => 'Nama Beda Di Tujuan', 'stok' => 5, 'harga' => 1]),
        );

        $transfer = kirimTransferUji($this->depot, $this->depotB);

        expect($transfer->items->first()->produk_tujuan_id)->toBe($produkTujuanLama->id);
    });

    it('menolak jika gudang asal dan tujuan sama', function () {
        expect(fn () => app(TransferStokService::class)->kirim(
            $this->depot, $this->depot, [['produk_id' => $this->produk->id, 'jumlah' => 1]], null, $this->admin,
        ))->toThrow(RuntimeException::class);

        expect($this->produk->fresh()->stok)->toBe(100);
    });

    it('menolak jika salah satu gudang bukan gudang penyimpanan', function () {
        $depotBiasa = Depot::factory()->create(['kode' => 'DEPOTC', 'gudang_penyimpanan' => false]);

        expect(fn () => app(TransferStokService::class)->kirim(
            $this->depot, $depotBiasa, [['produk_id' => $this->produk->id, 'jumlah' => 1]], null, $this->admin,
        ))->toThrow(RuntimeException::class);

        expect($this->produk->fresh()->stok)->toBe(100);
    });

    it('menolak jumlah yang melebihi stok tersedia', function () {
        expect(fn () => kirimTransferUji($this->depot, $this->depotB, [['produk_id' => $this->produk->id, 'jumlah' => 999]]))
            ->toThrow(RuntimeException::class);

        expect($this->produk->fresh()->stok)->toBe(100);
        expect(TransferStok::count())->toBe(0);
    });

    it('menolak baris item kosong', function () {
        expect(fn () => kirimTransferUji($this->depot, $this->depotB, []))->toThrow(RuntimeException::class);
    });

    it('terlihat dari gudang asal maupun gudang tujuan, tapi tidak dari gudang ketiga', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);

        expect(TransferStok::whereKey($transfer->id)->exists())->toBeTrue();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer) {
            expect(TransferStok::whereKey($transfer->id)->exists())->toBeTrue();
        });

        $depotKetiga = Depot::factory()->create(['kode' => 'DEPOTC', 'gudang_penyimpanan' => true]);
        DepotContext::jalankanSebagai($depotKetiga, function () use ($transfer) {
            expect(TransferStok::whereKey($transfer->id)->exists())->toBeFalse();
        });
    });

    it('menolak proses kirim di level service saat gudang asal bukan gudang penyimpanan, walau form tetap bisa dibuka', function () {
        // Tombol "Kirim Transfer" disembunyikan di UI saat gudang bukan
        // penyimpanan, tapi method Livewire-nya sendiri tidak menjaga itu —
        // pertahanan sesungguhnya ada di TransferStokService::kirim(), jadi
        // itulah yang diuji di sini (bukan asumsi form tidak bisa dibuka).
        $this->depot->update(['gudang_penyimpanan' => false]);

        Livewire::actingAs($this->admin)->test(DaftarTransferStok::class)
            ->assertSet('depotSekarangGudangPenyimpanan', false)
            ->call('buatBaruKirim')
            ->set('depotTujuanId', $this->depotB->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah', 5)
            ->call('kirim')
            ->assertDispatched('notifikasi');

        expect(TransferStok::count())->toBe(0);
    });

    it('bisa kirim lewat layar dengan beberapa baris produk', function () {
        $produk2 = Produk::create(['kode' => 'P2', 'nama' => 'Produk Dua', 'stok' => 50, 'harga' => 5_000]);

        Livewire::actingAs($this->admin)->test(DaftarTransferStok::class)
            ->call('buatBaruKirim')
            ->set('depotTujuanId', $this->depotB->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah', 10)
            ->call('tambahBaris')
            ->set('baris.1.produk_id', $produk2->id)
            ->set('baris.1.jumlah', 5)
            ->call('kirim')
            ->assertHasNoErrors();

        $transfer = TransferStok::latest('id')->first();
        expect($transfer->items)->toHaveCount(2)
            ->and($this->produk->fresh()->stok)->toBe(90)
            ->and($produk2->fresh()->stok)->toBe(45);
    });
});

describe('terima', function () {
    it('menambah stok tujuan sebesar jumlah penuh saat diterima tanpa revisi', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            app(TransferStokService::class)->terima($transfer, [$item->id => 10], null, [], test()->admin);
        });

        $produkTujuan = DepotContext::jalankanSebagai($this->depotB, fn () => Produk::where('kode', 'P1')->first());
        expect($produkTujuan->stok)->toBe(10)
            ->and($transfer->fresh()->status)->toBe(StatusTransferStok::Diterima)
            ->and($item->fresh()->jumlah_terima)->toBe(10);
    });

    it('revisi jumlah diterima lebih kecil hanya menambah stok sebesar yang benar-benar diterima', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            app(TransferStokService::class)->terima($transfer, [$item->id => 7], 'Kurang 3 dus', [], test()->admin);
        });

        $produkTujuan = DepotContext::jalankanSebagai($this->depotB, fn () => Produk::where('kode', 'P1')->first());
        expect($produkTujuan->stok)->toBe(7)
            ->and($item->fresh()->jumlah_terima)->toBe(7)
            ->and($item->fresh()->kekurangan)->toBe(3);
    });

    it('mengklem jumlah terima di server supaya tidak bisa melebihi jumlah kirim', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            app(TransferStokService::class)->terima($transfer, [$item->id => 999], null, [], test()->admin);
        });

        $produkTujuan = DepotContext::jalankanSebagai($this->depotB, fn () => Produk::where('kode', 'P1')->first());
        expect($produkTujuan->stok)->toBe(10);
    });

    it('mencatat mutasi masuk di gudang tujuan saat diterima', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            app(TransferStokService::class)->terima($transfer, [$item->id => 10], null, [], test()->admin);

            $produkTujuan = Produk::where('kode', 'P1')->first();
            $mutasi = StokMutasi::where('produk_id', $produkTujuan->id)->latest('id')->first();

            expect($mutasi->tipe)->toBe(JenisMutasiStok::TransferMasuk)
                ->and($mutasi->jumlah)->toBe(10);
        });
    });

    it('menolak menerima transfer yang sudah tidak berstatus dikirim', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            app(TransferStokService::class)->terima($transfer, [$item->id => 10], null, [], test()->admin);

            expect(fn () => app(TransferStokService::class)->terima($transfer->fresh(), [$item->id => 10], null, [], test()->admin))
                ->toThrow(RuntimeException::class);
        });
    });

    it('foto bukti penerimaan bersifat opsional — bisa diterima tanpa foto sama sekali', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            app(TransferStokService::class)->terima($transfer, [$item->id => 10], null, [], test()->admin);
        });

        expect($transfer->fresh()->fotos)->toHaveCount(0)
            ->and($transfer->fresh()->status)->toBe(StatusTransferStok::Diterima);
    });

    it('layar menolak submit sebelum semua item dicentang', function () {
        Storage::fake('public');
        $transfer = kirimTransferUji($this->depot, $this->depotB);

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer) {
            $admin = User::factory()->create(['role' => PeranPengguna::Admin]);

            Livewire::actingAs($admin)->test(DaftarTransferStok::class)
                ->call('bukaTerima', $transfer->id)
                ->assertSet('semuaTercekTerima', false)
                ->call('terima')
                ->assertDispatched('notifikasi');

            expect($transfer->fresh()->status)->toBe(StatusTransferStok::Dikirim);
        });
    });

    it('bisa menerima lewat layar dengan foto dan revisi jumlah', function () {
        Storage::fake('public');
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            $admin = User::factory()->create(['role' => PeranPengguna::Admin]);

            Livewire::actingAs($admin)->test(DaftarTransferStok::class)
                ->call('bukaTerima', $transfer->id)
                ->set("jumlahTerima.{$item->id}", 8)
                ->set("dicekTerima.{$item->id}", true)
                ->set('fotoTerima', [UploadedFile::fake()->image('terima.jpg')])
                ->call('terima')
                ->assertHasNoErrors();

            $produkTujuan = Produk::where('kode', 'P1')->first();
            expect($produkTujuan->stok)->toBe(8)
                ->and($transfer->fresh()->status)->toBe(StatusTransferStok::Diterima)
                ->and($transfer->fresh()->fotos)->toHaveCount(1);
        });
    });
});

describe('batalkan', function () {
    it('mengembalikan stok ke gudang asal dan mencatat mutasi masuk', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);

        app(TransferStokService::class)->batalkan($transfer, $this->admin, 'Salah kirim');

        expect($this->produk->fresh()->stok)->toBe(100)
            ->and($transfer->fresh()->status)->toBe(StatusTransferStok::Dibatalkan)
            ->and($transfer->fresh()->alasan_batal)->toBe('Salah kirim');

        $mutasi = StokMutasi::where('produk_id', $this->produk->id)->latest('id')->first();
        expect($mutasi->tipe)->toBe(JenisMutasiStok::TransferMasuk)
            ->and($mutasi->jumlah)->toBe(10);
    });

    it('menolak membatalkan transfer yang sudah diterima', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);
        $item = $transfer->items->first();

        DepotContext::jalankanSebagai($this->depotB, function () use ($transfer, $item) {
            app(TransferStokService::class)->terima($transfer, [$item->id => 10], null, [], test()->admin);
        });

        expect(fn () => app(TransferStokService::class)->batalkan($transfer->fresh(), $this->admin, null))
            ->toThrow(RuntimeException::class);

        // Stok asal tetap seperti sesudah kirim (90) — pembatalan yang
        // ditolak tidak boleh mengembalikan apa pun.
        expect($this->produk->fresh()->stok)->toBe(90);
    });

    it('bisa dibatalkan lewat layar oleh gudang pengirim', function () {
        $transfer = kirimTransferUji($this->depot, $this->depotB);

        Livewire::actingAs($this->admin)->test(DaftarTransferStok::class)
            ->call('bukaBatalkan', $transfer->id)
            ->set('alasanBatal', 'Salah input')
            ->call('batalkan')
            ->assertHasNoErrors();

        expect($transfer->fresh()->status)->toBe(StatusTransferStok::Dibatalkan)
            ->and($this->produk->fresh()->stok)->toBe(100);
    });
});

describe('akses', function () {
    it('hanya admin yang bisa membuka menu transfer stok', function () {
        $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
        $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

        $this->actingAs($sales)->get(route('transfer-stok.daftar'))->assertForbidden();
        $this->actingAs($driver)->get(route('transfer-stok.daftar'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('transfer-stok.daftar'))->assertOk();
    });
});
