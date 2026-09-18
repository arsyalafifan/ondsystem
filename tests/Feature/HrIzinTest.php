<?php

use App\Enums\JenisAbsensi;
use App\Enums\JenisIzin;
use App\Enums\LokasiAbsensi;
use App\Enums\PeranPengguna;
use App\Enums\PorsiIzin;
use App\Enums\StatusAbsensi;
use App\Enums\StatusPengajuan;
use App\Livewire\Hr\Absensi as LayarAbsensi;
use App\Livewire\Hr\AjukanIzin;
use App\Livewire\Hr\MonitoringAbsensi;
use App\Livewire\Hr\PersetujuanIzin;
use App\Models\Absensi;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\Posisi;
use App\Models\Shift;
use App\Models\User;
use App\Services\Absensi\AturanAbsensi;
use App\Services\Izin\PengajuanIzinService;
use App\Support\DepotContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Ajukan Izin & Persetujuan Izin: izin (penuh / ½ hari pertama / ½ hari
 * kedua) dan sakit, diputuskan HR, lalu tampil di Attendance Monitoring dan
 * menggeser jam acuan absensi untuk setengah hari.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    // Kamis, 17 September 2026 pukul 07:00.
    $this->travelTo(CarbonImmutable::parse('2026-09-17 07:00:00'));

    $this->hr = User::factory()->create(['role' => PeranPengguna::Hr]);
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->akun = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->depot->update(['lat' => -6.2, 'lng' => 106.8]);
    $this->department = Department::create(['kode' => 'D1', 'nama' => 'Operasional']);
    $this->jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff']);

    // 08:00–17:00, istirahat 60 menit → kerja bersih 8 jam, ½ hari = 4 jam.
    $this->posisi = Posisi::create([
        'kode' => 'STF', 'nama' => 'Staff Kantor', 'jam_masuk' => '08:00:00', 'jam_pulang' => '17:00:00',
        'durasi_istirahat_menit' => 60, 'toleransi_telat_menit' => 0,
        'lokasi_jenis' => LokasiAbsensi::Bebas, 'hari_kerja' => [1, 2, 3, 4, 5, 6],
    ]);

    $this->karyawan = karyawanIzin($this->posisi, $this->akun);
});

