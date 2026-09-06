<?php

use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Insentif\InsentifSales;
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
 * Insentif Sales: berapa dus yang berhasil terantar per akun sales,
 * dihitung dari siapa yang MENGINPUT pesanannya (dibuat_oleh), bukan siapa
 * yang mengantarkannya secara fisik — itu urusan "Insentif Driver" nanti.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1_000, 'harga' => 10_000]);
});

/**
 * Pesanan SELESAI dibuat langsung lewat Eloquent — yang diuji di sini
 * murni logika agregasi InsentifSales, bukan alur pengiriman/POS itu
 * sendiri (yang sudah punya tesnya masing-masing).
 *
 * Insentif Sales sekarang mengelompokkan tanggal lewat
 * `Pesanan::tanggal_pendapatan` (tanggal KEBERANGKATAN kendaraan untuk
 * kategori driver, bukan `selesai_at`) — jadi untuk jenis Normal/Kampas,
 * helper ini juga membuat rantai minimal RoutingBatch → Kendaraan →
 * KendaraanStop langsung lewat Eloquent (bukan RoutingService
 * sesungguhnya, tetap konsisten dengan semangat helper ini), dengan
 * tanggal keberangkatan batch-nya DISAMAKAN dengan `$selesaiAt` supaya
 * parameter yang sama tetap bisa dipakai memanipulasi "tanggal" pada
 * tes-tes penyaring hari/bulan/rentang di bawah. Jenis Pos tidak pernah
 * lewat kendaraan sama sekali, jadi cukup tanggal_lunas yang diisi.
 */
function buatPesananSelesai(
    User $pembuat,
    int $totalDus = 10,
    ?CarbonInterface $selesaiAt = null,
    JenisPesanan $jenis = JenisPesanan::Normal,
    ?string $namaToko = null,
): Pesanan {
    static $n = 0;
    $n++;

    $tanggal = $selesaiAt ?? now();

    $toko = Toko::create([
        'kode' => sprintf('TK-IS%04d', $n),
        'nama' => $namaToko ?? "Toko Insentif {$n}",
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Insentif',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = Pesanan::create([
        'kode' => sprintf('PSN-IS-%04d', $n),
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
            'kode' => sprintf('RB-IS-%04d', $n),
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
            'nama' => 'Mobil IS '.$n,
            'total_toko' => 1,
            'total_dus' => $totalDus,
            'target_dus' => $totalDus,
            'status' => 'selesai',
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

    return $pesanan->fresh(['stop.kendaraan.batch']);
}

it('menolak akses selain admin', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($sales)->get(route('insentif.sales'))->assertForbidden();
});

it('mode default adalah bulanan', function () {
    Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->assertSet('mode', 'bulan');
});

it('menghitung total dus terkirim per sales, terurut dari yang terbanyak', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales A']);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales B']);

    buatPesananSelesai($salesA, totalDus: 10);
    buatPesananSelesai($salesA, totalDus: 15);
    buatPesananSelesai($salesB, totalDus: 5);

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales)->toHaveCount(2)
        ->and($perSales[0]['nama'])->toBe('Sales A')
        ->and($perSales[0]['total_dus'])->toBe(25)
        ->and($perSales[0]['total_pesanan'])->toBe(2)
        ->and($perSales[1]['nama'])->toBe('Sales B')
        ->and($perSales[1]['total_dus'])->toBe(5);
});

it('menghitung dus yang benar-benar terkirim, bukan jumlah pesanan mentah', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $pesanan = buatPesananSelesai($sales, totalDus: 20);
    // Nota dicoret: toko cuma menerima 12 dari 20 dus yang dipesan.
    $pesanan->items()->first()->update(['jumlah_dus_terkirim' => 12]);

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales[0]['total_dus'])->toBe(12);
});

