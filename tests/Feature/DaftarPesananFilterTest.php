<?php

use App\Enums\PeranPengguna;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Livewire\Livewire;

/**
 * Penyaring "penginput" (filter berapa pesanan per sales) dan dua kolom baru
 * di daftar pesanan: tanggal pesanan diinput, dan "Update By | Date" —
 * aktor + waktu tindakan terakhir pada pesanan itu, jatuh kembali ke
 * penginput kalau belum ada tindakan lanjutan sama sekali.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 10_000]);
    $this->pesananService = app(PesananService::class);
});

function buatPesananUntukSales(User $sales, string $namaToko = 'Toko Uji'): Pesanan
{
    static $n = 0;
    $n++;

    $toko = Toko::create([
        'kode' => sprintf('TK-DF%04d', $n),
        'nama' => $namaToko,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Uji',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);

    return test()->pesananService->buat(
        $toko, [['produk_id' => test()->produk->id, 'jumlah_dus' => 10]], $sales,
    );
}

it('menyaring pesanan menurut penginput yang dipilih', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales A']);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales B']);

    $pesananA = buatPesananUntukSales($salesA);
    buatPesananUntukSales($salesB);

    $test = Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->set('filterPenginput', (string) $salesA->id)
        ->assertSee($pesananA->kode);

    expect($test->instance()->pesanans()->total())->toBe(1);
});

it('daftar pilihan penginput hanya berisi user yang pernah menginput pesanan', function () {
    $salesAktif = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Aktif']);
    $salesTanpaPesanan = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Kosong']);

    buatPesananUntukSales($salesAktif);

    $daftar = Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->instance()->penginputs();

    expect($daftar->pluck('id'))->toContain($salesAktif->id)
        ->and($daftar->pluck('id'))->not->toContain($salesTanpaPesanan->id);
});

it('ringkasan status ikut mengikuti penyaring penginput', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales]);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananUntukSales($salesA);
    buatPesananUntukSales($salesB);
    buatPesananUntukSales($salesB);

    $ringkasan = Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->set('filterPenginput', (string) $salesB->id)
        ->instance()->ringkasan();

    expect($ringkasan['order'])->toBe(2);
});

it('menampilkan tanggal pesanan diinput di tabel', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $pesanan = buatPesananUntukSales($sales);

    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->assertSee($pesanan->created_at->isoFormat('ll'));
});

it('kolom Update By | Date jatuh ke penginput kalau belum ada tindakan lanjutan', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Penginput']);
    $pesanan = buatPesananUntukSales($sales);

    $data = Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->instance()->pesanans();

    $baris = $data->firstWhere('id', $pesanan->id);
    $pembaru = $baris->pembaru_terakhir;

    expect($pembaru['user']->id)->toBe($sales->id)
        ->and($pembaru['at']->eq($pesanan->created_at))->toBeTrue();
});

it('kolom Update By | Date menunjukkan admin yang menyetujui, bukan penginput', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $pesanan = buatPesananUntukSales($sales);

    $this->pesananService->setujui($pesanan, $this->admin);

    $data = Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->instance()->pesanans();

    $baris = $data->firstWhere('id', $pesanan->id);
    $pembaru = $baris->pembaru_terakhir;

    expect($pembaru['user']->id)->toBe($this->admin->id)
        ->and($pembaru['at']->eq($baris->diproses_at))->toBeTrue();
});

/**
 * Sengaja memakai BEBERAPA pesanan pada satu halaman, bukan satu. Laravel 13
 * diam-diam memuat relasi yang kurang (autoload) ketika modelnya cuma satu,
 * jadi pelanggaran mode ketat pada pembuat/pemroses/pembatal/dilunasiOleh —
 * relasi yang dibutuhkan Pesanan::pembaruTerakhir() — baru kelihatan begitu
 * daftarnya berisi lebih dari satu baris, persis seperti tampilan aslinya.
 */
it('pembaru_terakhir bisa diakses pada banyak baris pesanan sekaligus tanpa lazy load', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    for ($i = 0; $i < 4; $i++) {
        buatPesananUntukSales($sales, "Toko Banyak Baris {$i}");
    }

    $data = Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->instance()->pesanans();

    expect($data)->toHaveCount(4);

    foreach ($data as $baris) {
        expect($baris->pembaru_terakhir['user'])->not->toBeNull();
    }
});

it('kolom Update By | Date menunjukkan admin yang membatalkan sebagai tindakan terbaru', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $pesanan = buatPesananUntukSales($sales);

    $this->pesananService->setujui($pesanan, $this->admin);
    $pembatal = User::factory()->create(['role' => PeranPengguna::Admin, 'name' => 'Admin Pembatal']);
    $this->pesananService->batalkan($pesanan->fresh(), $pembatal, 'Toko tutup');

    $data = Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->instance()->pesanans();

    $baris = $data->firstWhere('id', $pesanan->id);
    $pembaru = $baris->pembaru_terakhir;

    expect($pembaru['user']->id)->toBe($pembatal->id)
        ->and($pembaru['at']->eq($baris->dibatalkan_at))->toBeTrue();
});
