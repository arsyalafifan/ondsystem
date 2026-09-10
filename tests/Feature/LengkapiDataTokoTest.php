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
 * Layar bagi sales melengkapi profil toko tanggungannya sendiri
 * (data pemilik, kontak, alamat administratif) — admin/superadmin bisa
 * melengkapi data toko mana pun, tidak dibatasi tanggungan.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
});

function buatTokoLengkapi(string $nama = 'Toko Uji', array $atribut = []): Toko
{
    static $n = 0;
    $n++;

    return Toko::create(array_merge([
        'kode' => sprintf('TK-LD%04d', $n),
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Awal',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'sumber_koordinat' => 'manual',
    ], $atribut));
}

function tugaskanKeSales(Toko $toko, User $sales): void
{
    PenugasanToko::create([
        'toko_id' => $toko->id,
        'sales_id' => $sales->id,
        'hari' => HariKunjungan::Senin,
        'ditugaskan_oleh' => test()->admin->id,
    ]);
}

/** Data profil lengkap yang valid untuk dikirim lewat form simpan(). */
function dataProfilValid(array $override = []): array
{
    static $n = 0;
    $n++;

    return array_merge([
        'namaPemilik' => 'Pemilik '.$n,
        'nikPemilik' => str_pad((string) $n, 16, '9', STR_PAD_LEFT),
        'alamat' => 'Jl. Lengkap No. '.$n,
        'assetId' => 'IDNAH'.str_pad((string) $n, 15, '0', STR_PAD_LEFT),
        'telepon' => '08'.str_pad((string) $n, 10, '1', STR_PAD_LEFT),
        'kecamatan' => 'Kecamatan '.$n,
        'kota' => 'Kota '.$n,
        'provinsi' => 'Provinsi '.$n,
    ], $override);
}

it('menolak akses selain sales, admin, dan superadmin', function () {
    $driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->actingAs($driver)->get(route('toko.lengkapi-data'))->assertForbidden();
});

it('mengizinkan sales, admin, dan superadmin membuka halaman', function () {
    $superadmin = User::factory()->create(['role' => PeranPengguna::Superadmin]);

    $this->actingAs($this->sales)->get(route('toko.lengkapi-data'))->assertOk();
    $this->actingAs($this->admin)->get(route('toko.lengkapi-data'))->assertOk();
    $this->actingAs($superadmin)->get(route('toko.lengkapi-data'))->assertOk();
});

describe('pencarian toko dibatasi peran', function () {
    it('sales hanya menemukan toko tanggungannya sendiri', function () {
        $milikSaya = buatTokoLengkapi('Toko Milik Saya');
        $milikLain = buatTokoLengkapi('Toko Milik Lain');
        tugaskanKeSales($milikSaya, $this->sales);
        tugaskanKeSales($milikLain, $this->salesLain);

        $hasil = Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->set('cari', 'Toko Milik')
            ->instance()->hasilCari;

        expect($hasil->pluck('id'))->toContain($milikSaya->id)
            ->and($hasil->pluck('id'))->not->toContain($milikLain->id);
    });

    it('sales tidak menemukan toko yang belum ditugaskan ke siapa pun', function () {
        buatTokoLengkapi('Toko Tanpa Penugasan');

        $hasil = Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->set('cari', 'Toko Tanpa')
            ->instance()->hasilCari;

        expect($hasil)->toHaveCount(0);
    });

    it('admin menemukan toko siapa pun, tanpa terikat tanggungan', function () {
        $milikSales = buatTokoLengkapi('Toko Milik Sales Lain');
        tugaskanKeSales($milikSales, $this->salesLain);

        $hasil = Livewire::actingAs($this->admin)
            ->test(LengkapiData::class)
            ->set('cari', 'Toko Milik Sales Lain')
            ->instance()->hasilCari;

        expect($hasil->pluck('id'))->toContain($milikSales->id);
    });
});

it('memilih toko memuat data yang sudah ada ke form', function () {
    $toko = buatTokoLengkapi('Toko Terisi Sebagian', [
        'nama_pemilik' => 'Budi', 'kota' => 'Jakarta',
    ]);
    tugaskanKeSales($toko, $this->sales);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->assertSet('tokoId', $toko->id)
        ->assertSet('namaPemilik', 'Budi')
        ->assertSet('kota', 'Jakarta')
        ->assertSet('nikPemilik', '')
        ->assertSet('provinsi', '');
});

