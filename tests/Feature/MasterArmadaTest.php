<?php

use App\Enums\JenisArmada;
use App\Enums\PeranPengguna;
use App\Enums\StatusArmada;
use App\Livewire\Master\DaftarArmada;
use App\Models\Armada;
use App\Models\Depot;
use App\Models\User;
use App\Support\DepotContext;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * Master Armada: katalog unit kendaraan (plat, jenis, status perawatan)
 * untuk SEMUA gudang — belum ditautkan ke routing sama sekali, lihat
 * App\Models\Armada.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

it('halaman master armada hanya bisa dibuka pengguna yang berhak', function () {
    $this->get(route('master.armada'))->assertRedirect(route('masuk'));

    $this->actingAs($this->admin)
        ->get(route('master.armada'))
        ->assertOk()
        ->assertSee(__('master.judul_armada'));
});

it('bisa menambah armada baru dan menolak plat duplikat', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->call('buatBaru')
        ->set('plat', 'B 1234 CD')
        ->set('jenis', JenisArmada::Berpendingin->value)
        ->set('status', StatusArmada::Normal->value)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertDispatched('notifikasi');

    expect(Armada::where('plat', 'B 1234 CD')->exists())->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->call('buatBaru')
        ->set('plat', 'b 1234 cd')
        ->set('jenis', JenisArmada::BakTerbuka->value)
        ->call('simpan')
        ->assertHasErrors(['plat']);
});

it('merapikan plat jadi huruf besar', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->call('buatBaru')
        ->set('plat', ' b 5678 ef ')
        ->set('jenis', JenisArmada::Berpendingin->value)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Armada::first()->plat)->toBe('B 5678 EF');
});

it('bisa dilihat dan diedit dari gudang mana pun', function () {
    $armada = Armada::create(['plat' => 'B 9999 XX', 'jenis' => JenisArmada::Berpendingin, 'status' => StatusArmada::Normal]);

    $depotLain = Depot::factory()->create(['kode' => 'DEPOTLAIN']);
    $adminLain = DepotContext::jalankanSebagai($depotLain, fn () => User::factory()->create(['role' => PeranPengguna::Admin]));

    Livewire::actingAs($adminLain)
        ->test(DaftarArmada::class)
        ->assertSee('B 9999 XX')
        ->call('sunting', $armada->id)
        ->assertSet('plat', 'B 9999 XX')
        ->set('status', StatusArmada::SedangDiperbaiki->value)
        ->call('simpan')
        ->assertHasNoErrors();

    expect($armada->fresh()->status)->toBe(StatusArmada::SedangDiperbaiki);
});

it('bisa menghapus armada', function () {
    $armada = Armada::create(['plat' => 'B 1111 ZZ', 'jenis' => JenisArmada::BakTerbuka, 'status' => StatusArmada::Normal]);

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->call('hapus', $armada->id)
        ->assertDispatched('notifikasi');

    expect(Armada::find($armada->id))->toBeNull();
});

it('bisa mencari dan memfilter armada', function () {
    Armada::create(['plat' => 'B 1111 AA', 'jenis' => JenisArmada::Berpendingin, 'status' => StatusArmada::Normal]);
    Armada::create(['plat' => 'B 2222 BB', 'jenis' => JenisArmada::BakTerbuka, 'status' => StatusArmada::MenungguPerbaikan]);

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->set('cari', '1111')
        ->assertSee('B 1111 AA')
        ->assertDontSee('B 2222 BB')
        ->set('cari', '')
        ->set('filterJenis', JenisArmada::BakTerbuka->value)
        ->assertSee('B 2222 BB')
        ->assertDontSee('B 1111 AA')
        ->set('filterJenis', '')
        ->set('filterStatus', StatusArmada::MenungguPerbaikan->value)
        ->assertSee('B 2222 BB')
        ->assertDontSee('B 1111 AA');
});

it('bisa mengunduh berkas contoh dan ekspor excel', function () {
    Armada::create(['plat' => 'B 1000 EX', 'jenis' => JenisArmada::Berpendingin, 'status' => StatusArmada::Normal]);

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->call('unduhContohExcel')
        ->assertFileDownloaded('contoh-import-kendaraan.xlsx');

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->call('unduhExcel')
        ->assertFileDownloaded('kendaraan-'.now()->format('Y-m-d').'.xlsx');
});