function karyawanIzin(Posisi $posisi, ?User $akun = null, ?Shift $shift = null): Karyawan
{
    static $n = 0;
    $n++;

    return Karyawan::create([
        'kode_karyawan' => sprintf('IZN%03d', $n),
        'nama_lengkap' => 'Karyawan Izin '.$n,
        'nik' => str_pad((string) (9000 + $n), 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '1990-01-01',
        'no_hp' => '0813000'.$n,
        'alamat_domisili' => 'Jl. Uji',
        'department_id' => test()->department->id,
        'jabatan_id' => test()->jabatan->id,
        'posisi_id' => $posisi->id,
        'shift_id' => $shift?->id,
        'tanggal_masuk' => '2024-01-01',
        'status_karyawan' => 'tetap',
        'gaji_pokok' => 1_000_000,
        'depot_id' => DepotContext::currentOrFail()->id,
        'user_id' => $akun?->id,
    ]);
}

function ajukanIzin(
    JenisIzin $jenis = JenisIzin::Izin,
    PorsiIzin $porsi = PorsiIzin::Penuh,
    string $mulai = '2026-09-17',
    ?string $selesai = null,
    ?Karyawan $karyawan = null,
    ?UploadedFile $lampiran = null,
): PengajuanIzin {
    $karyawan ??= test()->karyawan;

    return app(PengajuanIzinService::class)->ajukan(
        $karyawan, $karyawan->user ?? test()->akun, $jenis, $porsi,
        CarbonImmutable::parse($mulai), CarbonImmutable::parse($selesai ?? $mulai),
        'Keperluan keluarga', $lampiran,
    );
}

function gambarIzinAbsen(): string
{
    $gambar = imagecreatetruecolor(120, 160);
    ob_start();
    imagejpeg($gambar, null, 80);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

function absenIzin(Karyawan $karyawan, JenisAbsensi $jenis): Absensi
{
    return app(AturanAbsensi::class)->catat($karyawan->fresh(), $jenis, gambarIzinAbsen());
}

// =====================================================================
// Mengajukan
// =====================================================================

it('mengajukan izin sehari penuh: menunggu HR, dihitung hari kerja, tidak dibayar', function () {
    $izin = ajukanIzin(mulai: '2026-09-18', selesai: '2026-09-21'); // Jumat–Senin

    expect($izin->status)->toBe(StatusPengajuan::Menunggu)
        ->and($izin->jumlah_hari)->toBe(3.0) // Minggu libur, tidak terhitung
        ->and($izin->dibayar)->toBeFalse()
        ->and($izin->diajukan_oleh)->toBe($this->akun->id);
});

it('izin setengah hari selalu satu tanggal dan terhitung setengah hari', function () {
    $izin = ajukanIzin(porsi: PorsiIzin::ParuhPertama, mulai: '2026-09-18', selesai: '2026-09-25');

    expect($izin->tanggal_selesai->toDateString())->toBe('2026-09-18')
        ->and($izin->jumlah_hari)->toBe(0.5);
});

it('sakit tidak bisa setengah hari dan tercatat dibayar', function () {
    expect(fn () => ajukanIzin(JenisIzin::Sakit, PorsiIzin::ParuhKedua))->toThrow(RuntimeException::class);

    expect(ajukanIzin(JenisIzin::Sakit)->dibayar)->toBeTrue();
});

it('izin tidak boleh mundur, sakit boleh mundur paling lama 3 hari', function () {
    expect(fn () => ajukanIzin(mulai: '2026-09-16'))->toThrow(RuntimeException::class);

    expect(ajukanIzin(JenisIzin::Sakit, mulai: '2026-09-14', selesai: '2026-09-14')->exists)->toBeTrue()
        ->and(fn () => ajukanIzin(JenisIzin::Sakit, mulai: '2026-09-12', selesai: '2026-09-12'))->toThrow(RuntimeException::class);
});

it('menolak rentang terbalik, terlalu panjang, atau tanpa hari kerja', function () {
    expect(fn () => ajukanIzin(mulai: '2026-09-20', selesai: '2026-09-18'))->toThrow(RuntimeException::class)
        ->and(fn () => ajukanIzin(mulai: '2026-09-18', selesai: '2026-10-30'))->toThrow(RuntimeException::class)
        ->and(fn () => ajukanIzin(mulai: '2026-09-20'))->toThrow(RuntimeException::class); // Minggu
});

it('menolak tanggal yang bertabrakan dengan pengajuan lain yang masih aktif', function () {
    $pertama = ajukanIzin(mulai: '2026-09-18', selesai: '2026-09-19');

    expect(fn () => ajukanIzin(porsi: PorsiIzin::ParuhKedua, mulai: '2026-09-19'))->toThrow(RuntimeException::class);

    app(PengajuanIzinService::class)->tolak($pertama, $this->hr, 'Bentrok jadwal');

    expect(ajukanIzin(porsi: PorsiIzin::ParuhKedua, mulai: '2026-09-19')->exists)->toBeTrue();
});

it('izin sehari penuh ditolak untuk hari yang sudah diabsen masuk, setengah hari tetap boleh', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 07:55:00'));
    absenIzin($this->karyawan, JenisAbsensi::Masuk);

    expect(fn () => ajukanIzin())->toThrow(RuntimeException::class)
        ->and(ajukanIzin(porsi: PorsiIzin::ParuhKedua)->exists)->toBeTrue();
});

it('lampiran disimpan di disk privat dan hanya bisa dibuka pemilik dan HR', function () {
    $izin = ajukanIzin(JenisIzin::Sakit, lampiran: UploadedFile::fake()->create('surat-dokter.pdf', 100, 'application/pdf'));

    Storage::disk('local')->assertExists($izin->lampiran);
    Storage::disk('public')->assertMissing($izin->lampiran);
    expect($izin->lampiran_nama)->toBe('surat-dokter.pdf');

    $orangLain = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($this->akun)->get(route('hr.izin.lampiran', $izin))->assertOk();
    $this->actingAs($this->hr)->get(route('hr.izin.lampiran', $izin))->assertOk();
    $this->actingAs($this->admin)->get(route('hr.izin.lampiran', $izin))->assertForbidden();
    $this->actingAs($orangLain)->get(route('hr.izin.lampiran', $izin))->assertForbidden();
});

// =====================================================================
// Layar Ajukan Izin
// =====================================================================

it('karyawan mengajukan lewat layar Ajukan Izin beserta lampiran', function () {
    Livewire::actingAs($this->akun)
        ->test(AjukanIzin::class)
        ->set('jenis', 'sakit')
        ->set('tanggalMulai', '2026-09-17')
        ->set('tanggalSelesai', '2026-09-18')
        ->set('alasan', 'Demam tinggi')
        ->set('lampiran', UploadedFile::fake()->image('surat.jpg'))
        ->call('ajukan')
        ->assertHasNoErrors()
        ->assertDispatched('notifikasi');

    $izin = PengajuanIzin::sole();

    expect($izin->jenis)->toBe(JenisIzin::Sakit)
        ->and($izin->jumlah_hari)->toBe(2.0)
        ->and($izin->lampiran)->not->toBeNull();
});

it('memilih sakit mengembalikan porsi ke sehari penuh, dan pratinjau menunjukkan jam setengah hari', function () {
    Livewire::actingAs($this->akun)
        ->test(AjukanIzin::class)
        ->set('porsi', 'paruh_pertama')
        ->assertSee('13:00')
        ->set('porsi', 'paruh_kedua')
        ->assertSee('12:00')
        ->set('jenis', 'sakit')
        ->assertSet('porsi', 'penuh');
});

it('karyawan bisa menarik pengajuan yang masih menunggu, tapi tidak yang sudah diputuskan', function () {
    $menunggu = ajukanIzin(mulai: '2026-09-18');
    $disetujui = ajukanIzin(mulai: '2026-09-21');
    app(PengajuanIzinService::class)->setujui($disetujui, $this->hr);

    Livewire::actingAs($this->akun)->test(AjukanIzin::class)->call('batalkan', $menunggu->id);
    Livewire::actingAs($this->akun)->test(AjukanIzin::class)->call('batalkan', $disetujui->id)->assertDispatched('notifikasi');

    expect($menunggu->fresh()->status)->toBe(StatusPengajuan::Dibatalkan)
        ->and($disetujui->fresh()->status)->toBe(StatusPengajuan::Disetujui);
});

// =====================================================================
// Persetujuan HR
// =====================================================================

it('hanya HR yang membuka Persetujuan Izin; semua peran bisa membuka Ajukan Izin', function () {
    $this->actingAs($this->hr)->get(route('hr.persetujuan-izin'))->assertOk();
    $this->actingAs($this->admin)->get(route('hr.persetujuan-izin'))->assertForbidden();
    $this->actingAs($this->akun)->get(route('hr.persetujuan-izin'))->assertForbidden();

    $this->actingAs($this->akun)->get(route('hr.izin'))->assertOk();
    $this->actingAs($this->admin)->get(route('hr.izin'))->assertOk();
});

it('HR menyetujui dengan catatan opsional, dan menolak wajib beralasan', function () {
    $a = ajukanIzin(mulai: '2026-09-18');
    $b = ajukanIzin(mulai: '2026-09-21');

    Livewire::actingAs($this->hr)->test(PersetujuanIzin::class)
        ->call('semuaTanggal')
        ->assertSee($this->karyawan->nama_lengkap)
        ->call('buka', $a->id, 'setujui')
        ->call('putuskan')
        ->assertHasNoErrors();

    Livewire::actingAs($this->hr)->test(PersetujuanIzin::class)
        ->call('buka', $b->id, 'tolak')
        ->call('putuskan')
        ->assertHasErrors('catatan')
        ->set('catatan', 'Sedang stock opname')
        ->call('putuskan')
        ->assertHasNoErrors();

    expect($a->fresh()->status)->toBe(StatusPengajuan::Disetujui)
        ->and($a->fresh()->diputuskan_oleh)->toBe($this->hr->id)
        ->and($b->fresh()->status)->toBe(StatusPengajuan::Ditolak)
        ->and($b->fresh()->catatan_keputusan)->toBe('Sedang stock opname');
});

it('pengajuan yang sudah diputuskan tidak bisa diputuskan lagi', function () {
    $izin = ajukanIzin(mulai: '2026-09-18');
    app(PengajuanIzinService::class)->setujui($izin, $this->hr);

    expect(fn () => app(PengajuanIzinService::class)->tolak($izin, $this->hr, 'x'))->toThrow(RuntimeException::class);
});

it('persetujuan ditolak bila karyawan ternyata sudah absen masuk sesudah mengajukan', function () {
    $izin = ajukanIzin();

    $this->travelTo(CarbonImmutable::parse('2026-09-17 07:58:00'));
    absenIzin($this->karyawan, JenisAbsensi::Masuk);

    expect(fn () => app(PengajuanIzinService::class)->setujui($izin, $this->hr))->toThrow(RuntimeException::class)
        ->and($izin->fresh()->status)->toBe(StatusPengajuan::Menunggu);
});

// =====================================================================
// Dampak ke absensi & monitoring
// =====================================================================

it('izin sehari penuh yang disetujui menolak absen masuk dan menyembunyikan tombol absen', function () {
    app(PengajuanIzinService::class)->setujui(ajukanIzin(), $this->hr);

    expect(fn () => absenIzin($this->karyawan, JenisAbsensi::Masuk))->toThrow(RuntimeException::class);

    Livewire::actingAs($this->akun)->test(LayarAbsensi::class)
        ->assertSet('langkah', []);
});

it('izin paruh pertama menggeser jam masuk ke pulang dikurangi setengah hari kerja bersih', function () {
    app(PengajuanIzinService::class)->setujui(ajukanIzin(porsi: PorsiIzin::ParuhPertama), $this->hr);

    $this->travelTo(CarbonImmutable::parse('2026-09-17 13:05:00'));
    $masuk = absenIzin($this->karyawan, JenisAbsensi::Masuk);

    expect($masuk->jam_acuan)->toBe('13:00:00')
        ->and($masuk->status)->toBe(StatusAbsensi::Terlambat)
        ->and($masuk->telat_menit)->toBe(5);
});

it('izin paruh kedua membolehkan pulang di tengah hari tanpa dianggap pulang cepat', function () {
    app(PengajuanIzinService::class)->setujui(ajukanIzin(porsi: PorsiIzin::ParuhKedua), $this->hr);

    $this->travelTo(CarbonImmutable::parse('2026-09-17 07:59:00'));
    absenIzin($this->karyawan, JenisAbsensi::Masuk);

    $this->travelTo(CarbonImmutable::parse('2026-09-17 12:00:00'));
    $pulang = absenIzin($this->karyawan, JenisAbsensi::Pulang);

    expect($pulang->jam_acuan)->toBe('12:00:00')
        ->and($pulang->status)->toBe(StatusAbsensi::TepatWaktu);
});

it('setengah hari memakai jam dan istirahat shift bila karyawan bershift', function () {
    $shift = Shift::create([
        'kode' => 'MLM', 'nama' => 'Malam', 'jam_masuk' => '22:00:00', 'jam_pulang' => '06:00:00',
        'lintas_hari' => true, 'durasi_istirahat_menit' => 30,
    ]);
    $karyawan = karyawanIzin($this->posisi, null, $shift);

    // 8 jam − 30 menit = 450 menit bersih → setengah hari 225 menit.
    expect(app(AturanAbsensi::class)->menitSetengahHari($karyawan))->toBe(225);

    // Tanpa istirahat shift → ikut posisi (60 menit): (480 − 60) / 2.
    $shift->update(['durasi_istirahat_menit' => null]);
    expect(app(AturanAbsensi::class)->menitSetengahHari($karyawan->fresh()))->toBe(210);
});

it('attendance monitoring menampilkan status sesuai izin yang disetujui, bukan yang masih menunggu', function () {
    $sakit = karyawanIzin($this->posisi);
    $paruh = karyawanIzin($this->posisi);
    $menunggu = karyawanIzin($this->posisi);

    $service = app(PengajuanIzinService::class);
    $service->setujui(ajukanIzin(), $this->hr);
    $service->setujui(ajukanIzin(JenisIzin::Sakit, karyawan: $sakit), $this->hr);
    $service->setujui(ajukanIzin(porsi: PorsiIzin::ParuhPertama, karyawan: $paruh), $this->hr);
    ajukanIzin(karyawan: $menunggu);

    $komponen = Livewire::actingAs($this->hr)->test(MonitoringAbsensi::class, ['tanggal' => '2026-09-17']);
    $status = $komponen->instance()->baris->mapWithKeys(fn ($b) => [$b['karyawan']->id => $b['status']]);

    expect($status[$this->karyawan->id])->toBe('izin')
        ->and($status[$sakit->id])->toBe('sakit')
        ->and($status[$paruh->id])->toBe('izin_paruh_pertama')
        ->and($status[$menunggu->id])->toBe('belum_absen');

    expect($komponen->instance()->ringkasan['izin'])->toBe(2)
        ->and($komponen->instance()->ringkasan['sakit'])->toBe(1);

    $komponen->assertSee(__('hr.status_harian_izin_paruh_pertama'));
});