it('sales tidak bisa memilih toko yang bukan tanggungannya, walau tahu id-nya', function () {
    $bukanMilik = buatTokoLengkapi('Toko Bukan Tanggungan');
    tugaskanKeSales($bukanMilik, $this->salesLain);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $bukanMilik->id)
        ->assertSet('tokoId', null);
});

it('menyimpan data profil yang lengkap dan valid', function () {
    $toko = buatTokoLengkapi();
    tugaskanKeSales($toko, $this->sales);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set(dataProfilValid())
        ->call('simpan')
        ->assertHasNoErrors();

    $segar = $toko->fresh();
    expect($segar->nama_pemilik)->not->toBeNull()
        ->and($segar->nik_pemilik)->not->toBeNull()
        ->and($segar->provinsi)->not->toBeNull()
        ->and($segar->profil_lengkap)->toBeTrue();
});

it('tidak pernah mengubah nama, kode, atau koordinat toko walau disimpan berkali-kali', function () {
    $toko = buatTokoLengkapi('Nama Asli', [
        'latitude' => -6.123456, 'longitude' => 106.123456,
    ]);
    $kodeAsli = $toko->kode;
    tugaskanKeSales($toko, $this->sales);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set(dataProfilValid())
        ->call('simpan')
        ->assertHasNoErrors();

    $segar = $toko->fresh();
    expect($segar->nama)->toBe('Nama Asli')
        ->and($segar->kode)->toBe($kodeAsli)
        ->and((float) $segar->latitude)->toBe(-6.123456)
        ->and((float) $segar->longitude)->toBe(106.123456);
});

/**
 * Kecamatan/kota/provinsi boleh dikosongkan — tidak memengaruhi rute
 * pengantaran (yang dipakai cuma titik koordinat) maupun transaksi lain,
 * beda dari kelima field wajib lainnya.
 */
it('mengizinkan simpan walau kecamatan, kota, dan provinsi dikosongkan', function () {
    $toko = buatTokoLengkapi();
    tugaskanKeSales($toko, $this->sales);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set(dataProfilValid(['kecamatan' => '', 'kota' => '', 'provinsi' => '']))
        ->call('simpan')
        ->assertHasNoErrors();

    $segar = $toko->fresh();
    expect($segar->kecamatan)->toBeNull()
        ->and($segar->kota)->toBeNull()
        ->and($segar->provinsi)->toBeNull()
        // Kelima field wajib tetap terisi, jadi tetap dianggap lengkap
        // walau ketiganya kosong.
        ->and($segar->profil_lengkap)->toBeTrue();
});

it('menolak simpan tanpa memilih toko', function () {
    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->set(dataProfilValid())
        ->call('simpan')
        ->assertHasErrors('tokoId');
});

it('menolak simpan kalau salah satu field wajib kosong', function () {
    $toko = buatTokoLengkapi();
    tugaskanKeSales($toko, $this->sales);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set(dataProfilValid(['namaPemilik' => '']))
        ->call('simpan')
        ->assertHasErrors('namaPemilik');

    expect($toko->fresh()->nama_pemilik)->toBeNull();
});

it('menolak NIK yang bukan 16 digit angka', function () {
    $toko = buatTokoLengkapi();
    tugaskanKeSales($toko, $this->sales);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set(dataProfilValid(['nikPemilik' => '12345']))
        ->call('simpan')
        ->assertHasErrors('nikPemilik');
});

