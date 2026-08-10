<?php

use App\Enums\JenisFotoKunjungan;
use App\Enums\PeranPengguna;
use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\PenugasanSales;
use App\Models\PeriodeKunjungan;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PenguraiQr;
use App\Services\Kunjungan\PenugasanService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Satu']);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Dua']);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $this->service = app(KunjunganService::class);
    $this->periodeService = app(PeriodeKunjunganService::class);
    $this->penugasanService = app(PenugasanService::class);
});

function buatToko(string $assetId = 'IDNAH202528004381', string $nama = 'Toko Uji'): Toko
{
    return Toko::create([
        'kode' => 'TK-'.substr($assetId, -4),
        'asset_id' => $assetId,
        'freezer_tipe' => 'SD-280',
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);
}

function tugaskan(Toko $toko, User $sales): void
{
    PenugasanSales::create([
        'sales_id' => $sales->id,
        'toko_id' => $toko->id,
        'bulan' => CarbonImmutable::today()->startOfMonth()->toDateString(),
        'ditugaskan_oleh' => test()->admin->id,
    ]);
}

/** Gambar JPEG kecil, sebagai pengganti bidikan kamera. */
function gambarUji(int $lebar = 640, int $tinggi = 480): string
{
    $gambar = imagecreatetruecolor($lebar, $tinggi);
    imagefilledrectangle($gambar, 0, 0, $lebar, $tinggi, imagecolorallocate($gambar, 120, 160, 200));

    ob_start();
    imagejpeg($gambar, null, 85);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return $isi;
}

function lengkapiFoto(Kunjungan $kunjungan): void
{
    foreach (JenisFotoKunjungan::urut() as $jenis) {
        test()->service->simpanFoto($kunjungan, $jenis, gambarUji(), -6.18, 106.83, 12);
    }

    $kunjungan->load('fotos');
}

// =====================================================================
describe('pengurai QR freezer', function () {
    it('mengambil nomor aset dari isi QR berlabel Mandarin', function () {
        $isi = "客户名称：IDN Halocoko\n资产编号：IDNAH202528004381\n产品型号：SD-280";

        $hasil = app(PenguraiQr::class)->urai($isi);

        expect($hasil)->not->toBeNull()
            ->and($hasil->assetId)->toBe('IDNAH202528004381')
            ->and($hasil->namaPelanggan)->toBe('IDN Halocoko')
            ->and($hasil->model)->toBe('SD-280');
    });

    it('menerima titik dua biasa dan pemisah baris yang berbeda', function () {
        $isi = "客户名称:IDN Halocoko\r\n资产编号: IDNAH202528004381\r\n产品型号:SD-280";

        expect(app(PenguraiQr::class)->urai($isi)?->assetId)->toBe('IDNAH202528004381');
    });

    it('menerima QR yang hanya berisi nomor asetnya saja', function () {
        expect(app(PenguraiQr::class)->urai('IDNAH202528004381')?->assetId)->toBe('IDNAH202528004381');
    });

    it('menyeragamkan huruf besar dan membuang spasi', function () {
        expect(app(PenguraiQr::class)->urai('资产编号：idnah 2025 2800 4381')?->assetId)
            ->toBe('IDNAH202528004381');
    });

    it('menolak isi yang bukan QR freezer', function () {
        expect(app(PenguraiQr::class)->urai('https://contoh.test/apa-saja'))->toBeNull()
            ->and(app(PenguraiQr::class)->urai(''))->toBeNull();
    });
});

// =====================================================================
describe('periode mingguan', function () {
    it('membuka periode Senin sampai Sabtu', function () {
        // Rabu, 5 Agustus 2026.
        $periode = $this->periodeService->periodeUntuk(CarbonImmutable::parse('2026-08-05'));

        expect($periode->tanggal_mulai->toDateString())->toBe('2026-08-03')   // Senin
            ->and($periode->tanggal_selesai->toDateString())->toBe('2026-08-08') // Sabtu
            ->and($periode->status)->toBe('berjalan');
    });

    it('memakai periode yang sama untuk seluruh hari dalam pekan itu', function () {
        $senin = $this->periodeService->periodeUntuk(CarbonImmutable::parse('2026-08-03'));
        $sabtu = $this->periodeService->periodeUntuk(CarbonImmutable::parse('2026-08-08'));

        expect($sabtu->id)->toBe($senin->id)
            ->and(PeriodeKunjungan::count())->toBe(1);
    });

    it('membuka periode baru pada Senin berikutnya', function () {
        $pekanIni = $this->periodeService->periodeUntuk(CarbonImmutable::parse('2026-08-05'));
        $pekanDepan = $this->periodeService->periodeUntuk(CarbonImmutable::parse('2026-08-10'));

        expect($pekanDepan->id)->not->toBe($pekanIni->id)
            ->and(PeriodeKunjungan::count())->toBe(2);
    });

    it('menyalin jumlah tanggungan sebagai target saat periode dibuka', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $periode = $this->periodeService->periodeBerjalan();
        $baris = $periode->periodeSales()->where('sales_id', $this->sales->id)->first();

        expect($baris->target_toko)->toBe(1);
    });

    it('menyimpan periode lama, bukan menghapusnya', function () {
        $lama = $this->periodeService->periodeUntuk(CarbonImmutable::today()->subWeeks(2));
        $this->periodeService->periodeBerjalan();
        $this->periodeService->tutupPeriodeLama();

        expect(PeriodeKunjungan::find($lama->id))->not->toBeNull()
            ->and($lama->fresh()->status)->toBe('selesai');
    });
});

