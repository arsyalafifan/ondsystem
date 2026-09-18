<?php

use App\Enums\JenisIzin;
use App\Enums\LokasiAbsensi;
use App\Enums\ModePersetujuanIzin;
use App\Enums\PeranPengguna;
use App\Enums\PorsiIzin;
use App\Enums\StatusPengajuan;
use App\Livewire\Hr\AjukanIzin;
use App\Livewire\Hr\DaftarKaryawan;
use App\Livewire\Hr\PersetujuanIzin;
use App\Livewire\Hr\SettingApprovalIzin;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\PengaturanIzin;
use App\Models\Posisi;
use App\Models\User;
use App\Services\Izin\ApproverIzin;
use App\Services\Izin\PengajuanIzinService;
use App\Support\DepotContext;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Siapa yang boleh memutuskan pengajuan izin — Setting Approval Izin dan
 * aturan tetapnya (tidak memutuskan milik sendiri, cadangan superadmin).
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 07:00:00'));

    $this->department = Department::create(['kode' => 'D1', 'nama' => 'Operasional']);
    $this->jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff']);
    $this->posisi = Posisi::create([
        'kode' => 'STF', 'nama' => 'Staff', 'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00',
        'lokasi_jenis' => LokasiAbsensi::Bebas,
    ]);

    $this->superadmin = User::factory()->superadmin()->create();
    $this->hr = User::factory()->create(['role' => PeranPengguna::Hr, 'name' => 'Hana HR']);
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin, 'name' => 'Adi Admin']);
    $this->spv = User::factory()->create(['role' => PeranPengguna::Supervisor, 'name' => 'Sinta Supervisor']);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->karyawanHr = karyawanApproval($this->hr);
    $this->karyawanSpv = karyawanApproval($this->spv);
    $this->karyawanSales = karyawanApproval($this->sales);
});

