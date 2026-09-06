<?php

use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarPromo;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Livewire\Livewire;

/**
 * Master Promo: setting "beli N dus gratis M dus" yang muncul otomatis di
 * Input Pesanan selama periodenya berjalan (lihat PesananPromoTest.php
 * untuk sisi Input Pesanan/PesananService::buat()). Aktif/tidaknya murni
 * ditentukan tanggal_mulai/tanggal_selesai — tidak ada tombol
 * aktif/nonaktif terpisah.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->produkA = Produk::create(['kode' => 'PA', 'nama' => 'Produk A', 'stok' => 1000, 'harga' => 20_000]);
    $this->produkB = Produk::create(['kode' => 'PB', 'nama' => 'Produk B', 'stok' => 1000, 'harga' => 15_000]);
});

it('menolak sales, mengizinkan admin', function () {
    $this->actingAs($this->sales)->get(route('master.promo'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('master.promo'))->assertOk();
});

it('membuat promo baru dengan produk yang berhak terpilih', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarPromo::class)
        ->set('nama', 'Promo Uji')
        ->set('tanggalMulai', '2026-09-01')
        ->set('tanggalSelesai', '2026-09-30')
        ->set('minimalDus', 15)
        ->set('bonusDus', 1)
        ->set('produkTerpilih', [$this->produkA->id, $this->produkB->id])
        ->call('simpan')
        ->assertHasNoErrors();

    $promo = Promo::with('produks')->firstOrFail();

    expect($promo->nama)->toBe('Promo Uji')
        ->and($promo->tanggal_mulai->toDateString())->toBe('2026-09-01')
        ->and($promo->tanggal_selesai->toDateString())->toBe('2026-09-30')
        ->and($promo->minimal_dus)->toBe(15)
        ->and($promo->bonus_dus)->toBe(1)
        ->and($promo->produks->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->produkA->id, $this->produkB->id])->sort()->values()->all());
});

it('menolak formulir tanpa produk yang dipilih', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarPromo::class)
        ->set('nama', 'Promo Tanpa Produk')
        ->set('tanggalMulai', '2026-09-01')
        ->set('tanggalSelesai', '2026-09-30')
        ->set('minimalDus', 15)
        ->set('bonusDus', 1)
        ->set('produkTerpilih', [])
        ->call('simpan')
        ->assertHasErrors('produkTerpilih');

    expect(Promo::count())->toBe(0);
});

it('menolak tanggal selesai sebelum tanggal mulai', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarPromo::class)
        ->set('nama', 'Promo Tanggal Salah')
        ->set('tanggalMulai', '2026-09-30')
        ->set('tanggalSelesai', '2026-09-01')
        ->set('minimalDus', 15)
        ->set('bonusDus', 1)
        ->set('produkTerpilih', [$this->produkA->id])
        ->call('simpan')
        ->assertHasErrors('tanggalSelesai');

    expect(Promo::count())->toBe(0);
});

it('menyunting promo memuat kembali produk yang sudah terpilih', function () {
    $promo = Promo::create([
        'nama' => 'Promo Lama', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30',
        'minimal_dus' => 15, 'bonus_dus' => 1,
    ]);
    $promo->produks()->sync([$this->produkA->id]);

    Livewire::actingAs($this->admin)
        ->test(DaftarPromo::class)
        ->call('sunting', $promo->id)
        ->assertSet('nama', 'Promo Lama')
        ->assertSet('produkTerpilih', [$this->produkA->id])
        ->set('nama', 'Promo Baru')
        ->set('produkTerpilih', [$this->produkB->id])
        ->call('simpan')
        ->assertHasNoErrors();

    $promo->refresh()->load('produks');

    expect($promo->nama)->toBe('Promo Baru')
        ->and($promo->produks->pluck('id')->all())->toBe([$this->produkB->id]);
});

describe('bentrok periode — V1 cuma satu promo aktif dalam satu waktu', function () {
    it('menolak periode yang tumpang tindih dengan promo lain', function () {
        Promo::create([
            'nama' => 'Promo Existing', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30',
            'minimal_dus' => 15, 'bonus_dus' => 1,
        ])->produks()->sync([$this->produkA->id]);

        Livewire::actingAs($this->admin)
            ->test(DaftarPromo::class)
            ->set('nama', 'Promo Bentrok')
            ->set('tanggalMulai', '2026-09-15')
            ->set('tanggalSelesai', '2026-10-15')
            ->set('minimalDus', 10)
            ->set('bonusDus', 2)
            ->set('produkTerpilih', [$this->produkB->id])
            ->call('simpan')
            ->assertHasErrors('tanggalMulai');

        expect(Promo::count())->toBe(1);
    });

    /**
     * Bentrok diperiksa terhadap SEMUA promo lain, bukan cuma yang sedang
     * aktif hari ini — rentang MASA DEPAN yang tumpang tindih tetap tidak
     * valid begitu tanggal itu tiba nanti, jadi harus dicegah sejak awal.
     */
    it('menolak periode masa depan yang tumpang tindih dengan promo masa depan lain', function () {
        Promo::create([
            'nama' => 'Promo Masa Depan', 'tanggal_mulai' => '2027-01-01', 'tanggal_selesai' => '2027-01-31',
            'minimal_dus' => 15, 'bonus_dus' => 1,
        ])->produks()->sync([$this->produkA->id]);

        Livewire::actingAs($this->admin)
            ->test(DaftarPromo::class)
            ->set('nama', 'Promo Bentrok Masa Depan')
            ->set('tanggalMulai', '2027-01-15')
            ->set('tanggalSelesai', '2027-02-15')
            ->set('minimalDus', 10)
            ->set('bonusDus', 2)
            ->set('produkTerpilih', [$this->produkB->id])
            ->call('simpan')
            ->assertHasErrors('tanggalMulai');

        expect(Promo::count())->toBe(1);
    });

    it('mengizinkan periode yang tidak tumpang tindih sama sekali', function () {
        Promo::create([
            'nama' => 'Promo September', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30',
            'minimal_dus' => 15, 'bonus_dus' => 1,
        ])->produks()->sync([$this->produkA->id]);

        Livewire::actingAs($this->admin)
            ->test(DaftarPromo::class)
            ->set('nama', 'Promo Oktober')
            ->set('tanggalMulai', '2026-10-01')
            ->set('tanggalSelesai', '2026-10-31')
            ->set('minimalDus', 10)
            ->set('bonusDus', 2)
            ->set('produkTerpilih', [$this->produkB->id])
            ->call('simpan')
            ->assertHasNoErrors();

        expect(Promo::count())->toBe(2);
    });

    it('mengizinkan menyunting periode promo itu sendiri tanpa dianggap bentrok', function () {
        $promo = Promo::create([
            'nama' => 'Promo Sendiri', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30',
            'minimal_dus' => 15, 'bonus_dus' => 1,
        ]);
        $promo->produks()->sync([$this->produkA->id]);

        Livewire::actingAs($this->admin)
            ->test(DaftarPromo::class)
            ->call('sunting', $promo->id)
            ->set('tanggalSelesai', '2026-10-05')
            ->call('simpan')
            ->assertHasNoErrors();

        expect($promo->fresh()->tanggal_selesai->toDateString())->toBe('2026-10-05');
    });
});

