<?php

use App\Enums\PeranPengguna;
use App\Models\User;

/**
 * Pemilih aplikasi (O&D System / HR System / Accounting / User Admin) dan
 * pemindahan Kelola Pengguna/Kelola Depot ke dalam "User Admin".
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);
    $this->hr = User::factory()->create(['role' => PeranPengguna::Hr]);
});

it('Admin melihat O&D, HR, dan Accounting di dropdown aplikasi, tapi tidak User Admin', function () {
    $halaman = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

    $halaman->assertSee(__('nav.aplikasi_ond'))
        ->assertSee(__('nav.aplikasi_hr'))
        ->assertSee(__('nav.aplikasi_accounting'))
        ->assertSee(__('nav.segera_hadir'))
        ->assertDontSee(__('nav.aplikasi_user_admin'));
});

it('Superadmin melihat keempat entri termasuk User Admin', function () {
    $halaman = $this->actingAs($this->superadmin)->get(route('dashboard'))->assertOk();

    $halaman->assertSee(__('nav.aplikasi_ond'))
        ->assertSee(__('nav.aplikasi_hr'))
        ->assertSee(__('nav.aplikasi_accounting'))
        ->assertSee(__('nav.aplikasi_user_admin'));
});

it('Hr tidak melihat pemilih aplikasi sama sekali', function () {
    $this->actingAs($this->hr)->get(route('hr.dashboard'))
        ->assertOk()
        ->assertDontSee(__('nav.aplikasi_hr'));
});

it('Superadmin membuka Kelola Pengguna melihat menu User Admin, bukan menu O&D', function () {
    $halaman = $this->actingAs($this->superadmin)->get(route('pengguna.daftar'))->assertOk();

    $halaman->assertSee(__('nav.manage_pengguna'))
        ->assertSee(__('nav.manage_depot'))
        ->assertDontSee(__('nav.master_toko'));
});

it('Admin/Superadmin membuka dasbor HR melihat menu HR, bukan menu O&D', function () {
    foreach (['admin', 'superadmin'] as $peran) {
        $halaman = $this->actingAs($this->{$peran})->get(route('hr.dashboard'))->assertOk();

        $halaman->assertSee(__('nav.hr_karyawan'))
            ->assertSee(__('nav.hr_department'))
            ->assertSee(__('nav.hr_jabatan'))
            ->assertDontSee(__('nav.master_toko'));
    }
});

it('Kelola Pengguna dan Kelola Depot tidak lagi tampil di sidebar O&D biasa', function () {
    $halaman = $this->actingAs($this->superadmin)->get(route('dashboard'))->assertOk();

    $halaman->assertDontSee(__('nav.manage_pengguna'))
        ->assertDontSee(__('nav.manage_depot'));
});

it('rute pengguna.daftar dan depot.daftar tidak berubah nama', function () {
    expect(route('pengguna.daftar'))->toContain('/pengguna')
        ->and(route('depot.daftar'))->toContain('/depot');
});
