<?php

use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Statistik\DusTerjualDriver;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use App\Models\RoutingBatch;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Carbon\CarbonInterface;
use Livewire\Livewire;

/**
 * Dus Terjual Driver: kriteria PERSIS sama dengan Insentif Sales (lihat
 * InsentifSalesTest.php) — dus yang benar-benar terantar, dihitung dari
 * siapa yang MENGINPUT pesanannya — bedanya cuma peran penginputnya:
 * di sini pesanan kampas yang driver input sendiri saat di lapangan.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1_000, 'harga' => 10_000]);
});

/** Sama seperti buatPesananSelesai() di InsentifSalesTest.php — murni menguji agregasi DusTerjualDriver. */
function buatPesananSelesaiDtd(
    User $pembuat,
    int $totalDus = 10,
    ?CarbonInterface $selesaiAt = null,
    JenisPesanan $jenis = JenisPesanan::Kampas,
    ?string $namaToko = null,
): Pesanan {
    static $n = 0;
    $n++;

    $tanggal = $selesaiAt ?? now();

    $toko = Toko::create([
        'kode' => sprintf('TK-DTD%04d', $n),
        'nama' => $namaToko ?? "Toko DTD {$n}",
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. DTD',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = Pesanan::create([
        'kode' => sprintf('PSN-DTD-%04d', $n),
        'toko_id' => $toko->id,
        'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => $pembuat->id,
        'status' => StatusPesanan::Selesai,
        'jenis' => $jenis,
        'tanggal' => today(),
        'total_dus' => $totalDus,
        'total_nilai' => $totalDus * 10_000,
        'selesai_at' => $tanggal,
        'status_bayar' => 'lunas',
        'tanggal_lunas' => $tanggal->toDateString(),
    ]);

    $pesanan->items()->create([
        'produk_id' => test()->produk->id,
        'jumlah_dus' => $totalDus,
        'harga_satuan' => 10_000,
        'subtotal' => $totalDus * 10_000,
    ]);

    if (in_array($jenis, [JenisPesanan::Normal, JenisPesanan::Kampas], true)) {
        $batch = RoutingBatch::create([
            'kode' => sprintf('RB-DTD-%04d', $n),
            'tanggal' => $tanggal->toDateString(),
            'status' => 'disetujui',
            'total_kendaraan' => 1,
            'total_toko' => 1,
            'total_dus' => $totalDus,
            'dibuat_oleh' => $pembuat->id,
        ]);

        $kendaraan = Kendaraan::create([
            'routing_batch_id' => $batch->id,
            'nomor' => 1,
            'nama' => 'Mobil DTD '.$n,
            'total_toko' => 1,
            'total_dus' => $totalDus,
            'target_dus' => $totalDus,
            'status' => 'selesai',
            'tanggal' => $tanggal->toDateString(),
        ]);

        KendaraanStop::create([
            'kendaraan_id' => $kendaraan->id,
            'pesanan_id' => $pesanan->id,
            'toko_id' => $toko->id,
            'urutan' => 1,
            'total_dus' => $totalDus,
            'total_dus_terkirim' => $totalDus,
            'status' => 'selesai',
            'selesai_at' => $tanggal,
        ]);
    }

    return $pesanan->fresh(['stop.kendaraan']);
}

it('menolak akses selain admin', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($sales)->get(route('statistik.dus-terjual-driver'))->assertForbidden();
});

it('mode default adalah bulanan', function () {
    Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->assertSet('mode', 'bulan');
});

it('menghitung total dus terkirim per driver, terurut dari yang terbanyak', function () {
    $driverA = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver A']);
    $driverB = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver B']);

    buatPesananSelesaiDtd($driverA, totalDus: 10);
    buatPesananSelesaiDtd($driverA, totalDus: 15);
    buatPesananSelesaiDtd($driverB, totalDus: 5);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(2)
        ->and($perDriver[0]['nama'])->toBe('Driver A')
        ->and($perDriver[0]['total_dus'])->toBe(25)
        ->and($perDriver[0]['total_pesanan'])->toBe(2)
        ->and($perDriver[1]['nama'])->toBe('Driver B')
        ->and($perDriver[1]['total_dus'])->toBe(5);
});

it('menghitung dus yang benar-benar terkirim, bukan jumlah pesanan mentah', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $pesanan = buatPesananSelesaiDtd($driver, totalDus: 20);
    // Nota dicoret: toko cuma menerima 12 dari 20 dus yang dikampaskan.
    $pesanan->items()->first()->update(['jumlah_dus_terkirim' => 12]);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver[0]['total_dus'])->toBe(12);
});

it('tidak menghitung pesanan yang belum atau tidak selesai', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatPesananSelesaiDtd($driver, totalDus: 10);

    $toko = Toko::create([
        'kode' => 'TK-DTD-BATAL', 'nama' => 'Toko Batal', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Batal', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);
    Pesanan::create([
        'kode' => 'PSN-DTD-BATAL', 'toko_id' => $toko->id, 'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => $driver->id, 'status' => StatusPesanan::Cancel, 'jenis' => JenisPesanan::Kampas,
        'tanggal' => today(), 'total_dus' => 999, 'total_nilai' => 0,
    ]);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['total_dus'])->toBe(10);
});

/**
 * Kebalikan dari tes yang sama di InsentifSalesTest.php: di sini pesanan
 * yang diinput SALES yang harus dikecualikan, karena Dus Terjual Driver
 * murni tentang pesanan yang driver input sendiri.
 */
