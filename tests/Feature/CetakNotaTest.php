<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Support\TokenCetakSekaliPakai;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::firstOrCreate(['kode' => 'W1'], ['nama' => 'Wilayah W1']);

    $this->toko = Toko::create([
        'kode' => 'TK-0001',
        'nama' => 'Toko Uji',
        'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'telepon' => '0812345678',
        'asset_id' => 'IDNAH202528000001',
    ]);

    $this->pesananService = app(PesananService::class);
});

/** @return array<int, array{produk_id: int, jumlah_dus: int}> */
function buatItemUji(int $jumlahProduk): array
{
    $items = [];

    for ($i = 0; $i < $jumlahProduk; $i++) {
        $produk = Produk::create([
            'kode' => 'PU-'.($i + 1),
            'nama' => 'Produk Uji '.($i + 1),
            'stok' => 1000,
            'harga' => 50_000,
        ]);

        $items[] = ['produk_id' => $produk->id, 'jumlah_dus' => 5];
    }

    return $items;
}

function buatPesananProcess(int $jumlahProduk = 1): Pesanan
{
    $pesanan = test()->pesananService->buat(test()->toko, buatItemUji($jumlahProduk), test()->sales);
    test()->pesananService->setujui($pesanan, test()->admin);

    return $pesanan->fresh();
}

it('menampilkan nota untuk pesanan berstatus PROCESS', function () {
    $pesanan = buatPesananProcess();

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertOk();
    $this->actingAs($this->sales)->get(route('pesanan.nota', $pesanan))->assertOk();
});

it('menampilkan sales yang menginput pesanan, bukan yang sedang mencetak', function () {
    // Nama pendek & pasti muat satu baris — ESC/P sengaja membungkus nama
    // panjang ke baris baru (bukan memotongnya), jadi memeriksa potongan
    // nama yang lebih panjang dari lebar kolom di sini hanya akan menguji
    // perilaku pembungkusan itu, bukan atribusi sales yang sebenarnya diuji.
    $this->sales->update(['name' => 'Sales Uji']);
    $this->admin->update(['name' => 'Admin Uji']);

    $pesanan = buatPesananProcess();

    // Dicetak oleh admin, tapi nota harus tetap menampilkan nama sales yang
    // menginput pesanannya ($this->sales), bukan $this->admin yang mencetak.
    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))
        ->assertOk()
        ->assertSee($this->sales->name)
        ->assertDontSee($this->admin->name);

    $isiEscp = $this->actingAs($this->admin)->get(route('pesanan.nota.escp', $pesanan))->getContent();
    expect($isiEscp)->toContain($this->sales->name)
        ->and($isiEscp)->not->toContain($this->admin->name);
});

it('menolak nota untuk pesanan berstatus ORDER', function () {
    $pesanan = $this->pesananService->buat($this->toko, buatItemUji(1), $this->sales);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertForbidden();
});

it('menolak nota untuk pesanan berstatus SELESAI', function () {
    $pesanan = buatPesananProcess();
    $pesanan->update(['status' => StatusPesanan::Selesai]);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertForbidden();
});

it('tetap bisa dicetak ulang saat status DELIVERY', function () {
    $pesanan = buatPesananProcess();
    $pesanan->update(['status' => StatusPesanan::Delivery]);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertOk();
});

it('menolak driver mengakses nota', function () {
    $pesanan = buatPesananProcess();

    $this->actingAs($this->driver)->get(route('pesanan.nota', $pesanan))->assertForbidden();
});

it('selalu mencetak dengan ukuran kertas besar 24x28cm, apapun jumlah itemnya', function (int $jumlahItem) {
    $pesanan = buatPesananProcess($jumlahItem);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))
        ->assertOk()
        ->assertSee('size: 24cm 28cm;', false);
})->with([5, 6]);

it('mengunduh nota sebagai berkas PDF sungguhan', function () {
    $pesanan = buatPesananProcess(3);

    $respons = $this->actingAs($this->admin)->get(route('pesanan.nota.pdf', $pesanan));

    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toBe('application/pdf');
    expect(str_starts_with($respons->getContent(), '%PDF-'))->toBeTrue();
});

it('menolak unduh PDF untuk pesanan berstatus ORDER', function () {
    $pesanan = $this->pesananService->buat($this->toko, buatItemUji(1), $this->sales);

    $this->actingAs($this->admin)->get(route('pesanan.nota.pdf', $pesanan))->assertForbidden();
});

