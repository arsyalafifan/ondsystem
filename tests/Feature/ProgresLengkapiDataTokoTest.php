<?php

use App\Enums\HariKunjungan;
use App\Enums\PeranPengguna;
use App\Livewire\Toko\LengkapiData;
use App\Models\Depot;
use App\Models\PenugasanToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\DepotContext;
use Livewire\Livewire;

/**
 * Tab "Progres" di layar Lengkapi Data Toko — satu menu/rute yang sama
 * dengan tab "Lengkapi Data" (lihat `LengkapiDataTokoTest.php`), bukan
 * layar terpisah. Progres kelengkapan data profil toko dihitung per
 * sales, dari SELURUH jadwal mingguannya (Senin-Minggu digabung, tidak
 * memandang hari). Admin/superadmin melihat progres semua sales; sales
 * hanya melihat progresnya sendiri.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Satu']);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Dua']);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
});

/** Toko dengan kelima field wajib TERISI (dianggap "lengkap" oleh profil_lengkap). */
function buatTokoLengkap(string $nama = 'Toko Lengkap'): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-PL%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Lengkap',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
        'nama_pemilik' => 'Pemilik '.$n,
        'nik_pemilik' => str_pad((string) $n, 16, '9', STR_PAD_LEFT),
        'asset_id' => 'IDNAH'.str_pad((string) $n, 15, '0', STR_PAD_LEFT),
        'telepon' => '08'.str_pad((string) $n, 10, '1', STR_PAD_LEFT),
    ]);
}