it('tidak menghitung pesanan yang diinput sales, hanya yang diinput driver', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatPesananSelesaiDtd($driver, totalDus: 10);
    buatPesananSelesaiDtd($this->sales, totalDus: 999, jenis: JenisPesanan::Normal);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['total_dus'])->toBe(10);
});

it('menghitung jumlah toko unik yang dilayani, bukan jumlah pesanan', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);
    $toko = Toko::create([
        'kode' => 'TK-DTD-ULANG', 'nama' => 'Toko Berulang', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Ulang', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    foreach ([5, 7] as $i => $dus) {
        $pesanan = Pesanan::create([
            'kode' => "PSN-DTD-ULANG-{$i}", 'toko_id' => $toko->id, 'wilayah_id' => $toko->wilayah_id,
            'dibuat_oleh' => $driver->id, 'status' => StatusPesanan::Selesai, 'jenis' => JenisPesanan::Kampas,
            'tanggal' => today(), 'total_dus' => $dus, 'total_nilai' => $dus * 10_000, 'selesai_at' => now(),
        ]);
        $pesanan->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => $dus, 'harga_satuan' => 10_000, 'subtotal' => $dus * 10_000]);
    }

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver[0]['total_pesanan'])->toBe(2)
        ->and($perDriver[0]['total_toko'])->toBe(1);
});

it('mode hari hanya menghitung pesanan yang selesai pada tanggal itu', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatPesananSelesaiDtd($driver, totalDus: 10, selesaiAt: today());
    buatPesananSelesaiDtd($driver, totalDus: 20, selesaiAt: today()->subDays(3));

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'hari')
        ->set('tanggal', today()->toDateString())
        ->instance()->perDriver();

    expect($perDriver[0]['total_dus'])->toBe(10);
});

it('mode bulan hanya menghitung pesanan yang selesai pada bulan itu', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatPesananSelesaiDtd($driver, totalDus: 10, selesaiAt: today());
    buatPesananSelesaiDtd($driver, totalDus: 20, selesaiAt: today()->subMonthsNoOverflow(2));

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'bulan')
        ->set('bulan', today()->format('Y-m'))
        ->instance()->perDriver();

    expect($perDriver[0]['total_dus'])->toBe(10);
});

it('mode rentang menghitung pesanan di antara dua tanggal inklusif', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatPesananSelesaiDtd($driver, totalDus: 10, selesaiAt: today()->subDays(2));
    buatPesananSelesaiDtd($driver, totalDus: 20, selesaiAt: today()->subDays(30));

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'rentang')
        ->set('dariTanggal', today()->subDays(5)->toDateString())
        ->set('sampaiTanggal', today()->toDateString())
        ->instance()->perDriver();

    expect($perDriver)->toHaveCount(1)
        ->and($perDriver[0]['total_dus'])->toBe(10);
});

it('mode semua tidak menyaring tanggal sama sekali', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    buatPesananSelesaiDtd($driver, totalDus: 10, selesaiAt: today());
    buatPesananSelesaiDtd($driver, totalDus: 20, selesaiAt: today()->subYears(2));

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver[0]['total_dus'])->toBe(30);
});

it('dus bonus tanpa promo tidak pernah ikut terhitung, bahkan pada pesanan yang penginputnya driver', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $pesanan = buatPesananSelesaiDtd($driver, totalDus: 10);
    $pesanan->items()->create([
        'produk_id' => $this->produk->id,
        'jumlah_dus' => 999,
        'harga_satuan' => 0,
        'subtotal' => 0,
        'is_bonus' => true,
    ]);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver[0]['total_dus'])->toBe(10);
});

it('dus bonus promo ikut terhitung penuh, beda dari bonus manual', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $promo = Promo::create([
        'nama' => 'Promo DTD Uji',
        'tanggal_mulai' => today()->subDay(),
        'tanggal_selesai' => today()->addDay(),
        'minimal_dus' => 15,
        'bonus_dus' => 1,
    ]);
    $promo->produks()->sync([$this->produk->id]);

    $pesanan = buatPesananSelesaiDtd($driver, totalDus: 15);
    $pesanan->update(['promo_id' => $promo->id]);
    $pesanan->items()->create([
        'produk_id' => $this->produk->id,
        'jumlah_dus' => 1,
        'harga_satuan' => 0,
        'subtotal' => 0,
        'is_bonus' => true,
    ]);

    $perDriver = Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->instance()->perDriver();

    expect($perDriver[0]['total_dus'])->toBe(16);
});

it('halaman tampil dengan beberapa driver sekaligus tanpa lazy load', function () {
    $driverA = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver Satu']);
    $driverB = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver Dua']);
    $driverC = User::factory()->create(['role' => PeranPengguna::Driver, 'name' => 'Driver Tiga']);

    buatPesananSelesaiDtd($driverA, totalDus: 10);
    buatPesananSelesaiDtd($driverB, totalDus: 8);
    buatPesananSelesaiDtd($driverC, totalDus: 6);

    Livewire::actingAs($this->admin)
        ->test(DusTerjualDriver::class)
        ->set('mode', 'semua')
        ->assertSee('Driver Satu')
        ->assertSee('Driver Dua')
        ->assertSee('Driver Tiga')
        ->assertOk();
});

it('halaman /statistik/dus-terjual-driver memuat lewat HTTP sungguhan', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);
    buatPesananSelesaiDtd($driver, totalDus: 10);

    $this->actingAs($this->admin)
        ->get(route('statistik.dus-terjual-driver'))
        ->assertOk()
        ->assertSee($driver->name);
});
