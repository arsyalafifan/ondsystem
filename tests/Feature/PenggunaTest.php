<?php

use App\Enums\PeranPengguna;
use App\Livewire\Pengguna\DaftarPengguna;
use App\Models\Depot;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use App\Support\DepotContext;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

it('membuat pengguna baru dengan kata sandi standar', function () {
    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->set('name', 'Sales Baru')
        ->set('email', 'sales-baru@ondsystem.test')
        ->set('role', PeranPengguna::Sales->value)
        ->set('depotIdForm', (string) $this->depot->id)
        ->call('simpan');

    $pengguna = User::where('email', 'sales-baru@ondsystem.test')->first();

    expect($pengguna)->not->toBeNull()
        ->and($pengguna->role)->toBe(PeranPengguna::Sales)
        ->and(Hash::check('password', $pengguna->password))->toBeTrue();
});

it('menyunting pengguna tanpa menyentuh kata sandinya', function () {
    $pengguna = User::factory()->create(['role' => PeranPengguna::Sales, 'password' => Hash::make('rahasia-lama')]);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->call('sunting', $pengguna->id)
        ->set('name', 'Nama Diubah')
        ->set('role', PeranPengguna::Admin->value)
        ->call('simpan');

    $segar = $pengguna->fresh();

    expect($segar->name)->toBe('Nama Diubah')
        ->and($segar->role)->toBe(PeranPengguna::Admin)
        ->and(Hash::check('rahasia-lama', $segar->password))->toBeTrue();
});

it('reset sandi mengembalikan ke kata sandi standar meski sudah pernah diganti', function () {
    $pengguna = User::factory()->create(['password' => Hash::make('sudah-diganti-user')]);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->call('resetSandi', $pengguna->id);

    expect(Hash::check('password', $pengguna->fresh()->password))->toBeTrue();
});

it('menolak email yang sudah dipakai pengguna lain', function () {
    User::factory()->create(['email' => 'dipakai@ondsystem.test']);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->set('name', 'Pengguna Lain')
        ->set('email', 'dipakai@ondsystem.test')
        ->set('role', PeranPengguna::Sales->value)
        ->set('depotIdForm', (string) $this->depot->id)
        ->call('simpan')
        ->assertHasErrors('email');
});

it('menyimpan status aktif nonaktif', function () {
    $pengguna = User::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->call('sunting', $pengguna->id)
        ->set('aktif', false)
        ->call('simpan');

    expect($pengguna->fresh()->aktif)->toBeFalse();
});

it('menolak admin biasa mengubah data pengguna', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarPengguna::class)
        ->set('name', 'Percobaan')
        ->set('email', 'percobaan@ondsystem.test')
        ->set('role', PeranPengguna::Sales->value)
        ->call('simpan')
        ->assertForbidden();
});

it('membuat pengguna baru di depot yang dipilih eksplisit dari form, bukan depot yang sedang aktif', function () {
    $depotLain = Depot::factory()->create(['kode' => 'DEPOTLAIN']);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->set('name', 'Sales Depot Lain')
        ->set('email', 'sales-lain@ondsystem.test')
        ->set('role', PeranPengguna::Sales->value)
        ->set('depotIdForm', (string) $depotLain->id)
        ->call('simpan');

    $pengguna = User::withoutGlobalScope(DepotScope::class)
        ->where('email', 'sales-lain@ondsystem.test')->first();

    expect($pengguna)->not->toBeNull()
        ->and($pengguna->depot_id)->toBe($depotLain->id);
});

it('menolak membuat pengguna baru tanpa memilih depot, kecuali perannya superadmin', function () {
    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->set('name', 'Tanpa Depot')
        ->set('email', 'tanpa-depot@ondsystem.test')
        ->set('role', PeranPengguna::Sales->value)
        ->set('depotIdForm', '')
        ->call('simpan')
        ->assertHasErrors('depotIdForm');
});

it('superadmin baru selalu tanpa depot, walau depotIdForm terisi', function () {
    $depotLain = Depot::factory()->create(['kode' => 'DEPOTLAIN2']);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->set('name', 'Superadmin Baru')
        ->set('email', 'superadmin-baru@ondsystem.test')
        ->set('role', PeranPengguna::Superadmin->value)
        ->set('depotIdForm', (string) $depotLain->id)
        ->call('simpan');

    $pengguna = User::withoutGlobalScope(DepotScope::class)
        ->where('email', 'superadmin-baru@ondsystem.test')->first();

    expect($pengguna->depot_id)->toBeNull();
});

it('email yang sama boleh dipakai di depot lain, tidak dianggap bentrok', function () {
    $depotLain = Depot::factory()->create(['kode' => 'DEPOTLAIN3']);
    User::factory()->create(['email' => 'sama@ondsystem.test']);

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->set('name', 'Pengguna Depot Lain')
        ->set('email', 'sama@ondsystem.test')
        ->set('role', PeranPengguna::Sales->value)
        ->set('depotIdForm', (string) $depotLain->id)
        ->call('simpan')
        ->assertHasNoErrors('email');

    $jumlah = User::withoutGlobalScope(DepotScope::class)
        ->where('email', 'sama@ondsystem.test')->count();

    expect($jumlah)->toBe(2);
});

it('daftar pengguna menampilkan akun dari semua depot sekaligus, bukan cuma depot yang sedang aktif', function () {
    $depotLain = Depot::factory()->create(['kode' => 'DEPOTLAIN4']);

    DepotContext::jalankanSebagai($depotLain, function () {
        User::factory()->create(['name' => 'Pengguna Depot Lain', 'role' => PeranPengguna::Sales]);
    });

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->assertSee('Pengguna Depot Lain');
});

it('filter depot pada daftar pengguna hanya menampilkan akun depot itu', function () {
    $depotLain = Depot::factory()->create(['kode' => 'DEPOTLAIN5']);

    DepotContext::jalankanSebagai($depotLain, function () {
        User::factory()->create(['name' => 'Pengguna Depot Lain', 'role' => PeranPengguna::Sales]);
    });

    Livewire::actingAs($this->superadmin)
        ->test(DaftarPengguna::class)
        ->set('filterDepot', (string) $this->admin->depot_id)
        ->assertSee($this->admin->name)
        ->assertDontSee('Pengguna Depot Lain');
});
