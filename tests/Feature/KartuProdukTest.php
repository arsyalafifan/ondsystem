<?php

use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarProduk;
use App\Livewire\Pesanan\BuatPesanan;
use App\Livewire\Pos\Kasir;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Kartu pemilihan produk (Input Pesanan & POS) dan foto produk di Master
 * Produk. Yang paling dijaga: kartu cuma mengisi $baris/$barisBonus dengan
 * struktur yang sama persis seperti sebelumnya — penyimpanan pesanannya
 * sendiri tidak berubah.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->air = Produk::create(['kode' => 'P1', 'nama' => 'Air Mineral', 'stok' => 100, 'harga' => 20_000]);
    $this->teh = Produk::create(['kode' => 'P2', 'nama' => 'Teh Kotak', 'stok' => 100, 'harga' => 10_000]);

    $this->toko = Toko::create([
        'kode' => 'TK-K1', 'nama' => 'Toko Kartu', 'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. Kartu',
    ]);
});

describe('aturJumlah di Input Pesanan', function () {
    it('menambah, mengubah, dan menghapus produk dengan struktur baris yang sama', function () {
        $komponen = Livewire::actingAs($this->sales)->test(BuatPesanan::class)
            ->call('aturJumlah', 'baris', $this->air->id, 3)
            ->call('aturJumlah', 'baris', $this->teh->id, 2);

        expect($komponen->get('baris'))->toBe([
            ['produk_id' => $this->air->id, 'jumlah_dus' => 3],
            ['produk_id' => $this->teh->id, 'jumlah_dus' => 2],
        ]);

        $komponen->call('aturJumlah', 'baris', $this->air->id, 7);
        expect($komponen->get('baris')[0])->toBe(['produk_id' => $this->air->id, 'jumlah_dus' => 7]);

        $komponen->call('aturJumlah', 'baris', $this->air->id, 0);
        expect($komponen->get('baris'))->toBe([['produk_id' => $this->teh->id, 'jumlah_dus' => 2]])
            ->and($komponen->get('totalDus'))->toBe(2)
            ->and($komponen->get('totalNilai'))->toBe(20_000.0);
    });

    it('menyatukan produk yang sama yang terlanjur ada di beberapa baris', function () {
        $komponen = Livewire::actingAs($this->sales)->test(BuatPesanan::class)
            ->set('baris', [
                ['produk_id' => $this->air->id, 'jumlah_dus' => 2],
                ['produk_id' => $this->teh->id, 'jumlah_dus' => 1],
                ['produk_id' => $this->air->id, 'jumlah_dus' => 4],
            ])
            ->call('aturJumlah', 'baris', $this->air->id, 5);

        expect($komponen->get('baris'))->toBe([
            ['produk_id' => $this->air->id, 'jumlah_dus' => 5],
            ['produk_id' => $this->teh->id, 'jumlah_dus' => 1],
        ]);
    });

    it('sales tidak bisa mengisi daftar bonus lewat kartu', function () {
        $komponen = Livewire::actingAs($this->sales)->test(BuatPesanan::class)
            ->call('aturJumlah', 'barisBonus', $this->air->id, 5);

        expect($komponen->get('barisBonus'))->toBe([]);
    });

    it('menolak nama daftar yang tidak dikenal', function () {
        $komponen = Livewire::actingAs($this->admin)->test(BuatPesanan::class)
            ->call('aturJumlah', 'catatan', $this->air->id, 5);

        expect($komponen->get('catatan'))->toBe('');
    });

    it('pesanan dari kartu tersimpan persis seperti input lama', function () {
        Livewire::actingAs($this->sales)->test(BuatPesanan::class)
            ->call('pilihToko', $this->toko->id)
            ->call('aturJumlah', 'baris', $this->air->id, 4)
            ->call('aturJumlah', 'baris', $this->teh->id, 3)
            ->call('simpan')
            ->assertHasNoErrors();

        $pesanan = Pesanan::with('items')->sole();

        expect($pesanan->total_dus)->toBe(7)
            ->and($pesanan->items->pluck('jumlah_dus', 'produk_id')->all())
            ->toEqual([$this->air->id => 4, $this->teh->id => 3]);
    });

    it('admin bisa mengisi bonus lewat kartu dan ikut tersimpan sebagai bonus', function () {
        $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

        Livewire::actingAs($this->admin)->test(BuatPesanan::class)
            ->call('pilihToko', $this->toko->id)
            ->set('salesId', $sales->id)
            ->call('aturJumlah', 'baris', $this->air->id, 5)
            ->call('aturJumlah', 'barisBonus', $this->teh->id, 1)
            ->call('simpan')
            ->assertHasNoErrors();

        $bonus = Pesanan::sole()->items()->where('is_bonus', true)->sole();

        expect($bonus->produk_id)->toBe($this->teh->id)
            ->and($bonus->jumlah_dus)->toBe(1);
    });
});

