<?php

use App\Enums\PeranPengguna;
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
 * Bonus di POS: sama seperti langkah bonus di Input Pesanan (harga SELALU
 * 0 apa pun produk/jumlahnya, stok tetap keluar sesuai dus yang benar-benar
 * keluar), khusus admin/superadmin. Bedanya dari Input Pesanan: POS tidak
 * pernah lewat rute pengantaran driver sama sekali (statusnya langsung
 * SELESAI, stok keluar fisik seketika, bukan direservasi dulu), jadi tidak
 * ada "Pilih Sales" — tidak ada faktur bercetak untuk POS yang perlu
 * atribusi nama sales.
 */
beforeEach(function () {
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 50, 'harga' => 20_000]);
    $this->produkBonus = Produk::create(['kode' => 'P2', 'nama' => 'Sirup Bonus', 'stok' => 50, 'harga' => 15_000]);

    $this->toko = Toko::create([
        'kode' => 'TK-POSB01',
        'nama' => 'Toko POS Bonus',
        'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. POS Bonus',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);

    $this->service = app(PesananService::class);
});

// =====================================================================
describe('PesananService::buatPos() dengan item bonus', function () {
    it('admin bisa menyertakan item bonus dengan harga 0, stok fisik tetap berkurang seketika', function () {
        $pesanan = $this->service->buatPos(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 3]],
            penjual: $this->admin,
            nominalCash: 60_000,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 2]],
        );

        $itemBiasa = $pesanan->items->firstWhere('is_bonus', false);
        $itemBonus = $pesanan->items->firstWhere('is_bonus', true);

        expect($pesanan->items)->toHaveCount(2)
            ->and($itemBonus->jumlah_dus)->toBe(2)
            ->and((float) $itemBonus->harga_satuan)->toBe(0.0)
            ->and((float) $itemBonus->subtotal)->toBe(0.0)
            ->and($itemBonus->jumlah_dus_terkirim)->toBe(2)
            // Total dus fisik mencakup bonus (3+2=5), tapi total_nilai
            // hanya dari item biasa (3 x Rp 20.000).
            ->and($pesanan->total_dus)->toBe(5)
            ->and((float) $pesanan->total_nilai)->toBe(60_000.0)
            ->and((float) $itemBiasa->subtotal)->toBe(60_000.0);

        $this->produkBonus->refresh();
        $this->produk->refresh();

        // Stok fisik keduanya berkurang SEKETIKA, bukan lewat reservasi.
        expect($this->produkBonus->stok)->toBe(48)
            ->and($this->produkBonus->stok_reserved)->toBe(0)
            ->and($this->produk->stok)->toBe(47);

        $this->assertDatabaseHas('stok_mutasis', [
            'pesanan_id' => $pesanan->id,
            'produk_id' => $this->produkBonus->id,
            'tipe' => 'keluar',
            'jumlah' => -2,
        ]);
    });

    it('nominal cash cukup mengikuti total item biasa saja, bonus tidak ikut ditagihkan', function () {
        // Kalau bonus ikut dihitung ke tagihan, nominal 60.000 akan
        // dianggap kurang (harusnya 60.000 + 30.000 = 90.000). Ini
        // memastikan bonus benar-benar tidak pernah masuk ke totalNilai.
        $pesanan = $this->service->buatPos(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 3]],
            penjual: $this->admin,
            nominalCash: 60_000,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 2]],
        );

        expect((float) $pesanan->total_nilai)->toBe(60_000.0);
    });

    it('produk yang sama di kedua daftar tetap tersimpan sebagai dua baris terpisah', function () {
        $pesanan = $this->service->buatPos(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 3]],
            penjual: $this->admin,
            nominalCash: 60_000,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $this->produk->id, 'jumlah_dus' => 2]],
        );

        expect($pesanan->items)->toHaveCount(2);

        $normal = $pesanan->items->firstWhere('is_bonus', false);
        $bonus = $pesanan->items->firstWhere('is_bonus', true);

        expect($normal->jumlah_dus)->toBe(3)
            ->and((float) $normal->harga_satuan)->toBe(20_000.0)
            ->and($bonus->jumlah_dus)->toBe(2)
            ->and((float) $bonus->harga_satuan)->toBe(0.0);

        $this->produk->refresh();
        // Kedua baris berbagi rak stok yang sama: 3 + 2 = 5 dus keluar.
        expect($this->produk->stok)->toBe(45);
    });

    it('memeriksa stok fisik atas permintaan gabungan biasa dan bonus, bukan dua kali terpisah', function () {
        // Stok fisik 50. 30 dus biasa + 30 dus bonus = 60, melebihi stok
        // meski masing-masing di bawah 50 kalau diperiksa sendirian.
        expect(fn () => $this->service->buatPos(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 30]],
            penjual: $this->admin,
            nominalCash: 600_000,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $this->produk->id, 'jumlah_dus' => 30]],
        ))->toThrow(ValidationException::class);

        expect(Pesanan::count())->toBe(0)
            ->and($this->produk->fresh()->stok)->toBe(50);
    });

    it('bonus saja (tanpa item biasa) tetap sah, total_nilai 0 dan nominal cash 0', function () {
        $pesanan = $this->service->buatPos(
            toko: $this->toko,
            items: [],
            penjual: $this->admin,
            nominalCash: 0,
            nominalTransfer: 0,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 4]],
        );

        expect($pesanan->items)->toHaveCount(1)
            ->and((float) $pesanan->total_nilai)->toBe(0.0)
            ->and($pesanan->total_dus)->toBe(4);
    });
});

