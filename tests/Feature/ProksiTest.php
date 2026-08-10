<?php

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Ketika aplikasi dilayani lewat proksi yang menangani HTTPS — cloudflared
 * saat menguji dari ponsel, atau nginx di server — permintaan sampai ke PHP
 * sebagai HTTP biasa.
 *
 * Kalau header penanda dari proksi tidak dipercaya, Laravel menyangka
 * halamannya diakses lewat http dan menuliskan URL aset berawalan http:// di
 * halaman https://. Peramban memblokir muatan campuran seperti itu, dan
 * akibatnya seluruh CSS serta JavaScript tidak termuat — termasuk berkas yang
 * menjalankan kamera. Gejalanya membingungkan karena halamannya sendiri tetap
 * terbuka, hanya tampil polos tanpa gaya.
 */
it('mempercayai proksi dari mesin ini sendiri', function () {
    expect(config('ond.proksi_dipercaya'))->toContain('127.0.0.1');

    // Daftar yang benar-benar dipakai middleware disimpan pada properti
    // statis yang tertutup, jadi dibaca lewat refleksi. Yang diperiksa bukan
    // isi berkas config, melainkan apakah AppServiceProvider sudah benar-benar
    // menerapkannya.
    $properti = new ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies');

    expect($properti->getValue())->toContain('127.0.0.1');
});

/**
 * Mengambil URL milik aplikasi sendiri dari sebuah halaman.
 *
 * Yang diperiksa adalah alamat yang dibentuk Laravel — action formulir, tautan
 * antarhalaman, dan aset. Sengaja tidak mematok pada '/build/', karena bentuk
 * URL aset berubah tergantung Vite sedang berjalan atau tidak; tes ini harus
 * menguji skema URL, bukan cara aset disajikan.
 *
 * @return array<int, string>
 */
function urlAplikasi(string $html, string $host): array
{
    preg_match_all('/(?:href|src|action)="(https?:\/\/'.preg_quote($host, '/').'[^"]*)"/', $html, $cocok);

    return array_values(array_unique($cocok[1]));
}

it('membuat URL https ketika proksi meneruskan permintaan https', function () {
    $html = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'contoh.trycloudflare.com',
        ])
        ->get(route('masuk'))
        ->assertOk()
        ->getContent();

    $url = urlAplikasi($html, 'contoh.trycloudflare.com');

    expect($url)->not->toBeEmpty('Halaman tidak memuat URL aplikasi apa pun.');

    foreach ($url as $satu) {
        expect($satu)->toStartWith('https://', "URL {$satu} memakai http pada halaman https.");
    }
});

it('tetap memakai http ketika diakses langsung tanpa proksi', function () {
    $html = $this->get(route('masuk'))->assertOk()->getContent();

    $url = urlAplikasi($html, parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost');

    expect($url)->not->toBeEmpty();

    foreach ($url as $satu) {
        expect($satu)->toStartWith('http://');
    }
});

it('mengabaikan header palsu dari pengirim yang bukan proksi tepercaya', function () {
    // Permintaan datang dari alamat luar, bukan dari proksi di mesin ini.
    // Header X-Forwarded-* miliknya tidak boleh dipercaya, kalau tidak siapa
    // pun bisa memalsukan asal dan skema permintaan.
    $html = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'penipu.example',
        ])
        ->get(route('masuk'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('penipu.example');
});