describe('aturJumlah di POS', function () {
    it('kartu dan pindai barcode mengisi daftar yang sama', function () {
        $this->air->update(['barcode' => '8990001']);

        $komponen = Livewire::actingAs($this->admin)->test(Kasir::class)
            ->call('aturJumlah', 'baris', $this->air->id, 2)
            ->set('cariBarcode', '8990001')
            ->call('tambahDariBarcode');

        expect($komponen->get('baris'))->toBe([['produk_id' => $this->air->id, 'jumlah_dus' => 3]]);
    });
});

describe('ringkasan pesanan sebelum simpan', function () {
    it('menampilkan item, bonus, total dus, dan total bayar', function () {
        Livewire::actingAs($this->admin)->test(BuatPesanan::class)
            ->assertSee(__('pesanan.ringkasan_kosong'))
            ->call('aturJumlah', 'baris', $this->air->id, 3)
            ->call('aturJumlah', 'barisBonus', $this->teh->id, 2)
            ->assertDontSee(__('pesanan.ringkasan_kosong'))
            ->assertSee(__('pesanan.ringkasan_subtotal', ['jumlah' => 1]))
            ->assertSee(__('pesanan.ringkasan_gratis'))
            ->assertSeeInOrder([__('pesanan.ringkasan_total_dus'), '5'])
            ->assertSeeInOrder([__('pesanan.ringkasan_total_bayar'), 'Rp 60.000']);
    });

    it('bonus sales tidak tampil di ringkasan karena memang tidak ikut tersimpan', function () {
        Livewire::actingAs($this->sales)->test(BuatPesanan::class)
            ->set('barisBonus', [['produk_id' => $this->teh->id, 'jumlah_dus' => 2]])
            ->call('aturJumlah', 'baris', $this->air->id, 1)
            ->assertDontSee(__('pesanan.ringkasan_gratis'));
    });

    it('item bisa dihapus langsung dari ringkasan', function () {
        $komponen = Livewire::actingAs($this->admin)->test(Kasir::class)
            ->call('aturJumlah', 'baris', $this->air->id, 2)
            ->call('aturJumlah', 'baris', $this->air->id, 0);

        expect($komponen->get('baris'))->toBe([]);
        $komponen->assertSee(__('pesanan.ringkasan_kosong'));
    });
});

describe('foto produk di Master Produk', function () {
    it('menyimpan, mengganti, dan menghapus foto produk', function () {
        $komponen = Livewire::actingAs($this->admin)->test(DaftarProduk::class)
            ->call('sunting', $this->air->id)
            ->set('foto', UploadedFile::fake()->image('air.png', 1200, 900))
            ->call('simpan')
            ->assertHasNoErrors();

        $fotoPertama = $this->air->fresh()->foto;
        expect($fotoPertama)->toStartWith('produk/');
        Storage::disk('public')->assertExists($fotoPertama);

        // Diperkecil sebelum disimpan supaya ringan dibuka di HP.
        [$lebar] = getimagesizefromstring(Storage::disk('public')->get($fotoPertama));
        expect($lebar)->toBeLessThanOrEqual(600);

        $komponen->call('sunting', $this->air->id)
            ->set('foto', UploadedFile::fake()->image('air2.jpg', 300, 300))
            ->call('simpan')
            ->assertHasNoErrors();

        $fotoKedua = $this->air->fresh()->foto;
        expect($fotoKedua)->not->toBe($fotoPertama);
        Storage::disk('public')->assertMissing($fotoPertama);

        $komponen->call('sunting', $this->air->id)
            ->call('buangFoto')
            ->call('simpan')
            ->assertHasNoErrors();

        expect($this->air->fresh()->foto)->toBeNull();
        Storage::disk('public')->assertMissing($fotoKedua);
    });

    it('menolak berkas yang bukan gambar', function () {
        Livewire::actingAs($this->admin)->test(DaftarProduk::class)
            ->call('sunting', $this->air->id)
            ->set('foto', UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf'))
            ->assertHasErrors('foto')
            ->assertSet('foto', null)
            // Simpan tetap jalan (tanpa foto) — formulir tidak macet.
            ->call('simpan')
            ->assertHasNoErrors();

        expect($this->air->fresh()->foto)->toBeNull();
    });

    it('foto produk tampil di kartu pemilihan Input Pesanan', function () {
        Livewire::actingAs($this->admin)->test(DaftarProduk::class)
            ->call('sunting', $this->air->id)
            ->set('foto', UploadedFile::fake()->image('air.png', 400, 400))
            ->call('simpan');

        Livewire::actingAs($this->sales)->test(BuatPesanan::class)
            ->assertSeeHtml($this->air->fresh()->foto_url);
    });
});
