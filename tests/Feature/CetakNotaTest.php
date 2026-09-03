<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\PesananService;
use App\Support\EscpNotaBuilder;
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

it('menampilkan total qty keseluruhan sejajar kolom Qty, bukan cuma per baris', function () {
    // 6 produk, 1 dus tiap baris — totalnya harus 6, ditampilkan sekali di
    // bawah tabel item, bukan cuma jumlah per baris yang berbeda-beda.
    $items = [];
    for ($i = 1; $i <= 6; $i++) {
        $produk = Produk::create(['kode' => "PU-QTY{$i}", 'nama' => "Produk Qty {$i}", 'stok' => 1000, 'harga' => 50_000]);
        $items[] = ['produk_id' => $produk->id, 'jumlah_dus' => 1];
    }

    $pesanan = $this->pesananService->buat($this->toko, $items, $this->sales);
    $this->pesananService->setujui($pesanan, $this->admin);

    $isi = $this->actingAs($this->admin)->get(route('pesanan.nota.escp', $pesanan))->getContent();
    $isi = iconv('CP437', 'UTF-8//IGNORE', $isi) ?: $isi;

    // Baris ringkasan qty ("Total" di kolom Nama, diikuti angka 6 di posisi
    // kolom Qty yang sama seperti baris-baris item di atasnya) muncul
    // persis sekali, terpisah dari "Total Harga" (header) dan
    // "Total Invoice" (ringkasan nominal) yang sama-sama mengandung kata
    // "Total" tapi bukan baris qty ini.
    preg_match_all('/Total {2,}6(\s|$)/', $isi, $cocok);
    expect($cocok[0])->toHaveCount(1);
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

// =====================================================================
describe('rendering EscpNotaBuilder untuk pesanan kurang_kirim', function () {
    /**
     * bisaDicetak() hanya mengizinkan status PROCESS/DELIVERY, sedangkan
     * kurang_kirim baru pernah bernilai true setelah pesanan berstatus
     * SELESAI (lewat coretNota() atau koreksiItemSetelahSelesai()) — jadi
     * rute cetak tidak pernah bisa dipakai untuk pesanan begini (lihat
     * 'menolak nota untuk pesanan berstatus SELESAI' di atas). Builder-nya
     * dites langsung di sini, lepas dari gerbang rute, supaya rumusnya
     * (terkirim, tagihan) tetap benar seandainya kelak ada fitur cetak-ulang.
     */
    it('tidak mencetak produk yang terkirim-nya 0 dus, dan totalnya memakai tagihan', function () {
        $pesanan = buatPesananProcess(2);
        $pesanan->load('items.produk');

        $itemDihapus = $pesanan->items->first();
        $itemDihapus->update(['jumlah_dus_terkirim' => 0]);
        $pesanan->update(['kurang_kirim' => true]);
        $pesanan->refresh()->load('items.produk', 'toko', 'pembuat');

        $hasil = EscpNotaBuilder::build($pesanan);
        $hasil = iconv('CP437', 'UTF-8//IGNORE', $hasil) ?: $hasil;

        expect($hasil)->not->toContain($itemDihapus->produk->nama)
            ->and($hasil)->toContain(number_format((float) $pesanan->tagihan, 0, ',', '.'));
    });
});

// =====================================================================
describe('faktur untuk pesanan dengan bonus', function () {
    it('menampilkan disc 100% dan harga 0 untuk baris bonus, tetap terpisah dari baris normal', function () {
        $produk = Produk::create(['kode' => 'PU-BONUS', 'nama' => 'Produk Bonus Uji', 'stok' => 1000, 'harga' => 50_000]);

        $pesanan = $this->pesananService->buat(
            toko: $this->toko,
            items: [['produk_id' => $produk->id, 'jumlah_dus' => 4]],
            pembuat: $this->admin,
            bonusItems: [['produk_id' => $produk->id, 'jumlah_dus' => 2]],
            atasNamaSales: $this->sales,
        );
        $this->pesananService->setujui($pesanan, $this->admin);

        $html = $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->getContent();

        // Dua baris terpisah untuk produk yang sama: satu harga normal
        // (Rp 50.000), satu lagi baris bonus berharga 0 dengan disc 100.
        expect(substr_count($html, 'Produk Bonus Uji'))->toBe(2);

        preg_match_all('#<tr>\s*<td>\d+</td>.*?</tr>#s', $html, $cocok);
        $barisNormal = $cocok[0][0];
        $barisBonus = $cocok[0][1];

        expect($barisNormal)->toContain('Produk Bonus Uji')
            ->and($barisBonus)->toContain('Produk Bonus Uji')
            // Baris normal: harga 50.000, disc 0. Baris bonus: harga & total
            // 0, disc 100 — persis satu-satunya baris dengan disc 100.
            ->and($barisNormal)->toContain('50.000')
            ->and(substr_count($html, '<td class="num">100</td>'))->toBe(1)
            ->and($barisBonus)->toContain('<td class="num">100</td>');

        $isiEscp = $this->actingAs($this->admin)->get(route('pesanan.nota.escp', $pesanan))->getContent();
        $isiEscp = iconv('CP437', 'UTF-8//IGNORE', $isiEscp) ?: $isiEscp;

        expect(substr_count($isiEscp, 'Produk Bonus Uji'))->toBe(2);
    });

    it('menampilkan nama sales atas nama, bukan admin yang menginput', function () {
        $this->sales->update(['name' => 'Sales Ditunjuk']);
        $this->admin->update(['name' => 'Admin Penginput']);

        $produk = Produk::create(['kode' => 'PU-ATAS-NAMA', 'nama' => 'Produk Atas Nama', 'stok' => 1000, 'harga' => 50_000]);

        $pesanan = $this->pesananService->buat(
            toko: $this->toko,
            items: [['produk_id' => $produk->id, 'jumlah_dus' => 5]],
            pembuat: $this->admin,
            bonusItems: [],
            atasNamaSales: $this->sales,
        );
        $this->pesananService->setujui($pesanan, $this->admin);

        $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))
            ->assertOk()
            ->assertSee('Sales Ditunjuk')
            ->assertDontSee('Admin Penginput');
    });
});

