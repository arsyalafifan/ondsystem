<?php

use App\Enums\HariKunjungan;
use App\Enums\JenisFotoKunjungan;
use App\Enums\PeranPengguna;
use App\Enums\SumberFotoKunjungan;
use App\Livewire\Kunjungan\DetailPeriode;
use App\Livewire\Kunjungan\Kunjungi;
use App\Models\PenugasanToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PembacaExif;
use App\Services\Kunjungan\PenugasanTokoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Unggah foto dari galeri — jalan keluar ketika kamera sales bermasalah.
 *
 * Jalur ini sengaja lebih longgar daripada bidikan langsung, jadi yang diuji
 * di sini bukan "apakah bisa masuk" saja, melainkan apakah KELEMAHANNYA
 * tercatat jujur: sumbernya tertulis 'unggah', waktunya diambil dari EXIF
 * berkas (bukan dikarang), dan berkas tanpa EXIF tetap diterima namun
 * ditandai tidak diketahui waktunya.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->service = app(KunjunganService::class);
});

function tokoUnggah(): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-UNG%04d', $n),
        'asset_id' => sprintf('IDNAH20252800%04d', $n),
        'nama' => "Toko Unggah {$n}",
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Unggah',
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);
}

function tugaskanUnggah(Toko $toko, User $sales): void
{
    $sudahAda = PenugasanToko::query()
        ->where('sales_id', $sales->id)
        ->where('hari', HariKunjungan::Senin->value)
        ->pluck('toko_id')
        ->all();

    app(PenugasanTokoService::class)->tetapkan($sales, HariKunjungan::Senin, [...$sudahAda, $toko->id], test()->admin);
}

/** JPEG polos tanpa metadata apa pun — seperti hasil kiriman WhatsApp. */
function jpegPolos(int $lebar = 320, int $tinggi = 240): string
{
    $gambar = imagecreatetruecolor($lebar, $tinggi);
    imagefilledrectangle($gambar, 0, 0, $lebar, $tinggi, imagecolorallocate($gambar, 90, 150, 200));

    ob_start();
    imagejpeg($gambar, null, 85);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return $isi;
}

/**
 * JPEG dengan blok EXIF yang disusun tangan.
 *
 * Dibuat manual, bukan dengan pustaka penulis EXIF, supaya pengujian tidak
 * bergantung pada dependensi tambahan — dan supaya bentuk bita yang dibaca
 * benar-benar bentuk yang ditulis kamera sungguhan.
 *
 * Urutan bita "II" (Intel/little-endian) dipilih agar cocok dengan penanda
 * pack() v/V di bawah. Memakai "MM" tanpa mengganti seluruh pack() ke n/N
 * menghasilkan blok yang ditolak diam-diam oleh exif_read_data().
 */
function jpegBerExif(string $waktu = '2026:09:10 08:30:00', ?array $gps = null): string
{
    $jpeg = jpegPolos();

    $tiffMulai = 8; // panjang header TIFF: "II" + 0x2a + offset IFD0

    $jumlahIfd0 = $gps === null ? 1 : 2;
    $panjangIfd0 = 2 + ($jumlahIfd0 * 12) + 4;
    $offsetSubIfd = $tiffMulai + $panjangIfd0;

    // Panjang SubIFD dihitung dulu lewat susunan sementara, karena offset
    // nilai waktunya baru bisa ditentukan setelah panjang itu diketahui.
    $panjangSubIfd = 2 + 12 + 4;
    $offsetNilaiWaktu = $offsetSubIfd + $panjangSubIfd;

    $subIfd = pack('v', 1)
        .pack('vvVV', 0x9003, 2, strlen($waktu) + 1, $offsetNilaiWaktu) // DateTimeOriginal
        .pack('V', 0);

    $ifd0Entri = pack('vvVV', 0x8769, 4, 1, $offsetSubIfd); // ExifIFDPointer
    $blokGps = '';

    if ($gps !== null) {
        $offsetGpsIfd = $offsetNilaiWaktu + strlen($waktu) + 1;
        $ifd0Entri .= pack('vvVV', 0x8825, 4, 1, $offsetGpsIfd); // GPSInfoIFDPointer

        $jumlahGps = 4; // Ref lintang, lintang, Ref bujur, bujur
        $offsetNilaiGps = $offsetGpsIfd + 2 + ($jumlahGps * 12) + 4;

        $latRasional = pack('VVVVVV', (int) $gps['lat_d'], 1, (int) $gps['lat_m'], 1, (int) $gps['lat_s'], 1);
        $lngRasional = pack('VVVVVV', (int) $gps['lng_d'], 1, (int) $gps['lng_m'], 1, (int) $gps['lng_s'], 1);

        $gpsEntri = pack('vvVa4', 1, 2, 2, $gps['lat_ref']."\0")
            .pack('vvVV', 2, 5, 3, $offsetNilaiGps)
            .pack('vvVa4', 3, 2, 2, $gps['lng_ref']."\0")
            .pack('vvVV', 4, 5, 3, $offsetNilaiGps + strlen($latRasional));

        $blokGps = pack('v', $jumlahGps).$gpsEntri.pack('V', 0).$latRasional.$lngRasional;
    }

    $ifd0 = pack('v', $jumlahIfd0).$ifd0Entri.pack('V', 0);
    $tiff = 'II'.pack('v', 0x2A).pack('V', 8).$ifd0.$subIfd.$waktu."\0".$blokGps;

    $app1 = "Exif\0\0".$tiff;
    $segmen = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

    // Disisipkan tepat setelah penanda awal berkas (SOI).
    return substr($jpeg, 0, 2).$segmen.substr($jpeg, 2);
}