/** Toko TANPA data profil sama sekali (dianggap "belum lengkap"). */
function buatTokoBelumLengkap(string $nama = 'Toko Belum Lengkap'): Toko
{
    static $n = 0;
    $n++;

    return Toko::create([
        'kode' => sprintf('TK-PB%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Belum',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ]);
}

function tugaskanProgres(Toko $toko, User $sales, HariKunjungan $hari = HariKunjungan::Senin): void
{
    PenugasanToko::create([
        'toko_id' => $toko->id,
        'sales_id' => $sales->id,
        'hari' => $hari,
        'ditugaskan_oleh' => test()->admin->id,
    ]);
}

it('bawaannya tab "lengkapi", berpindah ke tab "progres" lewat gantiTab()', function () {
    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->assertSet('tab', 'lengkapi')
        ->call('gantiTab', 'progres')
        ->assertSet('tab', 'progres')
        ->assertSee(__('toko.judul_progres'));
});

it('tidak menampilkan kunci terjemahan mentah di tab progres, admin maupun sales', function () {
    $toko = buatTokoLengkap();
    tugaskanProgres($toko, $this->sales);

    foreach ([$this->admin, $this->sales] as $pengguna) {
        $html = Livewire::actingAs($pengguna)
            ->test(LengkapiData::class)
            ->call('gantiTab', 'progres')
            ->html();

        preg_match_all('/\b(umum|nav|pesanan|master|toko)\.[a-z_]+\b/', strip_tags($html), $cocok);

        expect(array_unique($cocok[0]))->toBe([]);
    }
});

it('menghitung progres dari seluruh hari Senin sampai Minggu digabung, bukan cuma satu hari', function () {
    $lengkap1 = buatTokoLengkap('Toko Senin');
    $lengkap2 = buatTokoLengkap('Toko Rabu');
    $belum = buatTokoBelumLengkap('Toko Minggu');

    tugaskanProgres($lengkap1, $this->sales, HariKunjungan::Senin);
    tugaskanProgres($lengkap2, $this->sales, HariKunjungan::Rabu);
    tugaskanProgres($belum, $this->sales, HariKunjungan::Minggu);

    $baris = Livewire::actingAs($this->admin)
        ->test(LengkapiData::class)
        ->instance()->progres
        ->firstWhere('sales.id', $this->sales->id);

    expect($baris['total'])->toBe(3)
        ->and($baris['lengkap'])->toBe(2)
        ->and($baris['belum'])->toBe(1)
        ->and($baris['persen'])->toBe(67);
});

it('admin melihat progres semua sales sekaligus', function () {
    $tokoA = buatTokoLengkap('Toko A');
    $tokoB = buatTokoBelumLengkap('Toko B');
    tugaskanProgres($tokoA, $this->sales);
    tugaskanProgres($tokoB, $this->salesLain);

    $progres = Livewire::actingAs($this->admin)->test(LengkapiData::class)->instance()->progres;

    expect($progres->pluck('sales.id'))->toContain($this->sales->id)
        ->and($progres->pluck('sales.id'))->toContain($this->salesLain->id);
});

it('sales hanya melihat progres dirinya sendiri, tidak melihat sales lain', function () {
    $tokoSaya = buatTokoLengkap('Toko Saya');
    $tokoLain = buatTokoBelumLengkap('Toko Sales Lain');
    tugaskanProgres($tokoSaya, $this->sales);
    tugaskanProgres($tokoLain, $this->salesLain);

    $progres = Livewire::actingAs($this->sales)->test(LengkapiData::class)->instance()->progres;

    expect($progres)->toHaveCount(1)
        ->and($progres->first()['sales']->id)->toBe($this->sales->id);
});

it('sales yang belum punya toko tanggungan tetap muncul dengan progres nol', function () {
    $baris = Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->instance()->progres
        ->firstWhere('sales.id', $this->sales->id);

    expect($baris['total'])->toBe(0)
        ->and($baris['lengkap'])->toBe(0)
        ->and($baris['persen'])->toBe(0);
});

describe('rincian toko tanggungan sendiri (khusus sales)', function () {
    it('sales melihat daftar toko tanggungannya lengkap dengan status kelengkapan', function () {
        $lengkap = buatTokoLengkap('Toko Lengkap Saya');
        $belum = buatTokoBelumLengkap('Toko Belum Punya Saya');
        tugaskanProgres($lengkap, $this->sales);
        tugaskanProgres($belum, $this->sales);

        $tokoSaya = Livewire::actingAs($this->sales)->test(LengkapiData::class)->instance()->tokoSaya;

        expect($tokoSaya->pluck('id'))->toContain($lengkap->id)
            ->and($tokoSaya->pluck('id'))->toContain($belum->id)
            ->and($tokoSaya->firstWhere('id', $lengkap->id)->profil_lengkap)->toBeTrue()
            ->and($tokoSaya->firstWhere('id', $belum->id)->profil_lengkap)->toBeFalse();
    });

    it('admin tidak melihat rincian toko per sales, cukup ringkasan progres', function () {
        $toko = buatTokoLengkap();
        tugaskanProgres($toko, $this->sales);

        $tokoSaya = Livewire::actingAs($this->admin)->test(LengkapiData::class)->instance()->tokoSaya;

        expect($tokoSaya)->toHaveCount(0);
    });

    it('sales tidak melihat toko tanggungan sales lain di rincian miliknya', function () {
        $tokoLain = buatTokoLengkap('Toko Sales Lain');
        tugaskanProgres($tokoLain, $this->salesLain);

        $tokoSaya = Livewire::actingAs($this->sales)->test(LengkapiData::class)->instance()->tokoSaya;

        expect($tokoSaya->pluck('id'))->not->toContain($tokoLain->id);
    });
});

it('tidak menghitung sales atau toko dari depot lain', function () {
    $depotLain = Depot::factory()->create(['kode' => 'DEPOTPROG']);

    DepotContext::jalankanSebagai($depotLain, function () {
        $salesDepotLain = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Depot Lain']);
        $wilayahLain = Wilayah::create(['kode' => 'WDL', 'nama' => 'Wilayah Depot Lain']);
        $tokoDepotLain = Toko::create([
            'kode' => 'TK-DEPOTLAIN', 'nama' => 'Toko Depot Lain', 'wilayah_id' => $wilayahLain->id,
            'alamat' => 'Jl. Lain', 'latitude' => -6.2, 'longitude' => 106.8, 'sumber_koordinat' => 'manual',
        ]);
        $admin = User::factory()->create(['role' => PeranPengguna::Admin]);

        PenugasanToko::create([
            'toko_id' => $tokoDepotLain->id, 'sales_id' => $salesDepotLain->id,
            'hari' => HariKunjungan::Senin, 'ditugaskan_oleh' => $admin->id,
        ]);
    });

    $toko = buatTokoLengkap();
    tugaskanProgres($toko, $this->sales);

    $progres = Livewire::actingAs($this->admin)->test(LengkapiData::class)->instance()->progres;

    expect($progres->pluck('sales.name'))->not->toContain('Sales Depot Lain');
});
