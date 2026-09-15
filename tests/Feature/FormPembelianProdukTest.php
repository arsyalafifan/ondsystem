<?php

use App\Enums\HariKunjungan;
use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Statistik\FormPembelianProduk;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\PenugasanToko;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\RoutingBatch;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Rama Dhoni']);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Produk Uji', 'stok' => 1_000, 'harga' => 10_000]);
});

function tokoFp(string $nama, ?User $sales = null): Toko
{
    static $n = 0;
    $n++;

    $toko = Toko::create([
        'kode' => sprintf('TK-FP%04d', $n),
        'asset_id' => sprintf('IDNRH20253327%02d', $n),
        'nama' => $nama,
        'nama_pemilik' => 'Pemilik '.$n,
        'telepon' => '0821347792'.$n,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Uji No. '.$n,
        'latitude' => -1.6654,
        'longitude' => 101.4478,
        'sumber_koordinat' => 'manual',
    ]);

    if ($sales !== null) {
        PenugasanToko::create([
            'toko_id' => $toko->id,
            'sales_id' => $sales->id,
            'hari' => HariKunjungan::Senin,
            'ditugaskan_oleh' => test()->admin->id,
        ]);
    }

    return $toko;
}

/** Pesanan rute yang diantar kendaraan bertanggal $tanggal. */
function pesananFp(Toko $toko, string $tanggal, int $dus, StatusPesanan $status = StatusPesanan::Selesai, int $bonusManual = 0): Pesanan
{
    static $n = 0;
    $n++;

    $pesanan = Pesanan::create([
        'kode' => sprintf('PSN-FP-%04d', $n),
        'toko_id' => $toko->id,
        'wilayah_id' => $toko->wilayah_id,
        'dibuat_oleh' => test()->sales->id,
        'status' => $status,
        'jenis' => JenisPesanan::Normal,
        'tanggal' => $tanggal,
        'total_dus' => $dus,
        'total_nilai' => $dus * 10_000,
        'status_bayar' => 'lunas',
        'tanggal_lunas' => $tanggal,
    ]);

    $pesanan->items()->create(['produk_id' => test()->produk->id, 'jumlah_dus' => $dus, 'harga_satuan' => 10_000, 'subtotal' => $dus * 10_000]);

    if ($bonusManual > 0) {
        $pesanan->items()->create(['produk_id' => test()->produk->id, 'jumlah_dus' => $bonusManual, 'harga_satuan' => 0, 'subtotal' => 0, 'is_bonus' => true]);
    }

    $batch = RoutingBatch::create([
        'kode' => sprintf('RB-FP-%04d', $n), 'tanggal' => $tanggal, 'status' => 'disetujui',
        'total_kendaraan' => 1, 'total_toko' => 1, 'total_dus' => $dus, 'dibuat_oleh' => test()->admin->id,
    ]);

    $kendaraan = Kendaraan::create([
        'routing_batch_id' => $batch->id, 'nomor' => 1, 'nama' => 'Mobil FP '.$n,
        'total_toko' => 1, 'total_dus' => $dus, 'target_dus' => $dus, 'status' => 'selesai', 'tanggal' => $tanggal,
    ]);

    KendaraanStop::create([
        'kendaraan_id' => $kendaraan->id, 'pesanan_id' => $pesanan->id, 'toko_id' => $toko->id,
        'urutan' => 1, 'total_dus' => $dus, 'status' => 'selesai',
    ]);

    return $pesanan;
}

function sheetFp($test): Worksheet
{
    $path = tempnam(sys_get_temp_dir(), 'fp-uji').'.xlsx';
    file_put_contents($path, base64_decode(data_get($test->effects, 'download.content')));
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    return $sheet;
}

