<?php

use App\Enums\PeranPengguna;
use App\Enums\StatusNoo;
use App\Livewire\Noo\Persetujuan;
use App\Models\Noo;
use App\Models\PaketNoo;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Noo\NooService;
use Livewire\Livewire;

/**
 * Keputusan admin atas calon mitra baru.
 *
 * Titik paling menentukan: menyetujui berarti membuat baris `tokos` yang
 * sungguhan — tapi dalam keadaan BELUM aktif dan tanpa nomor freezer, karena
 * freezernya memang belum terpasang. Toko yang belum aktif tidak muncul di
 * pencarian Input Pesanan maupun ikut routing reguler.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->produk = Produk::create(['kode' => 'P1', 'nama' => 'Es Krim Cokelat', 'stok' => 500, 'harga' => 40_000]);

    $this->paket = PaketNoo::create(['nama' => '15+2', 'dus_reguler' => 15, 'dus_bonus' => 2, 'urutan' => 1, 'aktif' => true]);
    $this->paket->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => 15, 'is_bonus' => false]);
    $this->paket->items()->create(['produk_id' => $this->produk->id, 'jumlah_dus' => 2, 'is_bonus' => true]);
});

/** NOO berstatus Order yang siap diputuskan, tanpa lewat layar sales. */
function buatNooMenunggu(array $ubah = []): Noo
{
    return Noo::create([
        'kode' => 'NOO-20260922-'.str_pad((string) (Noo::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
        'status' => StatusNoo::Order,
        'paket_noo_id' => test()->paket->id,
        'nama' => 'Toko Maju Jaya',
        'alamat' => 'Jl. Merdeka No. 10',
        'wilayah_id' => test()->wilayah->id,
        'telepon' => '081234567890',
        'nama_pemilik' => 'Budi Santoso',
        'nik_pemilik' => '3201234567890123',
        'latitude' => -6.2100000,
        'longitude' => 106.8300000,
        'sumber_koordinat' => 'manual',
        'diajukan_oleh' => test()->sales->id,
        'diajukan_at' => now(),
        ...$ubah,
    ]);
}

it('membuat toko nonaktif tanpa IDN begitu disetujui', function () {
    $noo = buatNooMenunggu();

    Livewire::actingAs($this->admin)->test(Persetujuan::class)
        ->call('pilih', $noo->id)
        ->call('setujui')
        ->assertHasNoErrors();

    $noo = $noo->fresh();
    $toko = Toko::firstOrFail();

    expect($noo->status)->toBe(StatusNoo::Process)
        ->and($noo->toko_id)->toBe($toko->id)
        ->and($noo->disetujui_oleh)->toBe($this->admin->id)
        ->and($toko->nama)->toBe('Toko Maju Jaya')
        ->and($toko->kode)->toStartWith('TK-')
        // Inti keputusan desainnya: tokonya ada, tapi belum boleh dipesani.
        ->and($toko->aktif)->toBeFalse()
        ->and($toko->asset_id)->toBeNull()
        ->and($toko->nik_pemilik)->toBe('3201234567890123');
});

it('memakai deret kode toko yang sama dengan Master Toko', function () {
    Toko::create(['kode' => 'TK-0007', 'nama' => 'Toko Lama', 'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. Lama']);

    $noo = buatNooMenunggu();

    Livewire::actingAs($this->admin)->test(Persetujuan::class)
        ->call('pilih', $noo->id)
        ->call('setujui');

    expect(Toko::where('nama', 'Toko Maju Jaya')->value('kode'))->toBe('TK-0008');
});

it('memperingatkan admin ketika titiknya kurang dari 200 m dari toko aktif', function () {
    // ± 55 m dari titik NOO.
    Toko::create([
        'kode' => 'TK-0500', 'nama' => 'Toko Tetangga', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Sebelah', 'latitude' => -6.2105, 'longitude' => 106.8300,
        'sumber_koordinat' => 'manual', 'aktif' => true,
    ]);

    $noo = buatNooMenunggu();

    $komponen = Livewire::actingAs($this->admin)->test(Persetujuan::class)->call('pilih', $noo->id);

    $peringatan = $komponen->instance()->peringatanJarak;

    expect($peringatan)->not->toBeNull()
        ->and($peringatan['toko']->kode)->toBe('TK-0500')
        ->and($peringatan['jarak'])->toBeLessThan(200);

    // Klik pertama tidak langsung menyetujui — admin harus melewati
    // konfirmasinya dulu.
    $komponen->call('setujui');

    expect($noo->fresh()->status)->toBe(StatusNoo::Order)
        ->and($komponen->get('peringatanTerbuka'))->toBeTrue();

    $komponen->call('setujuiTetap');

    expect($noo->fresh()->status)->toBe(StatusNoo::Process);
});

it('tidak memperingatkan kalau toko terdekat masih nonaktif', function () {
    // Toko calon dari NOO lain yang freezernya belum terpasang: belum jadi
    // mitra, jadi wilayah jualannya belum perlu dilindungi.
    Toko::create([
        'kode' => 'TK-0501', 'nama' => 'Calon Lain', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Sebelah', 'latitude' => -6.2105, 'longitude' => 106.8300,
        'sumber_koordinat' => 'manual', 'aktif' => false,
    ]);

    $noo = buatNooMenunggu();

    $komponen = Livewire::actingAs($this->admin)->test(Persetujuan::class)->call('pilih', $noo->id);

    expect($komponen->instance()->peringatanJarak)->toBeNull();

    $komponen->call('setujui');

    expect($noo->fresh()->status)->toBe(StatusNoo::Process);
});

it('tidak memperingatkan kalau toko aktif terdekat lebih dari 200 m', function () {
    // ± 1,1 km dari titik NOO.
    Toko::create([
        'kode' => 'TK-0502', 'nama' => 'Toko Jauh', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Jauh', 'latitude' => -6.2200, 'longitude' => 106.8300,
        'sumber_koordinat' => 'manual', 'aktif' => true,
    ]);

    $noo = buatNooMenunggu();

    $komponen = Livewire::actingAs($this->admin)->test(Persetujuan::class)->call('pilih', $noo->id);

    expect($komponen->instance()->peringatanJarak)->toBeNull();
});

it('menyimpan koreksi admin beserta jejak siapa dan kapan', function () {
    $noo = buatNooMenunggu();

    Livewire::actingAs($this->admin)->test(Persetujuan::class)
        ->call('pilih', $noo->id)
        ->set('nama', 'Toko Maju Jaya Abadi')
        ->call('simpanPerubahan')
        ->assertHasNoErrors();

    $noo = $noo->fresh();

    expect($noo->nama)->toBe('Toko Maju Jaya Abadi')
        ->and($noo->diubah_admin_oleh)->toBe($this->admin->id)
        ->and($noo->diubah_admin_at)->not->toBeNull()
        // Belum disetujui — koreksi saja.
        ->and($noo->status)->toBe(StatusNoo::Order);
});

it('tidak menandai koreksi kalau tidak ada yang diubah', function () {
    $noo = buatNooMenunggu();

    Livewire::actingAs($this->admin)->test(Persetujuan::class)
        ->call('pilih', $noo->id)
        ->call('simpanPerubahan');

    expect($noo->fresh()->diubah_admin_at)->toBeNull();
});

it('menolak calon beserta alasannya, tanpa membuat toko', function () {
    $noo = buatNooMenunggu();

    Livewire::actingAs($this->admin)->test(Persetujuan::class)
        ->call('pilih', $noo->id)
        ->set('alasanTolak', 'Lokasinya berimpit dengan mitra lama.')
        ->call('tolak')
        ->assertHasNoErrors();

    $noo = $noo->fresh();

    expect($noo->status)->toBe(StatusNoo::Ditolak)
        ->and($noo->alasan_tolak)->toBe('Lokasinya berimpit dengan mitra lama.')
        ->and($noo->ditolak_oleh)->toBe($this->admin->id)
        ->and(Toko::count())->toBe(0);
});

it('menolak penolakan tanpa alasan', function () {
    $noo = buatNooMenunggu();

    Livewire::actingAs($this->admin)->test(Persetujuan::class)
        ->call('pilih', $noo->id)
        ->call('tolak')
        ->assertHasErrors('alasanTolak');

    expect($noo->fresh()->status)->toBe(StatusNoo::Order);
});

it('menolak menyetujui dua kali', function () {
    $noo = buatNooMenunggu();
    app(NooService::class)->setujui($noo, $this->admin);

    expect(fn () => app(NooService::class)->setujui($noo->fresh(), $this->admin))
        ->toThrow(RuntimeException::class);

    expect(Toko::count())->toBe(1);
});

it('menolak menyetujui kalau NIK pemiliknya sudah jadi toko di sela-sela waktu', function () {
    $noo = buatNooMenunggu();

    Toko::create([
        'kode' => 'TK-0600', 'nama' => 'Toko Duluan', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Duluan', 'nik_pemilik' => '3201234567890123',
    ]);

    expect(fn () => app(NooService::class)->setujui($noo, $this->admin))
        ->toThrow(RuntimeException::class);

    expect($noo->fresh()->status)->toBe(StatusNoo::Order);
});

it('hanya menampilkan antrean yang masih menunggu keputusan', function () {
    $menunggu = buatNooMenunggu();
    $ditolak = buatNooMenunggu(['nik_pemilik' => '3209999999999999', 'telepon' => '081200000000']);
    app(NooService::class)->tolak($ditolak, $this->admin, 'Tidak layak.');

    $antrean = Livewire::actingAs($this->admin)->test(Persetujuan::class)->instance()->antrean;

    expect($antrean->pluck('id')->all())->toBe([$menunggu->id]);
});
