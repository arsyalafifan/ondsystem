<?php

use App\Enums\HariKunjungan;
use App\Enums\PeranPengguna;
use App\Enums\StatusNoo;
use App\Enums\StatusPesanan;
use App\Livewire\Master\DaftarToko;
use App\Models\Freezer;
use App\Models\Noo;
use App\Models\PaketNoo;
use App\Models\PenugasanToko;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Livewire\Livewire;

/**
 * Hapus toko di Master Toko: hanya toko NONAKTIF yang belum punya riwayat
 * (pesanan, kunjungan, stop rute, NOO).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
});

function tokoUntukHapus(array $atribut = []): Toko
{
    static $n = 0;
    $n++;

    return Toko::create(array_merge([
        'kode' => sprintf('TK-HPS%03d', $n), 'nama' => 'Toko Hapus '.$n,
        'wilayah_id' => test()->wilayah->id, 'alamat' => 'Jl. Uji', 'aktif' => false,
    ], $atribut));
}

it('menghapus toko nonaktif tanpa riwayat, dan membebaskan IDN-nya', function () {
    Freezer::create(['idn' => 'IDN-HPS', 'tipe' => 'SD-200']);
    $toko = tokoUntukHapus(['asset_id' => 'IDN-HPS']);

    Livewire::actingAs($this->admin)->test(DaftarToko::class)
        ->call('konfirmasiHapusToko', $toko->id)
        ->assertSet('konfirmasiHapus', $toko->id)
        ->call('hapus')
        ->assertDispatched('notifikasi', jenis: 'sukses');

    expect(Toko::find($toko->id))->toBeNull()
        ->and(Freezer::where('idn', 'IDN-HPS')->first()->toko)->toBeNull();
});

it('menolak menghapus toko yang masih aktif', function () {
    $toko = tokoUntukHapus(['aktif' => true]);

    Livewire::actingAs($this->admin)->test(DaftarToko::class)
        ->call('konfirmasiHapusToko', $toko->id)
        ->assertSet('konfirmasiHapus', null)
        ->assertDispatched('notifikasi', jenis: 'error');

    // Walau modal dipaksa terbuka dari klien, eksekusinya tetap menolak.
    Livewire::actingAs($this->admin)->test(DaftarToko::class)
        ->set('konfirmasiHapus', $toko->id)
        ->call('hapus')
        ->assertDispatched('notifikasi', jenis: 'error');

    expect(Toko::find($toko->id))->not->toBeNull();
});

it('menolak menghapus toko yang sudah punya pesanan, walau sudah nonaktif', function () {
    $toko = tokoUntukHapus();

    Pesanan::create([
        'kode' => 'PSN-HPS', 'toko_id' => $toko->id, 'wilayah_id' => $this->wilayah->id,
        'dibuat_oleh' => $this->admin->id, 'status' => StatusPesanan::Order, 'jenis' => 'normal',
        'tanggal' => today(), 'total_dus' => 5, 'total_nilai' => 50_000,
    ]);

    Livewire::actingAs($this->admin)->test(DaftarToko::class)
        ->call('konfirmasiHapusToko', $toko->id)
        ->assertSet('konfirmasiHapus', null)
        ->assertDispatched('notifikasi', jenis: 'error');

    // Walau modal dipaksa terbuka dari klien, eksekusinya tetap menolak.
    Livewire::actingAs($this->admin)->test(DaftarToko::class)
        ->set('konfirmasiHapus', $toko->id)
        ->call('hapus')
        ->assertDispatched('notifikasi', jenis: 'error');

    expect(Toko::find($toko->id))->not->toBeNull();
});

it('menolak menghapus toko yang berasal dari NOO', function () {
    $toko = tokoUntukHapus();
    $paket = PaketNoo::create(['nama' => '15+2', 'dus_reguler' => 15, 'dus_bonus' => 2, 'urutan' => 1, 'aktif' => true]);

    Noo::create([
        'kode' => 'NOO-HPS-0001', 'status' => StatusNoo::Selesai, 'toko_id' => $toko->id, 'paket_noo_id' => $paket->id,
        'nama' => $toko->nama, 'alamat' => 'Jl. Uji', 'telepon' => '081200000001', 'nama_pemilik' => 'Budi', 'nik_pemilik' => '3201234567890123', 'wilayah_id' => $this->wilayah->id,
        'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        'diajukan_oleh' => $this->admin->id, 'diajukan_at' => now(),
    ]);

    Livewire::actingAs($this->admin)->test(DaftarToko::class)
        ->call('konfirmasiHapusToko', $toko->id)
        ->assertDispatched('notifikasi', jenis: 'error');

    expect(Toko::find($toko->id))->not->toBeNull();
});

it('ikut menghapus penugasan sales toko yang dihapus', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $toko = tokoUntukHapus();
    PenugasanToko::create([
        'toko_id' => $toko->id, 'sales_id' => $sales->id,
        'hari' => HariKunjungan::Senin, 'ditugaskan_oleh' => $this->admin->id,
    ]);

    Livewire::actingAs($this->admin)->test(DaftarToko::class)
        ->call('konfirmasiHapusToko', $toko->id)
        ->call('hapus');

    expect(Toko::find($toko->id))->toBeNull()
        ->and(PenugasanToko::where('toko_id', $toko->id)->count())->toBe(0);
});
