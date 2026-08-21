<?php

use App\Enums\PeranPengguna;
use App\Livewire\Akun\GantiKataSandi;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->pengguna = User::factory()->create([
        'role' => PeranPengguna::Sales,
        'password' => Hash::make('kata-sandi-lama'),
    ]);
});

it('menolak kata sandi lama yang salah', function () {
    Livewire::actingAs($this->pengguna)
        ->test(GantiKataSandi::class)
        ->set('kataSandiLama', 'salah')
        ->set('kataSandiBaru', 'kata-sandi-baru-123')
        ->set('kataSandiBaru_confirmation', 'kata-sandi-baru-123')
        ->call('simpan')
        ->assertHasErrors('kataSandiLama');

    expect(Hash::check('kata-sandi-lama', $this->pengguna->fresh()->password))->toBeTrue();
});

it('menolak konfirmasi kata sandi baru yang tidak cocok', function () {
    Livewire::actingAs($this->pengguna)
        ->test(GantiKataSandi::class)
        ->set('kataSandiLama', 'kata-sandi-lama')
        ->set('kataSandiBaru', 'kata-sandi-baru-123')
        ->set('kataSandiBaru_confirmation', 'tidak-cocok')
        ->call('simpan')
        ->assertHasErrors('kataSandiBaru');
});

it('mengganti kata sandi dan bisa langsung dipakai masuk', function () {
    Livewire::actingAs($this->pengguna)
        ->test(GantiKataSandi::class)
        ->set('kataSandiLama', 'kata-sandi-lama')
        ->set('kataSandiBaru', 'kata-sandi-baru-123')
        ->set('kataSandiBaru_confirmation', 'kata-sandi-baru-123')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Hash::check('kata-sandi-baru-123', $this->pengguna->fresh()->password))->toBeTrue();

    Livewire::test(Login::class)
        ->set('email', $this->pengguna->email)
        ->set('password', 'kata-sandi-baru-123')
        ->call('masuk')
        ->assertRedirect(route('pesanan.buat'));

    $this->assertAuthenticatedAs($this->pengguna->fresh());
});
