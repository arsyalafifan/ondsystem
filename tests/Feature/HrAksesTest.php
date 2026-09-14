<?php

use App\Enums\PeranPengguna;
use App\Models\User;

/**
 * Modul HR System: matriks akses. Peran `Hr` baru harus benar-benar
 * terisolasi ke HR System — bukan cuma "Admin dengan nama lain" (tidak
 * boleh menyentuh rute O&D sama sekali), dan sebaliknya rute HR harus
 * tertutup buat Sales/Driver.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);
    $this->hr = User::factory()->create(['role' => PeranPengguna::Hr]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);
});

$ruteHr = ['hr.dashboard', 'hr.karyawan', 'hr.department', 'hr.jabatan'];

describe('rute HR', function () use ($ruteHr) {
    it('bisa dibuka Admin, Hr, dan Superadmin', function (string $rute) {
        foreach (['admin', 'hr', 'superadmin'] as $peran) {
            $this->actingAs($this->{$peran})->get(route($rute))->assertOk();
        }
    })->with($ruteHr);

    it('ditolak untuk Sales dan Driver', function (string $rute) {
        $this->actingAs($this->sales)->get(route($rute))->assertForbidden();
        $this->actingAs($this->driver)->get(route($rute))->assertForbidden();
    })->with($ruteHr);
});

describe('peran Hr tidak boleh menyentuh O&D', function () {
    it('mendapat 403 di rute murni O&D', function (string $rute) {
        $this->actingAs($this->hr)->get(route($rute))->assertForbidden();
    })->with(['dashboard', 'master.toko', 'master.produk', 'pos.kasir', 'routing.generate']);

    it('mendapat 403 di rute superadmin', function () {
        $this->actingAs($this->hr)->get(route('pengguna.daftar'))->assertForbidden();
        $this->actingAs($this->hr)->get(route('depot.daftar'))->assertForbidden();
    });
});

it('peran Hr mendarat di dasbor HR setelah login, bukan dasbor O&D', function () {
    expect($this->hr->role->beranda())->toBe('hr.dashboard');
});
