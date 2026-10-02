<?php

use App\Enums\PeranPengguna;
use App\Livewire\Master\DaftarFreezer;
use App\Models\Depot;
use App\Models\Freezer;
use App\Models\Toko;
use App\Models\User;
use App\Support\DepotContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * IDN toko (tokos.asset_id) hanya sah kalau terdaftar di Master Freezer, dan
 * satu IDN tidak pernah menempel di dua toko.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
});

function tokoIdn(string $kode, ?string $idn, ?string $tipe = null): Toko
{
    return Toko::create(['kode' => $kode, 'nama' => 'Toko '.$kode, 'alamat' => 'Jl. Uji', 'asset_id' => $idn, 'freezer_tipe' => $tipe]);
}

function jalankanMigrasiIdn(): void
{
    (require database_path('migrations/2026_10_03_000100_bersihkan_idn_toko_tidak_terdaftar.php'))->up();
}

describe('migrasi pembersihan', function () {
    it('mengosongkan IDN yang tidak ada di Master Freezer dan mencatat cadangannya', function () {
        Freezer::create(['idn' => 'IDNAH-OK-1', 'tipe' => 'SD-200']);
        $sah = tokoIdn('TK-1', 'IDNAH-OK-1', 'SD-200');
        $yatim = tokoIdn('TK-2', 'IDNAH-HILANG', 'SD-500');

        jalankanMigrasiIdn();

        expect($sah->fresh()->asset_id)->toBe('IDNAH-OK-1')
            ->and($sah->fresh()->freezer_tipe)->toBe('SD-200')
            ->and($yatim->fresh()->asset_id)->toBeNull()
            ->and($yatim->fresh()->freezer_tipe)->toBeNull();

        $berkas = Storage::disk('local')->allFiles('cadangan');
        expect($berkas)->toHaveCount(1)
            ->and(Storage::disk('local')->get($berkas[0]))->toContain('IDNAH-HILANG')->not->toContain('IDNAH-OK-1');
    });

    it('membersihkan lintas gudang, bukan cuma gudang aktif', function () {
        $depotB = Depot::factory()->create(['kode' => 'DEPOTB']);
        DepotContext::jalankanSebagai($depotB, fn () => tokoIdn('TK-B1', 'IDNAH-B-HILANG'));

        jalankanMigrasiIdn();

        DepotContext::jalankanUntukSemuaDepot(function () {
            expect(Toko::where('kode', 'TK-B1')->value('asset_id'))->toBeNull();
        });
    });

    it('merapikan IDN yang cuma beda format dari freezer sah, bukan menghapusnya', function () {
        Freezer::create(['idn' => 'IDNAH-FMT-1', 'tipe' => 'SD-200']);
        $toko = tokoIdn('TK-F', 'idnah-fmt-1 ', 'SD-200');

        jalankanMigrasiIdn();

        expect($toko->fresh()->asset_id)->toBe('IDNAH-FMT-1');
        expect(Storage::disk('local')->allFiles('cadangan'))->toBeEmpty();
    });

    it('tidak menyentuh apa pun dan tidak membuat berkas kalau semuanya sudah sah', function () {
        Freezer::create(['idn' => 'IDNAH-OK-2', 'tipe' => 'SD-200']);
        tokoIdn('TK-OK', 'IDNAH-OK-2');
        tokoIdn('TK-TANPA', null);

        jalankanMigrasiIdn();

        expect(Toko::where('kode', 'TK-OK')->value('asset_id'))->toBe('IDNAH-OK-2');
        expect(Storage::disk('local')->allFiles('cadangan'))->toBeEmpty();
    });
});

describe('mengganti IDN freezer di Master Freezer', function () {
    it('IDN toko pemegangnya ikut diganti sehingga tetap terdaftar', function () {
        $freezer = Freezer::create(['idn' => 'IDNAH-LAMA', 'tipe' => 'SD-200']);
        $toko = tokoIdn('TK-P', 'IDNAH-LAMA', 'SD-200');

        Livewire::actingAs($this->admin)->test(DaftarFreezer::class)
            ->call('sunting', $freezer->id)
            ->set('idn', 'idnah baru')
            ->call('simpan')
            ->assertHasNoErrors();

        expect($freezer->fresh()->idn)->toBe('IDNAHBARU')
            ->and($toko->fresh()->asset_id)->toBe('IDNAHBARU')
            ->and($freezer->fresh()->toko?->id)->toBe($toko->id);
    });

    it('ditolak kalau IDN barunya sudah dipegang toko lain, dan tidak ada yang berubah', function () {
        $freezer = Freezer::create(['idn' => 'IDNAH-A', 'tipe' => 'SD-200']);
        $tokoA = tokoIdn('TK-A', 'IDNAH-A');
        $tokoB = tokoIdn('TK-B', 'IDNAH-B-LIAR');

        Livewire::actingAs($this->admin)->test(DaftarFreezer::class)
            ->call('sunting', $freezer->id)
            ->set('idn', 'IDNAH-B-LIAR')
            ->call('simpan')
            ->assertHasErrors('idn');

        expect($freezer->fresh()->idn)->toBe('IDNAH-A')
            ->and($tokoA->fresh()->asset_id)->toBe('IDNAH-A')
            ->and($tokoB->fresh()->asset_id)->toBe('IDNAH-B-LIAR');
    });

    it('mengubah tipe tanpa mengganti IDN tidak menyentuh toko', function () {
        $freezer = Freezer::create(['idn' => 'IDNAH-T', 'tipe' => 'SD-200']);
        $toko = tokoIdn('TK-T', 'IDNAH-T');

        Livewire::actingAs($this->admin)->test(DaftarFreezer::class)
            ->call('sunting', $freezer->id)
            ->set('tipe', 'SD-500')
            ->call('simpan')
            ->assertHasNoErrors();

        expect($freezer->fresh()->tipe)->toBe('SD-500')
            ->and($toko->fresh()->asset_id)->toBe('IDNAH-T');
    });

    it('freezer yang belum terpasang bebas diganti IDN-nya', function () {
        $freezer = Freezer::create(['idn' => 'IDNAH-BEBAS', 'tipe' => 'SD-200']);

        Livewire::actingAs($this->admin)->test(DaftarFreezer::class)
            ->call('sunting', $freezer->id)
            ->set('idn', 'IDNAH-BEBAS-2')
            ->call('simpan')
            ->assertHasNoErrors();

        expect($freezer->fresh()->idn)->toBe('IDNAH-BEBAS-2');
    });

    it('IDN toko di gudang lain ikut diganti (Master Freezer lintas gudang)', function () {
        $depotB = Depot::factory()->create(['kode' => 'DEPOTB']);
        $freezer = Freezer::create(['idn' => 'IDNAH-X', 'tipe' => 'SD-200']);
        $tokoB = DepotContext::jalankanSebagai($depotB, fn () => tokoIdn('TK-XB', 'IDNAH-X'));

        Livewire::actingAs($this->admin)->test(DaftarFreezer::class)
            ->call('sunting', $freezer->id)
            ->set('idn', 'IDNAH-Y')
            ->call('simpan')
            ->assertHasNoErrors();

        expect(DB::table('tokos')->where('id', $tokoB->id)->value('asset_id'))->toBe('IDNAH-Y');
    });
});