// =====================================================================
describe('pembaca EXIF', function () {
    it('membaca waktu pengambilan dari berkas', function () {
        $hasil = app(PembacaExif::class)->baca(jpegBerExif('2026:09:10 08:30:00'));

        expect($hasil['diambil_at'])->not->toBeNull()
            ->and($hasil['diambil_at']->format('Y-m-d H:i:s'))->toBe('2026-09-10 08:30:00');
    });

    it('membaca titik GPS dan mengubahnya jadi derajat desimal', function () {
        $hasil = app(PembacaExif::class)->baca(jpegBerExif(gps: [
            'lat_d' => 6, 'lat_m' => 10, 'lat_s' => 48, 'lat_ref' => 'S',
            'lng_d' => 106, 'lng_m' => 49, 'lng_s' => 48, 'lng_ref' => 'E',
        ]));

        // 6°10'48" LS = -6.18, 106°49'48" BT = 106.83
        expect(round($hasil['latitude'], 4))->toBe(-6.18)
            ->and(round($hasil['longitude'], 4))->toBe(106.83);
    });

    it('mengembalikan kosong untuk berkas tanpa EXIF, bukan meledak', function () {
        $hasil = app(PembacaExif::class)->baca(jpegPolos());

        expect($hasil['diambil_at'])->toBeNull()
            ->and($hasil['latitude'])->toBeNull()
            ->and($hasil['longitude'])->toBeNull();
    });

    it('mengabaikan tanggal kosong yang ditulis sebagian kamera', function () {
        $hasil = app(PembacaExif::class)->baca(jpegBerExif('0000:00:00 00:00:00'));

        expect($hasil['diambil_at'])->toBeNull();
    });

    it('tidak pernah meledak pada berkas rusak', function () {
        $hasil = app(PembacaExif::class)->baca('ini jelas bukan gambar');

        expect($hasil['diambil_at'])->toBeNull();
    });
});

