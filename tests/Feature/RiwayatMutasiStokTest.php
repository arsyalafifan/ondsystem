<?php

use App\Enums\JenisMutasiStok;
use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarProduk;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * "Riwayat Mutasi" pada Master Produk: daftar keluar-masuk stok satu
 * produk, bisa disaring per jenis mutasi dan rentang tanggal — dipakai
 * untuk menelusuri KENAPA angka stok sebuah produk berubah, bukan cuma
 * melihat angka akhirnya saja.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100, 'harga' => 20_000]);
    $this->produkLain = Produk::create(['kode' => 'P2', 'nama' => 'Teh Kotak', 'stok' => 50, 'harga' => 10_000]);

    $this->pesananService = app(PesananService::class);
});

function buatMutasiUji(Produk $produk, JenisMutasiStok $tipe, int $jumlah, ?CarbonImmutable $waktu = null, ?string $keterangan = null): StokMutasi
{
    $mutasi = StokMutasi::create([
        'produk_id' => $produk->id,
        'tipe' => $tipe->value,
        'jumlah' => $jumlah,
        'stok_sesudah' => $produk->stok,
        'reserved_sesudah' => $produk->stok_reserved,
        'keterangan' => $keterangan,
        'user_id' => test()->admin->id,
    ]);

    if ($waktu !== null) {
        $mutasi->forceFill(['created_at' => $waktu, 'updated_at' => $waktu])->save();
    }

    return $mutasi->fresh();
}

// =====================================================================
describe('JenisMutasiStok', function () {
    it('setiap kasus enum punya label dan warna badge', function () {
        foreach (JenisMutasiStok::cases() as $tipe) {
            expect($tipe->label())->toBeString()->not->toBeEmpty()
                ->and($tipe->badge())->toBeString()->not->toBeEmpty();
        }
    });

    it('StokMutasi::tipe otomatis ter-cast jadi enum, bukan string polos', function () {
        $mutasi = buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 10);

        expect($mutasi->tipe)->toBeInstanceOf(JenisMutasiStok::class)
            ->and($mutasi->tipe)->toBe(JenisMutasiStok::Masuk);

        // Baris mentahnya tetap tersimpan sebagai string biasa di kolom DB —
        // cast cuma memengaruhi sisi PHP, bukan skema/kueri basis data.
        $this->assertDatabaseHas('stok_mutasis', ['id' => $mutasi->id, 'tipe' => 'masuk']);
    });
});

// =====================================================================
describe('DaftarProduk (Livewire): Riwayat Mutasi', function () {
    it('tombol Riwayat Mutasi membuka modal untuk produk yang tepat', function () {
        Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->assertSet('produkRiwayat', $this->produk->id)
            ->assertSee(__('master.judul_riwayat_mutasi', ['nama' => $this->produk->nama]));
    });

    it('riwayat hanya menampilkan mutasi milik produk yang dibuka, bukan produk lain', function () {
        buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 10);
        buatMutasiUji($this->produkLain, JenisMutasiStok::Masuk, 99);

        $hasil = Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->instance()->riwayatMutasi();

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->jumlah)->toBe(10);
    });

    it('terurut dari yang paling baru', function () {
        $lama = buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 5, CarbonImmutable::now()->subDays(3));
        $baru = buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 7, CarbonImmutable::now());

        $hasil = Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->instance()->riwayatMutasi();

        expect($hasil->first()->id)->toBe($baru->id)
            ->and($hasil->last()->id)->toBe($lama->id);
    });

    it('bisa disaring menurut jenis mutasi', function () {
        buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 10);
        buatMutasiUji($this->produk, JenisMutasiStok::Keluar, -3);
        buatMutasiUji($this->produk, JenisMutasiStok::Penyesuaian, -2);

        $hasil = Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->set('riwayatTipe', JenisMutasiStok::Keluar->value)
            ->instance()->riwayatMutasi();

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->tipe)->toBe(JenisMutasiStok::Keluar);
    });

    it('bisa disaring menurut rentang tanggal', function () {
        buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 5, CarbonImmutable::parse('2026-08-01'));
        buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 7, CarbonImmutable::parse('2026-08-15'));
        buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 9, CarbonImmutable::parse('2026-09-01'));

        $hasil = Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->set('riwayatDari', '2026-08-10')
            ->set('riwayatSampai', '2026-08-31')
            ->instance()->riwayatMutasi();

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->jumlah)->toBe(7);
    });

    it('membuka riwayat produk lain membersihkan penyaring yang tersisa', function () {
        Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->set('riwayatTipe', JenisMutasiStok::Keluar->value)
            ->call('bukaRiwayat', $this->produkLain->id)
            ->assertSet('riwayatTipe', '')
            ->assertSet('produkRiwayat', $this->produkLain->id);
    });

    it('tombol Bersihkan mengembalikan seluruh riwayat', function () {
        buatMutasiUji($this->produk, JenisMutasiStok::Masuk, 10);
        buatMutasiUji($this->produk, JenisMutasiStok::Keluar, -3);

        Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->set('riwayatTipe', JenisMutasiStok::Keluar->value)
            ->assertCount('riwayatMutasi', 1)
            ->call('bersihkanFilterRiwayat')
            ->assertCount('riwayatMutasi', 2);
    });

    it('kolom Terkait menampilkan kode pesanan untuk mutasi yang berasal dari pesanan sungguhan', function () {
        $toko = Toko::create([
            'kode' => 'TK-RM01', 'nama' => 'Toko Riwayat Mutasi', 'wilayah_id' => $this->wilayah->id,
            'alamat' => 'Jl. Riwayat', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        ]);

        $pesanan = $this->pesananService->buat(
            $toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 5]], $this->sales,
        );

        $html = Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->html();

        expect($html)->toContain($pesanan->kode)
            ->and($html)->toContain(JenisMutasiStok::Reserve->label());
    });

    it('penyesuaian manual lewat modal ± Stok langsung muncul di riwayat', function () {
        Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaPenyesuaian', $this->produk->id)
            ->set('jumlahPenyesuaian', 15)
            ->set('keteranganPenyesuaian', 'Barang datang dari pabrik')
            ->call('simpanPenyesuaian');

        $hasil = Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->instance()->riwayatMutasi();

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->jumlah)->toBe(15)
            ->and($hasil->first()->keterangan)->toBe('Barang datang dari pabrik')
            ->and($hasil->first()->user->id)->toBe($this->admin->id);
    });

    it('tutupRiwayat membersihkan seluruh state modal', function () {
        Livewire::actingAs($this->admin)
            ->test(DaftarProduk::class)
            ->call('bukaRiwayat', $this->produk->id)
            ->set('riwayatTipe', JenisMutasiStok::Keluar->value)
            ->call('tutupRiwayat')
            ->assertSet('produkRiwayat', null)
            ->assertSet('riwayatTipe', '');
    });
});