describe('penghapusan', function () {
    it('menolak menghapus promo yang sudah pernah dipakai pesanan', function () {
        $wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
        $toko = Toko::create([
            'kode' => 'TK-0001', 'nama' => 'Toko Uji', 'wilayah_id' => $wilayah->id,
            'alamat' => 'Jl. Uji', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        ]);

        $promo = Promo::create([
            'nama' => 'Promo Dipakai', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30',
            'minimal_dus' => 15, 'bonus_dus' => 1,
        ]);
        $promo->produks()->sync([$this->produkA->id]);

        Pesanan::create([
            'kode' => 'PSN-TEST01', 'toko_id' => $toko->id, 'wilayah_id' => $wilayah->id,
            'dibuat_oleh' => $this->sales->id, 'promo_id' => $promo->id, 'status' => 'order',
            'tanggal' => today(), 'total_dus' => 16, 'total_nilai' => 0,
        ]);

        Livewire::actingAs($this->admin)->test(DaftarPromo::class)->call('hapus', $promo->id);

        expect(Promo::find($promo->id))->not->toBeNull();
    });

    it('berhasil menghapus promo yang belum pernah dipakai pesanan mana pun', function () {
        $promo = Promo::create([
            'nama' => 'Promo Belum Dipakai', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30',
            'minimal_dus' => 15, 'bonus_dus' => 1,
        ]);
        $promo->produks()->sync([$this->produkA->id]);

        Livewire::actingAs($this->admin)->test(DaftarPromo::class)->call('hapus', $promo->id);

        expect(Promo::find($promo->id))->toBeNull();
    });
});

it('Promo::aktifPada() menemukan promo yang periodenya mencakup tanggal itu, dan tidak menemukan di luar rentangnya', function () {
    $promo = Promo::create([
        'nama' => 'Promo Rentang', 'tanggal_mulai' => '2026-09-10', 'tanggal_selesai' => '2026-09-20',
        'minimal_dus' => 15, 'bonus_dus' => 1,
    ]);

    expect(Promo::query()->aktifPada('2026-09-10')->first()?->id)->toBe($promo->id)
        ->and(Promo::query()->aktifPada('2026-09-20')->first()?->id)->toBe($promo->id)
        ->and(Promo::query()->aktifPada('2026-09-15')->first()?->id)->toBe($promo->id)
        ->and(Promo::query()->aktifPada('2026-09-09')->first())->toBeNull()
        ->and(Promo::query()->aktifPada('2026-09-21')->first())->toBeNull();
});
