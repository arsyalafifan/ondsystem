<?php

use App\Enums\JenisAbsensi;
use App\Enums\JenisIzin;
use App\Enums\LokasiAbsensi;
use App\Enums\ModePersetujuanIzin;
use App\Enums\PeranPengguna;
use App\Enums\PorsiIzin;
use App\Enums\StatusPengajuan;
use App\Livewire\Hr\Absensi as LayarAbsensi;
use App\Livewire\Hr\AjukanLembur;
use App\Livewire\Hr\MonitoringAbsensi;
use App\Livewire\Hr\PersetujuanLembur;
use App\Livewire\Hr\SettingApprovalIzin;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\PengajuanLembur;
use App\Models\PengaturanIzin;
use App\Models\Posisi;
use App\Models\User;
use App\Services\Absensi\AturanAbsensi;
use App\Services\Izin\ApproverIzin;
use App\Services\Izin\PengajuanIzinService;
use App\Services\Lembur\PengajuanLemburService;
use App\Support\DepotContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Lembur: diajukan sebelum mulai, di luar jam kerja, diputuskan dengan
 * aturan approver izin, dan jam diakui = min(diajukan, batas, dijalani).
 */
beforeEach(function () {
    Storage::fake('public');

    // Kamis, 17 September 2026 pukul 10:00.
    $this->travelTo(CarbonImmutable::parse('2026-09-17 10:00:00'));

    $this->hr = User::factory()->create(['role' => PeranPengguna::Hr, 'name' => 'Hana HR']);
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->akun = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->department = Department::create(['kode' => 'D1', 'nama' => 'Operasional']);
    $this->jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff']);
    $this->posisi = Posisi::create([
        'kode' => 'STF', 'nama' => 'Staff', 'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00',
        'lokasi_jenis' => LokasiAbsensi::Bebas, 'hari_kerja' => [1, 2, 3, 4, 5, 6],
    ]);

    $this->karyawan = karyawanLembur($this->akun);
});

function karyawanLembur(?User $akun = null): Karyawan
{
    static $n = 0;
    $n++;

    return Karyawan::create([
        'kode_karyawan' => sprintf('LBR%03d', $n),
        'nama_lengkap' => 'Karyawan Lembur '.$n,
        'nik' => str_pad((string) (5000 + $n), 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '1990-01-01',
        'no_hp' => '0815000'.$n,
        'alamat_domisili' => 'Jl. Uji',
        'department_id' => test()->department->id,
        'jabatan_id' => test()->jabatan->id,
        'posisi_id' => test()->posisi->id,
        'tanggal_masuk' => '2024-01-01',
        'status_karyawan' => 'tetap',
        'gaji_pokok' => 1_000_000,
        'depot_id' => DepotContext::currentOrFail()->id,
        'user_id' => $akun?->id,
    ]);
}

function ajukanLembur(string $tanggal = '2026-09-17', string $mulai = '17:00', string $selesai = '20:00', ?Karyawan $karyawan = null): PengajuanLembur
{
    $karyawan ??= test()->karyawan;

    return app(PengajuanLemburService::class)->ajukan(
        $karyawan, $karyawan->user ?? test()->akun, CarbonImmutable::parse($tanggal), $mulai, $selesai, 'Stock opname gudang',
    );
}

function absenLembur(JenisAbsensi $jenis, string $waktu, ?Karyawan $karyawan = null): void
{
    test()->travelTo(CarbonImmutable::parse($waktu));

    $gambar = imagecreatetruecolor(60, 80);
    ob_start();
    imagejpeg($gambar);
    $isi = (string) ob_get_clean();

    app(AturanAbsensi::class)->catat(($karyawan ?? test()->karyawan)->fresh(), $jenis, 'data:image/jpeg;base64,'.base64_encode($isi));
}

// =====================================================================
// Mengajukan
// =====================================================================

it('mengajukan lembur: menunggu persetujuan, durasi dan batas saat itu tersimpan', function () {
    $lembur = ajukanLembur();

    expect($lembur->status)->toBe(StatusPengajuan::Menunggu)
        ->and($lembur->menit_diajukan)->toBe(180)
        ->and($lembur->menit_maks)->toBe(60)
        ->and($lembur->lintas_hari)->toBeFalse();
});

it('lembur melewati tengah malam dihitung sampai hari berikutnya', function () {
    $lembur = ajukanLembur(mulai: '22:00', selesai: '01:00');

    expect($lembur->lintas_hari)->toBeTrue()
        ->and($lembur->menit_diajukan)->toBe(180)
        ->and($lembur->selesaiAt()->toDateTimeString())->toBe('2026-09-18 01:00:00');
});

it('harus diajukan sebelum jam mulai', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 17:30:00'));

    expect(fn () => ajukanLembur())->toThrow(RuntimeException::class)
        ->and(fn () => ajukanLembur('2026-09-16'))->toThrow(RuntimeException::class);
});

