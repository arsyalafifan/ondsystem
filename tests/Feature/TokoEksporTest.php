<?php

use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Ekspor seluruh toko sebagai Excel — dipakai sebagai checkpoint sebelum
 * mengedit/menambah toko lewat impor: unduh, edit di Excel, unggah
 * kembali lewat "Impor CSV/Excel". Kolomnya karena itu HARUS persis
 * kolom yang dikenali impor (lihat DaftarTokoTest-nya sendiri tidak ada,
 * jadi round-trip diuji langsung di sini lewat mulaiImporCsv()).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
});

/** @return array<int, array<int, mixed>> baris mentah, termasuk header di baris pertama */
function bacaBarisEksporToko($test): array
{
    $konten = base64_decode(data_get($test->effects, 'download.content'));
    $path = tempnam(sys_get_temp_dir(), 'toko-ekspor-uji').'.xlsx';
    file_put_contents($path, $konten);

    $baris = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);

    unlink($path);

    return $baris;
}

it('menolak sales, mengizinkan admin', function () {
    $this->actingAs($this->sales)->get(route('master.toko'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('master.toko'))->assertOk();
});

it('header kolomnya persis sama dengan yang dikenali impor', function () {
    $test = Livewire::actingAs($this->admin)->test(DaftarToko::class)->call('unduhExcel');

    $baris = bacaBarisEksporToko($test);

    expect($baris[0])->toBe([
        'kode', 'nama', 'pemilik', 'nik', 'alamat', 'kelurahan', 'kecamatan',
        'kota', 'kode_pos', 'telepon', 'latitude', 'longitude', 'wilayah', 'asset_id',
    ]);
});

it('berisi seluruh toko dengan datanya masing-masing', function () {
    Toko::create([
        'kode' => 'TK-0001', 'nama' => 'Toko Satu', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Satu No. 1', 'kelurahan' => 'Kel A', 'kecamatan' => 'Kec A',
        'kota' => 'Kota A', 'kode_pos' => '12345', 'telepon' => '081234567890',
        'nama_pemilik' => 'Budi', 'nik_pemilik' => '3171012501900001',
        'latitude' => -6.1751, 'longitude' => 106.8272, 'asset_id' => 'IDNAH202528000001',
        'sumber_koordinat' => 'manual',
    ]);
    Toko::create([
        'kode' => 'TK-0002', 'nama' => 'Toko Dua', 'wilayah_id' => null,
        'alamat' => 'Jl. Dua No. 2',
    ]);

    $test = Livewire::actingAs($this->admin)->test(DaftarToko::class)->call('unduhExcel');
    $baris = bacaBarisEksporToko($test);

    expect($baris)->toHaveCount(3); // header + 2 toko

    expect($baris[1])->toBe([
        'TK-0001', 'Toko Satu', 'Budi', '3171012501900001', 'Jl. Satu No. 1',
        'Kel A', 'Kec A', 'Kota A', '12345', '081234567890',
        '-6.1751', '106.8272', 'Wilayah Satu', 'IDNAH202528000001',
    ]);

    // Toko Dua sengaja tanpa wilayah/koordinat/kontak — kolom kosongnya
    // harus benar-benar kosong (null lewat toArray), bukan string "null".
    expect($baris[2][0])->toBe('TK-0002')
        ->and($baris[2][1])->toBe('Toko Dua')
        ->and($baris[2][12])->toBeNull(); // kolom wilayah
});

it('toko internal (Tanpa Toko, untuk transaksi POS) tidak ikut diekspor', function () {
    Toko::internal();
    Toko::create(['kode' => 'TK-0001', 'nama' => 'Toko Nyata', 'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. Nyata']);

    $test = Livewire::actingAs($this->admin)->test(DaftarToko::class)->call('unduhExcel');
    $baris = bacaBarisEksporToko($test);

    $kodeSemua = array_column(array_slice($baris, 1), 0);

    expect($kodeSemua)->toBe(['TK-0001'])
        ->and($kodeSemua)->not->toContain(Toko::KODE_INTERNAL);
});

it('tidak mengikuti penyaring cari/wilayah yang sedang aktif di layar — selalu seluruh toko', function () {
    $wilayahLain = Wilayah::create(['kode' => 'W2', 'nama' => 'Wilayah Lain']);
    Toko::create(['kode' => 'TK-0001', 'nama' => 'Toko Tersaring', 'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. Satu']);
    Toko::create(['kode' => 'TK-0002', 'nama' => 'Toko Lain', 'wilayah_id' => $wilayahLain->id, 'alamat' => 'Jl. Dua']);

    $test = Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->set('cari', 'Toko Tersaring')
        ->set('filterWilayah', (string) $this->wilayah->id)
        ->call('unduhExcel');

    $baris = bacaBarisEksporToko($test);
    $kodeSemua = array_column(array_slice($baris, 1), 0);

    // Andai ekspornya ikut penyaring, "Toko Lain" (wilayah beda, tidak
    // cocok kata kunci) tidak akan pernah muncul.
    expect($kodeSemua)->toContain('TK-0001', 'TK-0002');
});

it('kolom rawan salah baca Excel (nik, kode pos, telepon, koordinat, asset_id) berformat teks', function () {
    Toko::create([
        'kode' => 'TK-0001', 'nama' => 'Toko Format', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Format', 'kode_pos' => '01234', 'telepon' => '081234567890',
        'nik_pemilik' => '3171012501900001', 'latitude' => -6.1751, 'longitude' => 106.8272,
        'asset_id' => 'IDNAH202528000001', 'sumber_koordinat' => 'manual',
    ]);

    $test = Livewire::actingAs($this->admin)->test(DaftarToko::class)->call('unduhExcel');

    $konten = base64_decode(data_get($test->effects, 'download.content'));
    $path = tempnam(sys_get_temp_dir(), 'toko-ekspor-format').'.xlsx';
    file_put_contents($path, $konten);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    // D=nik, I=kode_pos, J=telepon, K=latitude, L=longitude, N=asset_id.
    foreach (['D', 'I', 'J', 'K', 'L', 'N'] as $kolom) {
        expect($sheet->getStyle("{$kolom}2")->getNumberFormat()->getFormatCode())->toBe(NumberFormat::FORMAT_TEXT);
    }
});

/**
 * Validasi langsung terhadap tujuan fiturnya: berkas yang diekspor bisa
 * diunggah kembali lewat "Impor CSV/Excel" TANPA membuat toko baru atau
 * mengubah data — persis skenario "checkpoint" yang diminta pengguna.
 * Toko dikenali kembali lewat asset_id/kode (sama seperti impor biasa),
 * dan datanya yang diekspor apa adanya tetap idempoten begitu diimpor ulang.
 */
it('berkas hasil ekspor bisa diimpor ulang tanpa membuat toko baru atau mengubah data (round-trip)', function () {
    $toko = Toko::create([
        'kode' => 'TK-0001', 'nama' => 'Toko Checkpoint', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Checkpoint No. 1', 'kelurahan' => 'Kel A', 'kecamatan' => 'Kec A',
        'kota' => 'Kota A', 'kode_pos' => '12345', 'telepon' => '081234567890',
        'nama_pemilik' => 'Budi', 'nik_pemilik' => '3171012501900001',
        'latitude' => -6.1751, 'longitude' => 106.8272, 'asset_id' => 'IDNAH202528000001',
        'sumber_koordinat' => 'manual',
    ]);

    $eksporTest = Livewire::actingAs($this->admin)->test(DaftarToko::class)->call('unduhExcel');
    $kontenEkspor = base64_decode(data_get($eksporTest->effects, 'download.content'));

    $berkasUnggah = UploadedFile::fake()->createWithContent('checkpoint-toko.xlsx', $kontenEkspor);

    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->set('berkasCsv', $berkasUnggah)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    expect(Toko::count())->toBe(1);

    $toko->refresh();

    expect($toko->kode)->toBe('TK-0001')
        ->and($toko->nama)->toBe('Toko Checkpoint')
        ->and($toko->alamat)->toBe('Jl. Checkpoint No. 1')
        ->and($toko->kelurahan)->toBe('Kel A')
        ->and($toko->kode_pos)->toBe('12345')
        ->and($toko->telepon)->toBe('081234567890')
        ->and($toko->nama_pemilik)->toBe('Budi')
        ->and($toko->nik_pemilik)->toBe('3171012501900001')
        ->and((float) $toko->latitude)->toEqualWithDelta(-6.1751, 0.0001)
        ->and((float) $toko->longitude)->toEqualWithDelta(106.8272, 0.0001)
        ->and($toko->asset_id)->toBe('IDNAH202528000001')
        ->and($toko->wilayah_id)->toBe($this->wilayah->id);

    @unlink($berkasSementara);
});