it('mengunduh nota sebagai perintah ESC/P mentah', function () {
    $pesanan = buatPesananProcess(2);

    $respons = $this->actingAs($this->admin)->get(route('pesanan.nota.escp', $pesanan));

    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toBe('application/octet-stream');
    expect($respons->headers->get('content-disposition'))->toContain('.prn');

    $isi = $respons->getContent();
    expect(str_starts_with($isi, "\x1B@"))->toBeTrue() // ESC @ : inisialisasi printer
        ->and($isi)->toContain($pesanan->kode)
        ->and($isi)->toContain($pesanan->toko->nama)
        ->and(str_ends_with($isi, "\x0C"))->toBeTrue(); // form feed di akhir
});

it('membungkus nama barang yang panjang ke baris baru, bukan memotongnya', function () {
    $namaPanjang = 'Es Krim Cokelat Premium Kemasan Baru Ukuran Besar Sekali 900ml';
    $produk = Produk::create(['kode' => 'PU-PJG', 'nama' => $namaPanjang, 'stok' => 1000, 'harga' => 50_000]);

    $pesanan = $this->pesananService->buat($this->toko, [['produk_id' => $produk->id, 'jumlah_dus' => 5]], $this->sales);
    $this->pesananService->setujui($pesanan, $this->admin);

    $isi = $this->actingAs($this->admin)->get(route('pesanan.nota.escp', $pesanan))->getContent();

    // Baris boleh berpindah (wrap) ke baris baru, tapi wordwrap tidak pernah
    // memotong DI TENGAH kata — jadi tiap kata dari nama produk harus tetap
    // utuh ada di suatu tempat pada hasilnya, walau sudah pindah baris.
    foreach (explode(' ', $namaPanjang) as $kata) {
        expect($isi)->toContain($kata);
    }
});

it('menolak unduh ESC/P untuk pesanan berstatus ORDER', function () {
    $pesanan = $this->pesananService->buat($this->toko, buatItemUji(1), $this->sales);

    $this->actingAs($this->admin)->get(route('pesanan.nota.escp', $pesanan))->assertForbidden();
});

it('halaman cetak menyertakan link ondprint:// untuk OND Print Helper', function () {
    $pesanan = buatPesananProcess(1);

    $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))
        ->assertOk()
        ->assertSee('ondprint://print?url=', false);
});

it('OND Print Helper bisa mengambil ESC/P lewat link bertanda tangan tanpa sesi login', function () {
    $pesanan = buatPesananProcess(2);

    $url = URL::temporarySignedRoute(
        'pesanan.nota.escp.signed',
        now()->addMinutes(5),
        ['pesanan' => $pesanan->id, 'token' => TokenCetakSekaliPakai::buat()],
    );

    // Sengaja TANPA actingAs — inilah inti pengujiannya: OND Print Helper
    // memanggil endpoint ini tanpa cookie sesi sama sekali.
    $respons = $this->get($url);

    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toBe('application/octet-stream');
    expect($respons->getContent())->toContain($pesanan->kode);
});

/**
 * Latar belakang: protokol kustom ondprint:// dikenal kadang terpicu dua
 * kali oleh Windows/browser untuk satu klik yang sama, membuat isi yang
 * sama terkirim dua kali ke printer fisik dan terlihat "tercetak dua kali"
 * di kertas continuous form. Token sekali pakai menutup celah itu di sisi
 * server: siapa pun/apa pun yang meminta URL yang sama untuk kedua kalinya
 * ditolak, apa pun penyebab permintaan berulang itu.
 */
it('menolak link ondprint yang tokennya sudah dipakai', function () {
    $pesanan = buatPesananProcess(1);

    $url = URL::temporarySignedRoute(
        'pesanan.nota.escp.signed',
        now()->addMinutes(5),
        ['pesanan' => $pesanan->id, 'token' => TokenCetakSekaliPakai::buat()],
    );

    $this->get($url)->assertOk();
    $this->get($url)->assertStatus(410);
});

it('menolak link ondprint yang tanda tangannya sudah tidak sah', function () {
    $pesanan = buatPesananProcess(1);

    $url = URL::temporarySignedRoute(
        'pesanan.nota.escp.signed',
        now()->addMinutes(5),
        ['pesanan' => $pesanan->id],
    );

    // Mengganggu satu parameter membatalkan tanda tangannya.
    $this->get($url.'&diubah=1')->assertForbidden();
});

it('menolak link ondprint yang sudah kedaluwarsa', function () {
    $pesanan = buatPesananProcess(1);

    $url = URL::temporarySignedRoute(
        'pesanan.nota.escp.signed',
        now()->subMinute(),
        ['pesanan' => $pesanan->id],
    );

    $this->get($url)->assertForbidden();
});
