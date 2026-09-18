<?php

use App\Enums\JenisCatatanBbm;
use App\Enums\LevelBahanBakar;
use App\Models\CatatanBbm;
use App\Models\Kendaraan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Layanan routing membaca config('ond.*'), jadi tes unit pun perlu aplikasi
// yang sudah boot. Basis data tidak disentuh di sini.
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Melewati gerbang wajib foto KM+BBM sebelum berangkat (App\Livewire\Driver\CekKendaraan)
 * dengan mencatat baris berangkat langsung -- dipakai tes-tes layar
 * pengiriman driver yang bukan tentang gerbang itu sendiri (lihat
 * tests/Feature/CekKendaraanTest.php untuk tes gerbangnya).
 */
function catatBerangkatKendaraan(Kendaraan $kendaraan, User $driver): CatatanBbm
{
    return CatatanBbm::create([
        'kendaraan_id' => $kendaraan->id,
        'jenis' => JenisCatatanBbm::Berangkat,
        'foto' => 'test-berangkat.jpg',
        'km' => 1000,
        'level_bbm' => LevelBahanBakar::Penuh,
        'dicatat_oleh' => $driver->id,
    ]);
}
