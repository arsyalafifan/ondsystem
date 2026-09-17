<?php

use App\Enums\JenisAbsensi;
use App\Enums\LokasiAbsensi;
use App\Enums\PeranPengguna;
use App\Enums\StatusAbsensi;
use App\Enums\StatusKunjungan;
use App\Livewire\Hr\Absensi as LayarAbsensi;
use App\Models\Absensi;
use App\Models\Department;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Kunjungan;
use App\Models\PenugasanToko;
use App\Models\PeriodeSales;
use App\Models\Posisi;
use App\Models\Shift;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Absensi\AturanAbsensi;
use App\Services\Kunjungan\PeriodeKunjunganService;
use App\Support\DepotContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Absensi karyawan: jam kerja dari posisi (atau shift bila disetel HR),
 * penilaian tepat waktu/terlambat, dan penolakan absen di luar tempat kerja.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->depot->update(['lat' => -6.2, 'lng' => 106.8]);

    $this->department = Department::create(['kode' => 'D1', 'nama' => 'Operasional']);
    $this->jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function posisiUji(array $atribut = []): Posisi
{
    static $n = 0;
    $n++;

    return Posisi::create(array_merge([
        'kode' => 'POS'.$n,
        'nama' => 'Posisi '.$n,
        'jam_masuk' => '08:00:00',
        'jam_pulang' => '17:00:00',
        'toleransi_telat_menit' => 0,
        'lokasi_jenis' => LokasiAbsensi::Depot,
        'radius_meter' => 100,
    ], $atribut));
}

