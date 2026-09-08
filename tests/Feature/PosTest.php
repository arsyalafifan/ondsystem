<?php

use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusBayar;
use App\Enums\StatusPesanan;
use App\Livewire\Master\DaftarProduk;
use App\Livewire\Pembayaran\Pendapatan;
use App\Livewire\Pesanan\BuatPesanan;
use App\Livewire\Pos\Kasir;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Point of Sale — penjualan langsung di tempat, tidak lewat pengantaran
 * driver. Mengikuti pola kampas: langsung SELESAI dan LUNAS begitu dibuat,
 * stok fisik berkurang seketika, tidak ada minimal pembelian, dan tidak
 * pernah membuat KendaraanStop.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 50, 'harga' => 20_000]);

    $this->service = app(PesananService::class);
});

function buatTokoPos(string $nama = 'Toko POS'): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-POS%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. POS No. '.$n,
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);
}

// --- PesananService::buatPos() ---------------------------------------

it('membuat pesanan POS langsung SELESAI dan LUNAS tanpa fase DELIVERY', function () {
    $toko = buatTokoPos();

    $pesanan = $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 3]],
        penjual: $this->sales,
        nominalCash: 60_000,
        nominalTransfer: 0,
    );

    expect($pesanan->status)->toBe(StatusPesanan::Selesai)
        ->and($pesanan->jenis)->toBe(JenisPesanan::Pos)
        ->and($pesanan->status_bayar)->toBe(StatusBayar::Lunas)
        ->and($pesanan->nominal_cash)->toEqualWithDelta(60_000, 0.01)
        ->and($pesanan->total_dus)->toBe(3)
        ->and($pesanan->total_nilai)->toEqualWithDelta(60_000, 0.01)
        ->and($pesanan->dibuat_oleh)->toBe($this->sales->id)
        ->and($pesanan->dilunasi_oleh)->toBe($this->sales->id)
        ->and($pesanan->stop()->exists())->toBeFalse();
});

it('mengurangi stok fisik produk seketika, bukan lewat reservasi', function () {
    $toko = buatTokoPos();
    $stokAwal = $this->produk->stok;

    $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 4]],
        penjual: $this->sales,
        nominalCash: 80_000,
        nominalTransfer: 0,
    );

    $produk = $this->produk->fresh();

    expect($produk->stok)->toBe($stokAwal - 4)
        ->and($produk->stok_reserved)->toBe(0);
});

it('tidak menuntut minimal dus — satu dus saja boleh', function () {
    // Konfigurasi default: minimal 5 dus untuk pesanan biasa.
    expect($this->depot->min_dus_per_toko)->toBeGreaterThan(1);

    $toko = buatTokoPos();

    $pesanan = $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 1]],
        penjual: $this->sales,
        nominalCash: 20_000,
        nominalTransfer: 0,
    );

    expect($pesanan->total_dus)->toBe(1);
});

it('tetap melayani toko yang masih punya pesanan pengantaran aktif', function () {
    $toko = buatTokoPos();
    $this->service->buat($toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 10]], $this->sales);

    $pesanan = $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 2]],
        penjual: $this->sales,
        nominalCash: 40_000,
        nominalTransfer: 0,
    );

    expect($pesanan->jenis)->toBe(JenisPesanan::Pos);
});

it('menolak kalau stok fisik tidak cukup', function () {
    $toko = buatTokoPos();

    expect(fn () => $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 999]],
        penjual: $this->sales,
        nominalCash: 999 * 20_000,
        nominalTransfer: 0,
    ))->toThrow(ValidationException::class);

    expect($this->produk->fresh()->stok)->toBe(50);
});

/**
 * `PesananService::buatPos()` sebelumnya memeriksa `stok` fisik mentah,
 * bukan `stok_tersedia` — jadi dus yang sudah dikunci pesanan pengantaran
 * lain (belum dikirim sama sekali) masih bisa dijual lagi lewat POS, dus
 * yang sama terjanjikan dua kali. Diperbaiki dengan menyamakan
 * pemeriksaannya ke `stok_tersedia`, sama seperti buat().
 */
