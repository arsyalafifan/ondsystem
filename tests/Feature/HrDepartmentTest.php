<?php

use App\Enums\PeranPengguna;
use App\Livewire\Hr\DaftarDepartment;
use App\Models\Department;
use App\Models\Depot;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

/** Karyawan minimal langsung lewat Eloquent, dipakai untuk menguji blokir hapus. */
function buatKaryawanUjiDept(Department $department, Jabatan $jabatan, Depot $depot): Karyawan
{
    static $n = 0;
    $n++;

    return Karyawan::create([
        'kode_karyawan' => "EMP-DEPT-{$n}",
        'nama_lengkap' => "Karyawan Uji {$n}",
        'nik' => str_pad((string) (1000000000000000 + $n), 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'L',
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

it('menampilkan daftar department', function () {
    Department::create(['kode' => 'D1', 'nama' => 'Finance']);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->assertSee('Finance');
});

it('membuat department baru', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->call('buatBaru')
        ->set('kode', 'D1')
        ->set('nama', 'Finance')
        ->call('simpan')
        ->assertDispatched('notifikasi');

    expect(Department::where('kode', 'D1')->exists())->toBeTrue();
});

it('mengubah department', function () {
    $department = Department::create(['kode' => 'D1', 'nama' => 'Finance']);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->call('sunting', $department->id)
        ->set('nama', 'Finance & Accounting')
        ->call('simpan');

    expect($department->fresh()->nama)->toBe('Finance & Accounting');
});

it('menolak kode department yang sudah dipakai', function () {
    Department::create(['kode' => 'D1', 'nama' => 'Finance']);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->call('buatBaru')
        ->set('kode', 'D1')
        ->set('nama', 'Lain')
        ->call('simpan')
        ->assertHasErrors('kode');
});

it('menghapus department yang tidak dipakai karyawan', function () {
    $department = Department::create(['kode' => 'D1', 'nama' => 'Finance']);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->call('hapus', $department->id);

    expect(Department::withTrashed()->find($department->id)->trashed())->toBeTrue();
});

it('menolak menghapus department yang masih dipakai karyawan', function () {
    $department = Department::create(['kode' => 'D1', 'nama' => 'Finance']);
    $jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff']);
    buatKaryawanUjiDept($department, $jabatan, $this->depot);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->call('hapus', $department->id)
        ->assertDispatched('notifikasi');

    expect(Department::find($department->id))->not->toBeNull();
});

it('menolak akses selain Admin, Hr, dan Superadmin', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($sales)->get(route('hr.department'))->assertForbidden();
});

it('bisa mengunduh berkas contoh dan ekspor excel department', function () {
    Department::create(['kode' => 'D-EXP', 'nama' => 'Export Dept']);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->call('unduhContohExcel')
        ->assertFileDownloaded('contoh-import-department.xlsx');

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->call('unduhExcel')
        ->assertFileDownloaded('department-'.now()->format('Y-m-d').'.xlsx');
});

it('bisa mengimpor department dari csv dan memperbarui yang sudah ada', function () {
    $kontenCsv = "kode,nama\n".
        "HRD,Human Resources\n".
        "FIN,Finance\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('dept.csv', $kontenCsv);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->assertHasNoErrors()
        ->assertSet('imporBerjalan', true)
        ->call('lanjutkanImporCsv')
        ->assertSet('imporBerjalan', false);

    expect(Department::where('kode', 'HRD')->first()?->nama)->toBe('Human Resources')
        ->and(Department::where('kode', 'FIN')->first()?->nama)->toBe('Finance');

    // Update lewat import
    $kontenUpdate = "kode,nama\n".
        "HRD,Human Resources & GA\n";

    $fileUpdate = \Illuminate\Http\UploadedFile::fake()->createWithContent('dept_update.csv', $kontenUpdate);

    Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->set('berkasCsv', $fileUpdate)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    expect(Department::where('kode', 'HRD')->first()?->nama)->toBe('Human Resources & GA');
});

it('melewati baris department yang kosong atau duplikat dalam berkas impor', function () {
    $kontenCsv = "kode,nama\n".
        ",Tanpa Kode\n".
        "OPS,\n".
        "MKT,Marketing 1\n".
        "MKT,Marketing 2 (Duplikat)\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('dept_invalid.csv', $kontenCsv);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarDepartment::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $hasil = $komponen->get('hasilImpor');
    expect($hasil['baru'])->toBe(1)
        ->and(count($hasil['dilewati']))->toBe(3);

    expect(Department::where('kode', 'MKT')->first()?->nama)->toBe('Marketing 1');
});

