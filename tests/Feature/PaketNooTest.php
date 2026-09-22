<?php

use App\Enums\PeranPengguna;
use App\Livewire\Noo\DaftarPaket;
use App\Models\Depot;
use App\Models\PaketNoo;
use App\Models\Produk;
use App\Models\User;
use App\Services\DepotService;
use App\Support\DepotContext;
use Livewire\Livewire;

/**
 * Setting Default Pesanan NOO. Satu aturan yang menjaga seluruhnya: nama
 * paket adalah janji ke pemilik toko ("15 dus, bonus 2"), jadi rincian
 * produknya harus genap PERSIS segitu. Paket timpang ditolak di sini, di
 * depan admin yang sedang menyusunnya — bukan nanti saat driver sudah
 * berdiri di depan toko dan pesanan perdananya gagal terbentuk.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);

    $this->cokelat = Produk::create(['kode' => 'P1', 'nama' => 'Es Krim Cokelat', 'stok' => 500, 'harga' => 40_000]);
    $this->vanila = Produk::create(['kode' => 'P2', 'nama' => 'Es Krim Vanila', 'stok' => 500, 'harga' => 38_000]);
});

/** Paket 15+2 yang isinya sudah genap — titik awal tes yang bukan soal validasi. */
function isiPaketGenap($komponen, Produk $cokelat, Produk $vanila)
{
    return $komponen
        ->set('nama', '15+2')
        ->set('dusReguler', '15')
        ->set('dusBonus', '2')
        ->set('baris.0.produk_id', $cokelat->id)
        ->set('baris.0.jumlah_dus', 10)
        ->call('tambahBaris')
        ->set('baris.1.produk_id', $vanila->id)
        ->set('baris.1.jumlah_dus', 5)
        ->set('barisBonus.0.produk_id', $cokelat->id)
        ->set('barisBonus.0.jumlah_dus', 2);
}

it('menyimpan paket ketika rincian produknya genap', function () {
    $komponen = Livewire::actingAs($this->admin)->test(DaftarPaket::class)->call('buatBaru');

    isiPaketGenap($komponen, $this->cokelat, $this->vanila)
        ->call('simpan')
        ->assertHasNoErrors();

    $paket = PaketNoo::with('items')->where('nama', '15+2')->firstOrFail();

    expect($paket->dus_reguler)->toBe(15)
        ->and($paket->dus_bonus)->toBe(2)
        ->and($paket->items)->toHaveCount(3)
        ->and($paket->dusTerpilih(false))->toBe(15)
        ->and($paket->dusTerpilih(true))->toBe(2)
        ->and($paket->lengkap)->toBeTrue();
});

it('menolak paket yang jumlah dus regulernya tidak genap', function () {
    Livewire::actingAs($this->admin)->test(DaftarPaket::class)
        ->call('buatBaru')
        ->set('nama', '15+2')
        ->set('dusReguler', '15')
        ->set('dusBonus', '2')
        // Baru 10 dari 15 dus reguler yang dipilih produknya.
        ->set('baris.0.produk_id', $this->cokelat->id)
        ->set('baris.0.jumlah_dus', 10)
        ->set('barisBonus.0.produk_id', $this->cokelat->id)
        ->set('barisBonus.0.jumlah_dus', 2)
        ->call('simpan')
        ->assertHasErrors('baris');

    expect(PaketNoo::count())->toBe(0);
});

it('menolak paket yang jumlah dus bonusnya tidak genap', function () {
    Livewire::actingAs($this->admin)->test(DaftarPaket::class)
        ->call('buatBaru')
        ->set('nama', '15+2')
        ->set('dusReguler', '15')
        ->set('dusBonus', '2')
        ->set('baris.0.produk_id', $this->cokelat->id)
        ->set('baris.0.jumlah_dus', 15)
        // Bonusnya kelebihan: 3 dus untuk paket yang menjanjikan 2.
        ->set('barisBonus.0.produk_id', $this->vanila->id)
        ->set('barisBonus.0.jumlah_dus', 3)
        ->call('simpan')
        ->assertHasErrors('barisBonus');

    expect(PaketNoo::count())->toBe(0);
});