function karyawanApproval(?User $akun = null, ?Karyawan $atasan = null): Karyawan
{
    static $n = 0;
    $n++;

    return Karyawan::create([
        'kode_karyawan' => sprintf('APR%03d', $n),
        'nama_lengkap' => 'Karyawan Approval '.$n,
        'nik' => str_pad((string) (7000 + $n), 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '1990-01-01',
        'no_hp' => '0814000'.$n,
        'alamat_domisili' => 'Jl. Uji',
        'department_id' => test()->department->id,
        'jabatan_id' => test()->jabatan->id,
        'posisi_id' => test()->posisi->id,
        'atasan_id' => $atasan?->id,
        'tanggal_masuk' => '2024-01-01',
        'status_karyawan' => 'tetap',
        'gaji_pokok' => 1_000_000,
        'depot_id' => DepotContext::currentOrFail()->id,
        'user_id' => $akun?->id,
    ]);
}

function ajukanApproval(Karyawan $karyawan, string $tanggal = '2026-09-17'): PengajuanIzin
{
    return app(PengajuanIzinService::class)->ajukan(
        $karyawan, $karyawan->user, JenisIzin::Izin, PorsiIzin::Penuh,
        CarbonImmutable::parse($tanggal), CarbonImmutable::parse($tanggal), 'Keperluan keluarga',
    );
}

function aturApproval(ModePersetujuanIzin $mode, array $peran = ['hr'], array $pengguna = []): void
{
    PengaturanIzin::ambil()->update(['mode' => $mode, 'approver_peran' => $peran, 'approver_pengguna' => $pengguna]);
    app(ApproverIzin::class)->lupakan();
}

function bolehPutuskan(User $pengguna, PengajuanIzin $izin): bool
{
    return app(ApproverIzin::class)->bolehMemutuskan($pengguna, $izin->fresh());
}

// =====================================================================
// Aturan tetap
// =====================================================================

it('bawaannya approver umum = semua HR, persis perilaku sebelumnya', function () {
    $izin = ajukanApproval($this->karyawanSales);

    expect(PengaturanIzin::ambil()->mode)->toBe(ModePersetujuanIzin::ApproverUmum)
        ->and(bolehPutuskan($this->hr, $izin))->toBeTrue()
        ->and(bolehPutuskan($this->admin, $izin))->toBeFalse()
        ->and(bolehPutuskan($this->spv, $izin))->toBeFalse();
});

it('HR tidak bisa menyetujui pengajuannya sendiri, HR lain bisa', function () {
    $hrLain = User::factory()->create(['role' => PeranPengguna::Hr]);
    $izin = ajukanApproval($this->karyawanHr);

    expect(fn () => app(PengajuanIzinService::class)->setujui($izin, $this->hr))->toThrow(RuntimeException::class);

    app(PengajuanIzinService::class)->setujui($izin, $hrLain);

    expect($izin->fresh()->status)->toBe(StatusPengajuan::Disetujui);
});

it('tanpa approver lain yang sah, pengajuan jatuh ke superadmin supaya tidak macet', function () {
    $izin = ajukanApproval($this->karyawanHr);
    $penyetuju = app(ApproverIzin::class)->untuk($izin);

    expect($penyetuju['jalur'])->toBe(ApproverIzin::JALUR_CADANGAN)
        ->and($penyetuju['pengguna']->pluck('id')->all())->toBe([$this->superadmin->id])
        ->and(bolehPutuskan($this->superadmin, $izin))->toBeTrue();
});

it('superadmin pun tidak bisa memutuskan pengajuannya sendiri', function () {
    $karyawanSuper = karyawanApproval($this->superadmin);
    $izin = ajukanApproval($karyawanSuper);

    expect(bolehPutuskan($this->superadmin, $izin))->toBeFalse()
        ->and(bolehPutuskan($this->hr, $izin))->toBeTrue();
});

// =====================================================================
// Approver umum: peran & orang tertentu
// =====================================================================

it('orang tertentu di luar HR bisa ditunjuk sebagai approver dan mendapat menunya', function () {
    $izin = ajukanApproval($this->karyawanSales);

    $this->actingAs($this->admin)->get(route('hr.persetujuan-izin'))->assertForbidden();

    aturApproval(ModePersetujuanIzin::ApproverUmum, ['hr'], [$this->admin->id]);

    expect(bolehPutuskan($this->admin, $izin))->toBeTrue();

    $this->actingAs($this->admin)->get(route('hr.persetujuan-izin'))->assertOk()->assertSee($this->karyawanSales->nama_lengkap);
});

it('peran approver bisa diganti — HR tidak lagi menyetujui, supervisor yang menyetujui', function () {
    aturApproval(ModePersetujuanIzin::ApproverUmum, ['supervisor']);
    $izin = ajukanApproval($this->karyawanSales);

    expect(bolehPutuskan($this->hr, $izin))->toBeFalse()
        ->and(bolehPutuskan($this->spv, $izin))->toBeTrue();

    expect(fn () => app(PengajuanIzinService::class)->setujui($izin, $this->hr))->toThrow(RuntimeException::class);
});

// =====================================================================
// Mode atasan langsung
// =====================================================================

it('mode atasan: atasan langsung yang memutuskan, bukan approver umum', function () {
    aturApproval(ModePersetujuanIzin::Atasan);
    $bawahan = karyawanApproval(User::factory()->create(['role' => PeranPengguna::Driver]), $this->karyawanSpv);
    $izin = ajukanApproval($bawahan);

    $penyetuju = app(ApproverIzin::class)->untuk($izin);

    expect($penyetuju['jalur'])->toBe(ApproverIzin::JALUR_ATASAN)
        ->and(bolehPutuskan($this->spv, $izin))->toBeTrue()
        ->and(bolehPutuskan($this->hr, $izin))->toBeFalse();
});

it('mode atasan: tanpa atasan atau atasan tanpa akun, jatuh ke approver umum', function () {
    aturApproval(ModePersetujuanIzin::Atasan);

    $tanpaAtasan = ajukanApproval($this->karyawanSales);
    $atasanTanpaAkun = karyawanApproval(null);
    $bawahan = karyawanApproval(User::factory()->create(['role' => PeranPengguna::Driver]), $atasanTanpaAkun);
    $izinBawahan = ajukanApproval($bawahan);

    foreach ([$tanpaAtasan, $izinBawahan] as $izin) {
        expect(app(ApproverIzin::class)->untuk($izin)['jalur'])->toBe(ApproverIzin::JALUR_UMUM)
            ->and(bolehPutuskan($this->hr, $izin))->toBeTrue();
    }
});

it('mode atasan: atasan non-HR mendapat menu Persetujuan Izin dan hanya melihat bawahannya', function () {
    aturApproval(ModePersetujuanIzin::Atasan);
    $bawahan = karyawanApproval(User::factory()->create(['role' => PeranPengguna::Driver]), $this->karyawanSpv);
    ajukanApproval($bawahan);
    ajukanApproval($this->karyawanSales); // bukan bawahannya

    $this->actingAs($this->spv)->get(route('hr.persetujuan-izin'))->assertOk();

    Livewire::actingAs($this->spv)->test(PersetujuanIzin::class)
        ->assertSee($bawahan->nama_lengkap)
        ->assertDontSee($this->karyawanSales->nama_lengkap)
        ->assertSet('jumlahMenunggu', 1);

    // Di mode approver umum, supervisor tanpa penunjukan tidak punya menunya.
    aturApproval(ModePersetujuanIzin::ApproverUmum);
    $this->actingAs($this->spv)->get(route('hr.persetujuan-izin'))->assertForbidden();
});

it('HR tetap bisa melihat semua pengajuan, tapi tombol keputusan hanya pada yang ia berwenang', function () {
    aturApproval(ModePersetujuanIzin::Atasan);
    $bawahan = karyawanApproval(User::factory()->create(['role' => PeranPengguna::Driver]), $this->karyawanSpv);
    $izin = ajukanApproval($bawahan);

    Livewire::actingAs($this->hr)->test(PersetujuanIzin::class)
        ->assertSee($bawahan->nama_lengkap)
        ->assertSee(__('izin.menunggu_persetujuan', ['nama' => 'Sinta Supervisor']))
        ->call('buka', $izin->id, 'setujui')
        ->assertSet('pengajuanId', null)
        ->assertDispatched('notifikasi');
});

it('karyawan melihat pengajuannya menunggu persetujuan siapa', function () {
    ajukanApproval($this->karyawanSales);

    Livewire::actingAs($this->sales)->test(AjukanIzin::class)
        ->assertSee(__('izin.menunggu_persetujuan', ['nama' => 'Hana HR']));
});

// =====================================================================
// Layar setting & master karyawan
// =====================================================================

it('setting approval hanya dibuka HR dan tersimpan', function () {
    $this->actingAs($this->admin)->get(route('hr.setting-approval-izin'))->assertForbidden();
    $this->actingAs($this->hr)->get(route('hr.setting-approval-izin'))->assertOk();

    Livewire::actingAs($this->hr)->test(SettingApprovalIzin::class)
        ->set('mode', 'atasan')
        ->set('peran', ['hr', 'supervisor'])
        ->set('penggunaDipilih', (string) $this->admin->id)
        ->call('tambahPengguna')
        ->assertSee('Adi Admin')
        ->call('simpan')
        ->assertHasNoErrors();

    $pengaturan = PengaturanIzin::ambil();

    expect($pengaturan->mode)->toBe(ModePersetujuanIzin::Atasan)
        ->and($pengaturan->peran())->toBe(['hr', 'supervisor'])
        ->and($pengaturan->idPengguna())->toBe([$this->admin->id])
        ->and($pengaturan->diubah_oleh)->toBe($this->hr->id);
});

it('setting menolak peran superadmin karena superadmin sudah selalu boleh', function () {
    Livewire::actingAs($this->hr)->test(SettingApprovalIzin::class)
        ->set('peran', ['superadmin'])
        ->call('simpan')
        ->assertHasErrors('peran.0');
});

it('atasan langsung diisi di Master Karyawan dan tidak boleh dirinya sendiri', function () {
    Livewire::actingAs($this->hr)->test(DaftarKaryawan::class)
        ->call('sunting', $this->karyawanSales->id)
        ->set('atasanId', (string) $this->karyawanSales->id)
        ->call('simpan')
        ->assertHasErrors('atasanId')
        ->set('atasanId', (string) $this->karyawanSpv->id)
        ->call('simpan')
        ->assertHasNoErrors('atasanId');

    expect($this->karyawanSales->fresh()->atasan_id)->toBe($this->karyawanSpv->id);
});

// =====================================================================
// Filter tanggal di Persetujuan Izin
// =====================================================================

it('bawaannya hanya menampilkan pengajuan untuk tanggal izin hari ini', function () {
    $hariIni = ajukanApproval($this->karyawanSales, '2026-09-17');
    $besok = ajukanApproval($this->karyawanSpv, '2026-09-18');

    Livewire::actingAs($this->hr)->test(PersetujuanIzin::class)
        ->assertSet('dariTanggal', '2026-09-17')
        ->assertSet('sampaiTanggal', '2026-09-17')
        ->assertSee($this->karyawanSales->nama_lengkap)
        ->assertDontSee($this->karyawanSpv->nama_lengkap);
});

it('pengajuan rentang yang mencakup hari ini ikut tampil', function () {
    $rentang = app(PengajuanIzinService::class)->ajukan(
        $this->karyawanSales, $this->sales, JenisIzin::Sakit, PorsiIzin::Penuh,
        CarbonImmutable::parse('2026-09-15'), CarbonImmutable::parse('2026-09-19'), 'Demam',
    );

    Livewire::actingAs($this->hr)->test(PersetujuanIzin::class)
        ->assertSee($this->karyawanSales->nama_lengkap);
});

it('memberi tahu pengajuan menunggu di luar tanggal filter, dan bisa melihat semuanya', function () {
    ajukanApproval($this->karyawanSpv, '2026-09-21');

    Livewire::actingAs($this->hr)->test(PersetujuanIzin::class)
        ->assertDontSee($this->karyawanSpv->nama_lengkap)
        ->assertSet('menungguDiLuarTanggal', 1)
        ->assertSee(__('izin.menunggu_di_luar_tanggal', ['jumlah' => 1]))
        ->call('semuaTanggal')
        ->assertSee($this->karyawanSpv->nama_lengkap)
        ->assertSet('menungguDiLuarTanggal', 0);
});

it('rentang tanggal bisa diatur, dan tanggal sampai tidak bisa sebelum tanggal dari', function () {
    ajukanApproval($this->karyawanSpv, '2026-09-21');

    Livewire::actingAs($this->hr)->test(PersetujuanIzin::class)
        ->set('dariTanggal', '2026-09-21')
        ->assertSet('sampaiTanggal', '2026-09-21')
        ->assertSee($this->karyawanSpv->nama_lengkap)
        ->call('hariIni')
        ->assertDontSee($this->karyawanSpv->nama_lengkap);
});
