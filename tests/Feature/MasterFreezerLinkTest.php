<?php

use App\Enums\HariKunjungan;
use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarToko;
use App\Livewire\Toko\LengkapiData;
use App\Models\Freezer;
use App\Models\PenugasanToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * IDN (nomor stiker freezer) di Master Toko, Lengkapi Data Toko, dan impor
 * Excel/CSV semuanya harus merujuk ke Master Freezer — bukan lagi teks
 * bebas. Lihat App\Livewire\Concerns\PunyaPemilihFreezer.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
});

it('Master Toko menerima IDN yang terdaftar di Master Freezer, dan tipenya ikut otomatis', function () {
    Freezer::create(['depot_id' => $this->depot->id, 'idn' => 'IDN-OK-1', 'tipe' => 'SD-200']);

    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('buatBaru')
        ->set('kode', 'TK-IDN-A')
        ->set('nama', 'Toko IDN A')
        ->set('alamat', 'Jl. Uji')
        ->set('wilayahId', $this->wilayah->id)
        ->set('assetId', 'IDN-OK-1')
        ->assertSet('freezerTipe', 'SD-200')
        ->call('simpan')
        ->assertHasNoErrors();

    $toko = Toko::where('kode', 'TK-IDN-A')->firstOrFail();
    expect($toko->asset_id)->toBe('IDN-OK-1')
        ->and($toko->freezer_tipe)->toBe('SD-200');
});

it('Master Toko menolak IDN yang tidak terdaftar di Master Freezer', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('buatBaru')
        ->set('kode', 'TK-IDN-B')
        ->set('nama', 'Toko IDN B')
        ->set('alamat', 'Jl. Uji')
        ->set('wilayahId', $this->wilayah->id)
        ->set('assetId', 'IDN-KARANGAN')
        ->call('simpan')
        ->assertHasErrors('assetId');

    expect(Toko::where('kode', 'TK-IDN-B')->exists())->toBeFalse();
});

it('Master Toko membiarkan IDN lama yang sudah tidak terdaftar tetap tersimpan kalau tidak disentuh, tapi menampilkan peringatan', function () {
    $toko = Toko::create([
        'kode' => 'TK-IDN-C', 'nama' => 'Toko Lama', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Lama', 'asset_id' => 'IDN-USANG',
    ]);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('sunting', $toko->id)
        ->assertSet('assetId', 'IDN-USANG');

    expect($komponen->instance()->peringatanIdn)->toContain('IDN-USANG');

    // Field lain diubah, IDN-nya sendiri tidak disentuh sama sekali.
    $komponen->set('nama', 'Toko Lama Diperbarui')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($toko->fresh()->nama)->toBe('Toko Lama Diperbarui')
        ->and($toko->fresh()->asset_id)->toBe('IDN-USANG');
});

it('Lengkapi Data Toko menerima IDN terdaftar dan menolak yang tidak terdaftar', function () {
    Freezer::create(['depot_id' => $this->depot->id, 'idn' => 'IDN-SALES-1', 'tipe' => 'CF-300']);

    $toko = Toko::create([
        'kode' => 'TK-IDN-D', 'nama' => 'Toko Sales', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Awal', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
    ]);
    PenugasanToko::create([
        'toko_id' => $toko->id, 'sales_id' => $this->sales->id,
        'hari' => HariKunjungan::Senin, 'ditugaskan_oleh' => $this->admin->id,
    ]);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set('assetId', 'IDN-TIDAK-ADA')
        ->set('alamat', 'Jl. Baru')
        ->set('telepon', '081234500001')
        ->call('simpan')
        ->assertHasErrors('assetId');

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set('assetId', 'IDN-SALES-1')
        ->set('alamat', 'Jl. Baru')
        ->set('telepon', '081234500001')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($toko->fresh()->asset_id)->toBe('IDN-SALES-1');
});

/** Mengunggah satu baris impor lewat mulaiImporCsv()/lanjutkanImporCsv(). */
function importBarisIdn(string $idnMentah): array
{
    $isi = "kode,nama,alamat,wilayah,asset_id\nTK-IMPIDN,Toko Impor IDN,Jl. Impor,Wilayah Satu,{$idnMentah}\n";
    $berkas = UploadedFile::fake()->createWithContent('impor-idn.csv', $isi);

    $hasil = Livewire::actingAs(test()->admin)
        ->test(DaftarToko::class)
        ->set('berkasCsv', $berkas)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv')
        ->get('hasilImpor');

    return [Toko::where('kode', 'TK-IMPIDN')->firstOrFail(), $hasil];
}

it('impor mengenali IDN yang sudah terdaftar di Master Freezer', function () {
    Freezer::create(['depot_id' => $this->depot->id, 'idn' => 'IDN-IMP-OK', 'tipe' => 'SD-200']);

    [$toko, $hasil] = importBarisIdn('IDN-IMP-OK');

    expect($toko->asset_id)->toBe('IDN-IMP-OK')
        ->and($hasil['catatan'])->toHaveCount(0);
});

it('impor membiarkan baris tetap tersimpan meski IDN-nya tidak terdaftar, dan mencatatnya sebagai peringatan', function () {
    [$toko, $hasil] = importBarisIdn('IDN-TIDAK-TERDAFTAR');

    expect($toko->asset_id)->toBeNull()
        ->and($toko->nama)->toBe('Toko Impor IDN')
        ->and($hasil['catatan'])->toHaveCount(1)
        ->and($hasil['catatan'][0])->toContain('IDN-TIDAK-TERDAFTAR');
});
