<?php

use App\Enums\PeranPengguna;
use App\Livewire\Pesanan\BuatPesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Livewire\Livewire;

/**
 * Pemilihan toko pada halaman input pesanan.
 *
 * Ada dua jalan: mengetik dan memindai QR freezer. Keduanya menuju pemeriksaan
 * yang sama — toko harus aktif dan belum punya pesanan berjalan — supaya sales
 * tidak baru mengetahui penolakannya setelah selesai mengisi seluruh produk.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 50_000]);
});

function tokoBerAset(string $assetId, string $nama, bool $aktif = true): Toko
{
    return Toko::create([
        'kode' => 'TK-'.substr($assetId, -4),
        'asset_id' => $assetId,
        'freezer_tipe' => 'SD-280',
        'freezer_pelanggan' => 'IDN Halocoko',
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Contoh No. 1',
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
        'aktif' => $aktif,
    ]);
}

describe('pencarian dengan mengetik', function () {
    it('menemukan toko dari nomor aset lengkap dan hanya menyisakan satu', function () {
        tokoBerAset('IDNAH202528004381', 'Toko Satu');
        tokoBerAset('IDNAH202528004382', 'Toko Dua');
        tokoBerAset('IDNAH202528004383', 'Toko Tiga');

        $hasil = Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('cariToko', 'IDNAH202528004382')
            ->instance()
            ->hasilCari;

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->nama)->toBe('Toko Dua');
    });

    it('menemukan beberapa toko dari potongan nomor aset', function () {
        tokoBerAset('IDNAH202528004381', 'Toko Satu');
        tokoBerAset('IDNAH202528004382', 'Toko Dua');
        tokoBerAset('IDNBB202528009999', 'Toko Lain');

        $hasil = Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('cariToko', 'IDNAH2025280043')
            ->instance()
            ->hasilCari;

        expect($hasil)->toHaveCount(2)
            ->and($hasil->pluck('nama')->all())->toContain('Toko Satu', 'Toko Dua');
    });

    it('menerima nomor aset huruf kecil dan berspasi', function () {
        tokoBerAset('IDNAH202528004381', 'Toko Satu');

        $hasil = Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('cariToko', 'idnah 2025 2800 4381')
            ->instance()
            ->hasilCari;

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->nama)->toBe('Toko Satu');
    });

    it('menaruh kecocokan nomor aset persis di urutan teratas', function () {
        // Nama toko ini memuat nomor aset toko lain, jadi ikut terjaring
        // pencarian. Yang nomor asetnya cocok persis harus tetap di atas.
        tokoBerAset('IDNAH202528004381', 'Toko Utama');
        tokoBerAset('IDNAH202528009999', 'Cabang IDNAH202528004381');

        $hasil = Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('cariToko', 'IDNAH202528004381')
            ->instance()
            ->hasilCari;

        expect($hasil->first()->nama)->toBe('Toko Utama');
    });

    it('tetap menemukan toko dari nama, kode, dan alamat', function () {
        tokoBerAset('IDNAH202528004381', 'Warung Berkah');

        $komponen = Livewire::actingAs($this->sales)->test(BuatPesanan::class);

        foreach (['Berkah', 'TK-4381', 'Jl. Contoh'] as $kata) {
            expect($komponen->set('cariToko', $kata)->instance()->hasilCari)
                ->toHaveCount(1, "Pencarian '{$kata}' tidak menemukan toko.");
        }
    });
});

describe('pemilihan lewat pindai QR', function () {
    it('memilih toko dari isi QR freezer', function () {
        $toko = tokoBerAset('IDNAH202528004381', 'Toko QR');

        $isi = "客户名称：IDN Halocoko\n资产编号：IDNAH202528004381\n产品型号：SD-280";

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->call('pilihTokoDariQr', $isi)
            ->assertSet('tokoId', $toko->id);
    });

    it('menerima QR yang hanya berisi nomor asetnya', function () {
        $toko = tokoBerAset('IDNAH202528004381', 'Toko QR');

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->call('pilihTokoDariQr', 'IDNAH202528004381')
            ->assertSet('tokoId', $toko->id);
    });

    it('menolak nomor aset yang tidak terdaftar', function () {
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->call('pilihTokoDariQr', '资产编号：IDNAH999999999999')
            ->assertSet('tokoId', null)
            ->assertDispatched('qr-toko-ditolak');
    });

    it('menolak isi yang bukan QR freezer', function () {
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->call('pilihTokoDariQr', 'https://contoh.test/apa-saja')
            ->assertSet('tokoId', null)
            ->assertDispatched('qr-toko-ditolak');
    });

    it('menolak toko yang berstatus nonaktif', function () {
        tokoBerAset('IDNAH202528004381', 'Toko Nonaktif', aktif: false);

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->call('pilihTokoDariQr', 'IDNAH202528004381')
            ->assertSet('tokoId', null)
            ->assertDispatched('qr-toko-ditolak');
    });

    it('menolak toko yang masih punya pesanan berjalan', function () {
        $toko = tokoBerAset('IDNAH202528004381', 'Toko Sibuk');

        app(PesananService::class)->buat(
            $toko,
            [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]],
            $this->sales,
        );

        // Ditolak sejak pemindaian, bukan setelah seluruh produk terisi.
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->call('pilihTokoDariQr', 'IDNAH202528004381')
            ->assertSet('tokoId', null)
            ->assertDispatched('qr-toko-ditolak');
    });

    it('berganti antara cara ketik dan pindai tanpa membawa sisa ketikan', function () {
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->assertSet('caraPilihToko', 'ketik')
            ->set('cariToko', 'Berkah')
            ->call('gantiCaraPilih', 'pindai')
            ->assertSet('caraPilihToko', 'pindai')
            ->assertSet('cariToko', '')
            ->call('gantiCaraPilih', 'ketik')
            ->assertSet('caraPilihToko', 'ketik');
    });

    it('mengabaikan cara pilih yang tidak dikenal', function () {
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->call('gantiCaraPilih', 'entah-apa')
            ->assertSet('caraPilihToko', 'ketik');
    });
});
