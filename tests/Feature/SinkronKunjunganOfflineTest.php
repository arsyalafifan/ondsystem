<?php

use App\Enums\HariKunjungan;
use App\Enums\JenisFotoKunjungan;
use App\Enums\PeranPengguna;
use App\Enums\StatusKunjungan;
use App\Livewire\Kunjungan\DetailPeriode;
use App\Models\Kunjungan;
use App\Models\PenugasanToko;
use App\Models\PeriodeSales;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PenugasanTokoService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kunjungan yang dikerjakan sales di daerah tanpa sinyal, lalu dikirim
 * menyusul begitu jaringan kembali.
 *
 * Yang dijaga di sini bukan cuma "datanya masuk", tapi juga bahwa jalur ini
 * TIDAK menjadi pintu belakang yang lebih longgar dari alur daring: toko tetap
 * harus tanggungan sales itu, satu toko tetap sekali per periode, foto wajib
 * tetap harus lengkap, dan jam dari ponsel tetap dibatasi supaya kunjungan
 * "susulan" yang tidak pernah terjadi tidak bisa diselundupkan.
 */
beforeEach(function () {
    Storage::fake('public');

    // Waktu dibekukan di tengah pekan (Rabu). Banyak tes di sini membuat
    // kunjungan beberapa jam ke belakang, dan periode kunjungan berjalan
    // Senin–Sabtu — kalau suite kebetulan dijalankan tepat setelah tengah
    // malam Senin, "dua jam lalu" jatuh ke periode minggu LALU dan tes
    // bentrokan gagal karena pembandingnya ada di periode yang berbeda.
    // Itu kerapuhan tesnya, bukan cacat aturannya; membekukan waktu
    // membuat hasilnya sama kapan pun suite dijalankan.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-16 10:00:00'));

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Offline']);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Lain']);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->periodeService = app(PeriodeKunjunganService::class);
});

function tokoOffline(string $nama = 'Toko Offline', string $assetId = 'IDNAH202528007001'): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-OFF%04d', $n),
        'asset_id' => $assetId,
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Offline No. '.$n,
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);
}

function tugaskanOffline(Toko $toko, User $sales): void
{
    $sudahAda = PenugasanToko::query()
        ->where('sales_id', $sales->id)
        ->where('hari', HariKunjungan::Senin->value)
        ->pluck('toko_id')
        ->all();

    app(PenugasanTokoService::class)->tetapkan($sales, HariKunjungan::Senin, [...$sudahAda, $toko->id], test()->admin);
}

