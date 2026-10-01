<?php

use App\Enums\HariKunjungan;
use App\Enums\PeranPengguna;
use App\Livewire\Kunjungan\TugasSaya;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Livewire\Toko\LengkapiData;
use App\Models\PenugasanToko;
use App\Models\PenugasanTokoDefault;
use App\Models\PeriodeSales;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kunjungan\PenugasanTokoService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Livewire\Livewire;

/**
 * Toko nonaktif tidak boleh muncul di menu mana pun, termasuk rute
 * kunjungan sales: begitu dinonaktifkan, jadwalnya dilepas permanen
 * (diaktifkan lagi = ditugaskan ulang oleh admin).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->penugasan = app(PenugasanTokoService::class);

    $this->tokoA = Toko::create(['kode' => 'TK-NA1', 'nama' => 'Toko Tetap Aktif', 'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. A', 'asset_id' => 'IDNAH000000000001']);
    $this->tokoB = Toko::create(['kode' => 'TK-NA2', 'nama' => 'Toko Akan Nonaktif', 'wilayah_id' => $this->wilayah->id, 'alamat' => 'Jl. B', 'asset_id' => 'IDNAH000000000002']);

    $this->penugasan->tetapkan($this->sales, HariKunjungan::Senin, [$this->tokoA->id, $this->tokoB->id], $this->admin);
    $this->penugasan->jadikanDefault($this->sales);
});

it('menonaktifkan toko melepas jadwal kunjungan & jadwal default-nya otomatis', function () {
    $this->tokoB->update(['aktif' => false]);

    expect(PenugasanToko::where('toko_id', $this->tokoB->id)->exists())->toBeFalse()
        ->and(PenugasanTokoDefault::where('toko_id', $this->tokoB->id)->exists())->toBeFalse()
        // Toko lain milik sales yang sama tidak ikut terlepas.
        ->and(PenugasanToko::where('toko_id', $this->tokoA->id)->exists())->toBeTrue();
});

it('toko nonaktif hilang dari rute kunjungan sales (Tugas Saya)', function () {
    Livewire::actingAs($this->sales)->test(TugasSaya::class)
        ->set('saringHari', 'minggu')
        ->assertSee('Toko Akan Nonaktif');

    $this->tokoB->update(['aktif' => false]);

    Livewire::actingAs($this->sales)->test(TugasSaya::class)
        ->set('saringHari', 'minggu')
        ->assertSee('Toko Tetap Aktif')
        ->assertDontSee('Toko Akan Nonaktif');
});

it('target kunjungan minggu berjalan ikut turun', function () {
    $periodeService = app(PeriodeKunjunganService::class);
    $periode = $periodeService->periodeBerjalan();
    $periodeService->segarkanTarget($periode);

    $target = fn () => PeriodeSales::where('periode_kunjungan_id', $periode->id)->where('sales_id', $this->sales->id)->value('target_toko');
    expect($target())->toBe(2);

    $this->tokoB->update(['aktif' => false]);

    expect($target())->toBe(1);
});

it('mengaktifkan lagi tidak memulihkan jadwal — harus ditugaskan ulang admin', function () {
    $this->tokoB->update(['aktif' => false]);
    $this->tokoB->update(['aktif' => true]);

    expect(PenugasanToko::where('toko_id', $this->tokoB->id)->exists())->toBeFalse();

    // Slot-nya sudah bebas, jadi bisa langsung ditugaskan ke sales mana pun.
    $salesLain = User::factory()->create(['role' => PeranPengguna::Sales]);
    $hasil = $this->penugasan->tetapkan($salesLain, HariKunjungan::Selasa, [$this->tokoB->id], $this->admin);

    expect($hasil['ditambah'])->toBe(1);
});

it('perubahan lain pada toko aktif tidak menyentuh jadwalnya', function () {
    $this->tokoA->update(['nama' => 'Toko Ganti Nama']);

    expect(PenugasanToko::where('toko_id', $this->tokoA->id)->exists())->toBeTrue();
});

it('pulihkan default tidak memasukkan toko nonaktif yang tersisa di default lama', function () {
    // Mensimulasikan data lama (sebelum aturan ini): toko nonaktif masih
    // tercantum di default — dinonaktifkan lewat query mentah tanpa event.
    Toko::whereKey($this->tokoB->id)->toBase()->update(['aktif' => false]);

    $hasil = $this->penugasan->restoreDefault($this->sales, $this->admin);

    expect($hasil['dipulihkan'])->toBe(1)
        ->and(PenugasanToko::where('toko_id', $this->tokoB->id)->exists())->toBeFalse();
});

it('lapisan pengaman: jadwal yang tersisa untuk toko nonaktif tetap tidak tampil di menu', function () {
    // Nonaktif tanpa event model — jadwalnya sengaja dibiarkan tersisa.
    Toko::whereKey($this->tokoB->id)->toBase()->update(['aktif' => false]);

    Livewire::actingAs($this->sales)->test(TugasSaya::class)
        ->set('saringHari', 'minggu')
        ->assertDontSee('Toko Akan Nonaktif');

    Livewire::actingAs($this->sales)->test(LengkapiData::class)
        ->assertDontSee('Toko Akan Nonaktif');

    Livewire::actingAs($this->admin)->test(DaftarPesanan::class)
        ->call('bukaTokoTidakAktif')
        ->assertDontSee('Toko Akan Nonaktif')
        ->assertSee('Toko Tetap Aktif');
});
