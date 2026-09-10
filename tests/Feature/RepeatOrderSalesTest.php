<?php

use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Statistik\RepeatOrderSales;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Repeat Order Sales: total dus yang DIINPUT tiap sales (murni aktivitas
 * input), bukan capaian penjualan seperti Insentif Sales — SEMUA status
 * ikut dihitung, memakai `jumlah_dus` mentah (bukan `terkirim`), dan
 * disaring lewat `Pesanan::tanggal` (bukan `tanggal_pendapatan`, yang
 * belum tentu ada untuk pesanan yang belum tuntas/sudah batal).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1_000, 'harga' => 10_000]);
});

/** Pesanan dibuat langsung lewat Eloquent — murni menguji agregasi RepeatOrderSales. */
function buatPesananApaPun(
    User $pembuat,
    int $totalDus = 10,
    StatusPesanan $status = StatusPesanan::Order,
    ?CarbonImmutable $tanggal = null,
    ?int $terkirim = null,
    JenisPesanan $jenis = JenisPesanan::Normal,
    ?string $namaToko = null,
): Pesanan {
    static $n = 0;
    $n++;

    $tanggal = $tanggal ?? today();

    $toko = Toko::create([
        'kode' => sprintf('TK-ROS%04d', $n),
        'nama' => $namaToko ?? "Toko ROS {$n}",
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. ROS',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = Pesanan::create([
        'kode' => sprintf('PSN-ROS-%04d', $n),
        'toko_id' => $toko->id,
        'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => $pembuat->id,
        'status' => $status,
        'jenis' => $jenis,
        'tanggal' => $tanggal->toDateString(),
        'total_dus' => $totalDus,
        'total_nilai' => $totalDus * 10_000,
    ]);

    $pesanan->items()->create([
        'produk_id' => test()->produk->id,
        'jumlah_dus' => $totalDus,
        'jumlah_dus_terkirim' => $terkirim,
        'harga_satuan' => 10_000,
        'subtotal' => $totalDus * 10_000,
    ]);

    return $pesanan;
}

it('menolak akses selain admin', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($sales)->get(route('statistik.repeat-order-sales'))->assertForbidden();
});

it('mode default adalah bulanan', function () {
    Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->assertSet('mode', 'bulan');
});

it('menghitung SEMUA status pesanan, termasuk yang belum tuntas dan yang sudah batal', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales A']);

    buatPesananApaPun($sales, totalDus: 10, status: StatusPesanan::Order);
    buatPesananApaPun($sales, totalDus: 5, status: StatusPesanan::Process);
    buatPesananApaPun($sales, totalDus: 8, status: StatusPesanan::Delivery);
    buatPesananApaPun($sales, totalDus: 12, status: StatusPesanan::Selesai);
    buatPesananApaPun($sales, totalDus: 7, status: StatusPesanan::Cancel);

    $perSales = Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales)->toHaveCount(1)
        ->and($perSales[0]['nama'])->toBe('Sales A')
        ->and($perSales[0]['total_dus'])->toBe(42)
        ->and($perSales[0]['total_pesanan'])->toBe(5);
});

it('menghitung jumlah_dus mentah, BUKAN dus yang benar-benar terkirim', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    // Pesanan selesai tapi notanya dicoret — toko cuma menerima 3 dari 20.
    buatPesananApaPun($sales, totalDus: 20, status: StatusPesanan::Selesai, terkirim: 3);

    $perSales = Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    // Tetap 20 (yang diinput), bukan 3 (yang benar-benar terkirim) — beda
    // sengaja dari Insentif Sales.
    expect($perSales[0]['total_dus'])->toBe(20);
});

it('hanya menghitung pesanan yang diinput sales, bukan admin (POS) atau driver (kampas)', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatPesananApaPun($sales, totalDus: 10);
    buatPesananApaPun($this->admin, totalDus: 99, jenis: JenisPesanan::Pos);
    buatPesananApaPun($driver, totalDus: 99, jenis: JenisPesanan::Kampas);

    $perSales = Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales)->toHaveCount(1)
        ->and($perSales[0]['total_dus'])->toBe(10);
});

it('menyaring lewat Pesanan::tanggal, bukan tanggal_pendapatan — tetap terhitung walau belum pernah dirutekan', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $tanggal = CarbonImmutable::parse('2026-08-20');
    // Pesanan masih ORDER — belum pernah dirutekan, tidak pernah punya
    // stop/kendaraan, dan tidak pernah lunas. tanggal_pendapatan-nya akan
    // NULL untuk baris ini, tapi Pesanan::tanggal tetap ada sejak dibuat.
    buatPesananApaPun($sales, totalDus: 15, status: StatusPesanan::Order, tanggal: $tanggal);

    $ditemukan = Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->set('mode', 'hari')
        ->set('tanggal', $tanggal->toDateString())
        ->instance()->perSales();

    expect($ditemukan)->toHaveCount(1)
        ->and($ditemukan[0]['total_dus'])->toBe(15);
});

it('mode rentang mencakup tanggal di dalam batas, mengecualikan yang di luar', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananApaPun($sales, totalDus: 10, tanggal: CarbonImmutable::parse('2026-08-20'));
    buatPesananApaPun($sales, totalDus: 20, tanggal: CarbonImmutable::parse('2026-08-25'));

    $perSales = Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->set('mode', 'rentang')
        ->set('dariTanggal', '2026-08-19')
        ->set('sampaiTanggal', '2026-08-21')
        ->instance()->perSales();

    expect($perSales[0]['total_dus'])->toBe(10);
});

it('total_toko menghitung toko unik, bukan jumlah baris pesanan', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = Toko::create([
        'kode' => 'TK-ROSSAMA', 'nama' => 'Toko Sama', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Sama', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    foreach (range(1, 3) as $i) {
        Pesanan::create([
            'kode' => "PSN-ROSSAMA-{$i}", 'toko_id' => $toko->id, 'wilayah_id' => $toko->wilayah_id,
            'dibuat_oleh' => $sales->id, 'status' => StatusPesanan::Order, 'jenis' => JenisPesanan::Normal,
            'tanggal' => today(), 'total_dus' => 5, 'total_nilai' => 50_000,
        ])->items()->create([
            'produk_id' => $this->produk->id, 'jumlah_dus' => 5, 'harga_satuan' => 10_000, 'subtotal' => 50_000,
        ]);
    }

    $perSales = Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales[0]['total_pesanan'])->toBe(3)
        ->and($perSales[0]['total_toko'])->toBe(1);
});

it('terurut dari total dus terbanyak', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Kecil']);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Besar']);

    buatPesananApaPun($salesA, totalDus: 5);
    buatPesananApaPun($salesB, totalDus: 50);

    $perSales = Livewire::actingAs($this->admin)
        ->test(RepeatOrderSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales[0]['nama'])->toBe('Sales Besar')
        ->and($perSales[1]['nama'])->toBe('Sales Kecil');
});