function gambarOffline(): string
{
    $gambar = imagecreatetruecolor(320, 240);
    imagefilledrectangle($gambar, 0, 0, 320, 240, imagecolorallocate($gambar, 80, 140, 190));

    ob_start();
    imagejpeg($gambar, null, 80);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

/**
 * Satu kunjungan offline yang sah dan lengkap. Parameternya dibuka satu per
 * satu supaya tiap tes bisa merusak tepat satu hal.
 *
 * @param  ?array<int, string>  $jenisFoto  null = seluruh foto wajib
 */
function muatanOffline(
    Toko $toko,
    ?CarbonImmutable $waktu = null,
    string $status = 'selesai',
    ?array $jenisFoto = null,
    ?string $uuid = null,
    ?string $catatan = null,
): array {
    $waktu ??= CarbonImmutable::now()->subHours(2);
    $jenisFoto ??= array_map(fn (JenisFotoKunjungan $j): string => $j->value, JenisFotoKunjungan::urut());

    return [
        'uuid_klien' => $uuid ?? (string) Str::uuid(),
        'toko_id' => $toko->id,
        'status' => $status,
        'mulai_at' => $waktu->toIso8601String(),
        'selesai_at' => $waktu->addMinutes(8)->toIso8601String(),
        'asset_id_terpindai' => $toko->asset_id,
        'catatan_sales' => $catatan,
        'latitude' => -6.18,
        'longitude' => 106.83,
        'akurasi_m' => 12,
        'fotos' => array_map(fn (string $jenis): array => [
            'jenis' => $jenis,
            'gambar' => gambarOffline(),
            'diambil_at' => $waktu->addMinutes(2)->toIso8601String(),
            'latitude' => -6.18,
            'longitude' => 106.83,
            'akurasi_m' => 12,
        ], $jenisFoto),
    ];
}

function kirimSinkron(User $sales, array ...$kunjungans)
{
    return test()->actingAs($sales)->postJson(route('kunjungan.sinkron'), ['kunjungans' => $kunjungans]);
}

// =====================================================================
describe('hak akses', function () {
    it('hanya bisa diakses sales', function () {
        $this->actingAs($this->admin)
            ->postJson(route('kunjungan.sinkron'), ['kunjungans' => []])
            ->assertForbidden();

        $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

        $this->actingAs($driver)
            ->postJson(route('kunjungan.sinkron'), ['kunjungans' => []])
            ->assertForbidden();
    });

    it('menolak tamu yang belum login', function () {
        $this->postJson(route('kunjungan.sinkron'), ['kunjungans' => []])->assertUnauthorized();
    });
});

// =====================================================================
describe('kunjungan offline tersimpan', function () {
    it('menyimpan kunjungan lengkap beserta seluruh fotonya', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $waktu = CarbonImmutable::now()->subHours(3);

        kirimSinkron($this->sales, muatanOffline($toko, $waktu))
            ->assertOk()
            ->assertJsonPath('hasil.0.status', 'diterima');

        $kunjungan = Kunjungan::with('fotos')->first();

        expect($kunjungan->status)->toBe(StatusKunjungan::Selesai)
            ->and($kunjungan->sales_id)->toBe($this->sales->id)
            ->and($kunjungan->toko_id)->toBe($toko->id)
            ->and($kunjungan->fotos)->toHaveCount(count(JenisFotoKunjungan::urut()))
            // Waktu kunjungan dari ponsel, waktu sinkron dari server.
            ->and($kunjungan->mulai_at->format('Y-m-d H:i'))->toBe($waktu->format('Y-m-d H:i'))
            ->and($kunjungan->disinkronkan_at)->not->toBeNull()
            ->and($kunjungan->dibuat_offline)->toBeTrue();
    });

    it('menandai kunjungan daring biasa sebagai BUKAN offline', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $kunjungan = app(KunjunganService::class)->mulai($toko, $this->sales);

        expect($kunjungan->dibuat_offline)->toBeFalse()
            ->and($kunjungan->disinkronkan_at)->toBeNull();
    });

    it('menyimpan waktu pengambilan foto dari ponsel, bukan jam server', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $waktu = CarbonImmutable::now()->subHours(5);

        kirimSinkron($this->sales, muatanOffline($toko, $waktu))->assertOk();

        $foto = Kunjungan::first()->fotos()->first();

        expect($foto->diambil_at->format('Y-m-d H:i'))->toBe($waktu->addMinutes(2)->format('Y-m-d H:i'))
            // Penanda bahwa foto ini tidak pernah dilihat server saat diambil.
            ->and($foto->disinkronkan_at)->not->toBeNull();
    });

    it('menerima laporan toko tutup yang disertai keterangan', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        kirimSinkron($this->sales, muatanOffline(
            $toko,
            status: 'tutup_diajukan',
            jenisFoto: ['sales_depan_toko'],
            catatan: 'Rolling door terkunci, tetangga bilang tutup sejak minggu lalu',
        ))->assertJsonPath('hasil.0.status', 'diterima');

        expect(Kunjungan::first()->status)->toBe(StatusKunjungan::TutupDiajukan);
    });

    it('memasukkan kunjungan ke periode saat dikerjakan, bukan saat dikirim', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $mingguLalu = CarbonImmutable::now()->subDays(7);
        $periodeLama = $this->periodeService->periodeUntuk($mingguLalu);
        $periodeKini = $this->periodeService->periodeBerjalan();

        // Prasyarat tesnya: keduanya memang periode yang berbeda.
        expect($periodeLama->id)->not->toBe($periodeKini->id);

        kirimSinkron($this->sales, muatanOffline($toko, $mingguLalu))
            ->assertJsonPath('hasil.0.status', 'diterima');

        expect(Kunjungan::first()->periode_kunjungan_id)->toBe($periodeLama->id);
    });

    it('membuat baris progres sales bila periodenya belum punya', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        PeriodeSales::query()->delete();

        kirimSinkron($this->sales, muatanOffline($toko))->assertJsonPath('hasil.0.status', 'diterima');

        expect(PeriodeSales::where('sales_id', $this->sales->id)->exists())->toBeTrue();
    });
});

