<?php

namespace Tests;

use App\Models\Depot;
use App\Models\PengaturanKunjungan;
use App\Support\DepotContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected Depot $depot;

    protected function setUp(): void
    {
        parent::setUp();

        // tests/Pest.php memasang RefreshDatabase hanya untuk grup 'Feature'
        // — test Unit boot aplikasi yang sama tapi tidak pernah migrasi
        // basis datanya. Tanpa cek ini, setup depot di bawah akan gagal
        // dengan "no such table: depots" di setiap test Unit.
        if (! Schema::hasTable('depots')) {
            return;
        }

        // Depot default untuk seluruh test yang tidak sengaja menguji lebih
        // dari satu depot — dibuat di sini sekali (bukan per-file) supaya
        // seluruh test yang sudah ada sebelum multi-depot tetap jalan tanpa
        // diedit satupun, lewat mekanisme auto-stamp BerDepot yang sama
        // seperti produksi. Untuk menguji isolasi lintas-depot yang
        // sesungguhnya (dua depot sekaligus), lihat
        // tests/Feature/DepotIsolationTest.php — test ini sengaja
        // mengganti konteksnya sendiri lewat DepotContext::jalankanSebagai().
        $this->depot = Depot::factory()->create();

        DepotContext::pakai($this->depot);

        // Setiap depot nyata selalu punya tepat satu baris pengaturan
        // (lihat DepotService::buat() — belum ada di titik ini, tapi
        // invariant-nya sama): tanpa ini, PengaturanKunjungan::ambil()
        // yang dipakai banyak halaman kunjungan akan gagal menemukan
        // baris apapun untuk depot baru ini.
        PengaturanKunjungan::create(['maks_toko_per_hari' => 20]);
    }
}
