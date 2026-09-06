<?php

use App\Enums\PeranPengguna;
use App\Livewire\Pesanan\BuatPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Promo "beli N dus gratis M dus" (lihat PromoTest.php untuk sisi Master
 * Promo) — beda dari bonus manual admin-only di PesananBonusTest.php:
 * terbuka untuk SEMUA peran, tapi dibatasi ketat oleh aturan promo yang
 * SUNGGUH aktif di server (bukan dipercaya dari klien): total dus reguler
 * pesanan harus mencapai minimal_dus, produknya harus ada di daftar
 * berhak, dan jumlahnya tidak boleh melebihi bonus_dus.
 */
beforeEach(function () {
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 1000, 'harga' => 50_000]);
    $this->produkPromo = Produk::create(['kode' => 'P2', 'nama' => 'Sirup Promo', 'stok' => 1000, 'harga' => 30_000]);
    $this->produkLain = Produk::create(['kode' => 'P3', 'nama' => 'Teh Kotak', 'stok' => 1000, 'harga' => 10_000]);

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

/** Promo aktif hari ini, produk berhak default $this->produkPromo. */
function buatPromoAktif(int $minimalDus = 15, int $bonusDus = 1, ?array $produkIds = null): Promo
{
    $promo = Promo::create([
        'nama' => 'Promo Uji',
        'tanggal_mulai' => today()->subDay(),
        'tanggal_selesai' => today()->addDay(),
        'minimal_dus' => $minimalDus,
        'bonus_dus' => $bonusDus,
    ]);

    $promo->produks()->sync($produkIds ?? [test()->produkPromo->id]);

    return $promo;
}

// =====================================================================
describe('PesananService::buat() dengan promo bonus', function () {
    it('memberikan bonus promo dengan harga 0 dan mengunci stok begitu dus reguler mencapai ambang', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 1);

        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->sales,
            promoBonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 1]],
        );

        $itemPromo = $pesanan->items->firstWhere('is_bonus', true);

        expect($itemPromo->produk_id)->toBe($this->produkPromo->id)
            ->and($itemPromo->jumlah_dus)->toBe(1)
            ->and((float) $itemPromo->harga_satuan)->toBe(0.0)
            ->and((float) $itemPromo->subtotal)->toBe(0.0)
            ->and($pesanan->total_dus)->toBe(16);

        $this->produkPromo->refresh();
        expect($this->produkPromo->stok_reserved)->toBe(1);
    });

    it('mencatat promo_id pada pesanan yang memakai promo', function () {
        $promo = buatPromoAktif();

        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->sales,
            promoBonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 1]],
        );

        expect($pesanan->promo_id)->toBe($promo->id);
    });

    it('tidak mengisi promo_id kalau pesanan tidak memakai bonus promo sama sekali', function () {
        buatPromoAktif();

        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->sales,
        );

        expect($pesanan->promo_id)->toBeNull();
    });

    it('menolak bonus promo kalau tidak ada promo yang aktif sama sekali', function () {
        expect(fn () => $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->sales,
            promoBonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 1]],
        ))->toThrow(ValidationException::class);

        expect(Pesanan::count())->toBe(0);
    });

    /**
     * Server SELALU menghitung ulang ambangnya sendiri dari $items yang
     * dikirim — bukan mempercayai klaim pemanggil bahwa syaratnya sudah
     * terpenuhi. 14 dus < 15 dus ambang, jadi tetap ditolak.
     */
    it('menolak bonus promo kalau dus reguler belum mencapai ambang minimal', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 1);

        expect(fn () => $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 14]],
            pembuat: $this->sales,
            promoBonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 1]],
        ))->toThrow(ValidationException::class);

        expect(Pesanan::count())->toBe(0)
            ->and($this->produkPromo->fresh()->stok_reserved)->toBe(0);
    });

    it('menolak bonus promo yang jumlahnya melebihi batas bonus_dus', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 1);

        expect(fn () => $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->sales,
            promoBonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 2]],
        ))->toThrow(ValidationException::class);

        expect(Pesanan::count())->toBe(0);
    });

    it('menolak produk bonus promo yang tidak termasuk daftar berhak promo itu', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 5, produkIds: [$this->produkPromo->id]);

        expect(fn () => $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->sales,
            // produkLain TIDAK ada di daftar berhak promo ini.
            promoBonusItems: [['produk_id' => $this->produkLain->id, 'jumlah_dus' => 1]],
        ))->toThrow(ValidationException::class);

        expect(Pesanan::count())->toBe(0);
    });

    /**
     * Regresi untuk batasan unik (pesanan_id, produk_id, is_bonus) pada
     * pesanan_items: produk yang sama dipilih di bonus manual (admin) DAN
     * bonus promo pada pesanan yang sama harus DIGABUNG jadi satu baris,
     * bukan dua baris is_bonus=true yang bentrok di basis data.
     */
    it('produk yang sama di bonus manual dan bonus promo digabung jadi satu baris, tidak bentrok', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 3, produkIds: [$this->produkPromo->id]);

        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->admin,
            bonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 2]],
            atasNamaSales: $this->sales,
            promoBonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 3]],
        );

        $itemBonus = $pesanan->items->where('is_bonus', true);

        expect($itemBonus)->toHaveCount(1)
            ->and($itemBonus->first()->jumlah_dus)->toBe(5)
            ->and($pesanan->items)->toHaveCount(2);

        $this->produkPromo->refresh();
        expect($this->produkPromo->stok_reserved)->toBe(5);
    });

    it('dus bonus promo ikut dihitung ke total_dus dan batas minimal pesanan', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 5);

        // 3 dus biasa saja di bawah batas minimal pesanan (5), tapi
        // ditambah dus bonus promo totalnya cukup untuk lolos.
        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 15]],
            pembuat: $this->sales,
            promoBonusItems: [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 5]],
        );

        expect($pesanan->total_dus)->toBe(20);
    });
});