// =====================================================================
describe('pengiriman ulang dan bentrokan', function () {
    it('tidak menggandakan kunjungan ketika uuid yang sama dikirim dua kali', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $muatan = muatanOffline($toko);

        kirimSinkron($this->sales, $muatan)->assertJsonPath('hasil.0.status', 'diterima');
        // Sinyal putus tepat setelah server menyimpan: perangkat mengira
        // gagal lalu mengirim ulang isi yang sama persis.
        kirimSinkron($this->sales, $muatan)->assertJsonPath('hasil.0.status', 'duplikat');

        expect(Kunjungan::count())->toBe(1);
    });

    it('menolak toko yang pada periode itu sudah dituntaskan sales lain', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);
        tugaskanOffline($toko, $this->salesLain);

        $periode = $this->periodeService->periodeBerjalan();
        $periodeSales = PeriodeSales::firstOrCreate(
            ['periode_kunjungan_id' => $periode->id, 'sales_id' => $this->salesLain->id],
            ['target_toko' => 1],
        );

        Kunjungan::create([
            'periode_kunjungan_id' => $periode->id,
            'periode_sales_id' => $periodeSales->id,
            'sales_id' => $this->salesLain->id,
            'toko_id' => $toko->id,
            'status' => StatusKunjungan::Selesai,
            'mulai_at' => now()->subHour(),
            'selesai_at' => now()->subHour(),
        ]);

        kirimSinkron($this->sales, muatanOffline($toko))
            ->assertJsonPath('hasil.0.status', 'konflik');

        // Kunjungan milik sales lain tidak ikut tertimpa.
        expect(Kunjungan::count())->toBe(1)
            ->and(Kunjungan::first()->sales_id)->toBe($this->salesLain->id);
    });

    it('mengambil alih kunjungan yang laporan tutupnya sudah ditolak admin', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $service = app(KunjunganService::class);
        $kunjungan = $service->mulai($toko, $this->sales);
        $service->ajukanTokoTutup($kunjungan, 'Sepertinya tutup');
        $service->tolakTokoTutup($kunjungan->fresh(), $this->admin, 'Ternyata buka');

        kirimSinkron($this->sales, muatanOffline($toko))
            ->assertJsonPath('hasil.0.status', 'diterima');

        expect(Kunjungan::count())->toBe(1)
            ->and(Kunjungan::first()->status)->toBe(StatusKunjungan::Selesai);
    });

    it('melanjutkan kunjungan sendiri yang tertinggal berjalan saat sinyal hilang', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        // Sales sempat memindai QR saat masih ada sinyal, lalu masuk daerah mati.
        $awal = app(KunjunganService::class)->mulai($toko, $this->sales);

        kirimSinkron($this->sales, muatanOffline($toko))
            ->assertJsonPath('hasil.0.status', 'diterima');

        expect(Kunjungan::count())->toBe(1)
            ->and(Kunjungan::first()->id)->toBe($awal->id)
            ->and(Kunjungan::first()->status)->toBe(StatusKunjungan::Selesai);
    });
});

// =====================================================================
describe('pagar yang tetap berlaku sama seperti alur daring', function () {
    it('menolak toko yang bukan tanggungan sales itu', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->salesLain);

        kirimSinkron($this->sales, muatanOffline($toko))
            ->assertJsonPath('hasil.0.status', 'ditolak');

        expect(Kunjungan::count())->toBe(0);
    });

    it('menolak kunjungan selesai yang fotonya belum lengkap', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        kirimSinkron($this->sales, muatanOffline($toko, jenisFoto: ['spanduk', 'suhu_freezer']))
            ->assertJsonPath('hasil.0.status', 'ditolak');

        expect(Kunjungan::count())->toBe(0);
    });

    it('tidak meninggalkan berkas foto yatim ketika kunjungan ditolak', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        // Foto terakhir rusak: beberapa foto sebelumnya sudah terlanjur
        // ditulis ke disk sebelum penolakan terjadi.
        $muatan = muatanOffline($toko);
        $muatan['fotos'][count($muatan['fotos']) - 1]['gambar'] = 'data:image/jpeg;base64,bukan-gambar';

        kirimSinkron($this->sales, $muatan)->assertJsonPath('hasil.0.status', 'ditolak');

        expect(Kunjungan::count())->toBe(0)
            ->and(Storage::disk('public')->allFiles('kunjungan'))->toBeEmpty();
    });

    it('menolak laporan toko tutup tanpa keterangan', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        kirimSinkron($this->sales, muatanOffline($toko, status: 'tutup_diajukan', jenisFoto: ['spanduk']))
            ->assertJsonPath('hasil.0.status', 'ditolak');
    });

    it('menolak status yang bukan wewenang sales', function (string $status) {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        kirimSinkron($this->sales, muatanOffline($toko, status: $status))
            ->assertJsonPath('hasil.0.status', 'ditolak');
    })->with(['berjalan', 'tutup_disetujui', 'tutup_ditolak']);
});