it('menolak POS kalau stok sedang terkunci pesanan pengantaran lain, walau stok fisik masih terlihat cukup', function () {
    $toko = buatTokoPos();

    // Toko lain memesan 48 dari 50 dus lewat jalur pengantaran biasa —
    // belum terkirim sama sekali, jadi masih terkunci (stok_reserved).
    $this->service->buat($toko, [['produk_id' => $this->produk->id, 'jumlah_dus' => 48]], $this->sales);

    expect($this->produk->fresh()->stok_tersedia)->toBe(2);

    // 2 dus yang benar-benar bebas tetap boleh dijual lewat POS...
    $pesanan = $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 2]],
        penjual: $this->sales,
        nominalCash: 40_000,
        nominalTransfer: 0,
    );

    expect($pesanan->total_dus)->toBe(2);

    // ...tapi kuncian 48 dus milik pesanan pengantaran itu TIDAK ikut
    // terpotong oleh penjualan POS ini — keluarkanStokLangsung() sengaja
    // tidak pernah menyentuh stok_reserved sama sekali, beda dari
    // keluarkanStok() yang dipakai pesanan biasa.
    $produk = $this->produk->fresh();
    expect($produk->stok_reserved)->toBe(48)
        ->and($produk->stok)->toBe(48)
        ->and($produk->stok_tersedia)->toBe(0);

    // Tidak ada lagi sisa yang benar-benar bebas — ditolak.
    expect(fn () => $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 1]],
        penjual: $this->sales,
        nominalCash: 20_000,
        nominalTransfer: 0,
    ))->toThrow(ValidationException::class);
});

it('menolak kalau cash + transfer tidak sama dengan total belanja', function () {
    $toko = buatTokoPos();

    expect(fn () => $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 2]],
        penjual: $this->sales,
        nominalCash: 10_000,
        nominalTransfer: 0,
    ))->toThrow(ValidationException::class);

    expect(Pesanan::count())->toBe(0)
        ->and($this->produk->fresh()->stok)->toBe(50);
});

it('menolak toko yang belum punya wilayah', function () {
    $toko = buatTokoPos();
    $toko->update(['wilayah_id' => null]);

    expect(fn () => $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 1]],
        penjual: $this->sales,
        nominalCash: 20_000,
        nominalTransfer: 0,
    ))->toThrow(ValidationException::class);
});

// --- Livewire\Pos\Kasir ------------------------------------------------

it('menyelesaikan penjualan lewat layar kasir dari awal sampai akhir', function () {
    $toko = buatTokoPos('Toko Kasir Lengkap');

    $test = Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->set('cariToko', 'Kasir Lengkap')
        ->call('pilihToko', $toko->id)
        ->set('baris.0.produk_id', $this->produk->id)
        ->set('baris.0.jumlah_dus', 2)
        ->call('isiTotalBelanja')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertSet('tokoId', null);

    $pesanan = Pesanan::where('toko_id', $toko->id)->first();

    expect($pesanan)->not->toBeNull()
        ->and($pesanan->jenis)->toBe(JenisPesanan::Pos)
        ->and($pesanan->status)->toBe(StatusPesanan::Selesai)
        ->and((float) $pesanan->nominal_cash)->toEqualWithDelta(40_000, 0.01)
        ->and((float) $pesanan->nominal_transfer)->toBe(0.0)
        ->and($this->produk->fresh()->stok)->toBe(48);

    // `kodeTerakhir`/`idTerakhir` sengaja TIDAK ikut ter-reset bersama
    // field form lainnya — keduanya dipakai banner sukses untuk
    // menampilkan kode transaksi sekaligus link "Cetak Nota".
    $test->assertSet('kodeTerakhir', $pesanan->kode)
        ->assertSet('idTerakhir', $pesanan->id);
});

it('kasir menambah baris dari barcode yang cocok', function () {
    $this->produk->update(['barcode' => '8991234567890']);

    Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->set('cariBarcode', '8991234567890')
        ->call('tambahDariBarcode')
        ->assertSet('baris.0.produk_id', $this->produk->id)
        ->assertSet('baris.0.jumlah_dus', 1);
});

it('memindai barcode yang sama dua kali menambah jumlahnya, bukan baris baru', function () {
    $this->produk->update(['barcode' => '8991234567890']);

    Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->set('cariBarcode', '8991234567890')
        ->call('tambahDariBarcode')
        ->set('cariBarcode', '8991234567890')
        ->call('tambahDariBarcode')
        ->assertSet('baris.0.jumlah_dus', 2)
        ->assertCount('baris', 1);
});

it('barcode yang tidak dikenali menampilkan notifikasi, bukan menambah baris', function () {
    $test = Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->set('cariBarcode', 'TIDAK-ADA-000')
        ->call('tambahDariBarcode');

    $test->assertDispatched('notifikasi');

    expect($test->get('baris.0.produk_id'))->toBe('');
});