describe('keunikan NIK, nomor HP, dan nomor freezer antar toko', function () {
    it('menolak simpan kalau NIK sudah dipakai toko lain', function () {
        $tokoLain = buatTokoLengkapi('Toko Lain', ['nik_pemilik' => '1234567890123456']);
        $toko = buatTokoLengkapi();
        tugaskanKeSales($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->call('pilihToko', $toko->id)
            ->set(dataProfilValid(['nikPemilik' => '1234567890123456']))
            ->call('simpan')
            ->assertHasErrors('nikPemilik');

        expect($toko->fresh()->nik_pemilik)->toBeNull();
    });

    it('menolak simpan kalau nomor HP sudah dipakai toko lain', function () {
        buatTokoLengkapi('Toko Lain', ['telepon' => '081234567890']);
        $toko = buatTokoLengkapi();
        tugaskanKeSales($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->call('pilihToko', $toko->id)
            ->set(dataProfilValid(['telepon' => '081234567890']))
            ->call('simpan')
            ->assertHasErrors('telepon');
    });

    it('menolak simpan kalau nomor freezer sudah dipakai toko lain', function () {
        buatTokoLengkapi('Toko Lain', ['asset_id' => 'IDNAH999999999']);
        $toko = buatTokoLengkapi();
        tugaskanKeSales($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->call('pilihToko', $toko->id)
            ->set(dataProfilValid(['assetId' => 'IDNAH999999999']))
            ->call('simpan')
            ->assertHasErrors('assetId');
    });

    it('mengizinkan simpan ulang toko yang sama tanpa bentrok dengan nilainya sendiri', function () {
        $toko = buatTokoLengkapi(atribut: [
            'nik_pemilik' => '1111111111111111',
            'telepon' => '080000000000',
            'asset_id' => 'IDNAHSAMA0000001',
        ]);
        tugaskanKeSales($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->call('pilihToko', $toko->id)
            ->set(dataProfilValid([
                'nikPemilik' => '1111111111111111',
                'telepon' => '080000000000',
                'assetId' => 'IDNAHSAMA0000001',
            ]))
            ->call('simpan')
            ->assertHasNoErrors();

        expect($toko->fresh()->nik_pemilik)->toBe('1111111111111111');
    });
});

/**
 * Bukti perbaikan bug nyata: NIK/nomor HP pemilik toko di Perawang
 * tadinya juga menolak toko yang sama sekali berbeda di Dumai, padahal
 * itu toko yang sepenuhnya lain — cuma kebetulan sama-sama tercatat NIK
 * pemilik yang sama. asset_id (stiker QR freezer fisik) awalnya dibuat
 * TETAP unik global dengan alasan "satu barang fisik tidak mungkin ada
 * di 2 depot" — ternyata premis itu salah di lapangan: toko yang sama
 * BOLEH tercatat di dua depot (mis. wilayah perbatasan), dan freezer
 * fisiknya pun ikut sama, jadi nomor stikernya SAH berulang lintas
 * depot juga (lihat migrasi asset_id_toko_unik_per_depot). Yang tetap
 * dijaga: DALAM satu depot, ketiganya tetap harus unik.
 */
describe('NIK/HP/asset_id boleh sama lintas depot, tapi tetap unik dalam satu depot', function () {
    it('mengizinkan NIK yang sama dipakai toko di depot lain', function () {
        $depotLain = Depot::factory()->create(['kode' => 'DEPOTLD']);

        DepotContext::jalankanSebagai($depotLain, function () {
            buatTokoLengkapi('Toko Depot Lain', ['nik_pemilik' => '1234567890123456']);
        });

        $toko = buatTokoLengkapi();
        tugaskanKeSales($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->call('pilihToko', $toko->id)
            ->set(dataProfilValid(['nikPemilik' => '1234567890123456']))
            ->call('simpan')
            ->assertHasNoErrors();
    });

    it('mengizinkan nomor HP yang sama dipakai toko di depot lain', function () {
        $depotLain = Depot::factory()->create(['kode' => 'DEPOTLD2']);

        DepotContext::jalankanSebagai($depotLain, function () {
            buatTokoLengkapi('Toko Depot Lain', ['telepon' => '081234567890']);
        });

        $toko = buatTokoLengkapi();
        tugaskanKeSales($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->call('pilihToko', $toko->id)
            ->set(dataProfilValid(['telepon' => '081234567890']))
            ->call('simpan')
            ->assertHasNoErrors();
    });

    it('mengizinkan nomor freezer yang sama dipakai toko di depot lain', function () {
        $depotLain = Depot::factory()->create(['kode' => 'DEPOTLD3']);

        DepotContext::jalankanSebagai($depotLain, function () {
            buatTokoLengkapi('Toko Depot Lain', ['asset_id' => 'IDNAH999999999']);
        });

        $toko = buatTokoLengkapi();
        tugaskanKeSales($toko, $this->sales);

        Livewire::actingAs($this->sales)
            ->test(LengkapiData::class)
            ->call('pilihToko', $toko->id)
            ->set(dataProfilValid(['assetId' => 'IDNAH999999999']))
            ->call('simpan')
            ->assertHasNoErrors('assetId');
    });
});

it('nomor aset dirapikan huruf besar tanpa spasi, sama seperti Master Toko', function () {
    $toko = buatTokoLengkapi();
    tugaskanKeSales($toko, $this->sales);

    Livewire::actingAs($this->sales)
        ->test(LengkapiData::class)
        ->call('pilihToko', $toko->id)
        ->set(dataProfilValid(['assetId' => ' idnah 2025 0001 ']))
        ->call('simpan')
        ->assertHasNoErrors();

    expect($toko->fresh()->asset_id)->toBe('IDNAH20250001');
});
