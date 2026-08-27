<?php

use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Livewire\Pembayaran\Pendapatan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Livewire\Livewire;

/**
 * Penyaring pada tabel "Riwayat Pendapatan per Pesanan" — kode/toko, kategori
 * (pengantaran driver vs POS), dan metode bayar (cash/transfer). Sengaja
 * hanya menyaring tabel riwayat, bukan kartu ringkasan di atasnya — lihat
 * catatan pada properti riwayat* di Pendapatan.php.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1000, 'harga' => 10_000]);
});

/** Pesanan lunas langsung dibuat lewat Eloquent — yang diuji di sini murni logika penyaringan Pendapatan, bukan alur pembuatan pesanan. */
function buatPesananLunas(array $override = []): Pesanan
{
    static $n = 0;
    $n++;

    $toko = Toko::create([
        'kode' => sprintf('TK-PR%04d', $n),
        'nama' => $override['toko_nama'] ?? "Toko Riwayat {$n}",
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Riwayat',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);

    $pesanan = Pesanan::create(array_merge([
        'kode' => $override['kode'] ?? sprintf('PSN-RIW-%04d', $n),
        'toko_id' => $toko->id,
        'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => test()->sales->id,
        'status' => StatusPesanan::Selesai,
        'jenis' => $override['jenis'] ?? JenisPesanan::Normal,
        'tanggal' => today(),
        'total_dus' => 5,
        'total_nilai' => 100_000,
        'status_bayar' => StatusBayar::Lunas,
        'tanggal_lunas' => today(),
        'dilunasi_oleh' => test()->admin->id,
        'nominal_cash' => $override['nominal_cash'] ?? 100_000,
        'nominal_transfer' => $override['nominal_transfer'] ?? 0,
    ], $override['pesanan'] ?? []));

    $pesanan->items()->create([
        'produk_id' => test()->produk->id,
        'jumlah_dus' => 5,
        'harga_satuan' => 20_000,
        'subtotal' => 100_000,
    ]);

    return $pesanan;
}

it('menyaring riwayat lewat kode pesanan', function () {
    buatPesananLunas(['kode' => 'PSN-CARI-0001']);
    buatPesananLunas(['kode' => 'PSN-LAIN-0002']);

    $riwayat = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatCari', 'CARI')
        ->instance()->riwayat();

    expect($riwayat)->toHaveCount(1)
        ->and($riwayat->first()->kode)->toBe('PSN-CARI-0001');
});

it('menyaring riwayat lewat nama toko', function () {
    buatPesananLunas(['toko_nama' => 'Toko Mangga Dua']);
    buatPesananLunas(['toko_nama' => 'Toko Kelapa Gading']);

    $riwayat = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatCari', 'mangga')
        ->instance()->riwayat();

    expect($riwayat)->toHaveCount(1)
        ->and($riwayat->first()->toko->nama)->toBe('Toko Mangga Dua');
});

it('pencarian yang tidak cocok menampilkan tabel kosong dengan pesan yang tepat', function () {
    buatPesananLunas();

    Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatCari', 'TIDAK-ADA-YANG-COCOK')
        ->assertSee(__('pembayaran.riwayat_tidak_cocok'))
        ->assertDontSee(__('pembayaran.riwayat_kosong'));
});

it('menyaring riwayat menurut kategori pos', function () {
    buatPesananLunas(['jenis' => JenisPesanan::Normal]);
    buatPesananLunas(['jenis' => JenisPesanan::Pos]);
    buatPesananLunas(['jenis' => JenisPesanan::Kampas]);

    $riwayat = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatKategori', 'pos')
        ->instance()->riwayat();

    expect($riwayat)->toHaveCount(1)
        ->and($riwayat->first()->jenis)->toBe(JenisPesanan::Pos);
});

it('menyaring riwayat menurut kategori driver, mencakup rute biasa dan kampas', function () {
    buatPesananLunas(['jenis' => JenisPesanan::Normal]);
    buatPesananLunas(['jenis' => JenisPesanan::Kampas]);
    buatPesananLunas(['jenis' => JenisPesanan::Pos]);

    $riwayat = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatKategori', 'driver')
        ->instance()->riwayat();

    expect($riwayat)->toHaveCount(2)
        ->and($riwayat->pluck('jenis')->all())->toEqualCanonicalizing([JenisPesanan::Normal, JenisPesanan::Kampas]);
});

it('menyaring riwayat menurut metode cash', function () {
    buatPesananLunas(['nominal_cash' => 100_000, 'nominal_transfer' => 0]);
    buatPesananLunas(['nominal_cash' => 0, 'nominal_transfer' => 100_000]);

    $riwayat = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatMetode', 'cash')
        ->instance()->riwayat();

    expect($riwayat)->toHaveCount(1)
        ->and((float) $riwayat->first()->nominal_cash)->toBeGreaterThan(0);
});

it('menyaring riwayat menurut metode transfer', function () {
    buatPesananLunas(['nominal_cash' => 100_000, 'nominal_transfer' => 0]);
    buatPesananLunas(['nominal_cash' => 0, 'nominal_transfer' => 100_000]);

    $riwayat = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatMetode', 'transfer')
        ->instance()->riwayat();

    expect($riwayat)->toHaveCount(1)
        ->and((float) $riwayat->first()->nominal_transfer)->toBeGreaterThan(0);
});

/**
 * Pembayaran campuran (sebagian cash, sebagian transfer) tetap dianggap
 * "memakai cash" DAN "memakai transfer" — bukan cuma salah satu. Filternya
 * menjawab "transaksi ini ada unsur cash-nya?", bukan "metode tunggalnya
 * apa?", karena Pelunasan/POS memang mengizinkan pembayaran campuran.
 */
it('pembayaran campuran cash dan transfer muncul di kedua penyaring metode', function () {
    buatPesananLunas(['kode' => 'PSN-CAMPUR-0001', 'nominal_cash' => 60_000, 'nominal_transfer' => 40_000]);

    $test = Livewire::actingAs($this->admin)->test(Pendapatan::class)->set('mode', 'semua');

    expect($test->set('riwayatMetode', 'cash')->instance()->riwayat())->toHaveCount(1);
    expect($test->set('riwayatMetode', 'transfer')->instance()->riwayat())->toHaveCount(1);
});

it('bersihkan filter riwayat mengembalikan seluruh transaksi', function () {
    buatPesananLunas(['jenis' => JenisPesanan::Pos]);
    buatPesananLunas(['jenis' => JenisPesanan::Normal]);

    $test = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatKategori', 'pos')
        ->set('riwayatCari', 'sesuatu yang tidak cocok');

    expect($test->instance()->riwayat())->toHaveCount(0);

    $test->call('bersihkanFilterRiwayat');

    expect($test->get('riwayatCari'))->toBe('')
        ->and($test->get('riwayatKategori'))->toBe('')
        ->and($test->get('riwayatMetode'))->toBe('')
        ->and($test->instance()->riwayat())->toHaveCount(2);
});

/**
 * Penyaring riwayat sengaja TIDAK memengaruhi kartu ringkasan/kategori di
 * atas tabel — kartu itu tetap gambaran keseluruhan rentang tanggal, tabel
 * riwayat adalah alat cari yang menyaring di dalamnya.
 */
it('penyaring riwayat tidak mengubah kartu ringkasan kategori', function () {
    buatPesananLunas(['jenis' => JenisPesanan::Pos]);
    buatPesananLunas(['jenis' => JenisPesanan::Normal]);

    $kategori = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->set('riwayatKategori', 'pos')
        ->instance()->totalPerKategori();

    expect($kategori['pos']['jumlah'])->toBe(1)
        ->and($kategori['driver']['jumlah'])->toBe(1);
});

it('menampilkan filter riwayat lewat blade dengan beberapa transaksi sekaligus', function () {
    buatPesananLunas(['toko_nama' => 'Toko Filter Blade', 'jenis' => JenisPesanan::Pos]);
    buatPesananLunas(['toko_nama' => 'Toko Filter Blade Dua', 'jenis' => JenisPesanan::Normal]);

    Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->assertSee('Toko Filter Blade')
        ->assertSee('Toko Filter Blade Dua')
        ->assertSee(__('pembayaran.cari_riwayat'));
});