// =====================================================================
describe('BuatPesanan (Livewire): promo bonus', function () {
    it('bagian promo bonus tidak muncul sama sekali kalau tidak ada promo aktif', function () {
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->assertDontSee(__('pesanan.langkah_promo_bonus'));
    });

    it('sales melihat bagian promo bonus, beda dari bonus manual yang admin-only', function () {
        buatPromoAktif();

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->assertSee(__('pesanan.langkah_promo_bonus'))
            ->assertDontSee(__('pesanan.langkah_bonus'));
    });

    it('input bonus promo tidak muncul selama pesanan belum memenuhi syarat', function () {
        buatPromoAktif(minimalDus: 15);

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 5)
            ->assertSet('memenuhiSyaratPromo', false)
            ->assertDontSee(__('pesanan.tambah_baris_promo_bonus'));
    });

    it('input bonus promo muncul begitu ambang tercapai', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 2);

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 15)
            ->assertSet('memenuhiSyaratPromo', true)
            ->assertSee(__('pesanan.tambah_baris_promo_bonus'));
    });

    it('menyelesaikan pesanan dengan bonus promo dari awal sampai akhir lewat komponen', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 2);

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 15)
            ->set('barisPromoBonus.0.produk_id', $this->produkPromo->id)
            ->set('barisPromoBonus.0.jumlah_dus', 2)
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->items->firstWhere('is_bonus', true)->jumlah_dus)->toBe(2)
            ->and($pesanan->promo_id)->not->toBeNull();
    });

    /**
     * Mengosongkan barisPromoBonus begitu dus reguler turun lagi di bawah
     * ambang cuma kenyamanan tampilan — pertahanan sesungguhnya ada di
     * simpan(), yang sama sekali tidak pernah mengirim barisPromoBonus ke
     * service kalau memenuhiSyaratPromo() sedang false.
     */
    it('sales yang memaksa mengisi barisPromoBonus lewat komponen saat belum memenuhi syarat tetap tidak tersimpan sebagai bonus', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 2);

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 6)
            ->set('barisPromoBonus', [['produk_id' => $this->produkPromo->id, 'jumlah_dus' => 2]])
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->items)->toHaveCount(1)
            ->and($pesanan->items->first()->is_bonus)->toBeFalse()
            ->and($pesanan->promo_id)->toBeNull();

        $this->produkPromo->refresh();
        expect($this->produkPromo->stok_reserved)->toBe(0);
    });

    it('memaksa produk bonus promo yang tidak berhak lewat komponen saat sudah memenuhi syarat ditolak service', function () {
        buatPromoAktif(minimalDus: 15, bonusDus: 5, produkIds: [$this->produkPromo->id]);

        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 15)
            ->set('barisPromoBonus.0.produk_id', $this->produkLain->id)
            ->set('barisPromoBonus.0.jumlah_dus', 1)
            ->call('simpan')
            ->assertHasErrors('barisPromoBonus');

        expect(Pesanan::count())->toBe(0);
    });
});
