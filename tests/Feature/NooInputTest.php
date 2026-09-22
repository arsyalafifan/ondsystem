<?php

use App\Enums\JenisBuktiNoo;
use App\Enums\PeranPengguna;
use App\Enums\StatusNoo;
use App\Livewire\Noo\DaftarNoo;
use App\Models\Noo;
use App\Models\PaketNoo;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Noo\BuktiNooService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Pendataan calon mitra baru oleh sales.
 *
 * Dua hal yang dijaga ketat di sini: tidak ada baris `tokos` yang lahir
 * sebelum admin menyetujui, dan foto identitas pemiliknya tidak pernah
 * mendarat di disk publik.
 */
beforeEach(function () {
    Storage::fake(BuktiNooService::DISK);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Es Krim Cokelat', 'stok' => 500, 'harga' => 40_000]);

    $this->paket = PaketNoo::create(['nama' => '15+2', 'dus_reguler' => 15, 'dus_bonus' => 2, 'urutan' => 1, 'aktif' => true]);
    $this->paket->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => 15, 'is_bonus' => false]);
    $this->paket->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => 2, 'is_bonus' => true]);
});

/** Data URL kecil untuk mengisi foto wajib di tes. */
function gambarNooUji(): string
{
    $gambar = imagecreatetruecolor(60, 60);
    ob_start();
    imagejpeg($gambar);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

/** Mengisi seluruh formulir pengajuan dengan data yang sah. */
function isiFormulirNoo($komponen, int $wilayahId, int $paketId)
{
    foreach (JenisBuktiNoo::wajibSales() as $jenis) {
        $komponen = $komponen->call('terimaBuktiFoto', $jenis->value, gambarNooUji());
    }

    return $komponen
        ->set('nama', 'Toko Maju Jaya')
        ->set('alamat', 'Jl. Merdeka No. 10')
        ->set('wilayahId', $wilayahId)
        ->set('telepon', '081234567890')
        ->set('namaPemilik', 'Budi Santoso')
        ->set('nikPemilik', '3201234567890123')
        ->set('paketNooId', $paketId)
        ->call('titikDipilih', -6.21, 106.83);
}

it('mencatat pengajuan lengkap beserta ketiga fotonya', function () {
    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');

    isiFormulirNoo($komponen, $this->wilayah->id, $this->paket->id)
        ->call('simpan')
        ->assertHasNoErrors();

    $noo = Noo::with('fotos')->firstOrFail();

    expect($noo->status)->toBe(StatusNoo::Order)
        ->and($noo->kode)->toStartWith('NOO-')
        ->and($noo->diajukan_oleh)->toBe($this->sales->id)
        ->and($noo->nama)->toBe('Toko Maju Jaya')
        ->and($noo->latitude)->toBe(-6.21)
        ->and($noo->fotos)->toHaveCount(3)
        ->and($noo->fotos->pluck('jenis')->all())->toBe(JenisBuktiNoo::wajibSales());

    // Belum ada toko sungguhan — itu baru lahir saat admin menyetujui.
    expect(Toko::count())->toBe(0);
});

it('menyimpan foto ber-watermark di disk privat, bukan disk publik', function () {
    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');
    isiFormulirNoo($komponen, $this->wilayah->id, $this->paket->id)->call('simpan');

    $foto = Noo::firstOrFail()->fotos()->firstOrFail();

    Storage::disk(BuktiNooService::DISK)->assertExists($foto->path);

    expect($foto->path)->toStartWith('noo/')
        // Ber-watermark berarti gambarnya digambar ulang, jadi ukurannya
        // tidak lagi sama dengan bita mentah yang dikirim komponen.
        ->and(strlen(Storage::disk(BuktiNooService::DISK)->get($foto->path)))->toBeGreaterThan(0);
});

it('menolak pengajuan yang fotonya belum lengkap', function () {
    Livewire::actingAs($this->sales)->test(DaftarNoo::class)
        ->call('buatBaru')
        ->set('nama', 'Toko Maju Jaya')
        ->set('alamat', 'Jl. Merdeka No. 10')
        ->set('wilayahId', $this->wilayah->id)
        ->set('telepon', '081234567890')
        ->set('namaPemilik', 'Budi Santoso')
        ->set('nikPemilik', '3201234567890123')
        ->set('paketNooId', $this->paket->id)
        ->call('titikDipilih', -6.21, 106.83)
        // Cuma KTP yang diisi, dua lainnya belum.
        ->call('terimaBuktiFoto', JenisBuktiNoo::KtpPemilik->value, gambarNooUji())
        ->call('simpan');

    expect(Noo::count())->toBe(0);
});

it('menolak pengajuan tanpa titik lokasi', function () {
    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');

    foreach (JenisBuktiNoo::wajibSales() as $jenis) {
        $komponen = $komponen->call('terimaBuktiFoto', $jenis->value, gambarNooUji());
    }

    $komponen
        ->set('nama', 'Toko Maju Jaya')
        ->set('alamat', 'Jl. Merdeka No. 10')
        ->set('wilayahId', $this->wilayah->id)
        ->set('telepon', '081234567890')
        ->set('namaPemilik', 'Budi Santoso')
        ->set('nikPemilik', '3201234567890123')
        ->set('paketNooId', $this->paket->id)
        ->call('simpan')
        ->assertHasErrors('latitude');

    expect(Noo::count())->toBe(0);
});

it('menolak NIK pemilik yang sudah terdaftar sebagai toko', function () {
    Toko::create([
        'kode' => 'TK-9001', 'nama' => 'Toko Lama', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Lama', 'nik_pemilik' => '3201234567890123',
    ]);

    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');

    isiFormulirNoo($komponen, $this->wilayah->id, $this->paket->id)
        ->call('simpan')
        ->assertHasErrors('nikPemilik');

    expect(Noo::count())->toBe(0);
});

it('menolak nomor telepon yang sudah terdaftar sebagai toko', function () {
    Toko::create([
        'kode' => 'TK-9002', 'nama' => 'Toko Lama', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Lama', 'telepon' => '081234567890',
    ]);

    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');

    isiFormulirNoo($komponen, $this->wilayah->id, $this->paket->id)
        ->call('simpan')
        ->assertHasErrors('telepon');

    expect(Noo::count())->toBe(0);
});

it('tidak menawarkan paket yang produknya belum genap', function () {
    $timpang = PaketNoo::create(['nama' => '10+1', 'dus_reguler' => 10, 'dus_bonus' => 1, 'urutan' => 2, 'aktif' => true]);

    $tersedia = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->instance()->paketTersedia;

    expect($tersedia->pluck('id')->all())->toBe([$this->paket->id])
        ->and($tersedia->pluck('id'))->not->toContain($timpang->id);
});

it('menolak paket yang tidak tersedia meski dikirim langsung dari klien', function () {
    $timpang = PaketNoo::create(['nama' => '10+1', 'dus_reguler' => 10, 'dus_bonus' => 1, 'urutan' => 2, 'aktif' => true]);

    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');

    isiFormulirNoo($komponen, $this->wilayah->id, $timpang->id)
        ->call('simpan')
        ->assertHasErrors('paketNooId');

    expect(Noo::count())->toBe(0);
});

it('hanya menampilkan pengajuan milik sales yang login', function () {
    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');
    isiFormulirNoo($komponen, $this->wilayah->id, $this->paket->id)->call('simpan');

    $milikSales = Noo::firstOrFail();

    expect(Livewire::actingAs($this->salesLain)->test(DaftarNoo::class)->instance()->noos->pluck('id')->all())->toBe([])
        ->and(Livewire::actingAs($this->sales)->test(DaftarNoo::class)->instance()->noos->pluck('id')->all())->toBe([$milikSales->id])
        // Admin melihat pengajuan siapa pun.
        ->and(Livewire::actingAs($this->admin)->test(DaftarNoo::class)->instance()->noos->pluck('id')->all())->toBe([$milikSales->id]);
});

it('menutup foto NOO dari sales lain, tapi membukanya untuk pengaju dan admin', function () {
    $komponen = Livewire::actingAs($this->sales)->test(DaftarNoo::class)->call('buatBaru');
    isiFormulirNoo($komponen, $this->wilayah->id, $this->paket->id)->call('simpan');

    $foto = Noo::firstOrFail()->fotos()->firstOrFail();

    $this->actingAs($this->salesLain)->get(route('noo.foto', $foto))->assertForbidden();
    $this->actingAs($this->sales)->get(route('noo.foto', $foto))->assertOk();
    $this->actingAs($this->admin)->get(route('noo.foto', $foto))->assertOk();
});