it('tidak menghitung pesanan yang belum atau tidak selesai', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananSelesai($sales, totalDus: 10);

    $toko = Toko::create([
        'kode' => 'TK-IS-BATAL', 'nama' => 'Toko Batal', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Batal', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);
    Pesanan::create([
        'kode' => 'PSN-IS-BATAL', 'toko_id' => $toko->id, 'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => $sales->id, 'status' => StatusPesanan::Cancel, 'jenis' => JenisPesanan::Normal,
        'tanggal' => today(), 'total_dus' => 999, 'total_nilai' => 0,
    ]);

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales)->toHaveCount(1)
        ->and($perSales[0]['total_dus'])->toBe(10);
});

/**
 * Pesanan kampas dibuat DRIVER (dibuat_oleh = id driver), bukan sales —
 * jadi otomatis tersaring lewat penyaring peran, tanpa perlu mengecualikan
 * jenisnya secara eksplisit. Ini akan jadi bagian "Insentif Driver" nanti.
 */
it('tidak menghitung pesanan kampas, karena penginputnya driver bukan sales', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananSelesai($sales, totalDus: 10);
    buatPesananSelesai($this->driver, totalDus: 999, jenis: JenisPesanan::Kampas);

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales)->toHaveCount(1)
        ->and($perSales[0]['total_dus'])->toBe(10);
});

/**
 * Penjualan POS yang diinput ADMIN sengaja tidak dihitung sebagai insentif
 * sales, tapi POS yang diinput sales sendiri ikut terhitung — insentif ini
 * soal siapa yang menjual, bukan soal jalur penjualannya (driver vs POS).
 */
it('pos yang diinput sales terhitung, pos yang diinput admin tidak', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananSelesai($sales, totalDus: 8, jenis: JenisPesanan::Pos);
    buatPesananSelesai($this->admin, totalDus: 999, jenis: JenisPesanan::Pos);

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales)->toHaveCount(1)
        ->and($perSales[0]['total_dus'])->toBe(8);
});

it('menghitung jumlah toko unik yang dilayani, bukan jumlah pesanan', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = Toko::create([
        'kode' => 'TK-IS-ULANG', 'nama' => 'Toko Berulang', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Ulang', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);

    foreach ([5, 7] as $i => $dus) {
        $pesanan = Pesanan::create([
            'kode' => "PSN-IS-ULANG-{$i}", 'toko_id' => $toko->id, 'wilayah_id' => $toko->wilayah_id,
            'dibuat_oleh' => $sales->id, 'status' => StatusPesanan::Selesai, 'jenis' => JenisPesanan::Normal,
            'tanggal' => today(), 'total_dus' => $dus, 'total_nilai' => $dus * 10_000, 'selesai_at' => now(),
        ]);
        $pesanan->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => $dus, 'harga_satuan' => 10_000, 'subtotal' => $dus * 10_000]);
    }

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales[0]['total_pesanan'])->toBe(2)
        ->and($perSales[0]['total_toko'])->toBe(1);
});

it('mode hari hanya menghitung pesanan yang selesai pada tanggal itu', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananSelesai($sales, totalDus: 10, selesaiAt: today());
    buatPesananSelesai($sales, totalDus: 20, selesaiAt: today()->subDays(3));

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'hari')
        ->set('tanggal', today()->toDateString())
        ->instance()->perSales();

    expect($perSales[0]['total_dus'])->toBe(10);
});

it('mode bulan hanya menghitung pesanan yang selesai pada bulan itu', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananSelesai($sales, totalDus: 10, selesaiAt: today());
    buatPesananSelesai($sales, totalDus: 20, selesaiAt: today()->subMonthsNoOverflow(2));

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'bulan')
        ->set('bulan', today()->format('Y-m'))
        ->instance()->perSales();

    expect($perSales[0]['total_dus'])->toBe(10);
});

it('mode rentang menghitung pesanan di antara dua tanggal inklusif', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananSelesai($sales, totalDus: 10, selesaiAt: today()->subDays(2));
    buatPesananSelesai($sales, totalDus: 20, selesaiAt: today()->subDays(30));

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'rentang')
        ->set('dariTanggal', today()->subDays(5)->toDateString())
        ->set('sampaiTanggal', today()->toDateString())
        ->instance()->perSales();

    expect($perSales)->toHaveCount(1)
        ->and($perSales[0]['total_dus'])->toBe(10);
});

