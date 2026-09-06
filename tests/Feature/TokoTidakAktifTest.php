<?php

use App\Enums\HariKunjungan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kunjungan\PenugasanTokoService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * "Toko Belum Pesan 1 Bulan" di menu Pesanan: toko yang jadi tanggungan
 * sales saat ini (jadwal mingguan Penugasan Toko, berdiri terus) tapi
 * belum punya pesanan SELESAI dalam 1 bulan terakhir, dikelompokkan per
 * sales.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1_000, 'harga' => 10_000]);
    $this->penugasan = app(PenugasanTokoService::class);
});

function buatTokoTA(string $nama): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-TA%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Tidak Aktif',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);
}

/** Pesanan SELESAI dibuat langsung lewat Eloquent, selesai_at bebas ditentukan. */
function buatPesananSelesaiTA(Toko $toko, CarbonImmutable $selesaiAt): Pesanan
{
    static $n = 0;
    $n++;

    $pesanan = Pesanan::create([
        'kode' => "PSN-TA-{$n}",
        'toko_id' => $toko->id,
        'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => test()->admin->id,
        'status' => StatusPesanan::Selesai,
        'jenis' => 'normal',
        'tanggal' => $selesaiAt->toDateString(),
        'total_dus' => 5,
        'total_nilai' => 50_000,
        'selesai_at' => $selesaiAt,
    ]);

    $pesanan->items()->create([
        'produk_id' => test()->produk->id,
        'jumlah_dus' => 5,
        'harga_satuan' => 10_000,
        'subtotal' => 50_000,
    ]);

    return $pesanan;
}

it('menampilkan toko yang ditugaskan tapi belum pernah pesan sama sekali', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales A']);
    $toko = buatTokoTA('Toko Belum Pernah Pesan');

    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$toko->id], $this->admin);

    $hasil = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->instance()->tokoTidakAktifSemua();

    expect($hasil)->toHaveCount(1)
        ->and($hasil[0]['sales']->id)->toBe($sales->id)
        ->and($hasil[0]['tokos']->pluck('id'))->toContain($toko->id);
});

it('menampilkan toko yang pesanan terakhirnya selesai LEBIH dari 1 bulan lalu', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = buatTokoTA('Toko Lama Tidak Pesan');
    buatPesananSelesaiTA($toko, CarbonImmutable::now()->subDays(40));

    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$toko->id], $this->admin);

    $hasil = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->instance()->tokoTidakAktifSemua();

    expect($hasil)->toHaveCount(1)
        ->and($hasil[0]['tokos']->pluck('id'))->toContain($toko->id);
});

it('TIDAK menampilkan toko yang pesanan terakhirnya selesai KURANG dari 1 bulan lalu', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = buatTokoTA('Toko Baru Saja Pesan');
    buatPesananSelesaiTA($toko, CarbonImmutable::now()->subDays(10));

    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$toko->id], $this->admin);

    $hasil = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->instance()->tokoTidakAktifSemua();

    expect($hasil)->toHaveCount(0);
});

/**
 * Pesanan yang masih ORDER/PROCESS/DELIVERY (belum SELESAI) tidak boleh
 * membuat toko dianggap "aktif" — yang dihitung khusus pesanan yang
 * BENAR-BENAR tuntas, bukan sekadar pernah diinput.
 */
it('pesanan yang belum SELESAI tidak membuat toko dianggap aktif', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = buatTokoTA('Toko Pesanan Menggantung');

    Pesanan::create([
        'kode' => 'PSN-TA-GANTUNG', 'toko_id' => $toko->id, 'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => $this->admin->id, 'status' => StatusPesanan::Process, 'jenis' => 'normal',
        'tanggal' => today(), 'total_dus' => 5, 'total_nilai' => 50_000,
    ]);

    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$toko->id], $this->admin);

    $hasil = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->instance()->tokoTidakAktifSemua();

    expect($hasil)->toHaveCount(1)
        ->and($hasil[0]['tokos']->pluck('id'))->toContain($toko->id);
});

it('tidak menampilkan toko yang tidak ditugaskan ke sales mana pun, meski tidak aktif', function () {
    buatTokoTA('Toko Tanpa Penugasan');

    $hasil = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->instance()->tokoTidakAktifSemua();

    expect($hasil)->toHaveCount(0);
});

it('mengelompokkan toko per sales, bukan satu daftar gabungan', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales A']);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales B']);
    $tokoA1 = buatTokoTA('Toko A1');
    $tokoA2 = buatTokoTA('Toko A2');
    $tokoB1 = buatTokoTA('Toko B1');

    $this->penugasan->tetapkan($salesA, HariKunjungan::Senin, [$tokoA1->id, $tokoA2->id], $this->admin);
    $this->penugasan->tetapkan($salesB, HariKunjungan::Selasa, [$tokoB1->id], $this->admin);

    $hasil = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)->instance()->tokoTidakAktifSemua();

    expect($hasil)->toHaveCount(2);

    $grupA = $hasil->firstWhere(fn ($g) => $g['sales']->id === $salesA->id);
    $grupB = $hasil->firstWhere(fn ($g) => $g['sales']->id === $salesB->id);

    expect($grupA['tokos'])->toHaveCount(2)
        ->and($grupB['tokos'])->toHaveCount(1);
});