// =====================================================================
describe('nota untuk transaksi POS', function () {
    /**
     * Transaksi POS (Kasir) langsung tercatat SELESAI seketika dibuat —
     * tidak pernah lewat status PROCESS/DELIVERY seperti pesanan rute biasa
     * (lihat PesananService::buatPos()). `StatusPesanan::bisaDicetak()`
     * sendiri cuma mengizinkan PROCESS/DELIVERY, jadi kalau dipakai apa
     * adanya, POS tidak akan PERNAH punya nota yang bisa dicetak. Karena
     * itu `Pesanan::bisa_dicetak` (dipakai gerbang cetak, bukan enum-nya
     * langsung) sengaja meloloskan SEMUA pesanan berjenis POS apa pun
     * statusnya — lihat dokumentasi accessor-nya di Pesanan.php.
     */
    function buatPesananPos(int $jumlahProduk = 1): Pesanan
    {
        $items = buatItemUji($jumlahProduk);

        return test()->pesananService->buatPos(
            toko: test()->toko,
            items: $items,
            penjual: test()->admin,
            nominalCash: 250_000 * $jumlahProduk,
            nominalTransfer: 0.0,
        );
    }

    it('menampilkan nota untuk transaksi POS', function () {
        $pesanan = buatPesananPos();

        $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertOk();
    });

    it('mengunduh nota POS sebagai berkas PDF sungguhan', function () {
        $pesanan = buatPesananPos(2);

        $respons = $this->actingAs($this->admin)->get(route('pesanan.nota.pdf', $pesanan));

        $respons->assertOk();
        expect($respons->headers->get('content-type'))->toBe('application/pdf');
        expect(str_starts_with($respons->getContent(), '%PDF-'))->toBeTrue();
    });

    it('mengunduh nota POS sebagai perintah ESC/P mentah', function () {
        $pesanan = buatPesananPos(2);

        $respons = $this->actingAs($this->admin)->get(route('pesanan.nota.escp', $pesanan));

        $respons->assertOk();
        $isi = $respons->getContent();
        expect($isi)->toContain($pesanan->kode)
            ->and($isi)->toContain($pesanan->toko->nama);
    });

    it('pesanan rute biasa berstatus SELESAI tetap ditolak, tidak ikut terpengaruh pengecualian POS', function () {
        $pesanan = buatPesananProcess();
        $pesanan->update(['status' => StatusPesanan::Selesai]);

        $this->actingAs($this->admin)->get(route('pesanan.nota', $pesanan))->assertForbidden();
    });
});