it('hanya bisa dibuka admin', function () {
    $this->actingAs($this->sales)->get(route('statistik.form-pembelian-produk'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('statistik.form-pembelian-produk'))->assertOk();
});

it('menjumlahkan dus per toko per hari dalam satu bulan', function () {
    $toko = tokoFp('Berkah Wak Gonjes', $this->sales);
    tokoFp('Sultan Ponsel');

    pesananFp($toko, '2026-09-05', 6);
    pesananFp($toko, '2026-09-05', 3);
    pesananFp($toko, '2026-09-20', 5);
    pesananFp($toko, '2026-09-21', 9, StatusPesanan::Cancel);
    pesananFp($toko, '2026-10-01', 7);

    $rekap = Livewire::actingAs($this->admin)
        ->test(FormPembelianProduk::class, ['mode' => 'bulan', 'bulan' => '2026-09'])
        ->instance()->rekap;

    expect($rekap['bulan'])->toHaveCount(1)
        ->and($rekap['bulan'][0]['label'])->toBe('9.2026')
        ->and($rekap['bulan'][0]['tanggal'])->toHaveCount(30)
        ->and($rekap['baris'])->toHaveCount(2);

    $baris = collect($rekap['baris'])->firstWhere('nama', 'Berkah Wak Gonjes');

    expect($baris['sales'])->toBe('Rama Dhoni')
        ->and($baris['harian'])->toBe(['2026-09-05' => 9, '2026-09-20' => 5])
        ->and($baris['total'])->toBe(14)
        ->and($rekap['total_dus'])->toBe(14)
        // Toko bersales tampil lebih dulu dari toko yang belum ditugaskan.
        ->and($rekap['baris'][1]['nama'])->toBe('Sultan Ponsel')
        ->and($rekap['baris'][1]['total'])->toBe(0);
});

it('tidak menghitung bonus manual', function () {
    $toko = tokoFp('Toko Bonus', $this->sales);
    pesananFp($toko, '2026-09-10', 10, bonusManual: 2);

    $rekap = Livewire::actingAs($this->admin)
        ->test(FormPembelianProduk::class, ['mode' => 'hari', 'tanggal' => '2026-09-10'])
        ->instance()->rekap;

    expect($rekap['baris'][0]['total'])->toBe(10)
        ->and($rekap['bulan'][0]['tanggal'])->toBe(['2026-09-10']);
});

it('rentang lintas bulan dipecah per bulan, tahun berisi seluruh hari', function () {
    tokoFp('Toko A');

    $rentang = Livewire::actingAs($this->admin)
        ->test(FormPembelianProduk::class, ['mode' => 'rentang', 'dariTanggal' => '2026-09-29', 'sampaiTanggal' => '2026-10-02'])
        ->instance()->rekap;

    expect(array_column($rentang['bulan'], 'label'))->toBe(['9.2026', '10.2026'])
        ->and($rentang['bulan'][0]['tanggal'])->toBe(['2026-09-29', '2026-09-30']);

    $tahun = Livewire::actingAs($this->admin)
        ->test(FormPembelianProduk::class, ['mode' => 'tahun', 'tahun' => '2026'])
        ->instance()->rekap;

    expect($tahun['bulan'])->toHaveCount(12)
        ->and(array_sum(array_map(fn ($b) => count($b['tanggal']), $tahun['bulan'])))->toBe(365);
});

it('mode semua mengambil rentang dari data pembelian yang ada', function () {
    $toko = tokoFp('Toko Semua', $this->sales);
    pesananFp($toko, '2026-08-30', 4);
    pesananFp($toko, '2026-09-02', 6);

    $rekap = Livewire::actingAs($this->admin)
        ->test(FormPembelianProduk::class, ['mode' => 'semua'])
        ->instance()->rekap;

    expect($rekap['dari']->toDateString())->toBe('2026-08-30')
        ->and($rekap['sampai']->toDateString())->toBe('2026-09-02')
        ->and($rekap['baris'][0]['total'])->toBe(10);
});

it('mengekspor Excel berformat form dengan header dibekukan', function () {
    $toko = tokoFp('Berkah Wak Gonjes', $this->sales);
    pesananFp($toko, '2026-09-05', 6);

    $test = Livewire::actingAs($this->admin)
        ->test(FormPembelianProduk::class, ['mode' => 'bulan', 'bulan' => '2026-09'])
        ->call('unduhExcel');

    $sheet = sheetFp($test);

    // 8 kolom tetap + 30 tanggal + total = 39 kolom → AM.
    expect($sheet->getCell('A1')->getValue())->toContain('9月终端日进货表')
        ->and($sheet->getCell('A2')->getValue())->toContain('Form Pembelian Produk Harian Outlet halocoko Market')
        ->and($sheet->getCell('A2')->getValue())->toEndWith('bulan September')
        ->and($sheet->getCell('A3')->getValue())->toBe('NO序号')
        ->and($sheet->getCell('B3')->getValue())->toBe("SALES\n业务员")
        ->and($sheet->getCell('H3')->getValue())->toBe("KOORDINAT\n坐标")
        ->and($sheet->getCell('I3')->getValue())->toBe('9.2026')
        ->and($sheet->getCell('I4')->getValue())->toBe(1)
        ->and($sheet->getCell('AL4')->getValue())->toBe(30)
        ->and($sheet->getCell('AM3')->getValue())->toBe("合计\nTotal")
        ->and($sheet->getFreezePane())->toBe('A5')
        ->and($sheet->getCell('B5')->getValue())->toBe('Rama Dhoni')
        ->and($sheet->getCell('G5')->getValue())->toBe($toko->telepon)
        ->and($sheet->getCell('M5')->getValue())->toBe(6)
        ->and($sheet->getCell('L5')->getValue())->toBeNull()
        ->and($sheet->getCell('AM5')->getValue())->toBe(6)
        ->and($sheet->getStyle('A3')->getFill()->getStartColor()->getRGB())->toBe('A9D08E')
        ->and($sheet->getCell('H5')->getHyperlink()->getUrl())->toContain('google.com/maps?q=');
});