// =====================================================================
describe('pagar jam ponsel', function () {
    it('menolak kunjungan yang waktunya di masa depan', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $besok = CarbonImmutable::now()->addDay();

        kirimSinkron($this->sales, muatanOffline($toko, $besok))
            ->assertJsonPath('hasil.0.status', 'ditolak');

        expect(Kunjungan::count())->toBe(0);
    });

    it('memaafkan jam ponsel yang melenceng sedikit ke depan', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        // Masih di dalam toleransi_maju_menit (60 menit).
        $agakMaju = CarbonImmutable::now()->addMinutes(10);

        kirimSinkron($this->sales, muatanOffline($toko, $agakMaju))
            ->assertJsonPath('hasil.0.status', 'diterima');
    });

    /**
     * Peramban mengirim waktu dalam UTC (berakhiran Z), sementara seluruh
     * waktu lain di basis data memakai zona aplikasi. Kalau tidak dipindahkan,
     * kunjungan offline tersimpan tujuh jam lebih pagi daripada yang
     * sebenarnya — termasuk pada watermark fotonya. Bentuk Z inilah yang
     * benar-benar dikirim `new Date().toISOString()` di perangkat, jadi
     * justru bentuk ini yang harus diuji, bukan bentuk berzona lokal.
     */
    it('memindahkan waktu UTC dari peramban ke zona aplikasi', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $waktu = CarbonImmutable::now()->subHours(2);

        $muatan = muatanOffline($toko, $waktu);
        $muatan['mulai_at'] = $waktu->utc()->format('Y-m-d\TH:i:s.v\Z');
        $muatan['selesai_at'] = $waktu->utc()->format('Y-m-d\TH:i:s.v\Z');

        kirimSinkron($this->sales, $muatan)->assertJsonPath('hasil.0.status', 'diterima');

        $kunjungan = Kunjungan::first();

        expect($kunjungan->mulai_at->format('Y-m-d H:i'))->toBe($waktu->format('Y-m-d H:i'))
            // Jeda antara kunjungan selesai dan datanya sampai di server harus
            // mendekati nol, bukan sebesar selisih zona waktu.
            ->and(abs($kunjungan->jeda_sinkron_menit - 120))->toBeLessThan(5);
    });

    it('menolak kunjungan yang sudah terlalu lama ditahan di perangkat', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $lama = CarbonImmutable::now()->subDays((int) config('visit.offline.maks_umur_hari') + 1);

        kirimSinkron($this->sales, muatanOffline($toko, $lama))
            ->assertJsonPath('hasil.0.status', 'ditolak');

        expect(Kunjungan::count())->toBe(0);
    });
});

// =====================================================================
describe('ketahanan kiriman berisi banyak kunjungan', function () {
    it('satu kunjungan bermasalah tidak ikut menggagalkan yang lain', function () {
        $bagus = tokoOffline('Toko Bagus', 'IDNAH202528007101');
        $bukanTanggungan = tokoOffline('Toko Orang Lain', 'IDNAH202528007102');

        tugaskanOffline($bagus, $this->sales);
        tugaskanOffline($bukanTanggungan, $this->salesLain);

        $jawaban = kirimSinkron(
            $this->sales,
            muatanOffline($bukanTanggungan),
            muatanOffline($bagus),
        )->assertOk();

        $jawaban->assertJsonPath('hasil.0.status', 'ditolak')
            ->assertJsonPath('hasil.1.status', 'diterima');

        expect(Kunjungan::count())->toBe(1)
            ->and(Kunjungan::first()->toko_id)->toBe($bagus->id);
    });

    it('membatasi jumlah kunjungan per kiriman', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        $batas = (int) config('visit.offline.maks_per_kiriman');
        $muatan = array_fill(0, $batas + 1, muatanOffline($toko));

        $this->actingAs($this->sales)
            ->postJson(route('kunjungan.sinkron'), ['kunjungans' => $muatan])
            ->assertUnprocessable();
    });
});

// =====================================================================
describe('admin bisa membedakan kunjungan offline', function () {
    it('menandai kunjungan offline di layar rincian periode', function () {
        $toko = tokoOffline();
        tugaskanOffline($toko, $this->sales);

        kirimSinkron($this->sales, muatanOffline($toko))->assertOk();

        $periode = $this->periodeService->periodeBerjalan();

        Livewire\Livewire::actingAs($this->admin)
            ->test(DetailPeriode::class, ['periode' => $periode])
            ->call('bukaSales', $this->sales->id)
            ->assertSee(__('kunjungan.badge_offline'));
    });
});
