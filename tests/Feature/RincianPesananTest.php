<?php

use App\Enums\PeranPengguna;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PengirimanService;
use App\Services\PesananService;
use App\Services\RoutingService;
use App\Support\Bahasa;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Modal "Rincian Pesanan" di layar admin (Input Pesanan) menampilkan
 * jumlah dan total pesanan mentah dari kolomnya (jumlah_dus, total_nilai) —
 * tidak pernah memperhitungkan koreksi kurang_kirim. Begitu suatu item
 * dikoreksi jadi 0 dus diterima (toko sama sekali tidak mengambilnya), item
 * itu tetap muncul di modal seolah-olah ikut ditagihkan. Baris ini mengunci
 * perbaikannya: item dengan terkirim = 0 disembunyikan, dan total yang
 * ditampilkan mengikuti Pesanan::tagihan() (bukan total_nilai mentah).
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $wilayah = Wilayah::create(['kode' => 'W-RP', 'nama' => 'Wilayah Rincian']);
    $this->air = Produk::create(['kode' => 'RP1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 50_000]);
    $this->teh = Produk::create(['kode' => 'RP2', 'nama' => 'Teh Kotak', 'stok' => 1000, 'harga' => 40_000]);
    $this->toko = Toko::create([
        'kode' => 'TK-RP1', 'nama' => 'Toko Rincian', 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Rincian', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    $this->pesananService = app(PesananService::class);
});

/** Satu pesanan SELESAI (air 10 dus, teh 5 dus), lewat alur penuh. */
function selesaikanPesananRincian(): Pesanan
{
    $pesanan = test()->pesananService->buat(test()->toko, [
        ['produk_id' => test()->air->id, 'jumlah_dus' => 10],
        ['produk_id' => test()->teh->id, 'jumlah_dus' => 5],
    ], test()->sales);

    test()->pesananService->setujui($pesanan, test()->admin);
    $batch = app(RoutingService::class)->generate(test()->admin);
    app(RoutingService::class)->setujui($batch, test()->admin);

    $stop = KendaraanStop::where('pesanan_id', $pesanan->id)->first();

    $g = imagecreatetruecolor(50, 50);
    ob_start();
    imagejpeg($g);
    $isi = (string) ob_get_clean();
    imagedestroy($g);
    $path = 'nota/rincian-uji.jpg';
    Storage::disk('public')->put($path, $isi);

    test()->pesananService->selesaikanPengiriman($stop, $path, test()->driver);

    return $pesanan->fresh();
}

it('menyembunyikan produk yang dikoreksi jadi 0 dus diterima', function () {
    $pesanan = selesaikanPesananRincian();
    $itemTeh = $pesanan->items()->where('produk_id', $this->teh->id)->first();

    app(PengirimanService::class)->koreksiItemSetelahSelesai($itemTeh, 0, $this->admin);

    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->set('pesananDilihat', $pesanan->id)
        ->assertSee($this->air->nama)
        ->assertDontSee($this->teh->nama);
});

it('menampilkan total sesuai tagihan setelah koreksi, bukan total pesanan semula', function () {
    $pesanan = selesaikanPesananRincian();
    $itemTeh = $pesanan->items()->where('produk_id', $this->teh->id)->first();

    app(PengirimanService::class)->koreksiItemSetelahSelesai($itemTeh, 0, $this->admin);
    $pesanan->refresh();

    // Tagihan sekarang hanya 10 dus air = 500.000, bukan 700.000 semula.
    expect((float) $pesanan->tagihan)->toBe(500_000.0);

    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->set('pesananDilihat', $pesanan->id)
        ->assertSee(Bahasa::rupiah(500_000))
        ->assertSee(__('pesanan.ket_kurang_kirim'));
});

it('menampilkan seluruh produk apa adanya kalau pesanan tidak dikoreksi', function () {
    $pesanan = selesaikanPesananRincian();

    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->set('pesananDilihat', $pesanan->id)
        ->assertSee($this->air->nama)
        ->assertSee($this->teh->nama)
        ->assertDontSee(__('pesanan.ket_kurang_kirim'));
});

it('menampilkan jumlah terkirim sebagian, bukan jumlah pesanan semula', function () {
    $pesanan = selesaikanPesananRincian();
    $itemAir = $pesanan->items()->where('produk_id', $this->air->id)->first();

    // Dari 10 dus, ternyata cuma 6 yang benar-benar diambil toko.
    app(PengirimanService::class)->koreksiItemSetelahSelesai($itemAir, 6, $this->admin);

    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->set('pesananDilihat', $pesanan->id)
        ->assertSeeInOrder([$this->air->nama, '6'])
        ->assertSee($this->teh->nama);
});
