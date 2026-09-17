<?php

use App\Enums\JenisAbsensi;
use App\Enums\LokasiAbsensi;
use App\Enums\PeranPengguna;
use App\Enums\StatusAbsensi;
use App\Livewire\Hr\MonitoringAbsensi;
use App\Models\Absensi;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Posisi;
use App\Models\User;
use App\Support\DepotContext;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Attendance Monitoring: seluruh karyawan aktif muncul setiap hari, dan
 * statusnya berubah begitu yang bersangkutan absen.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->departmentMon = Department::create(['kode' => 'D1', 'nama' => 'Operasional']);
    $this->jabatanMon = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff']);
    $this->posisiMon = Posisi::create([
        'kode' => 'STF', 'nama' => 'Staff Kantor',
        'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00',
        'hari_kerja' => [1, 2, 3, 4, 5, 6],
        'lokasi_jenis' => LokasiAbsensi::Depot,
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function karyawanMon(string $nama, ?Posisi $posisi = null): Karyawan
{
    static $n = 0;
    $n++;

    return Karyawan::create([
        'kode_karyawan' => sprintf('MON%03d', $n),
        'nama_lengkap' => $nama,
        'nik' => str_pad((string) (900000 + $n), 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '1990-01-01',
        'no_hp' => '0813000'.$n,
        'alamat_domisili' => 'Jl. Monitoring',
        'department_id' => test()->departmentMon->id,
        'jabatan_id' => test()->jabatanMon->id,
        'posisi_id' => ($posisi ?? test()->posisiMon)->id,
        'tanggal_masuk' => '2024-01-01',
        'status_karyawan' => 'tetap',
        'gaji_pokok' => 1_000_000,
        'depot_id' => DepotContext::currentOrFail()->id,
    ]);
}

function absensiMon(Karyawan $karyawan, JenisAbsensi $jenis, string $waktu, StatusAbsensi $status = StatusAbsensi::TepatWaktu, int $telat = 0): Absensi
{
    return Absensi::create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => substr($waktu, 0, 10),
        'jenis' => $jenis,
        'waktu' => $waktu,
        'status' => $status,
        'telat_menit' => $telat,
        'jam_acuan' => '08:00:00',
        'posisi_id' => $karyawan->posisi_id,
        'foto' => 'absensi/uji.jpg',
        'lokasi_jenis' => LokasiAbsensi::Depot,
        'depot_id' => $karyawan->depot_id,
        'jarak_m' => 12,
    ]);
}

it('hanya admin/HR yang bisa membuka monitoring', function () {
    $this->actingAs($this->sales)->get(route('hr.monitoring-absensi'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('hr.monitoring-absensi'))->assertOk();
});

it('menampilkan semua karyawan aktif meski belum absen', function () {
    CarbonImmutable::setTestNow('2026-09-17 09:00:00');

    karyawanMon('Andi');
    karyawanMon('Budi');

    $baris = Livewire::actingAs($this->admin)
        ->test(MonitoringAbsensi::class)
        ->instance()->baris;

    expect($baris)->toHaveCount(2)
        ->and($baris->pluck('status')->all())->toBe(['belum_absen', 'belum_absen']);
});

it('memperbarui status begitu karyawan absen, termasuk durasi kerjanya', function () {
    CarbonImmutable::setTestNow('2026-09-17 18:00:00');

    $andi = karyawanMon('Andi');
    $budi = karyawanMon('Budi');

    absensiMon($andi, JenisAbsensi::Masuk, '2026-09-17 07:55:00');
    absensiMon($andi, JenisAbsensi::Pulang, '2026-09-17 17:10:00');
    absensiMon($budi, JenisAbsensi::Masuk, '2026-09-17 08:20:00', StatusAbsensi::Terlambat, 20);

    $baris = Livewire::actingAs($this->admin)
        ->test(MonitoringAbsensi::class)
        ->instance()->baris
        ->keyBy(fn (array $b) => $b['karyawan']->nama_lengkap);

    expect($baris['Andi']['status'])->toBe('selesai')
        ->and($baris['Andi']['durasi_menit'])->toBe(555) // 07:55 → 17:10
        ->and($baris['Budi']['status'])->toBe('terlambat')
        ->and($baris['Budi']['masuk']->telat_menit)->toBe(20)
        ->and($baris['Budi']['durasi_menit'])->toBeNull();
});

it('menandai alfa hanya setelah jam pulang lewat pada hari kerja', function () {
    karyawanMon('Andi');

    CarbonImmutable::setTestNow('2026-09-17 12:00:00'); // Kamis, belum lewat jam pulang
    expect(Livewire::actingAs($this->admin)->test(MonitoringAbsensi::class)->instance()->baris->first()['status'])
        ->toBe('belum_absen');

    CarbonImmutable::setTestNow('2026-09-17 20:00:00'); // Kamis, sudah lewat jam pulang
    expect(Livewire::actingAs($this->admin)->test(MonitoringAbsensi::class)->instance()->baris->first()['status'])
        ->toBe('alfa');
});

it('hari di luar hari kerja posisinya berstatus libur, bukan alfa', function () {
    // 2026-09-20 jatuh pada hari Minggu, di luar hari kerja posisi ini.
    CarbonImmutable::setTestNow('2026-09-20 20:00:00');

    karyawanMon('Andi');

    expect(Livewire::actingAs($this->admin)->test(MonitoringAbsensi::class)->instance()->baris->first()['status'])
        ->toBe('libur');
});

it('menyaring menurut tanggal, posisi, dan status', function () {
    CarbonImmutable::setTestNow('2026-09-17 18:00:00');

    $andi = karyawanMon('Andi');
    $posisiLain = Posisi::create(['kode' => 'SLS', 'nama' => 'Sales', 'hari_kerja' => [1, 2, 3, 4, 5, 6]]);
    karyawanMon('Budi', $posisiLain);

    absensiMon($andi, JenisAbsensi::Masuk, '2026-09-17 07:55:00');

    $uji = Livewire::actingAs($this->admin)->test(MonitoringAbsensi::class);

    expect($uji->instance()->baris)->toHaveCount(2);

    // Tanggal lain: belum ada absen sama sekali.
    expect($uji->set('tanggal', '2026-09-16')->instance()->baris->pluck('status')->unique()->all())
        ->toBe(['alfa']);

    expect($uji->set('tanggal', '2026-09-17')->set('filterPosisi', (string) $posisiLain->id)->instance()->baris)
        ->toHaveCount(1);

    expect($uji->set('filterPosisi', '')->set('filterStatus', 'hadir')->instance()->baris->first()['karyawan']->nama_lengkap)
        ->toBe('Andi');
});

it('ringkasan menghitung jumlah karyawan per status', function () {
    CarbonImmutable::setTestNow('2026-09-17 18:00:00');

    $andi = karyawanMon('Andi');
    karyawanMon('Budi');

    absensiMon($andi, JenisAbsensi::Masuk, '2026-09-17 08:30:00', StatusAbsensi::Terlambat, 30);

    $ringkasan = Livewire::actingAs($this->admin)->test(MonitoringAbsensi::class)->instance()->ringkasan;

    expect($ringkasan['terlambat'])->toBe(1)
        ->and($ringkasan['alfa'])->toBe(1)
        ->and($ringkasan['hadir'])->toBe(0);
});