// =====================================================================
describe('Kasir (Livewire): visibilitas langkah bonus', function () {
    it('sales sama sekali tidak melihat langkah bonus, dan penomoran langkah tidak berubah', function () {
        Livewire::actingAs($this->sales)
            ->test(Kasir::class)
            ->assertSee(__('pos.langkah_pembayaran'))
            ->assertSee(__('pos.langkah_catatan'))
            ->assertDontSee(__('pesanan.langkah_bonus'))
            ->assertDontSee(__('pos.langkah_pembayaran_admin'))
            ->assertDontSee(__('pos.langkah_catatan_admin'))
            ->assertSet('barisBonus', []);
    });

    it('admin melihat langkah bonus, dan pembayaran/catatan bergeser nomor', function () {
        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->assertSee(__('pesanan.langkah_bonus'))
            ->assertSee(__('pos.langkah_pembayaran_admin'))
            ->assertSee(__('pos.langkah_catatan_admin'))
            ->assertDontSee(__('pos.langkah_pembayaran'))
            ->assertDontSee(__('pos.langkah_catatan'));
    });

    it('admin bisa menyimpan transaksi POS dengan bonus lewat komponen', function () {
        Livewire::actingAs($this->admin)
            ->test(Kasir::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 3)
            ->set('barisBonus.0.produk_id', $this->produkBonus->id)
            ->set('barisBonus.0.jumlah_dus', 2)
            ->set('nominalCash', '60000')
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->dibuat_oleh)->toBe($this->admin->id)
            ->and($pesanan->items)->toHaveCount(2)
            ->and($pesanan->items->firstWhere('is_bonus', true)->jumlah_dus)->toBe(2)
            ->and((float) $pesanan->total_nilai)->toBe(60_000.0);
    });

    /**
     * State komponen ($barisBonus) tidak pernah dipercaya begitu saja —
     * hanya peran akun yang sedang login yang menentukan. Sales tidak
     * diberi UI untuk mengisi properti ini, tapi diuji lewat ->set()
     * langsung untuk memastikan pertahanannya ada di server.
     */
    it('sales yang memaksa mengisi state bonus lewat komponen tetap tidak tersimpan sebagai bonus', function () {
        Livewire::actingAs($this->sales)
            ->test(Kasir::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 3)
            ->set('barisBonus', [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 99]])
            ->set('nominalCash', '60000')
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->items)->toHaveCount(1)
            ->and($pesanan->items->first()->is_bonus)->toBeFalse();

        $this->produkBonus->refresh();
        expect($this->produkBonus->stok)->toBe(50);
    });
});