it('menolak produk yang sama dipilih dua kali dalam satu bagian', function () {
    Livewire::actingAs($this->admin)->test(DaftarPaket::class)
        ->call('buatBaru')
        ->set('nama', '15+2')
        ->set('dusReguler', '15')
        ->set('dusBonus', '2')
        ->set('baris.0.produk_id', $this->cokelat->id)
        ->set('baris.0.jumlah_dus', 10)
        ->call('tambahBaris')
        ->set('baris.1.produk_id', $this->cokelat->id)
        ->set('baris.1.jumlah_dus', 5)
        ->set('barisBonus.0.produk_id', $this->cokelat->id)
        ->set('barisBonus.0.jumlah_dus', 2)
        ->call('simpan')
        ->assertHasErrors('baris.1.produk_id');

    expect(PaketNoo::count())->toBe(0);
});

it('mengizinkan produk yang sama muncul sekali sebagai reguler dan sekali sebagai bonus', function () {
    // Justru ini bentuk paket yang lazim: "15 dus cokelat, bonus 2 cokelat".
    Livewire::actingAs($this->admin)->test(DaftarPaket::class)
        ->call('buatBaru')
        ->set('nama', '15+2')
        ->set('dusReguler', '15')
        ->set('dusBonus', '2')
        ->set('baris.0.produk_id', $this->cokelat->id)
        ->set('baris.0.jumlah_dus', 15)
        ->set('barisBonus.0.produk_id', $this->cokelat->id)
        ->set('barisBonus.0.jumlah_dus', 2)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(PaketNoo::with('items')->firstOrFail()->items)->toHaveCount(2);
});

it('menolak nama paket yang sudah dipakai di depot yang sama', function () {
    $komponen = Livewire::actingAs($this->admin)->test(DaftarPaket::class)->call('buatBaru');
    isiPaketGenap($komponen, $this->cokelat, $this->vanila)->call('simpan')->assertHasNoErrors();

    $lagi = Livewire::actingAs($this->admin)->test(DaftarPaket::class)->call('buatBaru');
    isiPaketGenap($lagi, $this->cokelat, $this->vanila)->call('simpan')->assertHasErrors('nama');

    expect(PaketNoo::count())->toBe(1);
});

it('menimpa isi paket lama saat disunting, bukan menumpuknya', function () {
    $komponen = Livewire::actingAs($this->admin)->test(DaftarPaket::class)->call('buatBaru');
    isiPaketGenap($komponen, $this->cokelat, $this->vanila)->call('simpan');

    $paket = PaketNoo::firstOrFail();

    Livewire::actingAs($this->admin)->test(DaftarPaket::class)
        ->call('sunting', $paket->id)
        ->set('dusReguler', '10')
        ->set('dusBonus', '1')
        ->set('baris', [['produk_id' => $this->vanila->id, 'jumlah_dus' => 10]])
        ->set('barisBonus', [['produk_id' => $this->vanila->id, 'jumlah_dus' => 1]])
        ->call('simpan')
        ->assertHasNoErrors();

    $paket = $paket->fresh('items');

    expect(PaketNoo::count())->toBe(1)
        ->and($paket->items)->toHaveCount(2)
        ->and($paket->dusTerpilih(false))->toBe(10)
        ->and($paket->lengkap)->toBeTrue();
});

it('menganggap paket yang produknya belum dipilih sebagai belum lengkap', function () {
    // Bentuk persis paket bawaan hasil migrasi: angkanya sudah ada,
    // produknya belum — belum boleh ditawarkan ke sales.
    $paket = PaketNoo::create(['nama' => '10+1', 'dus_reguler' => 10, 'dus_bonus' => 1, 'urutan' => 1, 'aktif' => true]);

    expect($paket->load('items')->lengkap)->toBeFalse();
});

it('memberi depot baru dua paket bawaan tanpa isi', function () {
    $depot = app(DepotService::class)->buat(Depot::factory()->raw());

    $paket = DepotContext::jalankanSebagai($depot, fn () => PaketNoo::with('items')->orderBy('urutan')->get());

    expect($paket->pluck('nama')->all())->toBe(['15+2', '10+1'])
        ->and($paket->every(fn (PaketNoo $p): bool => $p->items->isEmpty()))->toBeTrue()
        ->and($paket->every(fn (PaketNoo $p): bool => ! $p->lengkap))->toBeTrue();
});

it('menghapus paket beserta isinya', function () {
    $komponen = Livewire::actingAs($this->admin)->test(DaftarPaket::class)->call('buatBaru');
    isiPaketGenap($komponen, $this->cokelat, $this->vanila)->call('simpan');

    $paket = PaketNoo::firstOrFail();

    Livewire::actingAs($this->admin)->test(DaftarPaket::class)->call('hapus', $paket->id);

    expect(PaketNoo::count())->toBe(0)
        ->and(DB::table('paket_noo_items')->count())->toBe(0);
});
