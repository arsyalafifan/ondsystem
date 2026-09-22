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

it('bisa mengunduh berkas contoh dan ekspor excel jabatan', function () {
    Jabatan::create(['kode' => 'J-EXP', 'nama' => 'Export Jabatan']);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->call('unduhContohExcel')
        ->assertFileDownloaded('contoh-import-jabatan.xlsx');

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->call('unduhExcel')
        ->assertFileDownloaded('jabatan-'.now()->format('Y-m-d').'.xlsx');
});

it('bisa mengimpor jabatan dari csv dan memperbarui yang sudah ada', function () {
    $kontenCsv = "kode,nama\n".
        "MGR,Manager\n".
        "SPV,Supervisor\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('jabatan.csv', $kontenCsv);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->assertHasNoErrors()
        ->assertSet('imporBerjalan', true)
        ->call('lanjutkanImporCsv')
        ->assertSet('imporBerjalan', false);

    expect(Jabatan::where('kode', 'MGR')->first()?->nama)->toBe('Manager')
        ->and(Jabatan::where('kode', 'SPV')->first()?->nama)->toBe('Supervisor');

    // Update lewat import
    $kontenUpdate = "kode,nama\n".
        "MGR,General Manager\n";

    $fileUpdate = \Illuminate\Http\UploadedFile::fake()->createWithContent('jabatan_update.csv', $kontenUpdate);

    Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->set('berkasCsv', $fileUpdate)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    expect(Jabatan::where('kode', 'MGR')->first()?->nama)->toBe('General Manager');
});

it('melewati baris jabatan yang kosong atau duplikat dalam berkas impor', function () {
    $kontenCsv = "kode,nama\n".
        ",Tanpa Kode\n".
        "DIR,\n".
        "STF,Staff 1\n".
        "STF,Staff 2 (Duplikat)\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('jabatan_invalid.csv', $kontenCsv);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarJabatan::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $hasil = $komponen->get('hasilImpor');
    expect($hasil['baru'])->toBe(1)
        ->and(count($hasil['dilewati']))->toBe(3);

    expect(Jabatan::where('kode', 'STF')->first()?->nama)->toBe('Staff 1');
});

