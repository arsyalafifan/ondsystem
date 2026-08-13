<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::firstOrCreate(['kode' => 'W1'], ['nama' => 'Wilayah W1']);

    $this->toko = Toko::create([
        'kode' => 'TK-0001',
        'nama' => 'Toko Uji',
        'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'telepon' => '0812345678',
        'asset_id' => 'IDNAH202528000001',
    ]);

    $this->pesananService = app(PesananService::class);
});

/** @return array<int, array{produk_id: int, jumlah_dus: int}> */
function buatItemUji(int $jumlahProduk): array
{
    $items = [];

    for ($i = 0; $i < $jumlahProduk; $i++) {
        $produk = Produk::create([
            'kode' => 'PU-'.($i + 1),
            'nama' => 'Produk Uji '.($i + 1),
            'stok' => 1000,
            'harga' => 50_000,
        ]);

        $items[] = ['produk_id' => $produk->id, 'jumlah_dus' => 5];
    }

    return $items;
}

function buatPesananProcess(int $jumlahProduk = 1): Pesanan
{
    $pesanan = test()->pesananService->buat(test()->toko, buatItemUji($jumlahProduk), test()->sales);
    test()->pesananService->setujui($pesanan, test()->admin);

    return $pesanan->fresh();
}

it('menampilkan nota untuk pesanan berstatus PROCESS', function () {
    $pesanan = buatPesananProcess();

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertOk();
    $this->actingAs($this->sales)->get(route('pesanan.nota', $pesanan))->assertOk();
});

it('menolak nota untuk pesanan berstatus ORDER', function () {
    $pesanan = $this->pesananService->buat($this->toko, buatItemUji(1), $this->sales);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertForbidden();
});

it('menolak nota untuk pesanan berstatus SELESAI', function () {
    $pesanan = buatPesananProcess();
    $pesanan->update(['status' => StatusPesanan::Selesai]);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertForbidden();
});

it('tetap bisa dicetak ulang saat status DELIVERY', function () {
    $pesanan = buatPesananProcess();
    $pesanan->update(['status' => StatusPesanan::Delivery]);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertOk();
});

it('menolak driver mengakses nota', function () {
    $pesanan = buatPesananProcess();

    $this->actingAs($this->driver)->get(route('pesanan.nota', $pesanan))->assertForbidden();
});

it('memilih ukuran kecil untuk lima item atau kurang', function () {
    $pesanan = buatPesananProcess(5);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))
        ->assertOk()
        ->assertViewHas('ukuran', 'kecil');
});

it('memilih ukuran besar untuk lebih dari lima item', function () {
    $pesanan = buatPesananProcess(6);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))
        ->assertOk()
        ->assertViewHas('ukuran', 'besar');
});