it('tombol simpan terkunci kalau nominal cash belum pas dengan total belanja', function () {
    $toko = buatTokoPos();

    $test = Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->call('pilihToko', $toko->id)
        ->set('baris.0.produk_id', $this->produk->id)
        ->set('baris.0.jumlah_dus', 2)
        ->set('nominalCash', '10000');

    expect($test->instance()->adaHalangan('nominal'))->toBeTrue();

    $test->call('isiTotalBelanja');

    expect($test->instance()->adaHalangan('nominal'))->toBeFalse();
});

/**
 * Opsi transfer sengaja dihilangkan sementara (belum dibutuhkan
 * operasional) — POS sekarang cuma cash, diketik manual. Tombol
 * "Isi Total Belanja" cuma bantuan awal; nominalnya tetap boleh diubah
 * sesudahnya, dan yang benar-benar tersimpan adalah angka TERAKHIR yang
 * ada di kolom saat Simpan ditekan, bukan otomatis total belanja.
 */
it('nominal cash yang diubah manual setelah Isi Total Belanja, itu yang tersimpan', function () {
    $toko = buatTokoPos();

    Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->call('pilihToko', $toko->id)
        ->set('baris.0.produk_id', $this->produk->id)
        ->set('baris.0.jumlah_dus', 2)
        ->call('isiTotalBelanja')
        ->set('nominalCash', '40000')
        ->call('simpan')
        ->assertHasNoErrors();

    $pesanan = Pesanan::where('toko_id', $toko->id)->first();

    expect((float) $pesanan->nominal_cash)->toEqualWithDelta(40_000, 0.01)
        ->and((float) $pesanan->nominal_transfer)->toBe(0.0);
});

// --- Riwayat pendapatan: kategori driver vs POS ------------------------

it('penjualan POS masuk ke Pendapatan dengan kategori pos', function () {
    $toko = buatTokoPos();

    $this->service->buatPos(
        toko: $toko,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 2]],
        penjual: $this->sales,
        nominalCash: 40_000,
        nominalTransfer: 0,
    );

    $kategori = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->instance()->totalPerKategori();

    expect($kategori['pos']['jumlah'])->toBe(1)
        ->and($kategori['pos']['total'])->toEqualWithDelta(40_000, 0.01)
        ->and($kategori['driver']['jumlah'])->toBe(0);
});

/**
 * Pesanan biasa yang sampai LUNAS lewat alur pengantaran normal (bukan
 * kampas, bukan POS) harus dikategorikan "driver" — dibuat langsung lewat
 * Pesanan::create() karena yang diuji di sini murni logika pengelompokan
 * Pendapatan, bukan alur routing lengkap yang sudah dites di tempat lain.
 */
it('pesanan pengantaran biasa yang lunas masuk kategori driver', function () {
    $toko = buatTokoPos();

    $pesanan = Pesanan::create([
        'kode' => 'PSN-TESTDRV-0001',
        'toko_id' => $toko->id,
        'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => $this->sales->id,
        'status' => StatusPesanan::Selesai,
        'tanggal' => today(),
        'total_dus' => 5,
        'total_nilai' => 100_000,
        'status_bayar' => StatusBayar::Lunas,
        'tanggal_lunas' => today(),
        'dilunasi_oleh' => $this->admin->id,
        'nominal_cash' => 100_000,
        'nominal_transfer' => 0,
    ]);
    $pesanan->items()->create([
        'produk_id' => $this->produk->id,
        'jumlah_dus' => 5,
        'harga_satuan' => 20_000,
        'subtotal' => 100_000,
    ]);

    $kategori = Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->instance()->totalPerKategori();

    expect($kategori['driver']['jumlah'])->toBe(1)
        ->and($kategori['driver']['total'])->toEqualWithDelta(100_000, 0.01)
        ->and($kategori['pos']['jumlah'])->toBe(0);
});

/**
 * Sengaja memakai BEBERAPA pesanan (bukan satu) dan me-render blade-nya
 * sungguhan lewat ->assertSee(), bukan cuma memanggil computed-nya lewat
 * ->instance(). Laravel 13 diam-diam memuat relasi yang kurang (autoload)
 * ketika modelnya cuma satu, jadi pelanggaran mode ketat pada relasi
 * `toko` — dibaca blade riwayat lewat $p->toko->nama — baru kelihatan
 * begitu daftarnya berisi lebih dari satu baris.
 */
