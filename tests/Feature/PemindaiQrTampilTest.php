<?php

use App\Enums\PeranPengguna;
use App\Models\PenugasanSales;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Carbon\CarbonImmutable;

/**
 * Menjaga agar wadah pemindai QR tidak pernah lagi disembunyikan lewat kelas
 * yang dihitung di sisi server.
 *
 * `wire:ignore` membuat Livewire melewati elemen itu sepenuhnya — termasuk
 * atribut class-nya. Kelas yang dihitung Blade karena itu membeku pada nilai
 * saat halaman pertama digambar: pemindai yang mula-mula tersembunyi tidak
 * akan pernah muncul, sekeras apa pun tombolnya ditekan.
 *
 * Kerusakan seperti ini tidak menimbulkan galat apa pun. Tidak ada tanda di
 * log, tidak ada halaman error — kameranya hanya tidak muncul. Karena itu
 * bentuk penandanya diperiksa langsung di sini.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);

    $toko = Toko::create([
        'kode' => 'TK-0001',
        'asset_id' => 'IDNAH202528004381',
        'nama' => 'Toko Uji',
        'wilayah_id' => $wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'sumber_koordinat' => 'belum',
    ]);

    PenugasanSales::create([
        'sales_id' => $this->sales->id,
        'toko_id' => $toko->id,
        'bulan' => CarbonImmutable::today()->startOfMonth()->toDateString(),
        'ditugaskan_oleh' => $this->admin->id,
    ]);
});

/** Mengambil tag pembuka sebuah elemen ber-id dari HTML. */
function tagPembuka(string $html, string $id): string
{
    preg_match('/<div wire:ignore id="'.preg_quote($id, '/').'"[\s\S]{0,400}?>/', $html, $cocok);

    return preg_replace('/\s+/', ' ', $cocok[0] ?? '') ?? '';
}

it('menampilkan wadah pemindai tanpa kelas sembunyi dari server', function (string $rute, string $id) {
    $html = $this->actingAs($this->sales)->get(route($rute))->assertOk()->getContent();

    $tag = tagPembuka($html, $id);

    expect($tag)->not->toBe('', "Wadah pemindai #{$id} tidak ditemukan pada halaman {$rute}.");

    // Kelas 'hidden' dari server tidak akan pernah dilepas Livewire. Pesannya
    // ditaruh pada toBe, karena toContain memperlakukan argumen kedua sebagai
    // kata pencarian tambahan, bukan keterangan kegagalan.
    expect(str_contains($tag, 'hidden'))->toBe(
        false,
        "Wadah #{$id} disembunyikan lewat kelas dari server; wire:ignore membuat kelas itu tidak pernah berubah.",
    );

    // Tampil-sembunyinya harus dikendalikan Alpine, yang berjalan di peramban
    // dan tidak dihalangi wire:ignore.
    expect(str_contains($tag, 'x-show'))->toBe(
        true,
        "Wadah #{$id} tidak punya x-show, jadi tidak ada yang mengendalikan tampilnya.",
    );
})->with([
    'halaman input pesanan' => ['pesanan.buat', 'pemindai-toko'],
    'halaman kunjungan sales' => ['kunjungan.kunjungi', 'pemindai-qr'],
]);

it('mematikan kamera lewat Alpine ketika pemindai tersembunyi', function (string $rute, string $id) {
    $html = $this->actingAs($this->sales)->get(route($rute))->assertOk()->getContent();

    // Tanpa ini lampu kamera tetap menyala setelah pemindai ditutup.
    expect(str_contains(tagPembuka($html, $id), 'x-effect'))->toBe(
        true,
        "Wadah #{$id} tidak mematikan kamera saat tersembunyi.",
    );
})->with([
    'halaman input pesanan' => ['pesanan.buat', 'pemindai-toko'],
    'halaman kunjungan sales' => ['kunjungan.kunjungi', 'pemindai-qr'],
]);

it('tidak pernah memanggil video.play() tanpa penangkap kegagalan', function () {
    // Safari di iOS kerap menolak play() karena konteks sentuhan dianggap
    // hilang setelah menunggu getUserMedia. Penolakan itu berupa Promise yang
    // gagal: bila di-await tanpa penangkap, seluruh penyalaan kamera berhenti
    // diam-diam — tanpa gambar dan tanpa pesan apa pun.
    //
    // Semua pemutaran karena itu harus lewat mainkanVideo(), yang menangkap
    // kegagalannya dan melaporkannya sebagai nilai.
    $bermasalah = [];

    foreach (File::allFiles(resource_path('js')) as $berkas) {
        if ($berkas->getFilename() === 'kamera-util.js') {
            continue;
        }

        if (str_contains($berkas->getContents(), 'video.play()')) {
            $bermasalah[] = $berkas->getRelativePathname();
        }
    }

    expect($bermasalah)->toBe(
        [],
        'Berkas berikut memanggil video.play() langsung, bukan lewat mainkanVideo(): '.implode(', ', $bermasalah),
    );
});

it('memakai penanda berhasil yang tidak bergantung pada getaran', function () {
    // Safari di iOS tidak mengenal Vibration API sama sekali, jadi getaran
    // saja membuat pengguna iPhone kehilangan satu-satunya tanda bahwa kode
    // sudah terbaca.
    $pemindai = File::get(resource_path('js/pemindai-qr.js'));

    expect(str_contains($pemindai, 'tandaBerhasil'))->toBe(true)
        ->and(str_contains($pemindai, 'navigator.vibrate'))->toBe(
            false,
            'Pemindai masih bergantung langsung pada getaran, yang tidak ada di iOS.',
        );
});

it('menampilkan pemberitahuan melayang agar terlihat di mana pun halaman digulir', function () {
    $html = $this->actingAs($this->sales)->get(route('kunjungan.kunjungi'))->assertOk()->getContent();

    // Di ponsel, layar kerap sedang menampilkan jendela kamera di bagian bawah
    // halaman. Pesan yang tergambar menyatu di puncak halaman berada di luar
    // pandangan, dan pengguna menyimpulkan tombolnya tidak berfungsi.
    preg_match('/<template x-if="pesan">[\s\S]{0,400}?<div[^>]*>/', $html, $cocok);

    expect($cocok[0] ?? '')->not->toBe('', 'Templat pemberitahuan tidak ditemukan.');
    expect(str_contains($cocok[0], 'fixed'))->toBe(
        true,
        'Pemberitahuan tidak melayang, jadi bisa berada di luar layar saat halaman digulir.',
    );
});

it('tidak menyisakan wire:ignore yang kelasnya dihitung Blade', function () {
    $bermasalah = [];

    foreach (File::allFiles(resource_path('views')) as $berkas) {
        $isi = $berkas->getContents();

        // Pola: wire:ignore ... class="{{ ... }}" pada tag yang sama.
        if (preg_match('/wire:ignore[^>]*class="\{\{/', $isi)) {
            $bermasalah[] = $berkas->getRelativePathname();
        }
    }

    expect($bermasalah)->toBe(
        [],
        'Berkas berikut menyembunyikan elemen wire:ignore lewat kelas dari server: '.implode(', ', $bermasalah),
    );
});
