<?php

use App\Enums\PeranPengguna;
use App\Livewire\Pengguna\DaftarPengguna;
use App\Models\User;
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
