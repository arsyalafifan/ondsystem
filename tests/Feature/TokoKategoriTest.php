<?php

use App\Enums\KategoriToko;
use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * Kategori toko (Sekolah, Perusahaan, Pemerintah, Toko, Lainnya) — lewat
 * formulir Master Toko dan lewat impor CSV/Excel (case-insensitif).
 * Ekspornya sendiri sudah diuji langsung di TokoEksporTest.php (bagian
 * dari round-trip checkpoint impor↔ekspor).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
});

it('formulir bisa menyimpan toko tanpa kategori (opsional)', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('buatBaru')
        ->set('kode', 'TK-KAT-A')
        ->set('nama', 'Toko Tanpa Kategori')
        ->set('alamat', 'Jl. Uji')
        ->set('wilayahId', $this->wilayah->id)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Toko::where('kode', 'TK-KAT-A')->first()->kategori)->toBeNull();
});

it('formulir menyimpan kategori yang dipilih', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('buatBaru')
        ->set('kode', 'TK-KAT-B')
        ->set('nama', 'Toko Sekolah')
        ->set('alamat', 'Jl. Uji')
        ->set('wilayahId', $this->wilayah->id)
        ->set('kategori', KategoriToko::Sekolah->value)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Toko::where('kode', 'TK-KAT-B')->first()->kategori)->toBe(KategoriToko::Sekolah);
});

it('menyunting toko memuat kategorinya, dan bisa dikosongkan lagi', function () {
    $toko = Toko::create([
        'kode' => 'TK-KAT-C', 'nama' => 'Toko Perusahaan', 'kategori' => 'perusahaan',
        'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. Uji',
    ]);

    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('sunting', $toko->id)
        ->assertSet('kategori', 'perusahaan')
        ->set('kategori', '')
        ->call('simpan');

    expect($toko->fresh()->kategori)->toBeNull();
});

it('menolak nilai kategori yang bukan salah satu pilihan', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->call('buatBaru')
        ->set('kode', 'TK-KAT-D')
        ->set('nama', 'Toko Aneh')
        ->set('alamat', 'Jl. Uji')
        ->set('wilayahId', $this->wilayah->id)
        ->set('kategori', 'klinik')
        ->call('simpan')
        ->assertHasErrors('kategori');
});

/** Mengunggah satu baris impor lewat mulaiImporCsv()/lanjutkanImporCsv(). */
function importBarisKategori(string $kategoriMentah): Toko
{
    $isi = "kode,nama,alamat,wilayah,kategori\nTK-IMPKAT,Toko Impor Kategori,Jl. Impor,Wilayah Satu,{$kategoriMentah}\n";
    $berkas = UploadedFile::fake()->createWithContent('impor-kategori.csv', $isi);

    Livewire::actingAs(test()->admin)
        ->test(DaftarToko::class)
        ->set('berkasCsv', $berkas)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    return Toko::where('kode', 'TK-IMPKAT')->firstOrFail();
}

it('impor mengenali kategori tanpa peduli huruf besar/kecil', function () {
    expect(importBarisKategori('Sekolah')->kategori)->toBe(KategoriToko::Sekolah);

    Toko::where('kode', 'TK-IMPKAT')->delete();
    expect(importBarisKategori('PERUSAHAAN')->kategori)->toBe(KategoriToko::Perusahaan);

    Toko::where('kode', 'TK-IMPKAT')->delete();
    expect(importBarisKategori('pemerintah')->kategori)->toBe(KategoriToko::Pemerintah);

    Toko::where('kode', 'TK-IMPKAT')->delete();
    expect(importBarisKategori('lAiNnYa')->kategori)->toBe(KategoriToko::Lainnya);
});

it('impor membiarkan baris tetap tersimpan meski kategorinya tidak dikenal, dan mencatatnya', function () {
    $isi = "kode,nama,alamat,wilayah,kategori\nTK-IMPKAT,Toko Impor Kategori,Jl. Impor,Wilayah Satu,Klinik\n";
    $berkas = UploadedFile::fake()->createWithContent('impor-kategori-salah.csv', $isi);

    $hasil = Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->set('berkasCsv', $berkas)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv')
        ->get('hasilImpor');

    $toko = Toko::where('kode', 'TK-IMPKAT')->firstOrFail();

    expect($toko->kategori)->toBeNull()
        ->and($hasil['catatan'])->toHaveCount(1)
        ->and($hasil['catatan'][0])->toContain('Klinik');
});

it('impor tanpa kolom kategori tidak menghapus kategori yang sudah tersimpan', function () {
    $toko = Toko::create([
        'kode' => 'TK-IMPKAT', 'nama' => 'Toko Lama', 'kategori' => 'sekolah',
        'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. Lama',
    ]);

    // Berkas pembaruan sengaja TANPA kolom kategori sama sekali — mensimulasikan
    // admin yang mengedit sebagian kolom saja lewat template lama.
    $isi = "kode,nama,alamat,wilayah\nTK-IMPKAT,Toko Lama Diperbarui,Jl. Lama,Wilayah Satu\n";
    $berkas = UploadedFile::fake()->createWithContent('impor-tanpa-kategori.csv', $isi);

    Livewire::actingAs($this->admin)
        ->test(DaftarToko::class)
        ->set('berkasCsv', $berkas)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    expect($toko->fresh()->kategori)->toBe(KategoriToko::Sekolah)
        ->and($toko->fresh()->nama)->toBe('Toko Lama Diperbarui');
});
