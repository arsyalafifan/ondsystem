<?php

use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarFreezer;
use App\Models\Depot;
use App\Models\Freezer;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\DepotContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->admin = User::factory()->create([
        'role' => PeranPengguna::Admin,
        'depot_id' => $this->depot->id,
    ]);
});

it('halaman master freezer hanya bisa dibuka pengguna yang berhak', function () {
    $this->get(route('master.freezer'))
        ->assertRedirect(route('masuk'));

    $this->actingAs($this->admin)
        ->get(route('master.freezer'))
        ->assertOk()
        ->assertSee(__('master.judul_freezer'))
        ->assertSee(__('umum.semua_status'))
        ->assertDontSee('umum.semua_status');
});

it('bisa menambah freezer baru dan menolak idn duplikat', function () {
    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('buatBaru')
        ->set('idn', 'IDN-001')
        ->set('tipe', 'SD-200')
        ->set('keterangan', 'Freezer Kaca')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertDispatched('notifikasi');

    expect(Freezer::where('idn', 'IDN-001')->exists())->toBeTrue();

    // Coba simpan IDN yang sama lagi
    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('buatBaru')
        ->set('idn', 'IDN-001')
        ->set('tipe', 'CF-300')
        ->call('simpan')
        ->assertHasErrors(['idn']);
});