// =====================================================================
describe('aturan kunjungan', function () {
    it('mengenali toko dari QR dan memulai kunjungan', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulaiDariQr(
            "客户名称：IDN Halocoko\n资产编号：IDNAH202528004381\n产品型号：SD-280",
            $this->sales,
        );

        expect($kunjungan->toko_id)->toBe($toko->id)
            ->and($kunjungan->sales_id)->toBe($this->sales->id)
            ->and($kunjungan->status)->toBe(StatusKunjungan::Berjalan)
            ->and($kunjungan->asset_id_terpindai)->toBe('IDNAH202528004381');
    });

    it('menolak nomor aset yang tidak terdaftar', function () {
        expect(fn () => $this->service->mulaiDariQr('资产编号：IDNAH999999999999', $this->sales))
            ->toThrow(RuntimeException::class);
    });

    it('menolak toko yang bukan tanggungan sales itu', function () {
        $toko = buatToko();
        tugaskan($toko, $this->salesLain);

        expect(fn () => $this->service->mulai($toko, $this->sales))
            ->toThrow(RuntimeException::class);

        expect(Kunjungan::count())->toBe(0);
    });

    it('menolak toko yang sudah dikunjungi sales lain minggu ini', function () {
        $toko = buatToko();
        // Ditugaskan ke dua sales lewat basis data langsung, meniru keadaan
        // data lama sebelum aturan satu-toko-satu-sales diberlakukan.
        tugaskan($toko, $this->sales);
        PenugasanSales::withoutEvents(fn () => PenugasanSales::insert([
            'sales_id' => $this->salesLain->id,
            'toko_id' => $toko->id,
            'bulan' => CarbonImmutable::today()->startOfMonth()->toDateString(),
            'ditugaskan_oleh' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $pertama = $this->service->mulai($toko, $this->sales);
        lengkapiFoto($pertama);
        $this->service->selesaikan($pertama);

        expect(fn () => $this->service->mulai($toko, $this->salesLain))
            ->toThrow(RuntimeException::class);

        expect(Kunjungan::count())->toBe(1);
    });

    it('melanjutkan kunjungan sendiri yang belum tuntas, bukan membuat yang baru', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $pertama = $this->service->mulai($toko, $this->sales);
        $kedua = $this->service->mulai($toko, $this->sales);

        expect($kedua->id)->toBe($pertama->id)
            ->and(Kunjungan::count())->toBe(1);
    });

    it('membolehkan toko yang sama dikunjungi lagi pada minggu berikutnya', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $pertama = $this->service->mulai($toko, $this->sales);
        lengkapiFoto($pertama);
        $this->service->selesaikan($pertama);

        // Maju ke pekan berikutnya.
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addWeek());

        $kedua = $this->service->mulai($toko, $this->sales);

        expect($kedua->id)->not->toBe($pertama->id)
            ->and($kedua->periode_kunjungan_id)->not->toBe($pertama->periode_kunjungan_id);

        CarbonImmutable::setTestNow();
    });
});