// =====================================================================
describe('menyimpan foto unggahan', function () {
    it('mencatat sumbernya sebagai unggahan, bukan bidikan kamera', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        $foto = $this->service->simpanFoto(
            kunjungan: $kunjungan,
            jenis: JenisFotoKunjungan::Spanduk,
            isiGambar: jpegBerExif('2026:09:10 08:30:00'),
            sumber: SumberFotoKunjungan::Unggah,
        );

        expect($foto->sumber)->toBe(SumberFotoKunjungan::Unggah)
            ->and($foto->exif_diambil_at->format('Y-m-d H:i'))->toBe('2026-09-10 08:30')
            // Watermark memakai waktu dari berkas, bukan jam server.
            ->and($foto->diambil_at->format('Y-m-d H:i'))->toBe('2026-09-10 08:30');
    });

    it('mengambil lokasi dari berkas ketika ada', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        $foto = $this->service->simpanFoto(
            kunjungan: $kunjungan,
            jenis: JenisFotoKunjungan::Spanduk,
            isiGambar: jpegBerExif(gps: [
                'lat_d' => 6, 'lat_m' => 10, 'lat_s' => 48, 'lat_ref' => 'S',
                'lng_d' => 106, 'lng_m' => 49, 'lng_s' => 48, 'lng_ref' => 'E',
            ]),
            sumber: SumberFotoKunjungan::Unggah,
        );

        expect($foto->punya_lokasi)->toBeTrue()
            ->and(round($foto->latitude, 2))->toBe(-6.18);
    });

    it('menerima berkas tanpa metadata apa pun, apa adanya', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        $foto = $this->service->simpanFoto(
            kunjungan: $kunjungan,
            jenis: JenisFotoKunjungan::Spanduk,
            isiGambar: jpegPolos(),
            sumber: SumberFotoKunjungan::Unggah,
        );

        expect($foto->exif_diambil_at)->toBeNull()
            ->and($foto->waktu_tidak_diketahui)->toBeTrue()
            ->and($foto->punya_lokasi)->toBeFalse()
            // diambil_at tetap terisi (jam server) supaya watermark punya
            // keterangan — kolom exif yang kosong yang menyatakan bedanya.
            ->and($foto->diambil_at)->not->toBeNull();
    });

    /**
     * Titik GPS peramban menyatakan di mana sales berdiri saat mengunggah,
     * bukan di mana fotonya diambil. Memakainya akan mengarang riwayat yang
     * tidak pernah terjadi, jadi untuk unggahan ia harus diabaikan.
     */
    it('tidak memakai lokasi peramban untuk foto unggahan', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        $foto = $this->service->simpanFoto(
            kunjungan: $kunjungan,
            jenis: JenisFotoKunjungan::Spanduk,
            isiGambar: jpegPolos(),
            lat: -6.99,
            lng: 106.99,
            akurasi: 5,
            sumber: SumberFotoKunjungan::Unggah,
        );

        expect($foto->latitude)->toBeNull()
            ->and($foto->longitude)->toBeNull();
    });

    it('bidikan kamera tetap tercatat sebagai kamera dan tanpa data exif', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        // Sengaja memakai berkas yang PUNYA EXIF: jalur kamera tidak boleh
        // membacanya sama sekali, karena jam server lebih dipercaya.
        $foto = $this->service->simpanFoto(
            kunjungan: $kunjungan,
            jenis: JenisFotoKunjungan::Spanduk,
            isiGambar: jpegBerExif('2020:01:01 00:00:00'),
            lat: -6.18,
            lng: 106.83,
            akurasi: 10,
        );

        expect($foto->sumber)->toBe(SumberFotoKunjungan::Kamera)
            ->and($foto->exif_diambil_at)->toBeNull()
            ->and($foto->diambil_at->year)->toBe(CarbonImmutable::now()->year)
            ->and($foto->latitude)->not->toBeNull();
    });
});

// =====================================================================
describe('layar sales', function () {
    it('menyimpan foto yang diunggah lewat layar kunjungan', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);

        $berkas = UploadedFile::fake()->createWithContent('toko.jpg', jpegBerExif('2026:09:10 08:30:00'));

        Livewire::actingAs($this->sales)
            ->test(Kunjungi::class)
            ->call('pilihUnggahan', JenisFotoKunjungan::Spanduk->value)
            ->set('berkasUnggahan', $berkas)
            ->call('unggahFoto')
            ->assertHasNoErrors();

        $foto = $kunjungan->fresh(['fotos'])->fotos->first();

        expect($foto)->not->toBeNull()
            ->and($foto->sumber)->toBe(SumberFotoKunjungan::Unggah)
            ->and($foto->exif_diambil_at->format('Y-m-d H:i'))->toBe('2026-09-10 08:30');
    });

    it('menolak berkas yang bukan gambar', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);
        $this->service->mulai($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(Kunjungi::class)
            ->call('pilihUnggahan', JenisFotoKunjungan::Spanduk->value)
            ->set('berkasUnggahan', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
            ->call('unggahFoto')
            ->assertHasErrors('berkasUnggahan');
    });

    it('menampilkan tombol unggah pada tiap jenis foto', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);
        $this->service->mulai($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(Kunjungi::class)
            ->assertSee(__('kunjungan.unggah_foto'))
            // Pesan kondisi abnormal kameranya ikut dirender, disembunyikan
            // Alpine sampai modul kamera benar-benar melaporkan galat.
            ->assertSee(__('kunjungan.kamera_bermasalah'));
    });
});

// =====================================================================
describe('layar admin', function () {
    it('menandai foto unggahan beserta keterangan waktunya', function () {
        $toko = tokoUnggah();
        tugaskanUnggah($toko, $this->sales);

        $kunjungan = $this->service->mulai($toko, $this->sales);
        $this->service->simpanFoto(
            kunjungan: $kunjungan,
            jenis: JenisFotoKunjungan::Spanduk,
            isiGambar: jpegPolos(),
            sumber: SumberFotoKunjungan::Unggah,
        );

        $periode = $kunjungan->periode;

        Livewire::actingAs($this->admin)
            ->test(DetailPeriode::class, ['periode' => $periode])
            ->set('kunjunganDilihat', $kunjungan->id)
            ->assertSee(__('kunjungan.sumber_unggah'))
            ->assertSee(__('kunjungan.exif_waktu_kosong'));
    });
});