it('di hari kerja harus di luar jam kerja, di hari libur bebas', function () {
    expect(fn () => ajukanLembur(mulai: '16:00', selesai: '18:00'))->toThrow(RuntimeException::class)
        ->and(fn () => ajukanLembur('2026-09-18', '06:00', '08:30'))->toThrow(RuntimeException::class);

    expect(ajukanLembur('2026-09-18', '06:00', '08:00')->exists)->toBeTrue()   // sebelum masuk
        ->and(ajukanLembur('2026-09-20', '09:00', '12:00')->exists)->toBeTrue(); // Minggu, libur
});

it('menolak lembur yang bertumpuk dengan pengajuan lain yang masih aktif', function () {
    $pertama = ajukanLembur(mulai: '17:00', selesai: '19:00');

    expect(fn () => ajukanLembur(mulai: '18:30', selesai: '20:00'))->toThrow(RuntimeException::class);

    // Tidak bertumpuk (langsung menyambung) tetap boleh.
    expect(ajukanLembur(mulai: '19:00', selesai: '20:00')->exists)->toBeTrue();

    app(PengajuanLemburService::class)->tolak($pertama, $this->hr, 'Tidak perlu');
    expect(ajukanLembur(mulai: '17:30', selesai: '18:30')->exists)->toBeTrue();
});

it('menolak durasi tidak masuk akal dan lembur di hari izin sehari penuh', function () {
    expect(fn () => ajukanLembur('2026-09-18', '17:00', '06:00'))->toThrow(RuntimeException::class); // 13 jam

    $izin = app(PengajuanIzinService::class)->ajukan(
        $this->karyawan, $this->akun, JenisIzin::Izin, PorsiIzin::Penuh,
        CarbonImmutable::parse('2026-09-19'), CarbonImmutable::parse('2026-09-19'), 'Acara keluarga',
    );

    expect(fn () => ajukanLembur('2026-09-19'))->toThrow(RuntimeException::class);
});

// =====================================================================
// Jam yang diakui
// =====================================================================

it('jam diakui = min(diajukan, batas, dijalani menurut absen pulang)', function () {
    $lembur = ajukanLembur(mulai: '17:00', selesai: '20:00'); // 3 jam, batas 1 jam
    app(PengajuanLemburService::class)->setujui($lembur, $this->hr);
    $layanan = app(PengajuanLemburService::class);

    absenLembur(JenisAbsensi::Masuk, '2026-09-17 07:55:00');

    // Belum absen pulang di hari yang sama: belum bisa dinilai.
    $h = $layanan->hitung($lembur->fresh());
    expect($h['rencana'])->toBe(60)->and($h['realisasi'])->toBeNull()->and($h['diakui'])->toBeNull();

    // Pulang 17:40 → baru 40 menit dijalani.
    absenLembur(JenisAbsensi::Pulang, '2026-09-17 17:40:00');
    $h = $layanan->hitung($lembur->fresh());
    expect($h['realisasi'])->toBe(40)->and($h['diakui'])->toBe(40);
});

it('lembur 3 jam yang benar-benar dijalani tetap diakui sebatas maksimal 1 jam', function () {
    $lembur = ajukanLembur();
    app(PengajuanLemburService::class)->setujui($lembur, $this->hr);

    absenLembur(JenisAbsensi::Masuk, '2026-09-17 07:55:00');
    absenLembur(JenisAbsensi::Pulang, '2026-09-17 20:05:00');

    $h = app(PengajuanLemburService::class)->hitung($lembur->fresh());

    expect($h['diajukan'])->toBe(180)->and($h['realisasi'])->toBe(180)->and($h['diakui'])->toBe(60);
});

it('tanpa absen sama sekali, lembur dianggap tidak dijalani setelah harinya lewat', function () {
    $lembur = ajukanLembur();
    app(PengajuanLemburService::class)->setujui($lembur, $this->hr);

    $this->travelTo(CarbonImmutable::parse('2026-09-19 08:00:00'));

    expect(app(PengajuanLemburService::class)->hitung($lembur->fresh())['diakui'])->toBe(0);
});

it('batas lembur diatur di setting; pengajuan lama tetap memakai batas saat diajukan', function () {
    $lama = ajukanLembur(mulai: '17:00', selesai: '18:00');

    Livewire::actingAs($this->hr)->test(SettingApprovalIzin::class)
        ->assertSet('maksLemburJam', '1')
        ->set('maksLemburJam', '1.5')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(PengaturanIzin::ambil()->maks_lembur_menit)->toBe(90);

    app(ApproverIzin::class)->lupakan();
    $baru = ajukanLembur(mulai: '19:00', selesai: '21:00');

    expect($lama->fresh()->menit_maks)->toBe(60)->and($baru->menit_maks)->toBe(90);
});

// =====================================================================
// Persetujuan — aturan yang sama dengan izin
// =====================================================================

