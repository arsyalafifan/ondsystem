<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Insentif\InsentifSales;
use App\Livewire\Pesanan\BuatPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Langkah 3 "Pilih Bonus Produk & Jumlah Dus": khusus admin/superadmin,
 * dipakai untuk toko yang berhak dapat bonus. Harganya SELALU 0 apa pun
 * produk dan jumlahnya, tapi stok tetap dikunci/berkurang seperti item
 * biasa. Karena admin sendiri yang menginput (bukan sales), faktur tetap
 * perlu tahu sales mana yang bertanggung jawab lewat atasNamaSales.
 */
beforeEach(function () {
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100, 'harga' => 50_000]);
    $this->produkBonus = Produk::create(['kode' => 'P2', 'nama' => 'Sirup Bonus', 'stok' => 100, 'harga' => 30_000]);

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

// =====================================================================
describe('PesananService::buat() dengan item bonus', function () {
    it('admin bisa menyimpan item bonus dengan harga 0 tapi stok tetap dikunci', function () {
        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [],
            pembuat: $this->admin,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 6]],
            atasNamaSales: $this->sales,
        );

        $itemBonus = $pesanan->items->first();

        expect($pesanan->items)->toHaveCount(1)
            ->and($itemBonus->is_bonus)->toBeTrue()
            ->and((float) $itemBonus->harga_satuan)->toBe(0.0)
            ->and((float) $itemBonus->subtotal)->toBe(0.0)
            ->and($itemBonus->jumlah_dus)->toBe(6)
            ->and((float) $pesanan->total_nilai)->toBe(0.0)
            ->and($pesanan->sales_id)->toBe($this->sales->id);

        $this->produkBonus->refresh();
        expect($this->produkBonus->stok_reserved)->toBe(6)
            ->and($this->produkBonus->stok_tersedia)->toBe(94);

        $this->assertDatabaseHas('stok_mutasis', [
            'pesanan_id' => $pesanan->id,
            'produk_id' => $this->produkBonus->id,
            'tipe' => 'reserve',
            'jumlah' => -6,
        ]);
    });

    it('menolak admin yang tidak memilih sales', function () {
        expect(fn () => $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 6]],
            pembuat: $this->admin,
        ))->toThrow(ValidationException::class);

        expect(Pesanan::count())->toBe(0)
            ->and($this->produk->fresh()->stok_reserved)->toBe(0);
    });

    it('menolak akun atas nama sales yang bukan berperan sales', function () {
        expect(fn () => $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 6]],
            pembuat: $this->admin,
            atasNamaSales: $this->admin,
        ))->toThrow(ValidationException::class);
    });

    it('sales yang menginput sendiri tidak perlu memilih atas nama sales', function () {
        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 6]],
            pembuat: $this->sales,
        );

        expect($pesanan->sales_id)->toBeNull()
            ->and($pesanan->dibuat_oleh)->toBe($this->sales->id);
    });

    it('dus bonus ikut dihitung ke batas minimal pesanan', function () {
        // 3 dus biasa saja di bawah batas minimal (5), tapi ditambah 3 dus
        // bonus totalnya 6 — cukup untuk lolos batas minimal.
        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 3]],
            pembuat: $this->admin,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 3]],
            atasNamaSales: $this->sales,
        );

        expect($pesanan->total_dus)->toBe(6);
    });

    it('produk yang sama di kedua daftar tetap tersimpan sebagai dua baris terpisah', function () {
        $pesanan = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 4]],
            pembuat: $this->admin,
            bonusItems: [['produk_id' => $this->produk->id, 'jumlah_dus' => 2]],
            atasNamaSales: $this->sales,
        );

        expect($pesanan->items)->toHaveCount(2);

        $normal = $pesanan->items->firstWhere('is_bonus', false);
        $bonus = $pesanan->items->firstWhere('is_bonus', true);

        expect($normal->jumlah_dus)->toBe(4)
            ->and((float) $normal->harga_satuan)->toBe(50_000.0)
            ->and($bonus->jumlah_dus)->toBe(2)
            ->and((float) $bonus->harga_satuan)->toBe(0.0)
            ->and((float) $pesanan->total_nilai)->toBe(200_000.0);

        // Kedua baris berbagi rak stok yang sama: 4 + 2 = 6 dus terkunci.
        $this->produk->refresh();
        expect($this->produk->stok_reserved)->toBe(6);
    });

    it('memeriksa stok atas permintaan gabungan biasa dan bonus, bukan dua kali terpisah', function () {
        // Stok tersedia 100. 60 dus biasa + 60 dus bonus = 120, melebihi
        // stok meski masing-masing di bawah 100 kalau diperiksa sendirian.
        expect(fn () => $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 60]],
            pembuat: $this->admin,
            bonusItems: [['produk_id' => $this->produk->id, 'jumlah_dus' => 60]],
            atasNamaSales: $this->sales,
        ))->toThrow(ValidationException::class);

        expect(Pesanan::count())->toBe(0)
            ->and($this->produk->fresh()->stok_reserved)->toBe(0);
    });
});

