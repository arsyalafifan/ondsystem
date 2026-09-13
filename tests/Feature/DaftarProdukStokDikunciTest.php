<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Master\DaftarProduk;
use App\Models\Depot;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\DepotContext;
use Livewire\Livewire;

/**
 * `stok_reserved` adalah kuncian TOTAL (lihat dokumentasi di
 * PesananService) — tes ini membuktikan DaftarProduk memecahnya jadi dua
 * kolom: yang masih menunggu di gudang ("dikunci_gudang") dan yang sudah
 * dimuat ke kendaraan (pesanan berstatus Delivery, "dalam_pengiriman").
 * Invariant utamanya: tersedia + dikunci_gudang + dalam_pengiriman = stok
 * fisik — sama seperti sebelumnya (tersedia + stok_reserved = stok fisik),
 * cuma stok_reserved dipecah tanpa mengubah totalnya.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->toko = Toko::create([
        'kode' => 'TK-DPD1', 'nama' => 'Toko Uji', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);
});

/** Pesanan dibuat langsung lewat Eloquent — murni menguji agregasi DaftarProduk::produks(). */
function buatPesananBerstatus(Produk $produk, StatusPesanan $status, int $jumlahDus, bool $bonus = false): Pesanan
{
    static $n = 0;
    $n++;

    $pesanan = Pesanan::create([
        'kode' => sprintf('PSN-DPD-%04d', $n),
        'toko_id' => test()->toko->id,
        'wilayah_id' => test()->toko->wilayah_id,
        'dibuat_oleh' => test()->admin->id,
        'status' => $status,
        'jenis' => 'normal',
        'tanggal' => today(),
        'total_dus' => $jumlahDus,
        'total_nilai' => $jumlahDus * 10_000,
    ]);

    $pesanan->items()->create([
        'produk_id' => $produk->id,
        'jumlah_dus' => $jumlahDus,
        'harga_satuan' => $bonus ? 0 : 10_000,
        'subtotal' => $bonus ? 0 : $jumlahDus * 10_000,
        'is_bonus' => $bonus,
    ]);

    return $pesanan;
}

it('memecah stok_reserved jadi dikunci gudang dan dikunci dalam pengiriman', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 100, 'stok_reserved' => 13, 'harga' => 10_000]);

    buatPesananBerstatus($produk, StatusPesanan::Order, jumlahDus: 5);
    buatPesananBerstatus($produk, StatusPesanan::Delivery, jumlahDus: 8);

    $produks = Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->instance()->produks();

    $baris = $produks->firstWhere('id', $produk->id);

    expect($baris->dalam_pengiriman)->toBe(8)
        ->and($baris->dikunci_gudang)->toBe(5);
});

it('invariant: tersedia + dikunci gudang + dikunci dalam pengiriman = stok fisik', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 100, 'stok_reserved' => 20, 'harga' => 10_000]);

    buatPesananBerstatus($produk, StatusPesanan::Process, jumlahDus: 12);
    buatPesananBerstatus($produk, StatusPesanan::Delivery, jumlahDus: 8);

    $baris = Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->instance()->produks()
        ->firstWhere('id', $produk->id);

    expect($baris->stok_tersedia + $baris->dikunci_gudang + $baris->dalam_pengiriman)->toBe(100);
});

it('pesanan selesai atau batal tidak dihitung sebagai dalam pengiriman', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 100, 'stok_reserved' => 5, 'harga' => 10_000]);

    buatPesananBerstatus($produk, StatusPesanan::Delivery, jumlahDus: 5);
    buatPesananBerstatus($produk, StatusPesanan::Selesai, jumlahDus: 999);
    buatPesananBerstatus($produk, StatusPesanan::Cancel, jumlahDus: 999);

    $baris = Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->instance()->produks()
        ->firstWhere('id', $produk->id);

    expect($baris->dalam_pengiriman)->toBe(5);
});

it('dus bonus ikut dihitung sebagai dalam pengiriman, sama seperti item biasa', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 100, 'stok_reserved' => 11, 'harga' => 10_000]);

    buatPesananBerstatus($produk, StatusPesanan::Delivery, jumlahDus: 10);
    buatPesananBerstatus($produk, StatusPesanan::Delivery, jumlahDus: 1, bonus: true);

    $baris = Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->instance()->produks()
        ->firstWhere('id', $produk->id);

    expect($baris->dalam_pengiriman)->toBe(11);
});

it('produk tanpa pesanan berjalan sama sekali menampilkan dalam pengiriman nol', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Sepi', 'stok' => 50, 'stok_reserved' => 0, 'harga' => 10_000]);

    $baris = Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->instance()->produks()
        ->firstWhere('id', $produk->id);

    expect($baris->dalam_pengiriman)->toBe(0)
        ->and($baris->dikunci_gudang)->toBe(0);
});

it('tidak mencampur dalam pengiriman lintas depot', function () {
    $depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Depot B']);

    $produkA = Produk::create(['kode' => 'P1', 'nama' => 'Produk A', 'stok' => 100, 'stok_reserved' => 5, 'harga' => 10_000]);
    buatPesananBerstatus($produkA, StatusPesanan::Delivery, jumlahDus: 5);

    DepotContext::jalankanSebagai($depotB, function () {
        $adminB = User::factory()->create(['role' => PeranPengguna::Admin]);
        $wilayahB = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah B']);
        $tokoB = Toko::create([
            'kode' => 'TK-B1', 'nama' => 'Toko B', 'wilayah_id' => $wilayahB->id,
            'alamat' => 'Jl. B', 'latitude' => -6.3, 'longitude' => 106.9, 'sumber_koordinat' => 'manual',
        ]);
        $produkB = Produk::create(['kode' => 'P1', 'nama' => 'Produk B', 'stok' => 100, 'stok_reserved' => 40, 'harga' => 10_000]);

        Pesanan::create([
            'kode' => 'PSN-DPD-B0001', 'toko_id' => $tokoB->id, 'wilayah_id' => $tokoB->wilayah_id,
            'dibuat_oleh' => $adminB->id, 'status' => StatusPesanan::Delivery, 'jenis' => 'normal',
            'tanggal' => today(), 'total_dus' => 40, 'total_nilai' => 400_000,
        ])->items()->create([
            'produk_id' => $produkB->id, 'jumlah_dus' => 40, 'harga_satuan' => 10_000, 'subtotal' => 400_000,
        ]);
    });

    // Konteks saat ini masih depot A (dari TestCase::setUp()) — produk A
    // tidak boleh ikut kebawa dus milik produk B yang sama-sama berkode P1.
    $baris = Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->instance()->produks()
        ->firstWhere('id', $produkA->id);

    expect($baris->dalam_pengiriman)->toBe(5);
});

it('halaman /master/produk menampilkan kolom dikunci dalam pengiriman', function () {
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 100, 'stok_reserved' => 8, 'harga' => 10_000]);
    buatPesananBerstatus($produk, StatusPesanan::Delivery, jumlahDus: 8);

    $this->actingAs($this->admin)
        ->get(route('master.produk'))
        ->assertOk()
        ->assertSee(__('master.dikunci_pengiriman'));
});
