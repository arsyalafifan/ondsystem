<?php

use App\Akses\HakAkses;
use App\Enums\PeranPengguna;
use App\Livewire\Freezer\FreezerKeGudang;
use App\Livewire\Master\DaftarFreezer;
use App\Models\Depot;
use App\Models\Freezer;
use App\Models\Toko;
use App\Models\User;
use Livewire\Livewire;

/**
 * Menu "Freezer ke Gudang": mencatat gudang tempat freezer TANPA toko
 * disimpan. Gudang sebuah freezer di Master Freezer = COALESCE(gudang toko,
 * gudang simpan).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->gudangB = Depot::factory()->create(['kode' => 'GUD-B', 'nama' => 'Gudang Beta', 'urutan' => 2]);
});

it('hanya admin yang boleh membuka menu', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($sales)->get(route('freezer.gudang'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('freezer.gudang'))->assertOk()->assertSee(__('freezer_gudang.judul'));
});

it('memindai QR freezer bebas lalu mencatat gudangnya beserta siapa dan kapan', function () {
    Freezer::create(['idn' => 'IDNAH-GD-001', 'tipe' => 'SD-200']);

    Livewire::actingAs($this->admin)->test(FreezerKeGudang::class)
        ->call('pindaiQr', "资产编号：IDNAH-GD-001\n产品型号：SD-200")
        ->assertSet('idn', 'IDNAH-GD-001')
        ->set('depotId', (string) $this->gudangB->id)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertSet('idn', null);

    $freezer = Freezer::where('idn', 'IDNAH-GD-001')->firstOrFail();

    expect($freezer->depot_simpan_id)->toBe($this->gudangB->id)
        ->and($freezer->gudang_dicatat_oleh)->toBe($this->admin->id)
        ->and($freezer->gudang_dicatat_at)->not->toBeNull()
        ->and($freezer->gudang_saat_ini->id)->toBe($this->gudangB->id)
        ->and($freezer->sumber_gudang)->toBe('gudang');
});

it('bisa memindahkan freezer yang sudah pernah dicatat ke gudang lain', function () {
    Freezer::create(['idn' => 'IDNAH-GD-002', 'tipe' => 'SD-200', 'depot_simpan_id' => $this->depot->id]);

    Livewire::actingAs($this->admin)->test(FreezerKeGudang::class)
        ->set('idnKetik', ' idnah-gd-002 ')
        ->call('cariKetik')
        ->assertSet('idn', 'IDNAH-GD-002')
        ->assertSet('depotId', (string) $this->depot->id)
        ->set('depotId', (string) $this->gudangB->id)
        ->call('simpan');

    expect(Freezer::where('idn', 'IDNAH-GD-002')->value('depot_simpan_id'))->toBe($this->gudangB->id);
});

it('menolak IDN yang belum terdaftar di Master Freezer', function () {
    Livewire::actingAs($this->admin)->test(FreezerKeGudang::class)
        ->call('pindaiQr', '资产编号：IDNAH-TIDAK-ADA')
        ->assertSet('idn', null)
        ->assertDispatched('notifikasi', jenis: 'error');
});

it('menolak mencatat gudang untuk freezer yang masih terpasang di toko', function () {
    Freezer::create(['idn' => 'IDNAH-GD-003', 'tipe' => 'SD-200']);
    Toko::create(['kode' => 'TK-GD3', 'nama' => 'Toko GD3', 'alamat' => 'Jl. X', 'asset_id' => 'IDNAH-GD-003']);

    $komponen = Livewire::actingAs($this->admin)->test(FreezerKeGudang::class)
        ->call('pindaiQr', '资产编号：IDNAH-GD-003')
        ->assertSet('idn', 'IDNAH-GD-003')
        ->set('depotId', (string) $this->gudangB->id)
        ->call('simpan')
        ->assertDispatched('notifikasi', jenis: 'error');

    expect(Freezer::where('idn', 'IDNAH-GD-003')->value('depot_simpan_id'))->toBeNull();
});

it('gudang freezer mengikuti toko kalau terpasang, selain itu gudang simpan, selain itu kosong', function () {
    $bebas = Freezer::create(['idn' => 'IDNAH-C-1', 'tipe' => 'SD-200']);
    $disimpan = Freezer::create(['idn' => 'IDNAH-C-2', 'tipe' => 'SD-200', 'depot_simpan_id' => $this->gudangB->id]);
    $terpasang = Freezer::create(['idn' => 'IDNAH-C-3', 'tipe' => 'SD-200', 'depot_simpan_id' => $this->gudangB->id]);

    // Toko di gudang lain (default), padahal gudang simpan tercatat gudang B.
    Toko::create(['kode' => 'TK-C3', 'nama' => 'Toko C3', 'alamat' => 'Jl. C', 'asset_id' => 'IDNAH-C-3']);

    $ambil = fn (Freezer $f) => Freezer::with(['toko.depot', 'depotSimpan'])->find($f->id);

    expect($ambil($bebas)->gudang_saat_ini)->toBeNull()
        ->and($ambil($bebas)->sumber_gudang)->toBeNull()
        ->and($ambil($disimpan)->gudang_saat_ini->id)->toBe($this->gudangB->id)
        ->and($ambil($terpasang)->gudang_saat_ini->id)->toBe($this->depot->id)
        ->and($ambil($terpasang)->sumber_gudang)->toBe('toko');
});

it('Master Freezer menyaring berdasarkan penempatan dan gudang (COALESCE)', function () {
    Freezer::create(['idn' => 'IDNAH-F-BEBAS', 'tipe' => 'SD-200']);
    Freezer::create(['idn' => 'IDNAH-F-GUDANGB', 'tipe' => 'SD-200', 'depot_simpan_id' => $this->gudangB->id]);
    Freezer::create(['idn' => 'IDNAH-F-TOKO', 'tipe' => 'SD-200', 'depot_simpan_id' => $this->gudangB->id]);
    Toko::create(['kode' => 'TK-F', 'nama' => 'Toko F', 'alamat' => 'Jl. F', 'asset_id' => 'IDNAH-F-TOKO']);

    $layar = Livewire::actingAs($this->admin)->test(DaftarFreezer::class);

    $layar->set('filterPenempatan', 'belum')
        ->assertSee('IDNAH-F-BEBAS')->assertDontSee('IDNAH-F-GUDANGB')->assertDontSee('IDNAH-F-TOKO')
        ->set('filterPenempatan', 'gudang')
        ->assertSee('IDNAH-F-GUDANGB')->assertDontSee('IDNAH-F-BEBAS')->assertDontSee('IDNAH-F-TOKO')
        ->set('filterPenempatan', 'toko')
        ->assertSee('IDNAH-F-TOKO')->assertDontSee('IDNAH-F-GUDANGB')
        ->set('filterPenempatan', '')
        // Gudang B: hanya yang tanpa toko dan disimpan di B — freezer bertoko
        // ikut gudang tokonya (gudang default), bukan gudang simpan yang basi.
        ->set('filterGudang', (string) $this->gudangB->id)
        ->assertSee('IDNAH-F-GUDANGB')->assertDontSee('IDNAH-F-TOKO')->assertDontSee('IDNAH-F-BEBAS')
        ->set('filterGudang', (string) $this->depot->id)
        ->assertSee('IDNAH-F-TOKO')->assertDontSee('IDNAH-F-GUDANGB');
});

it('mengekspor gudang hasil COALESCE', function () {
    Freezer::create(['idn' => 'IDNAH-E-1', 'tipe' => 'SD-200', 'depot_simpan_id' => $this->gudangB->id]);

    Livewire::actingAs($this->admin)->test(DaftarFreezer::class)
        ->call('unduhExcel')
        ->assertFileDownloaded('freezer-'.now()->format('Y-m-d').'.xlsx');
});

it('tidak muncul di sidebar, tapi dibuka lewat tombol di Master Freezer', function () {
    $this->actingAs($this->admin)->get(route('master.freezer'))
        ->assertOk()
        ->assertSee(route('freezer.gudang'))
        ->assertSee('Freezer ke Gudang');

    // Sidebar admin tidak lagi memuat tautannya sebagai menu sendiri.
    expect(app(HakAkses::class)->menuUntuk($this->admin, 'ond'))
        ->not->toContain(fn ($m) => ($m['rute'] ?? null) === 'freezer.gudang');

    $anak = collect(app(HakAkses::class)->menuUntuk($this->admin, 'ond'))->pluck('anak')->filter()->flatten(1)->pluck('rute');
    expect($anak)->not->toContain('freezer.gudang');
});

it('Master Freezer menampilkan ringkasan dan kartunya menyaring cepat', function () {
    Freezer::create(['idn' => 'IDNAH-S-BEBAS', 'tipe' => 'SD-200']);
    Freezer::create(['idn' => 'IDNAH-S-GUDANG', 'tipe' => 'SD-200', 'depot_simpan_id' => $this->gudangB->id]);
    Freezer::create(['idn' => 'IDNAH-S-TOKO', 'tipe' => 'SD-200']);
    Freezer::create(['idn' => 'IDNAH-S-OFF', 'tipe' => 'SD-200', 'aktif' => false]);
    Toko::create(['kode' => 'TK-S', 'nama' => 'Toko S', 'alamat' => 'Jl. S', 'asset_id' => 'IDNAH-S-TOKO']);

    $layar = Livewire::actingAs($this->admin)->test(DaftarFreezer::class);

    expect($layar->instance()->ringkasan)->toBe([
        'total' => 4, 'toko' => 1, 'tanpa_toko' => 3, 'gudang' => 1, 'belum' => 2, 'nonaktif' => 1,
    ]);

    $layar->call('saringKartu', 'tanpa_toko')
        ->assertSee('IDNAH-S-BEBAS')->assertSee('IDNAH-S-GUDANG')->assertDontSee('IDNAH-S-TOKO')
        ->call('saringKartu', 'tanpa_toko')
        ->assertSet('filterPenempatan', '')
        ->call('saringKartu', 'nonaktif')
        ->assertSet('filterStatus', '0')->assertSee('IDNAH-S-OFF')->assertDontSee('IDNAH-S-TOKO');
});