it('bisa mengimpor kendaraan dari csv bertahap dan memperbarui data yang sudah ada', function () {
    $kontenCsv = "plat,jenis,status\n".
        "B 1001 IM,berpendingin,normal\n".
        "B 1002 IM,bak_terbuka,menunggu_perbaikan\n";

    $file = UploadedFile::fake()->createWithContent('kendaraan.csv', $kontenCsv);

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->assertHasNoErrors()
        ->assertSet('imporBerjalan', true)
        ->call('lanjutkanImporCsv')
        ->assertSet('imporBerjalan', false);

    expect(Armada::where('plat', 'B 1001 IM')->first()?->jenis)->toBe(JenisArmada::Berpendingin)
        ->and(Armada::where('plat', 'B 1002 IM')->first()?->status)->toBe(StatusArmada::MenungguPerbaikan);

    // Re-import untuk update jenis & status B 1001 IM.
    $kontenUpdate = "plat,jenis,status\nB 1001 IM,bak_terbuka,sedang_diperbaiki\n";
    $fileUpdate = UploadedFile::fake()->createWithContent('kendaraan_update.csv', $kontenUpdate);

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->set('berkasCsv', $fileUpdate)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $diupdate = Armada::where('plat', 'B 1001 IM')->first();
    expect($diupdate->jenis)->toBe(JenisArmada::BakTerbuka)
        ->and($diupdate->status)->toBe(StatusArmada::SedangDiperbaiki);
});

it('melewati baris dengan plat duplikat di dalam berkas impor yang sama', function () {
    $kontenCsv = "plat,jenis,status\n".
        "B 2001 DP,berpendingin,normal\n".
        "B 2001 DP,bak_terbuka,normal\n";

    $file = UploadedFile::fake()->createWithContent('kendaraan_dup.csv', $kontenCsv);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $hasilImpor = $komponen->get('hasilImpor');
    expect($hasilImpor['baru'])->toBe(1)
        ->and(count($hasilImpor['dilewati']))->toBe(1);

    expect(Armada::where('plat', 'B 2001 DP')->first()->jenis)->toBe(JenisArmada::Berpendingin);
});

it('melewati baris dengan jenis kosong atau tidak dikenal, tapi tetap memproses baris lain', function () {
    $kontenCsv = "plat,jenis,status\n".
        "B 3001 JS,mobil_terbang,normal\n".
        "B 3002 JS,berpendingin,normal\n";

    $file = UploadedFile::fake()->createWithContent('kendaraan_jenis.csv', $kontenCsv);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $hasilImpor = $komponen->get('hasilImpor');
    expect($hasilImpor['baru'])->toBe(1)
        ->and(count($hasilImpor['dilewati']))->toBe(1)
        ->and(Armada::where('plat', 'B 3001 JS')->exists())->toBeFalse()
        ->and(Armada::where('plat', 'B 3002 JS')->exists())->toBeTrue();
});

it('mengabaikan status yang tidak dikenal saat impor, dan mencatatnya sebagai peringatan', function () {
    $armadaLama = Armada::create(['plat' => 'B 4001 ST', 'jenis' => JenisArmada::Berpendingin, 'status' => StatusArmada::SedangDiperbaiki]);

    $kontenCsv = "plat,jenis,status\nB 4001 ST,berpendingin,rusak_parah\n";
    $file = UploadedFile::fake()->createWithContent('kendaraan_status.csv', $kontenCsv);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $hasilImpor = $komponen->get('hasilImpor');

    // Status lama TIDAK ikut tertimpa oleh nilai yang tidak dikenal.
    expect($armadaLama->fresh()->status)->toBe(StatusArmada::SedangDiperbaiki)
        ->and($hasilImpor['catatan'])->toHaveCount(1)
        ->and($hasilImpor['catatan'][0])->toContain('rusak_parah');
});

it('kendaraan baru tanpa kolom status terisi mengikuti status bawaan (normal)', function () {
    $kontenCsv = "plat,jenis\nB 5001 DEF,bak_terbuka\n";
    $file = UploadedFile::fake()->createWithContent('kendaraan_default.csv', $kontenCsv);

    Livewire::actingAs($this->admin)
        ->test(DaftarArmada::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    expect(Armada::where('plat', 'B 5001 DEF')->first()?->status)->toBe(StatusArmada::Normal);
});