it('filter sales menyaring daftar yang ditampilkan', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales A']);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales B']);
    $tokoA = buatTokoTA('Toko A');
    $tokoB = buatTokoTA('Toko B');

    $this->penugasan->tetapkan($salesA, HariKunjungan::Senin, [$tokoA->id], $this->admin);
    $this->penugasan->tetapkan($salesB, HariKunjungan::Selasa, [$tokoB->id], $this->admin);

    $test = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
        ->set('filterSalesTidakAktif', (string) $salesA->id);

    $hasil = $test->instance()->tokoTidakAktif();

    expect($hasil)->toHaveCount(1)
        ->and($hasil[0]['sales']->id)->toBe($salesA->id);

    // Badge (jumlah total) tetap menunjukkan angka SEBENARNYA, tidak
    // terpengaruh filter yang sedang aktif di modal.
    expect($test->instance()->totalTokoTidakAktif())->toBe(2);
});

it('pencarian menyaring toko lewat nama atau kode', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $tokoCocok = buatTokoTA('Toko Mangga Dua');
    $tokoLain = buatTokoTA('Toko Kelapa Gading');

    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$tokoCocok->id, $tokoLain->id], $this->admin);

    $hasil = Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
        ->set('cariTokoTidakAktif', 'mangga')
        ->instance()->tokoTidakAktif();

    expect($hasil)->toHaveCount(1)
        ->and($hasil[0]['tokos'])->toHaveCount(1)
        ->and($hasil[0]['tokos']->first()->id)->toBe($tokoCocok->id);
});

it('tutupTokoTidakAktif mengosongkan filter', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = buatTokoTA('Toko X');
    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$toko->id], $this->admin);

    Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
        ->set('filterSalesTidakAktif', (string) $sales->id)
        ->set('cariTokoTidakAktif', 'x')
        ->call('bukaTokoTidakAktif')
        ->call('tutupTokoTidakAktif')
        ->assertSet('filterSalesTidakAktif', '')
        ->assertSet('cariTokoTidakAktif', '')
        ->assertSet('tokoTidakAktifTerbuka', false);
});

/**
 * Sengaja memakai BEBERAPA sales dan toko sekaligus, bukan satu. Laravel
 * 13 diam-diam memuat relasi yang kurang (autoload) ketika modelnya cuma
 * satu, jadi pelanggaran mode ketat pada relasi `sales`/`toko` baru
 * kelihatan begitu koleksinya lebih dari satu model — pelajaran yang sama
 * seperti `InsentifSalesTest` dan `DaftarPesananFilterTest`.
 */
it('halaman tampil dengan beberapa sales dan toko sekaligus tanpa lazy load', function () {
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Satu']);
    $salesB = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Dua']);
    $tokoA = buatTokoTA('Toko Alpha');
    $tokoB = buatTokoTA('Toko Beta');
    $tokoC = buatTokoTA('Toko Gamma');

    $this->penugasan->tetapkan($salesA, HariKunjungan::Senin, [$tokoA->id, $tokoB->id], $this->admin);
    $this->penugasan->tetapkan($salesB, HariKunjungan::Selasa, [$tokoC->id], $this->admin);

    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->call('bukaTokoTidakAktif')
        ->assertSee('Sales Satu')
        ->assertSee('Sales Dua')
        ->assertSee('Toko Alpha')
        ->assertSee('Toko Gamma')
        ->assertOk();
});

it('menampilkan pesan khusus kalau belum ada penugasan toko sama sekali', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->call('bukaTokoTidakAktif')
        ->assertSee(__('pesanan.toko_tanpa_penugasan'));
});

it('menampilkan pesan "semua aktif" kalau penugasan ada tapi semua toko sudah pesan', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = buatTokoTA('Toko Rajin Pesan');
    buatPesananSelesaiTA($toko, CarbonImmutable::now()->subDays(3));

    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$toko->id], $this->admin);

    Livewire::actingAs($this->admin)
        ->test(DaftarPesanan::class)
        ->call('bukaTokoTidakAktif')
        ->assertSee(__('pesanan.kosong_toko_tidak_aktif'));
});

it('badge jumlah tampil di tombol pada halaman', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = buatTokoTA('Toko Badge');
    $this->penugasan->tetapkan($sales, HariKunjungan::Senin, [$toko->id], $this->admin);

    $test = Livewire::actingAs($this->admin)->test(DaftarPesanan::class);

    $test->assertSee(__('pesanan.tombol_toko_tidak_aktif'))
        // Badge (kelas bg-amber-500 yang membungkus angkanya) hanya
        // dirender kalau totalnya > 0 — lihat @if di blade.
        ->assertSeeHtml('bg-amber-500');

    expect($test->instance()->totalTokoTidakAktif())->toBe(1);
});

it('halaman /pesanan memuat lewat HTTP sungguhan dengan fitur ini', function () {
    $this->actingAs($this->admin)->get(route('pesanan.daftar'))->assertOk();
});
