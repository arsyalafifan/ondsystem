<?php

use App\Enums\LokasiAbsensi;
use App\Enums\PeranPengguna;
use App\Livewire\Hr\DaftarPosisi;
use App\Livewire\Hr\SettingJamKerja;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Posisi;
use App\Models\User;
use App\Support\DepotContext;
use Livewire\Livewire;

/**
 * Master Posisi (identitas) dan Setting Jam Kerja (aturan) — dua menu yang
 * menyunting baris `posisis` yang sama.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
});

it('hanya admin/HR yang bisa membuka master posisi dan setting jam kerja', function () {
    foreach (['hr.posisi', 'hr.jam-kerja'] as $rute) {
        $this->actingAs($this->sales)->get(route($rute))->assertForbidden();
        $this->actingAs($this->admin)->get(route($rute))->assertOk();
    }
});

it('membuat, menyunting, dan menghapus posisi', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarPosisi::class)
        ->call('buatBaru')
        ->set('kode', 'STF')
        ->set('nama', 'Staff Kantor')
        ->call('simpan')
        ->assertHasNoErrors();

    $posisi = Posisi::firstOrFail();

    expect($posisi->kode)->toBe('STF')
        ->and($posisi->aktif)->toBeTrue()
        // Bawaan yang masuk akal sebelum Setting Jam Kerja disentuh.
        ->and($posisi->lokasi_jenis)->toBe(LokasiAbsensi::Depot)
        ->and($posisi->radius_meter)->toBe(100);

    Livewire::actingAs($this->admin)
        ->test(DaftarPosisi::class)
        ->call('sunting', $posisi->id)
        ->set('nama', 'Staff Kantor Pusat')
        ->call('simpan');

    expect($posisi->fresh()->nama)->toBe('Staff Kantor Pusat');

    Livewire::actingAs($this->admin)->test(DaftarPosisi::class)->call('hapus', $posisi->id);

    expect(Posisi::count())->toBe(0);
});

it('menolak kode posisi yang sama', function () {
    Posisi::create(['kode' => 'STF', 'nama' => 'Staff']);

    Livewire::actingAs($this->admin)
        ->test(DaftarPosisi::class)
        ->call('buatBaru')
        ->set('kode', 'STF')
        ->set('nama', 'Staff Lain')
        ->call('simpan')
        ->assertHasErrors('kode');
});

it('menolak menghapus posisi yang masih dipakai karyawan', function () {
    $posisi = Posisi::create(['kode' => 'STF', 'nama' => 'Staff']);

    Karyawan::create([
        'kode_karyawan' => 'KRY001', 'nama_lengkap' => 'Karyawan Satu', 'nik' => '1234567890123456',
        'jenis_kelamin' => 'L', 'tanggal_lahir' => '1990-01-01', 'no_hp' => '0812', 'alamat_domisili' => 'Jl.',
        'department_id' => Department::create(['kode' => 'D1', 'nama' => 'Dept'])->id,
        'jabatan_id' => Jabatan::create(['kode' => 'J1', 'nama' => 'Jabatan'])->id,
        'posisi_id' => $posisi->id, 'tanggal_masuk' => '2024-01-01', 'status_karyawan' => 'tetap',
        'gaji_pokok' => 1, 'depot_id' => DepotContext::currentOrFail()->id,
    ]);

    Livewire::actingAs($this->admin)->test(DaftarPosisi::class)->call('hapus', $posisi->id);

    expect(Posisi::count())->toBe(1);
});

it('menyimpan setting jam kerja lengkap dengan kondisi absennya', function () {
    $posisi = Posisi::create(['kode' => 'SLS', 'nama' => 'Sales Lapangan']);

    Livewire::actingAs($this->admin)
        ->test(SettingJamKerja::class)
        ->call('sunting', $posisi->id)
        ->set('jamMasuk', '07:30')
        ->set('jamPulang', '16:30')
        ->set('toleransiTelatMenit', '15')
        ->set('hariKerja', ['1', '2', '3', '4', '5'])
        ->set('lokasiJenis', LokasiAbsensi::TokoTanggungan->value)
        ->set('radiusMeter', '250')
        ->call('simpan')
        ->assertHasNoErrors();

    $posisi->refresh();

    expect($posisi->jam_masuk)->toBe('07:30:00')
        ->and($posisi->jam_pulang)->toBe('16:30:00')
        ->and($posisi->toleransi_telat_menit)->toBe(15)
        ->and($posisi->hariKerja())->toBe([1, 2, 3, 4, 5])
        ->and($posisi->lokasi_jenis)->toBe(LokasiAbsensi::TokoTanggungan)
        ->and($posisi->radius_meter)->toBe(250)
        ->and($posisi->pakai_absen_istirahat)->toBeFalse();
});

it('mewajibkan jam kembali istirahat ketika absen istirahat dinyalakan', function () {
    $posisi = Posisi::create(['kode' => 'STF', 'nama' => 'Staff']);

    Livewire::actingAs($this->admin)
        ->test(SettingJamKerja::class)
        ->call('sunting', $posisi->id)
        ->set('pakaiAbsenIstirahat', true)
        ->set('istirahatPalingLambat', '')
        ->call('simpan')
        ->assertHasErrors('istirahatPalingLambat');
});

it('mengosongkan jam istirahat ketika absen istirahat dimatikan', function () {
    $posisi = Posisi::create([
        'kode' => 'STF', 'nama' => 'Staff',
        'pakai_absen_istirahat' => true, 'istirahat_paling_lambat' => '13:00:00',
    ]);

    Livewire::actingAs($this->admin)
        ->test(SettingJamKerja::class)
        ->call('sunting', $posisi->id)
        ->set('pakaiAbsenIstirahat', false)
        ->call('simpan');

    expect($posisi->fresh()->istirahat_paling_lambat)->toBeNull();
});

it('hari kerja bawaan mengikuti hari kerja perusahaan, bukan tujuh hari penuh', function () {
    expect(Posisi::create(['kode' => 'STF', 'nama' => 'Staff'])->hariKerja())
        ->toBe(range((int) config('visit.hari_mulai'), (int) config('visit.hari_selesai')));
});
