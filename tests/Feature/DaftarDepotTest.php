<?php

use App\Enums\PeranPengguna;
use App\Livewire\Depot\DaftarDepot;
use App\Models\Depot;
use App\Models\PengaturanKunjungan;
use App\Models\Toko;
use App\Models\User;
use App\Support\DepotContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->superadmin = User::factory()->superadmin()->create();
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

it('superadmin bisa membuka layar kelola depot', function () {
    $this->actingAs($this->superadmin)->get(route('depot.daftar'))->assertOk();
});

it('admin biasa tidak bisa membuka layar kelola depot', function () {
    $this->actingAs($this->admin)->get(route('depot.daftar'))->assertForbidden();
});

it('membuat depot baru sekaligus baris pengaturan_kunjungans awalnya', function () {
    Livewire::actingAs($this->superadmin)
        ->test(DaftarDepot::class)
        ->set('kode', 'DUMAI')
        ->set('nama', 'Dumai')
        ->set('lat', '1.6667')
        ->set('lng', '101.4500')
        ->set('serviceMinutes', '10')
        ->set('jamBerangkat', '08:00')
        ->set('maxToko', '25')
        ->set('maxDus', '220')
        ->set('minDusPerToko', '5')
        ->call('simpan');

    $depot = Depot::where('kode', 'DUMAI')->first();

    expect($depot)->not->toBeNull()
        ->and($depot->nama)->toBe('Dumai')
        ->and((float) $depot->lat)->toBe(1.6667);

    DepotContext::jalankanUntukSemuaDepot(function () use ($depot) {
        expect(PengaturanKunjungan::where('depot_id', $depot->id)->exists())->toBeTrue();
    });
});

it('depot baru mulai kosong sepenuhnya — tidak mewarisi data depot lain', function () {
    Toko::create(['kode' => 'TK-A1', 'nama' => 'Toko Milik Perawang', 'alamat' => 'Jl. A']);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarDepot::class)
        ->set('kode', 'BENGKALIS')
        ->set('nama', 'Bengkalis')
        ->set('serviceMinutes', '10')
        ->set('jamBerangkat', '08:00')
        ->set('maxToko', '25')
        ->set('maxDus', '220')
        ->set('minDusPerToko', '5')
        ->call('simpan');

    $depotBaru = Depot::where('kode', 'BENGKALIS')->first();

    DepotContext::jalankanSebagai($depotBaru, function () {
        expect(Toko::count())->toBe(0);
    });
});

it('menolak kode depot yang sudah dipakai depot lain', function () {
    Depot::factory()->create(['kode' => 'DUMAI']);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarDepot::class)
        ->set('kode', 'DUMAI')
        ->set('nama', 'Dumai Duplikat')
        ->set('serviceMinutes', '10')
        ->set('jamBerangkat', '08:00')
        ->set('maxToko', '25')
        ->set('maxDus', '220')
        ->set('minDusPerToko', '5')
        ->call('simpan')
        ->assertHasErrors('kode');
});

it('menyunting depot yang sudah ada tanpa membuat baris pengaturan_kunjungans baru', function () {
    $depot = Depot::factory()->create(['kode' => 'DUMAI', 'nama' => 'Dumai Lama']);
    PengaturanKunjungan::create(['depot_id' => $depot->id, 'maks_toko_per_hari' => 20]);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarDepot::class)
        ->call('sunting', $depot->id)
        ->set('nama', 'Dumai Baru')
        ->set('maxToko', '30')
        ->call('simpan');

    expect($depot->fresh()->nama)->toBe('Dumai Baru')
        ->and($depot->fresh()->max_toko)->toBe(30);

    DepotContext::jalankanUntukSemuaDepot(function () use ($depot) {
        expect(PengaturanKunjungan::where('depot_id', $depot->id)->count())->toBe(1);
    });
});