// =====================================================================
describe('foto bukti', function () {
    it('menolak penyelesaian sebelum keenam foto lengkap', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        $this->service->simpanFoto($kunjungan, JenisFotoKunjungan::SalesDepanToko, gambarUji());

        expect(fn () => $this->service->selesaikan($kunjungan->fresh()))
            ->toThrow(RuntimeException::class);

        expect($kunjungan->fresh()->status)->toBe(StatusKunjungan::Berjalan);
    });

    it('menyelesaikan kunjungan setelah keenam foto terkumpul', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        lengkapiFoto($kunjungan);

        $this->service->selesaikan($kunjungan, -6.18, 106.83, 10, 'Pemilik ramah');

        $kunjungan->refresh();

        expect($kunjungan->status)->toBe(StatusKunjungan::Selesai)
            ->and($kunjungan->fotos)->toHaveCount(6)
            ->and($kunjungan->selesai_at)->not->toBeNull()
            ->and($kunjungan->catatan_sales)->toBe('Pemilik ramah');
    });

    it('memberi watermark dan menyimpan waktu pengambilan dari jam server', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        $foto = $this->service->simpanFoto($kunjungan, JenisFotoKunjungan::Spanduk, gambarUji(), -6.18, 106.83, 8);

        Storage::disk('public')->assertExists($foto->path);

        // Waktu berasal dari server, bukan dari perangkat sales, sehingga
        // tidak bisa dimundurkan dengan menyetel jam ponsel.
        expect($foto->diambil_at->diffInSeconds(now()))->toBeLessThan(5)
            ->and($foto->latitude)->toBe(-6.18)
            ->and($foto->ukuran_byte)->toBeGreaterThan(0);
    });

    it('memperkecil foto yang lebih lebar dari batas', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        $foto = $this->service->simpanFoto($kunjungan, JenisFotoKunjungan::Spanduk, gambarUji(2400, 1800));

        expect($foto->lebar)->toBe((int) config('visit.foto.lebar_maks'));
    });

    it('mengganti foto lama ketika jenis yang sama diambil ulang', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        $pertama = $this->service->simpanFoto($kunjungan, JenisFotoKunjungan::Spanduk, gambarUji());
        $kedua = $this->service->simpanFoto($kunjungan->fresh(), JenisFotoKunjungan::Spanduk, gambarUji());

        expect($kunjungan->fresh()->fotos)->toHaveCount(1)
            ->and($kedua->path)->not->toBe($pertama->path);

        Storage::disk('public')->assertMissing($pertama->path);
    });

    it('mencatat jarak antara titik foto dan koordinat toko', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        lengkapiFoto($kunjungan);

        // Sekitar 1,1 km dari titik toko.
        $this->service->selesaikan($kunjungan, -6.19, 106.83, 10);

        $kunjungan->refresh();

        expect($kunjungan->jarak_dari_toko_m)->toBeGreaterThan(900)
            ->and($kunjungan->lokasi_mencurigakan)->toBeTrue();
    });
});

// =====================================================================
describe('toko tutup', function () {
    it('mengirim laporan tutup untuk ditinjau admin', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        $this->service->ajukanTokoTutup($kunjungan, 'Rolling door terkunci', -6.18, 106.83);

        expect($kunjungan->fresh()->status)->toBe(StatusKunjungan::TutupDiajukan);
    });

    it('menolak laporan tutup tanpa keterangan', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        expect(fn () => $this->service->ajukanTokoTutup($kunjungan, '   '))
            ->toThrow(RuntimeException::class);
    });

    it('mengeluarkan toko dari target setelah admin membenarkan', function () {
        $tokoA = buatToko('IDNAH202528000001', 'Toko A');
        $tokoB = buatToko('IDNAH202528000002', 'Toko B');
        tugaskan($tokoA, $this->sales);
        tugaskan($tokoB, $this->sales);

        $periode = $this->periodeService->periodeBerjalan();
        $this->periodeService->segarkanTarget($periode);

        $kunjungan = $this->service->mulai($tokoA, $this->sales);
        $this->service->ajukanTokoTutup($kunjungan, 'Tutup permanen');
        $this->service->setujuiTokoTutup($kunjungan->fresh(), $this->admin, 'Sudah dicek');

        $baris = $periode->periodeSales()->with('kunjungans')->where('sales_id', $this->sales->id)->first();

        expect($baris->progres['target'])->toBe(2)
            // Penyebutnya berkurang, jadi menyelesaikan satu toko lagi
            // sudah menghasilkan 100%.
            ->and($baris->progres['target_efektif'])->toBe(1)
            ->and($baris->progres['tutup'])->toBe(1);
    });

    it('mengembalikan toko ke daftar wajib kunjung ketika laporan ditolak', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        $this->service->ajukanTokoTutup($kunjungan, 'Sepertinya tutup');
        $this->service->tolakTokoTutup($kunjungan->fresh(), $this->admin, 'Tetangga bilang buka sore');

        expect($kunjungan->fresh()->status)->toBe(StatusKunjungan::TutupDitolak);

        // Sales bisa memulai lagi kunjungan ke toko yang sama.
        $ulang = $this->service->mulai($toko, $this->sales);

        expect($ulang->status)->toBe(StatusKunjungan::Berjalan)
            ->and(Kunjungan::count())->toBe(1);
    });
});