it('mode semua tidak menyaring tanggal sama sekali', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    buatPesananSelesai($sales, totalDus: 10, selesaiAt: today());
    buatPesananSelesai($sales, totalDus: 20, selesaiAt: today()->subYears(2));

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales[0]['total_dus'])->toBe(30);
});

/**
 * Sengaja memakai BEBERAPA sales sekaligus, bukan satu. Laravel 13
 * diam-diam memuat relasi yang kurang (autoload) ketika modelnya cuma
 * satu, jadi pelanggaran mode ketat pada relasi `pembuat` baru kelihatan
 * begitu koleksinya lebih dari satu model — pelajaran yang sama seperti
 * `rute:perbaiki-geometry` dan `DaftarPesananFilterTest`.
 */
it('halaman tampil dengan beberapa sales sekaligus tanpa lazy load', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Satu']);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Dua']);
    $salesC = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Tiga']);

    buatPesananSelesai($salesA, totalDus: 10);
    buatPesananSelesai($salesB, totalDus: 8);
    buatPesananSelesai($salesC, totalDus: 6);

    Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->assertSee('Sales Satu')
        ->assertSee('Sales Dua')
        ->assertSee('Sales Tiga')
        ->assertOk();
});

/**
 * Item bonus MANUAL (langkah "Bonus Produk" admin/superadmin di Input
 * Pesanan/POS) secara struktural sudah tidak mungkin lolos
 * whereHas('pembuat', role Sales) di atas — cuma admin yang bisa
 * menginputnya. Baris ini menguji lapis pertahanan KEDUA di perSales():
 * dus is_bonus=true TANPA promo_id (bukan hasil promo — pemberian sepihak
 * admin) tetap dikecualikan sekalipun (secara hipotetis) muncul pada
 * pesanan yang penginputnya berperan sales.
 */
it('dus bonus tanpa promo tidak pernah ikut terhitung, bahkan pada pesanan yang penginputnya sales', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $pesanan = buatPesananSelesai($sales, totalDus: 10);
    $pesanan->items()->create([
        'produk_id' => $this->produk->id,
        'jumlah_dus' => 999,
        'harga_satuan' => 0,
        'subtotal' => 0,
        'is_bonus' => true,
    ]);

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales[0]['total_dus'])->toBe(10);
});

/**
 * Beda dari bonus manual di atas: dus bonus PROMO adalah jatah yang sales
 * benar-benar peroleh dari pencapaian jualan mereka sendiri (bukan
 * pemberian sepihak siapa pun), jadi ikut dihitung penuh ke insentif —
 * persis skenario yang dikonfirmasi pengguna: pesanan 15 dus + 1 dus bonus
 * promo = 16 dus yang dihitung, bukan cuma 15.
 */
it('dus bonus promo ikut terhitung penuh, beda dari bonus manual', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $promo = Promo::create([
        'nama' => 'Promo Insentif Uji',
        'tanggal_mulai' => today()->subDay(),
        'tanggal_selesai' => today()->addDay(),
        'minimal_dus' => 15,
        'bonus_dus' => 1,
    ]);
    $promo->produks()->sync([$this->produk->id]);

    $pesanan = buatPesananSelesai($sales, totalDus: 15);
    $pesanan->update(['promo_id' => $promo->id]);
    $pesanan->items()->create([
        'produk_id' => $this->produk->id,
        'jumlah_dus' => 1,
        'harga_satuan' => 0,
        'subtotal' => 0,
        'is_bonus' => true,
    ]);

    $perSales = Livewire::actingAs($this->admin)
        ->test(InsentifSales::class)
        ->set('mode', 'semua')
        ->instance()->perSales();

    expect($perSales[0]['total_dus'])->toBe(16);
});

it('halaman /insentif/sales memuat lewat HTTP sungguhan', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    buatPesananSelesai($sales, totalDus: 10);

    $this->actingAs($this->admin)->get(route('insentif.sales'))->assertOk();
});