function karyawanUji(Posisi $posisi, ?User $akun = null, ?Shift $shift = null): Karyawan
{
    static $n = 0;
    $n++;

    return Karyawan::create([
        'kode_karyawan' => sprintf('KRY%03d', $n),
        'nama_lengkap' => 'Karyawan '.$n,
        'nik' => str_pad((string) $n, 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '1990-01-01',
        'no_hp' => '0812000'.$n,
        'alamat_domisili' => 'Jl. Uji',
        'department_id' => test()->department->id,
        'jabatan_id' => test()->jabatan->id,
        'posisi_id' => $posisi->id,
        'shift_id' => $shift?->id,
        'tanggal_masuk' => '2024-01-01',
        'status_karyawan' => 'tetap',
        'gaji_pokok' => 1_000_000,
        // DepotContext, bukan test()->depot: properti itu protected di
        // Tests\TestCase sehingga tidak terbaca dari fungsi helper global.
        'depot_id' => DepotContext::currentOrFail()->id,
        'user_id' => $akun?->id,
    ]);
}

function gambarAbsen(): string
{
    $gambar = imagecreatetruecolor(240, 320);
    imagefilledrectangle($gambar, 0, 0, 240, 320, imagecolorallocate($gambar, 120, 140, 160));

    ob_start();
    imagejpeg($gambar, null, 80);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

/** Absen lewat service, dengan titik GPS yang bisa diatur per test. */
function absen(Karyawan $karyawan, JenisAbsensi $jenis, ?float $lat = -6.2, ?float $lng = 106.8): Absensi
{
    return app(AturanAbsensi::class)->catat($karyawan, $jenis, gambarAbsen(), $lat, $lng, 8);
}

it('mencatat absen masuk tepat waktu beserta foto dan jaraknya', function () {
    CarbonImmutable::setTestNow('2026-09-17 07:55:00');

    $karyawan = karyawanUji(posisiUji());

    // ±11 m dari titik depot, masih di dalam radius 100 m.
    $absensi = absen($karyawan, JenisAbsensi::Masuk, -6.2001, 106.8);

    expect($absensi->status)->toBe(StatusAbsensi::TepatWaktu)
        ->and($absensi->telat_menit)->toBe(0)
        ->and($absensi->jam_acuan)->toBe('08:00:00')
        ->and($absensi->depot_id)->toBe($this->depot->id)
        ->and($absensi->jarak_m)->toBeLessThan(100)
        ->and($absensi->tanggal->toDateString())->toBe('2026-09-17');

    Storage::disk('public')->assertExists($absensi->foto);
});

it('menilai terlambat dari jam acuan, bukan dari batas toleransi', function () {
    CarbonImmutable::setTestNow('2026-09-17 08:15:00');

    $absensi = absen(karyawanUji(posisiUji(['toleransi_telat_menit' => 10])), JenisAbsensi::Masuk);

    expect($absensi->status)->toBe(StatusAbsensi::Terlambat)
        ->and($absensi->telat_menit)->toBe(15);
});

it('masih tepat waktu selama dalam toleransi', function () {
    CarbonImmutable::setTestNow('2026-09-17 08:08:00');

    expect(absen(karyawanUji(posisiUji(['toleransi_telat_menit' => 10])), JenisAbsensi::Masuk)->status)
        ->toBe(StatusAbsensi::TepatWaktu);
});

it('shift karyawan menimpa jam kerja posisinya', function () {
    CarbonImmutable::setTestNow('2026-09-17 21:55:00');

    $shift = Shift::create(['kode' => 'MLM', 'nama' => 'Malam', 'jam_masuk' => '22:00:00', 'jam_pulang' => '06:00:00', 'lintas_hari' => true]);

    // Tanpa shift, jam 21:55 akan dinilai terlambat 13 jam terhadap 08:00.
    $absensi = absen(karyawanUji(posisiUji(), null, $shift), JenisAbsensi::Masuk);

    expect($absensi->status)->toBe(StatusAbsensi::TepatWaktu)
        ->and($absensi->jam_acuan)->toBe('22:00:00')
        ->and($absensi->shift_id)->toBe($shift->id);
});

it('absen pulang shift lintas hari menempel ke tanggal masuknya', function () {
    $shift = Shift::create(['kode' => 'MLM', 'nama' => 'Malam', 'jam_masuk' => '22:00:00', 'jam_pulang' => '06:00:00', 'lintas_hari' => true]);
    $karyawan = karyawanUji(posisiUji(), null, $shift);

    CarbonImmutable::setTestNow('2026-09-17 22:00:00');
    absen($karyawan, JenisAbsensi::Masuk);

    CarbonImmutable::setTestNow('2026-09-18 06:05:00');
    $pulang = absen($karyawan, JenisAbsensi::Pulang);

    expect($pulang->tanggal->toDateString())->toBe('2026-09-17')
        ->and($pulang->status)->toBe(StatusAbsensi::TepatWaktu);
});

it('menolak absen di luar radius tempat kerja tanpa meninggalkan foto', function () {
    CarbonImmutable::setTestNow('2026-09-17 07:55:00');

    $karyawan = karyawanUji(posisiUji(['radius_meter' => 100]));

    // ±1,1 km dari depot.
    expect(fn () => absen($karyawan, JenisAbsensi::Masuk, -6.21, 106.8))
        ->toThrow(RuntimeException::class);

    expect(Absensi::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('menolak absen ketika lokasi belum terbaca', function () {
    CarbonImmutable::setTestNow('2026-09-17 07:55:00');

    expect(fn () => absen(karyawanUji(posisiUji()), JenisAbsensi::Masuk, null, null))
        ->toThrow(RuntimeException::class);
});

it('posisi bebas lokasi tetap merekam titik GPS tanpa memeriksa jarak', function () {
    CarbonImmutable::setTestNow('2026-09-17 07:55:00');

    $absensi = absen(karyawanUji(posisiUji(['lokasi_jenis' => LokasiAbsensi::Bebas])), JenisAbsensi::Masuk, -8.0, 110.0);

    expect($absensi->jarak_m)->toBeNull()
        ->and($absensi->latitude)->toBe(-8.0);
});

it('menolak absen istirahat untuk posisi yang tidak memakainya', function () {
    CarbonImmutable::setTestNow('2026-09-17 07:55:00');

    $karyawan = karyawanUji(posisiUji());
    absen($karyawan, JenisAbsensi::Masuk);

    expect(fn () => absen($karyawan, JenisAbsensi::Istirahat))->toThrow(RuntimeException::class);
});

it('menilai absen kembali istirahat terhadap batas jamnya', function () {
    $posisi = posisiUji(['pakai_absen_istirahat' => true, 'istirahat_paling_lambat' => '13:00:00']);
    $karyawan = karyawanUji($posisi);

    CarbonImmutable::setTestNow('2026-09-17 07:55:00');
    absen($karyawan, JenisAbsensi::Masuk);

    CarbonImmutable::setTestNow('2026-09-17 13:07:00');
    $istirahat = absen($karyawan, JenisAbsensi::Istirahat);

    expect($istirahat->status)->toBe(StatusAbsensi::Terlambat)
        ->and($istirahat->telat_menit)->toBe(7);
});

it('menandai pulang sebelum jam pulang sebagai pulang cepat', function () {
    $karyawan = karyawanUji(posisiUji());

    CarbonImmutable::setTestNow('2026-09-17 07:55:00');
    absen($karyawan, JenisAbsensi::Masuk);

    CarbonImmutable::setTestNow('2026-09-17 15:30:00');
    $pulang = absen($karyawan, JenisAbsensi::Pulang);

    expect($pulang->status)->toBe(StatusAbsensi::PulangCepat)
        ->and($pulang->telat_menit)->toBe(90);
});

it('menolak absen jenis yang sama dua kali dalam sehari', function () {
    CarbonImmutable::setTestNow('2026-09-17 07:55:00');

    $karyawan = karyawanUji(posisiUji());
    absen($karyawan, JenisAbsensi::Masuk);

    expect(fn () => absen($karyawan, JenisAbsensi::Masuk))->toThrow(RuntimeException::class);
    expect(Absensi::count())->toBe(1);
});

it('mewajibkan absen masuk sebelum absen pulang', function () {
    CarbonImmutable::setTestNow('2026-09-17 17:05:00');

    expect(fn () => absen(karyawanUji(posisiUji()), JenisAbsensi::Pulang))->toThrow(RuntimeException::class);
});

it('menolak absen bila posisi karyawan belum diisi', function () {
    CarbonImmutable::setTestNow('2026-09-17 07:55:00');

    $karyawan = karyawanUji(posisiUji());
    $karyawan->update(['posisi_id' => null]);

    expect(fn () => absen($karyawan->fresh(), JenisAbsensi::Masuk))->toThrow(RuntimeException::class);
});

// =====================================================================
describe('sales absen di toko tanggungan', function () {
    beforeEach(function () {
        $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
        $this->posisiSales = posisiUji(['lokasi_jenis' => LokasiAbsensi::TokoTanggungan, 'radius_meter' => 100]);
        $this->karyawanSales = karyawanUji($this->posisiSales, $this->sales);
    });

    function tokoTanggungan(float $lat, float $lng): Toko
    {
        static $n = 0;
        $n++;

        $toko = Toko::create([
            'kode' => sprintf('TK-ABS%03d', $n),
            'nama' => 'Toko Absen '.$n,
            'wilayah_id' => test()->wilayah->id,
            'alamat' => 'Jl. Toko '.$n,
            'latitude' => $lat,
            'longitude' => $lng,
            'sumber_koordinat' => 'manual',
        ]);

        PenugasanToko::create([
            'toko_id' => $toko->id,
            'sales_id' => test()->sales->id,
            'hari' => 1,
            'ditugaskan_oleh' => test()->admin->id,
        ]);

        return $toko;
    }

    it('menerima absen di toko tanggungan yang belum dikunjungi', function () {
        CarbonImmutable::setTestNow('2026-09-17 07:55:00');

        $toko = tokoTanggungan(-6.2, 106.8);

        $absensi = absen($this->karyawanSales, JenisAbsensi::Masuk, -6.2001, 106.8);

        expect($absensi->toko_id)->toBe($toko->id)
            ->and($absensi->depot_id)->toBeNull()
            ->and($absensi->lokasi_jenis)->toBe(LokasiAbsensi::TokoTanggungan);
    });

    it('menolak absen di toko yang sudah dikunjungi minggu ini', function () {
        CarbonImmutable::setTestNow('2026-09-17 07:55:00');

        $toko = tokoTanggungan(-6.2, 106.8);

        // Toko itu sudah dikunjungi pada periode berjalan, jadi tidak lagi
        // jadi kandidat tempat absen.
        $periode = app(PeriodeKunjunganService::class)->periodeBerjalan();
        $periodeSales = PeriodeSales::firstOrCreate(
            ['periode_kunjungan_id' => $periode->id, 'sales_id' => $this->sales->id],
            ['target_toko' => 1],
        );

        Kunjungan::create([
            'periode_kunjungan_id' => $periode->id,
            'periode_sales_id' => $periodeSales->id,
            'sales_id' => $this->sales->id,
            'toko_id' => $toko->id,
            'status' => StatusKunjungan::Selesai,
            'mulai_at' => now()->subHour(),
            'selesai_at' => now()->subMinutes(30),
        ]);

        expect(fn () => absen($this->karyawanSales, JenisAbsensi::Masuk, -6.2001, 106.8))
            ->toThrow(RuntimeException::class);
    });

    it('menolak absen yang terlalu jauh dari seluruh toko tanggungan', function () {
        CarbonImmutable::setTestNow('2026-09-17 07:55:00');

        tokoTanggungan(-6.2, 106.8);

        expect(fn () => absen($this->karyawanSales, JenisAbsensi::Masuk, -6.25, 106.8))
            ->toThrow(RuntimeException::class);
    });

    it('menolak absen bila tidak ada toko tanggungan tersisa', function () {
        CarbonImmutable::setTestNow('2026-09-17 07:55:00');

        expect(fn () => absen($this->karyawanSales, JenisAbsensi::Masuk, -6.2, 106.8))
            ->toThrow(RuntimeException::class);
    });
});

// =====================================================================
describe('layar absensi', function () {
    it('bisa dibuka semua peran yang absen', function () {
        foreach (['admin', 'sales'] as $peran) {
            $this->actingAs($this->{$peran})->get(route('hr.absensi'))->assertOk();
        }
    });

    it('memberi tahu ketika akun belum tertaut ke data karyawan', function () {
        $this->actingAs($this->sales)->get(route('hr.absensi'))
            ->assertOk()
            ->assertSee(__('hr.galat_tanpa_karyawan'));
    });

    it('menyimpan bidikan kamera dan menampilkan statusnya', function () {
        CarbonImmutable::setTestNow('2026-09-17 08:30:00');

        karyawanUji(posisiUji(), $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LayarAbsensi::class)
            ->call('simpanJepretan', JenisAbsensi::Masuk->value, gambarAbsen(), ['lat' => -6.2001, 'lng' => 106.8, 'akurasi' => 9])
            ->assertDispatched('notifikasi');

        $absensi = Absensi::firstOrFail();

        expect($absensi->status)->toBe(StatusAbsensi::Terlambat)
            ->and($absensi->telat_menit)->toBe(30);
    });

    it('menampilkan pesan penolakan ketika absen di luar radius', function () {
        CarbonImmutable::setTestNow('2026-09-17 07:55:00');

        karyawanUji(posisiUji(), $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LayarAbsensi::class)
            ->call('simpanJepretan', JenisAbsensi::Masuk->value, gambarAbsen(), ['lat' => -6.21, 'lng' => 106.8])
            ->assertDispatched('notifikasi', jenis: 'error');

        expect(Absensi::count())->toBe(0);
    });
});