// =====================================================================
describe('penugasan toko', function () {
    it('menolak penugasan melebihi batas per sales', function () {
        $tokoIds = [];

        for ($i = 1; $i <= 3; $i++) {
            $tokoIds[] = buatToko(sprintf('IDNAH20252800%04d', $i), "Toko {$i}")->id;
        }

        config(['visit.maks_toko_per_sales' => 2]);

        expect(fn () => $this->penugasanService->tetapkan(
            $this->sales, $tokoIds, CarbonImmutable::today()->toDateString(), $this->admin,
        ))->toThrow(RuntimeException::class);

        expect(PenugasanSales::count())->toBe(0);
    });

    it('mencegah satu toko dipegang dua sales pada bulan yang sama', function () {
        $toko = buatToko();
        $bulan = CarbonImmutable::today()->toDateString();

        $this->penugasanService->tetapkan($this->sales, [$toko->id], $bulan, $this->admin);
        $hasil = $this->penugasanService->tetapkan($this->salesLain, [$toko->id], $bulan, $this->admin);

        expect($hasil['ditambah'])->toBe(0)
            ->and($hasil['ditolak'])->toHaveCount(1)
            ->and(PenugasanSales::where('toko_id', $toko->id)->count())->toBe(1)
            ->and(PenugasanSales::where('toko_id', $toko->id)->value('sales_id'))->toBe($this->sales->id);
    });

    it('mengganti daftar lama saat penugasan disimpan ulang', function () {
        $a = buatToko('IDNAH202528000001', 'Toko A');
        $b = buatToko('IDNAH202528000002', 'Toko B');
        $c = buatToko('IDNAH202528000003', 'Toko C');
        $bulan = CarbonImmutable::today()->toDateString();

        $this->penugasanService->tetapkan($this->sales, [$a->id, $b->id], $bulan, $this->admin);
        $hasil = $this->penugasanService->tetapkan($this->sales, [$b->id, $c->id], $bulan, $this->admin);

        expect($hasil['ditambah'])->toBe(1)
            ->and($hasil['dihapus'])->toBe(1)
            ->and(PenugasanSales::where('sales_id', $this->sales->id)->pluck('toko_id')->sort()->values()->all())
            ->toBe([$b->id, $c->id]);
    });

    it('menyembunyikan toko yang sudah dipegang sales lain dari daftar pilihan', function () {
        $a = buatToko('IDNAH202528000001', 'Toko A');
        $b = buatToko('IDNAH202528000002', 'Toko B');
        $bulan = CarbonImmutable::today()->toDateString();

        $this->penugasanService->tetapkan($this->salesLain, [$a->id], $bulan, $this->admin);

        $tersedia = $this->penugasanService->tokoTersedia($bulan, $this->sales->id)->pluck('id')->all();

        expect($tersedia)->toContain($b->id)
            ->and($tersedia)->not->toContain($a->id);
    });

    it('menyalin penugasan ke bulan berikutnya', function () {
        $toko = buatToko();
        $bulanIni = CarbonImmutable::today()->startOfMonth();

        $this->penugasanService->tetapkan($this->sales, [$toko->id], $bulanIni->toDateString(), $this->admin);
        $jumlah = $this->penugasanService->salinKeBulan(
            $bulanIni->toDateString(), $bulanIni->addMonth()->toDateString(), $this->admin,
        );

        expect($jumlah)->toBe(1)
            ->and(PenugasanSales::count())->toBe(2);
    });
});

// =====================================================================
describe('halaman kunjungan', function () {
    it('menampilkan halaman admin', function (string $rute) {
        $this->actingAs($this->admin)->get(route($rute))->assertOk();
    })->with(['kunjungan.periode', 'kunjungan.penugasan']);

    it('menampilkan rincian periode', function () {
        $toko = buatToko();
        tugaskan($toko, $this->sales);

        $periode = $this->periodeService->periodeBerjalan();
        $this->periodeService->segarkanTarget($periode);

        $this->actingAs($this->admin)
            ->get(route('kunjungan.periode.lihat', $periode))
            ->assertOk()
            ->assertSee($periode->kode)
            ->assertSee($this->sales->name);
    });

    it('menampilkan halaman sales', function (string $rute) {
        $this->actingAs($this->sales)->get(route($rute))->assertOk();
    })->with(['kunjungan.tugas', 'kunjungan.kunjungi']);

    it('menutup halaman sales dari admin dan sebaliknya', function () {
        $this->actingAs($this->admin)->get(route('kunjungan.tugas'))->assertForbidden();
        $this->actingAs($this->sales)->get(route('kunjungan.periode'))->assertForbidden();
        $this->actingAs($this->sales)->get(route('kunjungan.penugasan'))->assertForbidden();
    });

    it('menampilkan toko tanggungan pada halaman tugas sales', function () {
        $toko = buatToko(nama: 'Toko Tanggungan');
        tugaskan($toko, $this->sales);

        $this->actingAs($this->sales)
            ->get(route('kunjungan.tugas'))
            ->assertOk()
            ->assertSee('Toko Tanggungan');
    });
});
