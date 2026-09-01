<?php

use App\Enums\PeranPengguna;
use App\Livewire\Pos\Kasir;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Livewire\Livewire;

/**
 * POS "Tanpa Toko": khusus admin/superadmin. BUKAN jenis transaksi khusus —
 * produk, bonus (lihat PosBonusTest), maupun pembayarannya tetap persis
 * sama seperti transaksi POS yang memilih toko sungguhan. Satu-satunya beda
 * adalah toko tidak wajib diisi, dipakai untuk transaksi yang tidak diikat
 * ke toko/perusahaan pelanggan tertentu (pembeli perorangan, pemberian ke
 * karyawan, dsb.) — baik itu berbayar maupun cuma-cuma (cuma-cuma dicapai
 * lewat langkah bonus yang sudah ada, independen dari Tanpa Toko).
 *
 * Diimplementasikan lewat Toko::internal() — satu baris Toko semu yang
 * nonaktif (bukan toko_id yang benar-benar NULL), supaya seluruh kode yang
 * mengandalkan $pesanan->toko selalu ada tanpa perlu diaudit ulang.
 */
beforeEach(function () {
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 50, 'harga' => 20_000]);
    $this->produkBonus = Produk::create(['kode' => 'P2', 'nama' => 'Sirup Bonus', 'stok' => 50, 'harga' => 15_000]);

    $this->service = app(PesananService::class);
});

// =====================================================================
describe('Toko::internal()', function () {
    it('membuat satu baris toko semu, nonaktif, dan idempoten', function () {
        $pertama = Toko::internal();
        $kedua = Toko::internal();

        expect($pertama->id)->toBe($kedua->id)
            ->and($pertama->aktif)->toBeFalse()
            ->and($pertama->kode)->toBe(Toko::KODE_INTERNAL)
            ->and(Toko::query()->where('kode', Toko::KODE_INTERNAL)->count())->toBe(1);
    });

    it('tidak pernah muncul di pencarian toko biasa karena nonaktif', function () {
        Toko::internal();

        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->set('cariToko', 'Tanpa Toko')
            ->assertDontSee('INTERNAL-POS');
    });
});

// =====================================================================
describe('Kasir (Livewire): opsi Tanpa Toko', function () {
    it('sales sama sekali tidak melihat opsi Tanpa Toko', function () {
        Livewire::actingAs($this->sales)
            ->test(Kasir::class)
            ->assertDontSee(__('pos.tab_tanpa_toko'))
            ->assertSet('tanpaToko', false);
    });

    it('admin melihat opsi Tanpa Toko, dan mengaktifkannya TIDAK mengubah langkah lain', function () {
        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->assertSee(__('pos.tab_tanpa_toko'))
            ->call('aktifkanTanpaToko')
            ->assertSet('tanpaToko', true)
            ->assertSet('tokoId', Toko::internal()->id)
            // Langkah bonus dan pembayaran TETAP tampil apa adanya — Tanpa
            // Toko cuma membuat toko opsional, tidak mengubah apa pun lagi.
            ->assertSee(__('pesanan.langkah_bonus'))
            ->assertSee(__('pos.langkah_pembayaran_admin'));
    });

    it('admin bisa menyimpan transaksi Tanpa Toko yang BERBAYAR, sama seperti transaksi biasa', function () {
        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->call('aktifkanTanpaToko')
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 5)
            ->set('nominalCash', '100000')
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->toko_id)->toBe(Toko::internal()->id)
            ->and($pesanan->dibuat_oleh)->toBe($this->admin->id)
            ->and($pesanan->items)->toHaveCount(1)
            ->and($pesanan->items->first()->is_bonus)->toBeFalse()
            ->and((float) $pesanan->items->first()->harga_satuan)->toBe(20_000.0)
            ->and((float) $pesanan->total_nilai)->toBe(100_000.0)
            ->and((float) $pesanan->nominal_cash)->toBe(100_000.0);

        $this->produk->refresh();
        expect($this->produk->stok)->toBe(45);
    });

    it('transaksi Tanpa Toko yang berbayar tetap ditolak kalau nominal cash tidak pas', function () {
        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->call('aktifkanTanpaToko')
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 5)
            ->set('nominalCash', '50000')
            ->call('simpan')
            ->assertHasErrors('nominalCash');

        expect(Pesanan::count())->toBe(0);
    });

    it('admin bisa mengombinasikan Tanpa Toko dengan langkah bonus, persis seperti transaksi biasa', function () {
        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->call('aktifkanTanpaToko')
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 3)
            ->set('barisBonus.0.produk_id', $this->produkBonus->id)
            ->set('barisBonus.0.jumlah_dus', 2)
            ->set('nominalCash', '60000')
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->toko_id)->toBe(Toko::internal()->id)
            ->and($pesanan->items)->toHaveCount(2)
            ->and($pesanan->items->firstWhere('is_bonus', true)->jumlah_dus)->toBe(2)
            ->and((float) $pesanan->total_nilai)->toBe(60_000.0);

        $this->produkBonus->refresh();
        expect($this->produkBonus->stok)->toBe(48);
    });

    it('transaksi Tanpa Toko tetap ditolak kalau stok fisik kurang', function () {
        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->call('aktifkanTanpaToko')
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 999)
            ->set('nominalCash', '19980000')
            ->call('simpan')
            ->assertHasErrors('baris');

        expect(Pesanan::count())->toBe(0)
            ->and($this->produk->fresh()->stok)->toBe(50);
    });

    it('kembali ke mode toko biasa membersihkan status Tanpa Toko', function () {
        $toko = Toko::create([
            'kode' => 'TK-0001', 'nama' => 'Toko Biasa', 'wilayah_id' => $this->wilayah->id,
            'alamat' => 'Jl. Uji', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        ]);

        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->call('aktifkanTanpaToko')
            ->assertSet('tanpaToko', true)
            ->call('nonaktifkanTanpaToko')
            ->assertSet('tanpaToko', false)
            ->assertSet('tokoId', null)
            ->call('pilihToko', $toko->id)
            ->assertSet('tanpaToko', false)
            ->assertSet('tokoId', $toko->id);
    });

    /**
     * State komponen ($tanpaToko) tidak pernah dipercaya begitu saja — hanya
     * peran akun yang sedang login yang menentukan. Sales tidak diberi UI
     * untuk mengaktifkan opsi ini: `tokoId` tidak pernah otomatis terisi ke
     * toko semu untuk mereka (aktifkanTanpaToko() no-op untuk non-admin),
     * jadi memaksa tanpaToko=true lewat komponen tidak membuat toko jadi
     * opsional — tokoId kosong tetap ditolak sama seperti biasa.
     */
    it('sales yang memaksa tanpaToko=true lewat komponen tetap wajib memilih toko sungguhan', function () {
        Livewire::actingAs($this->sales)
            ->test(Kasir::class)
            ->set('tanpaToko', true)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 5)
            ->set('nominalCash', '100000')
            ->call('simpan')
            ->assertHasErrors('tokoId');

        expect(Pesanan::count())->toBe(0);
    });

    it('bukan admin, aktifkanTanpaToko() lewat panggilan langsung tidak berefek apa-apa', function () {
        Livewire::actingAs($this->sales)
            ->test(Kasir::class)
            ->call('aktifkanTanpaToko')
            ->assertSet('tanpaToko', false)
            ->assertSet('tokoId', null);
    });
});
