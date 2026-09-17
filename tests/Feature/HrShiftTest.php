<?php

use App\Enums\PeranPengguna;
use App\Livewire\Hr\DaftarShift;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Posisi;
use App\Models\Shift;
use App\Models\User;
use App\Support\DepotContext;
use Livewire\Livewire;

/**
 * Master Shift Kerja. Shift menempel ke karyawan, bukan ke posisi — lihat
 * App\Models\Shift.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
});

it('hanya admin/HR yang bisa membuka master shift', function () {
    $this->actingAs($this->sales)->get(route('hr.shift'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('hr.shift'))->assertOk();
});

it('membuat shift malam yang pulangnya di hari berikutnya', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarShift::class)
        ->call('buatBaru')
        ->set('kode', 'MLM')
        ->set('nama', 'Malam')
        ->set('jamMasuk', '22:00')
        ->set('jamPulang', '06:00')
        ->set('lintasHari', true)
        ->call('simpan')
        ->assertHasNoErrors();

    $shift = Shift::firstOrFail();

    expect($shift->jam_masuk)->toBe('22:00:00')
        ->and($shift->jam_pulang)->toBe('06:00:00')
        ->and($shift->lintas_hari)->toBeTrue()
        ->and($shift->aktif)->toBeTrue();
});

it('menolak jam yang bukan format waktu', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarShift::class)
        ->call('buatBaru')
        ->set('kode', 'PGI')
        ->set('nama', 'Pagi')
        ->set('jamMasuk', 'pagi sekali')
        ->call('simpan')
        ->assertHasErrors('jamMasuk');
});

it('menolak menghapus shift yang masih dipakai karyawan', function () {
    $shift = Shift::create(['kode' => 'MLM', 'nama' => 'Malam', 'jam_masuk' => '22:00:00', 'jam_pulang' => '06:00:00']);

    Karyawan::create([
        'kode_karyawan' => 'KRY001', 'nama_lengkap' => 'Karyawan Satu', 'nik' => '1234567890123456',
        'jenis_kelamin' => 'L', 'tanggal_lahir' => '1990-01-01', 'no_hp' => '0812', 'alamat_domisili' => 'Jl.',
        'department_id' => Department::create(['kode' => 'D1', 'nama' => 'Dept'])->id,
        'jabatan_id' => Jabatan::create(['kode' => 'J1', 'nama' => 'Jabatan'])->id,
        'posisi_id' => Posisi::create(['kode' => 'P1', 'nama' => 'Posisi'])->id,
        'shift_id' => $shift->id,
        'tanggal_masuk' => '2024-01-01', 'status_karyawan' => 'tetap',
        'gaji_pokok' => 1, 'depot_id' => DepotContext::currentOrFail()->id,
    ]);

    Livewire::actingAs($this->admin)->test(DaftarShift::class)->call('hapus', $shift->id);

    expect(Shift::count())->toBe(1);
});

it('menghapus shift yang belum dipakai', function () {
    $shift = Shift::create(['kode' => 'MLM', 'nama' => 'Malam', 'jam_masuk' => '22:00:00', 'jam_pulang' => '06:00:00']);

    Livewire::actingAs($this->admin)->test(DaftarShift::class)->call('hapus', $shift->id);

    expect(Shift::count())->toBe(0);
});