it('HR tidak bisa menyetujui lemburnya sendiri; atasan langsung bisa di mode atasan', function () {
    $karyawanHr = karyawanLembur($this->hr);
    $lemburHr = ajukanLembur(karyawan: $karyawanHr);

    expect(fn () => app(PengajuanLemburService::class)->setujui($lemburHr, $this->hr))->toThrow(RuntimeException::class);

    $spv = User::factory()->create(['role' => PeranPengguna::Supervisor]);
    $this->karyawan->update(['atasan_id' => karyawanLembur($spv)->id]);
    PengaturanIzin::ambil()->update(['mode' => ModePersetujuanIzin::Atasan]);
    app(ApproverIzin::class)->lupakan();

    $lembur = ajukanLembur('2026-09-18');

    expect(fn () => app(PengajuanLemburService::class)->setujui($lembur, $this->hr))->toThrow(RuntimeException::class);

    app(PengajuanLemburService::class)->setujui($lembur, $spv);
    expect($lembur->fresh()->status)->toBe(StatusPengajuan::Disetujui);

    // Atasan non-HR mendapat menu Persetujuan Lembur.
    $this->actingAs($spv)->get(route('hr.persetujuan-lembur'))->assertOk();
});

it('layar Persetujuan Lembur: bawaan hari ini, pemberitahuan tanggal lain, setujui dan tolak', function () {
    $hariIni = ajukanLembur();
    $besok = ajukanLembur('2026-09-18', '17:00', '18:00', karyawanLembur(User::factory()->create(['role' => PeranPengguna::Driver])));

    Livewire::actingAs($this->hr)->test(PersetujuanLembur::class)
        ->assertSee($this->karyawan->nama_lengkap)
        ->assertDontSee($besok->karyawan->nama_lengkap)
        ->assertSet('menungguDiLuarTanggal', 1)
        ->assertSee(__('lembur.dibatasi_singkat', ['durasi' => PengajuanLembur::formatMenit(60)]))
        ->call('buka', $hariIni->id, 'tolak')
        ->call('putuskan')
        ->assertHasErrors('catatan')
        ->call('buka', $hariIni->id, 'setujui')
        ->call('putuskan')
        ->assertHasNoErrors();

    expect($hariIni->fresh()->status)->toBe(StatusPengajuan::Disetujui)
        ->and($hariIni->fresh()->diputuskan_oleh)->toBe($this->hr->id);
});

it('akses menu: semua peran bisa ajukan, persetujuan hanya HR', function () {
    $this->actingAs($this->akun)->get(route('hr.lembur'))->assertOk();
    $this->actingAs($this->admin)->get(route('hr.lembur'))->assertOk();
    $this->actingAs($this->hr)->get(route('hr.persetujuan-lembur'))->assertOk();
    $this->actingAs($this->admin)->get(route('hr.persetujuan-lembur'))->assertForbidden();
});

// =====================================================================
// Layar karyawan, absensi & monitoring
// =====================================================================

it('karyawan mengajukan lewat layar dengan pratinjau batas, lalu bisa menarik pengajuan', function () {
    $komponen = Livewire::actingAs($this->akun)->test(AjukanLembur::class)
        ->assertSet('jamMulai', '17:00')   // usulan: mulai di jam pulang
        ->assertSet('jamSelesai', '18:00') // selama satu batas lembur
        ->set('jamSelesai', '20:00')
        ->assertSee(__('lembur.pratinjau_dibatasi', ['batas' => PengajuanLembur::formatMenit(60)]))
        ->set('tugas', 'Stock opname gudang')
        ->call('ajukan')
        ->assertHasNoErrors();

    $lembur = PengajuanLembur::sole();
    expect($lembur->menit_diajukan)->toBe(180);

    $komponen->assertSee(__('izin.menunggu_persetujuan', ['nama' => 'Hana HR']))
        ->call('batalkan', $lembur->id);

    expect($lembur->fresh()->status)->toBe(StatusPengajuan::Dibatalkan);
});

it('lembur yang disetujui muncul di Attendance Monitoring dan mengingatkan di layar Absensi', function () {
    $lembur = ajukanLembur();
    app(PengajuanLemburService::class)->setujui($lembur, $this->hr);

    Livewire::actingAs($this->akun)->test(LayarAbsensi::class)
        ->assertSee(__('lembur.info_absen', ['jam' => $lembur->jamTeks()]));

    absenLembur(JenisAbsensi::Masuk, '2026-09-17 07:55:00');
    absenLembur(JenisAbsensi::Pulang, '2026-09-17 18:30:00');

    Livewire::actingAs($this->hr)->test(MonitoringAbsensi::class, ['tanggal' => '2026-09-17'])
        ->assertSee($lembur->jamTeks())
        ->assertSee(__('lembur.diakui', ['durasi' => PengajuanLembur::formatMenit(60)]));
});
