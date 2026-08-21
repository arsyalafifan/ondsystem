<?php

use App\Enums\PeranPengguna;
use App\Livewire\Routing\GenerateRouting;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

/** Satu pesanan PROCESS siap-routing, supaya ringkasanMenunggu() punya isi. */
function siapkanSatuPesananSiapRouting(): void
{
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk', 'stok' => 1000, 'harga' => 10_000]);
    $toko = Toko::create([
        'kode' => 'TK1', 'nama' => 'Toko Uji', 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Uji', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);
    $pesanan = app(PesananService::class)->buat($toko, [['produk_id' => $produk->id, 'jumlah_dus' => 10]], $sales);
    app(PesananService::class)->setujui($pesanan, test()->admin);
}

/**
 * Input angka lewat wire:model.live mengirim string kosong sesaat ketika
 * pengguna menghapus isinya sebelum mengetik angka baru (select-all lalu
 * ketik ulang). Livewire diam-diam gagal menaruh string kosong ke properti
 * bertipe `int` tanpa nilai bawaan (`public int $maxToko`), membuatnya
 * "uninitialized" — akses berikutnya ($this->maxToko di dalam
 * ringkasanMenunggu(), yang hanya tersentuh kalau ada pesanan menunggu)
 * melempar Livewire\Exceptions\PropertyNotFoundException dan mematikan
 * seluruh halaman. Reproduksinya butuh setidaknya satu pesanan siap-routing
 * — dengan daftar kosong, ringkasanMenunggu() lewat cabang ternary yang
 * tidak pernah menyentuh $maxToko sama sekali, sehingga bug ini tidak
 * kelihatan di halaman yang masih kosong.
 */
it('tidak error saat input maks toko dikosongkan sesaat, walau ada pesanan menunggu', function () {
    siapkanSatuPesananSiapRouting();

    Livewire::actingAs($this->admin)
        ->test(GenerateRouting::class)
        ->set('maxToko', '')
        ->assertOk()
        ->assertSet('maxToko', 1);
});

it('tidak error saat input maks dus dikosongkan sesaat, walau ada pesanan menunggu', function () {
    siapkanSatuPesananSiapRouting();

    Livewire::actingAs($this->admin)
        ->test(GenerateRouting::class)
        ->set('maxDus', '')
        ->assertOk()
        ->assertSet('maxDus', 1);
});

it('menerima angka baru dengan benar setelah sempat dikosongkan', function () {
    siapkanSatuPesananSiapRouting();

    Livewire::actingAs($this->admin)
        ->test(GenerateRouting::class)
        ->set('maxToko', '')
        ->set('maxToko', 30)
        ->assertOk()
        ->assertSet('maxToko', 30);
});