it('riwayat pendapatan menampilkan pesanan POS dan driver bersama-sama', function () {
    $tokoPos = buatTokoPos('Toko Riwayat POS');
    $tokoDriver = buatTokoPos('Toko Riwayat Driver');

    $this->service->buatPos(
        toko: $tokoPos,
        items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 1]],
        penjual: $this->sales,
        nominalCash: 20_000,
        nominalTransfer: 0,
    );

    $pesananDriver = Pesanan::create([
        'kode' => 'PSN-TESTDRV-0002',
        'toko_id' => $tokoDriver->id,
        'wilayah_id' => $tokoDriver->wilayah_id,
        'dibuat_oleh' => $this->sales->id,
        'status' => StatusPesanan::Selesai,
        'tanggal' => today(),
        'total_dus' => 5,
        'total_nilai' => 100_000,
        'status_bayar' => StatusBayar::Lunas,
        'tanggal_lunas' => today(),
        'dilunasi_oleh' => $this->admin->id,
        'nominal_cash' => 100_000,
        'nominal_transfer' => 0,
    ]);
    $pesananDriver->items()->create([
        'produk_id' => $this->produk->id,
        'jumlah_dus' => 5,
        'harga_satuan' => 20_000,
        'subtotal' => 100_000,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Pendapatan::class)
        ->set('mode', 'semua')
        ->assertSee('Toko Riwayat POS')
        ->assertSee('Toko Riwayat Driver')
        ->assertSee(__('pembayaran.kategori_pos'))
        ->assertSee(__('pembayaran.kategori_driver'));
});

// --- Master Produk: field barcode ---------------------------------------

it('admin bisa melengkapi barcode produk lewat Master Produk', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->call('sunting', $this->produk->id)
        ->set('barcode', '8991234567890')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($this->produk->fresh()->barcode)->toBe('8991234567890');
});

it('menolak barcode yang sudah dipakai produk lain', function () {
    Produk::create(['kode' => 'P2', 'barcode' => '8991234567890', 'nama' => 'Teh Kotak', 'stok' => 10, 'harga' => 5_000]);

    Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->call('sunting', $this->produk->id)
        ->set('barcode', '8991234567890')
        ->call('simpan')
        ->assertHasErrors('barcode');

    expect($this->produk->fresh()->barcode)->toBeNull();
});

it('dua produk boleh sama-sama belum punya barcode', function () {
    $lain = Produk::create(['kode' => 'P2', 'nama' => 'Teh Kotak', 'stok' => 10, 'harga' => 5_000]);

    Livewire::actingAs($this->admin)
        ->test(DaftarProduk::class)
        ->call('sunting', $this->produk->id)
        ->set('nama', 'Air Mineral Diperbarui')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($this->produk->fresh()->barcode)->toBeNull()
        ->and($lain->fresh()->barcode)->toBeNull();
});

// --- Pemilih produk yang bisa dicari (<x-pilih-cari>) -------------------

/**
 * Menyeleksi produk lewat $wire.set() dari Alpine (bukan wire:model pada
 * <select>) tetap mengarah ke properti Livewire yang sama persis — jadi
 * memilih baris via set() di sini membuktikan jalur baru itu berfungsi
 * end-to-end, bukan cuma bahwa markup-nya tampil.
 */
it('kasir bisa memilih produk lewat pemilih yang bisa dicari, lalu menyimpan', function () {
    $toko = buatTokoPos('Toko Pilih Cari');

    Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->call('pilihToko', $toko->id)
        ->set('baris.0.produk_id', $this->produk->id)
        ->set('baris.0.jumlah_dus', 1)
        ->assertSee($this->produk->nama)
        ->call('isiTotalBelanja')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Pesanan::where('toko_id', $toko->id)->exists())->toBeTrue();
});

it('input pesanan menampilkan label produk yang sudah terpilih di pemilih cari', function () {
    Livewire::actingAs($this->sales)
        ->test(BuatPesanan::class)
        ->set('baris.0.produk_id', $this->produk->id)
        ->assertSee($this->produk->nama)
        ->assertSee($this->produk->kode);
});

it('pemilih cari menampilkan opsi kosong kalau belum ada produk terpilih', function () {
    Livewire::actingAs($this->sales)
        ->test(Kasir::class)
        ->assertSee(__('pesanan.pilih_produk'));
});
