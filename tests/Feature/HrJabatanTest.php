<?php

use App\Enums\PeranPengguna;
use App\Livewire\Hr\DaftarJabatan;
use App\Models\Department;
use App\Models\Depot;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

function buatKaryawanUjiJabatan(Department $department, Jabatan $jabatan, Depot $depot): Karyawan
{
    static $n = 0;
    $n++;

    return Karyawan::create([
        'kode_karyawan' => "EMP-JBT-{$n}",
        'nama_lengkap' => "Karyawan Uji {$n}",
        'nik' => str_pad((string) (2000000000000000 + $n), 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'P',
        'tanggal_lahir' => '1995-01-01',
        'no_hp' => '081234567890',
        'alamat_domisili' => 'Jl. Uji',
        'department_id' => $department->id,
        'jabatan_id' => $jabatan->id,
        'tanggal_masuk' => today(),
        'status_karyawan' => 'tetap',
        'gaji_pokok' => 5_000_000,
        'depot_id' => $depot->id,
    ]);
}

it('menampilkan daftar jabatan', function () {
    Jabatan::create(['kode' => 'J1', 'nama' => 'Staff Gudang']);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->assertSee('Staff Gudang');
});

it('membuat jabatan baru', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->call('buatBaru')
        ->set('kode', 'J1')
        ->set('nama', 'Staff Gudang')
        ->call('simpan')
        ->assertDispatched('notifikasi');

    expect(Jabatan::where('kode', 'J1')->exists())->toBeTrue();
});

it('mengubah jabatan', function () {
    $jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff Gudang']);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->call('sunting', $jabatan->id)
        ->set('nama', 'Kepala Gudang')
        ->call('simpan');

    expect($jabatan->fresh()->nama)->toBe('Kepala Gudang');
});

it('menolak kode jabatan yang sudah dipakai', function () {
    Jabatan::create(['kode' => 'J1', 'nama' => 'Staff Gudang']);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->call('buatBaru')
        ->set('kode', 'J1')
        ->set('nama', 'Lain')
        ->call('simpan')
        ->assertHasErrors('kode');
});

it('menghapus jabatan yang tidak dipakai karyawan', function () {
    $jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff Gudang']);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->call('hapus', $jabatan->id);

    expect(Jabatan::withTrashed()->find($jabatan->id)->trashed())->toBeTrue();
});

it('menolak menghapus jabatan yang masih dipakai karyawan', function () {
    $department = Department::create(['kode' => 'D1', 'nama' => 'Finance']);
    $jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff Gudang']);
    buatKaryawanUjiJabatan($department, $jabatan, $this->depot);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->call('hapus', $jabatan->id)
        ->assertDispatched('notifikasi');

    expect(Jabatan::find($jabatan->id))->not->toBeNull();
});

it('menolak akses selain Admin, Hr, dan Superadmin', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->actingAs($driver)->get(route('hr.jabatan'))->assertForbidden();
});