// =====================================================================
describe('BuatPesanan (Livewire): visibilitas langkah bonus', function () {
    it('sales sama sekali tidak melihat langkah bonus, dan langkah catatan tetap nomor 3', function () {
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->assertSee('3. '.__('pesanan.langkah_catatan'))
            ->assertDontSee(__('pesanan.langkah_bonus'))
            ->assertSet('barisBonus', []);
    });

    it('admin melihat langkah bonus, dan langkah catatan berpindah ke nomor 4', function () {
        Livewire::actingAs($this->admin)
            ->test(BuatPesanan::class)
            ->assertSee(__('pesanan.langkah_bonus'))
            ->assertSee('4. '.__('pesanan.langkah_catatan'))
            ->assertDontSee('3. '.__('pesanan.langkah_catatan'));
    });

    it('admin tidak bisa menyimpan pesanan tanpa memilih sales', function () {
        Livewire::actingAs($this->admin)
            ->test(BuatPesanan::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 6)
            ->call('simpan')
            ->assertHasErrors('salesId');

        expect(Pesanan::count())->toBe(0);
    });

    it('admin bisa menyimpan pesanan dengan bonus dan atas nama sales lewat komponen', function () {
        Livewire::actingAs($this->admin)
            ->test(BuatPesanan::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 4)
            ->set('barisBonus.0.produk_id', $this->produkBonus->id)
            ->set('barisBonus.0.jumlah_dus', 2)
            ->set('salesId', $this->sales->id)
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->dibuat_oleh)->toBe($this->admin->id)
            ->and($pesanan->sales_id)->toBe($this->sales->id)
            ->and($pesanan->items)->toHaveCount(2)
            ->and($pesanan->items->firstWhere('is_bonus', true)->jumlah_dus)->toBe(2);
    });

    /**
     * State komponen ($barisBonus/$salesId) tidak pernah dipercaya begitu
     * saja — hanya peran akun yang sedang login yang menentukan apakah
     * bonus ikut disimpan atau tidak. Sales tidak diberi UI untuk mengubah
     * dua properti ini, tapi diuji lewat ->set() langsung untuk memastikan
     * pertahanannya ada di server, bukan cuma disembunyikan di tampilan.
     */
    it('sales yang memaksa mengisi state bonus lewat komponen tetap tidak tersimpan sebagai bonus', function () {
        Livewire::actingAs($this->sales)
            ->test(BuatPesanan::class)
            ->set('tokoId', $this->toko->id)
            ->set('baris.0.produk_id', $this->produk->id)
            ->set('baris.0.jumlah_dus', 6)
            ->set('barisBonus', [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 99]])
            ->set('salesId', $this->salesLain->id)
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->firstOrFail();

        expect($pesanan->sales_id)->toBeNull()
            ->and($pesanan->items)->toHaveCount(1)
            ->and($pesanan->items->first()->is_bonus)->toBeFalse();

        $this->produkBonus->refresh();
        expect($this->produkBonus->stok_reserved)->toBe(0);
    });
});

// =====================================================================
describe('Insentif Sales: pesanan yang diinput admin', function () {
    /**
     * Bukan cuma baris bonusnya yang tidak dihitung — SELURUH pesanan yang
     * diinput admin (termasuk baris biasa/berbayar di dalamnya) tidak masuk
     * Insentif Sales sama sekali, karena penyaringnya di pesanans() memakai
     * peran PENGINPUT (dibuat_oleh), bukan menyaring per baris item. Sales
     * cuma dapat insentif dari pesanan yang dia INPUT SENDIRI dan
     * dus-nya benar-benar terkirim sampai status SELESAI — persis seperti
     * yang berlaku sebelum fitur bonus ini ada.
     */
    it('dus dari pesanan yang diinput admin sama sekali tidak masuk insentif sales, baris biasa maupun bonus', function () {
        // Sales sungguh menginput sendiri: dus ini yang seharusnya SATU-SATUNYA masuk.
        $pesananSales = $this->service->buat(
            toko: $this->toko,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 5]],
            pembuat: $this->sales,
        );

        $tokoLain = Toko::create([
            'kode' => 'TK-0002', 'nama' => 'Toko Dua', 'wilayah_id' => $this->wilayah->id,
            'alamat' => 'Jl. Dua', 'latitude' => -6.19, 'longitude' => 106.84, 'sumber_koordinat' => 'manual',
        ]);

        // Admin menginput pesanan atas nama sales yang sama, berisi baris
        // biasa (berbayar) SEKALIGUS baris bonus.
        $pesananAdmin = $this->service->buat(
            toko: $tokoLain,
            items: [['produk_id' => $this->produk->id, 'jumlah_dus' => 4]],
            pembuat: $this->admin,
            bonusItems: [['produk_id' => $this->produkBonus->id, 'jumlah_dus' => 2]],
            atasNamaSales: $this->sales,
        );

        // Keduanya benar-benar terkirim tuntas sampai SELESAI.
        foreach ([$pesananSales, $pesananAdmin] as $p) {
            $p->update(['status' => StatusPesanan::Selesai, 'selesai_at' => now()]);
        }

        $perSales = Livewire::actingAs($this->admin)
            ->test(InsentifSales::class)
            ->set('mode', 'semua')
            ->instance()->perSales();

        // Cuma satu baris (sales), dan dus-nya persis 5 — bukan 5+4+2=11.
        // Baris biasa (4 dus) maupun bonus (2 dus) pada pesanan yang
        // diinput admin sama-sama tidak ikut, meski sama-sama "atas nama"
        // sales yang sama dan sama-sama sudah terkirim tuntas.
        expect($perSales)->toHaveCount(1)
            ->and($perSales[0]['user_id'])->toBe($this->sales->id)
            ->and($perSales[0]['total_dus'])->toBe(5);
    });
});
