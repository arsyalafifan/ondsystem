<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->produk = Produk::create([
        'kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100, 'harga' => 50_000,
    ]);

    $this->toko = Toko::create([
        'kode' => 'TK-0001',
        'nama' => 'Toko Uji',
        'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);

    $this->service = app(PesananService::class);
});

function buatPesanan(int $jumlah = 10): Pesanan
{
    return test()->service->buat(
        test()->toko,
        [['produk_id' => test()->produk->id, 'jumlah_dus' => $jumlah]],
        test()->sales,
    );
}

it('menyimpan pesanan dan langsung mengunci stoknya', function () {
    $pesanan = buatPesanan(10);

    expect($pesanan->status)->toBe(StatusPesanan::Order)
        ->and($pesanan->total_dus)->toBe(10)
        ->and((float) $pesanan->total_nilai)->toBe(500_000.0)
        ->and($pesanan->dibuat_oleh)->toBe($this->sales->id);

    $this->produk->refresh();

    // Stok fisik belum berkurang, yang berkurang hanya yang bisa dijanjikan.
    expect($this->produk->stok)->toBe(100)
        ->and($this->produk->stok_reserved)->toBe(10)
        ->and($this->produk->stok_tersedia)->toBe(90);

    $this->assertDatabaseHas('stok_mutasis', [
        'pesanan_id' => $pesanan->id,
        'tipe' => 'reserve',
        'jumlah' => -10,
    ]);
});

it('menolak pesanan di bawah batas minimal dus', function () {
    expect(fn () => buatPesanan(3))
        ->toThrow(ValidationException::class);

    expect(Pesanan::count())->toBe(0)
        ->and($this->produk->fresh()->stok_reserved)->toBe(0);
});

it('menolak pesanan kedua selama pesanan toko masih berjalan', function () {
    buatPesanan(10);

    expect(fn () => buatPesanan(10))->toThrow(ValidationException::class);

    expect(Pesanan::count())->toBe(1);
});

it('mengizinkan pesanan baru setelah pesanan sebelumnya dibatalkan', function () {
    $pertama = buatPesanan(10);
    $this->service->batalkan($pertama, $this->admin, 'Toko tutup');

    $kedua = buatPesanan(7);

    expect($kedua->status)->toBe(StatusPesanan::Order)
        ->and(Pesanan::count())->toBe(2);
});

it('menolak pesanan yang melebihi stok tersedia', function () {
    expect(fn () => buatPesanan(150))->toThrow(ValidationException::class);

    expect($this->produk->fresh()->stok_reserved)->toBe(0);
});

it('memperhitungkan stok yang sudah dikunci pesanan lain', function () {
    $tokoLain = Toko::create([
        'kode' => 'TK-0002', 'nama' => 'Toko Dua', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Dua', 'latitude' => -6.19, 'longitude' => 106.84,
    ]);

    buatPesanan(80);

    // Sisa tersedia tinggal 20, jadi permintaan 30 harus ditolak.
    expect(fn () => $this->service->buat(
        $tokoLain,
        [['produk_id' => $this->produk->id, 'jumlah_dus' => 30]],
        $this->sales,
    ))->toThrow(ValidationException::class);
});

it('menggabungkan baris produk yang sama menjadi satu', function () {
    $pesanan = $this->service->buat($this->toko, [
        ['produk_id' => $this->produk->id, 'jumlah_dus' => 4],
        ['produk_id' => $this->produk->id, 'jumlah_dus' => 6],
    ], $this->sales);

    expect($pesanan->items)->toHaveCount(1)
        ->and($pesanan->items->first()->jumlah_dus)->toBe(10)
        ->and($pesanan->total_dus)->toBe(10);
});

it('mengembalikan kuncian stok saat pesanan dibatalkan', function () {
    $pesanan = buatPesanan(10);

    $this->service->batalkan($pesanan, $this->admin, 'Salah input', 'Catatan uji');

    $pesanan->refresh();
    $this->produk->refresh();

    expect($pesanan->status)->toBe(StatusPesanan::Cancel)
        ->and($pesanan->alasan_cancel)->toBe('Salah input')
        ->and($pesanan->dibatalkan_oleh)->toBe($this->admin->id)
        ->and($this->produk->stok)->toBe(100)
        ->and($this->produk->stok_reserved)->toBe(0);

    $this->assertDatabaseHas('stok_mutasis', [
        'pesanan_id' => $pesanan->id,
        'tipe' => 'release',
        'jumlah' => 10,
    ]);
});

it('menaikkan status dari ORDER ke PROCESS', function () {
    $pesanan = buatPesanan(10);

    $this->service->setujui($pesanan, $this->admin);

    $pesanan->refresh();

    expect($pesanan->status)->toBe(StatusPesanan::Process)
        ->and($pesanan->diproses_oleh)->toBe($this->admin->id)
        ->and($pesanan->diproses_at)->not->toBeNull();
});

it('menolak persetujuan pesanan yang statusnya bukan ORDER', function () {
    $pesanan = buatPesanan(10);
    $this->service->setujui($pesanan, $this->admin);

    expect(fn () => $this->service->setujui($pesanan->fresh(), $this->admin))
        ->toThrow(RuntimeException::class);
});

it('tidak bisa membatalkan pesanan yang sudah selesai', function () {
    $pesanan = buatPesanan(10);
    $pesanan->update(['status' => StatusPesanan::Selesai]);

    expect(fn () => $this->service->batalkan($pesanan, $this->admin, 'Apa pun'))
        ->toThrow(RuntimeException::class);
});