it('bisa mengedit data freezer yang ada', function () {
    $freezer = Freezer::create([
        'idn' => 'IDN-EDIT',
        'tipe' => 'Tipe Lama',
        'keterangan' => 'Catatan Lama',
        'aktif' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('sunting', $freezer->id)
        ->assertSet('idn', 'IDN-EDIT')
        ->assertSet('tipe', 'Tipe Lama')
        ->set('tipe', 'Tipe Baru')
        ->set('keterangan', 'Catatan Baru')
        ->call('simpan')
        ->assertHasNoErrors();

    $freezer->refresh();
    expect($freezer->tipe)->toBe('Tipe Baru')
        ->and($freezer->keterangan)->toBe('Catatan Baru');
});

it('bisa menghapus freezer', function () {
    $freezer = Freezer::create([
        'idn' => 'IDN-DEL',
        'tipe' => 'SD-100',
    ]);

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('hapus', $freezer->id)
        ->assertDispatched('notifikasi');

    expect(Freezer::find($freezer->id))->toBeNull();
});

it('bisa mengunduh berkas contoh dan ekspor excel', function () {
    Freezer::create([
        'idn' => 'IDN-EXP-1',
        'tipe' => 'SD-200',
    ]);

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('unduhContohExcel')
        ->assertFileDownloaded('contoh-import-freezer.xlsx');

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('unduhExcel')
        ->assertFileDownloaded('freezer-'.now()->format('Y-m-d').'.xlsx');
});

it('bisa mengimpor freezer dari csv bertahap dan memperbarui data yang sudah ada', function () {
    $kontenCsv = "idn,tipe,keterangan,status\n".
        "IDN-IMP-1,SD-200,Freezer 200L,aktif\n".
        "IDN-IMP-2,CF-300,Chest 300L,aktif\n";

    $file = UploadedFile::fake()->createWithContent('freezer.csv', $kontenCsv);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->assertHasNoErrors()
        ->assertSet('imporBerjalan', true)
        ->call('lanjutkanImporCsv')
        ->assertSet('imporBerjalan', false);

    expect(Freezer::where('idn', 'IDN-IMP-1')->first())->not->toBeNull()
        ->and(Freezer::where('idn', 'IDN-IMP-2')->first())->not->toBeNull();

    // Re-import untuk update tipe IDN-IMP-1
    $kontenUpdate = "idn,tipe,keterangan,status\n".
        "IDN-IMP-1,SD-250-NEW,Freezer 250L Updated,aktif\n";

    $fileUpdate = UploadedFile::fake()->createWithContent('freezer_update.csv', $kontenUpdate);

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->set('berkasCsv', $fileUpdate)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $freezerDiupdate = Freezer::where('idn', 'IDN-IMP-1')->first();
    expect($freezerDiupdate->tipe)->toBe('SD-250-NEW')
        ->and($freezerDiupdate->keterangan)->toBe('Freezer 250L Updated');
});

it('melewati baris dengan idn duplikat di dalam berkas impor yang sama', function () {
    $kontenCsv = "idn,tipe,keterangan,status\n".
        "IDN-DUP-1,SD-200,Baris Pertama,aktif\n".
        "IDN-DUP-1,CF-300,Baris Duplikat Harus Dilewati,aktif\n";

    $file = UploadedFile::fake()->createWithContent('freezer_dup.csv', $kontenCsv);

    $komponen = Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->set('berkasCsv', $file)
        ->call('mulaiImporCsv')
        ->call('lanjutkanImporCsv');

    $hasilImpor = $komponen->get('hasilImpor');
    expect($hasilImpor['baru'])->toBe(1)
        ->and(count($hasilImpor['dilewati']))->toBe(1);

    $freezer = Freezer::where('idn', 'IDN-DUP-1')->first();
    expect($freezer->tipe)->toBe('SD-200');
});

it('bisa mencari freezer dan memfilter berdasarkan status', function () {
    Freezer::create(['idn' => 'IDN-ALPHA', 'tipe' => 'SD-100', 'aktif' => true]);
    Freezer::create(['idn' => 'IDN-BETA', 'tipe' => 'CF-200', 'aktif' => false]);

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->set('cari', 'ALPHA')
        ->assertSee('IDN-ALPHA')
        ->assertDontSee('IDN-BETA')
        ->set('cari', '')
        ->set('filterStatus', '0')
        ->assertSee('IDN-BETA')
        ->assertDontSee('IDN-ALPHA');
});

/** Toko di gudang lain yang memegang IDN tertentu. */
function tokoDiGudangLain(string $namaGudang, string $idn, string $namaToko = 'Toko Seberang'): Toko
{
    $depotLain = Depot::factory()->create(['kode' => strtoupper('G-'.substr(md5($namaGudang), 0, 6)), 'nama' => $namaGudang]);
    $wilayah = DepotContext::jalankanSebagai($depotLain, fn () => Wilayah::create(['kode' => 'WX', 'nama' => 'Wilayah X']));

    return DepotContext::jalankanSebagai($depotLain, fn () => Toko::create([
        'kode' => 'TK-SEB1', 'nama' => $namaToko, 'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Seberang', 'asset_id' => $idn,
    ]));
}

it('menampilkan freezer lintas gudang beserta nama toko dan gudang pemegangnya', function () {
    Freezer::create(['idn' => 'IDN-GLB-1', 'tipe' => 'SD-200']);
    Freezer::create(['idn' => 'IDN-GLB-2', 'tipe' => 'SD-200']);
    tokoDiGudangLain('Gudang Seberang', 'IDN-GLB-1', 'Toko Seberang Jaya');

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->assertSee('IDN-GLB-1')
        ->assertSee('Toko Seberang Jaya')
        ->assertSee('Gudang Seberang')
        ->assertSee('IDN-GLB-2')
        ->assertSee(__('master.freezer_belum_terpasang'));
});

it('mencari freezer lewat nama toko pemegangnya', function () {
    Freezer::create(['idn' => 'IDN-CARI-1', 'tipe' => 'SD-200']);
    Freezer::create(['idn' => 'IDN-CARI-2', 'tipe' => 'SD-200']);
    tokoDiGudangLain('Gudang Seberang', 'IDN-CARI-1', 'Toko Makmur Sentosa');

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->set('cari', 'Makmur')
        ->assertSee('IDN-CARI-1')
        ->assertDontSee('IDN-CARI-2');
});

it('menolak idn yang sama walau dibuat dari gudang yang berbeda', function () {
    $depotLain = Depot::factory()->create(['kode' => 'G-LAIN']);
    $adminLain = User::factory()->create(['role' => PeranPengguna::Admin, 'depot_id' => $depotLain->id]);

    Freezer::create(['idn' => 'IDN-SAMA', 'tipe' => 'SD-200']);

    Livewire::actingAs($adminLain)
        ->test(DaftarFreezer::class)
        ->call('buatBaru')
        ->set('idn', 'IDN-SAMA')
        ->set('tipe', 'CF-300')
        ->call('simpan')
        ->assertHasErrors(['idn']);
});

it('tidak menghapus freezer yang masih terpasang di toko gudang mana pun', function () {
    $freezer = Freezer::create(['idn' => 'IDN-TERPASANG', 'tipe' => 'SD-200']);
    tokoDiGudangLain('Gudang Seberang', 'IDN-TERPASANG');

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('hapus', $freezer->id)
        ->assertDispatched('notifikasi', jenis: 'error');

    expect(Freezer::find($freezer->id))->not->toBeNull();
});

it('menyertakan nama toko dan gudang pada berkas ekspor', function () {
    Freezer::create(['idn' => 'IDN-EXP-2', 'tipe' => 'SD-200']);
    tokoDiGudangLain('Gudang Seberang', 'IDN-EXP-2');

    Livewire::actingAs($this->admin)
        ->test(DaftarFreezer::class)
        ->call('unduhExcel')
        ->assertFileDownloaded('freezer-'.now()->format('Y-m-d').'.xlsx');
});
