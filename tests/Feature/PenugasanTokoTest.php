<?php

use App\Enums\HariKunjungan;
use App\Enums\PeranPengguna;
use App\Models\PengaturanKunjungan;
use App\Models\PenugasanToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\Kunjungan\PenugasanTokoService;

/**
 * Jadwal kunjungan MINGGUAN per hari — lihat dokumentasi `PenugasanToko`
 * dan `PenugasanTokoService`. Menggantikan cakupan pengujian penugasan
 * bulanan lama (`PenugasanService`, dihapus bersama redesain ini).
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Satu']);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sales Dua']);
    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    $this->service = app(PenugasanTokoService::class);
});

function buatTokoPT(string $assetId, string $nama): Toko
{
    return Toko::create([
        'kode' => 'TK-'.substr($assetId, -4),
        'asset_id' => $assetId,
        'nama' => $nama,
        'wilayah_id' => test()->wilayah->id,
        'alamat' => 'Jl. Uji No. 1',
        'latitude' => -6.18,
        'longitude' => 106.83,
        'sumber_koordinat' => 'manual',
    ]);
}

it('menolak penugasan melebihi batas per hari', function () {
    $tokoIds = [];

    for ($i = 1; $i <= 3; $i++) {
        $tokoIds[] = buatTokoPT(sprintf('IDNAH20252800%04d', $i), "Toko {$i}")->id;
    }

    PengaturanKunjungan::ambil()->update(['maks_toko_per_hari' => 2]);

    expect(fn () => $this->service->tetapkan(
        $this->sales, HariKunjungan::Senin, $tokoIds, $this->admin,
    ))->toThrow(RuntimeException::class);

    expect(PenugasanToko::count())->toBe(0);
});

it('mencegah satu toko dipegang dua sales, walau pada hari yang berbeda', function () {
    $toko = buatTokoPT('IDNAH202528000001', 'Toko A');

    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$toko->id], $this->admin);
    $hasil = $this->service->tetapkan($this->salesLain, HariKunjungan::Selasa, [$toko->id], $this->admin);

    expect($hasil['ditambah'])->toBe(0)
        ->and($hasil['ditolak'])->toHaveCount(1)
        ->and(PenugasanToko::where('toko_id', $toko->id)->count())->toBe(1)
        ->and(PenugasanToko::where('toko_id', $toko->id)->value('sales_id'))->toBe($this->sales->id);
});

it('mengganti daftar lama satu hari saat penugasan disimpan ulang, hari lain tidak tersentuh', function () {
    $a = buatTokoPT('IDNAH202528000001', 'Toko A');
    $b = buatTokoPT('IDNAH202528000002', 'Toko B');
    $c = buatTokoPT('IDNAH202528000003', 'Toko C');
    $d = buatTokoPT('IDNAH202528000004', 'Toko D');

    $this->service->tetapkan($this->sales, HariKunjungan::Selasa, [$d->id], $this->admin);
    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$a->id, $b->id], $this->admin);
    $hasil = $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$b->id, $c->id], $this->admin);

    expect($hasil['ditambah'])->toBe(1)
        ->and($hasil['dihapus'])->toBe(1)
        ->and(PenugasanToko::where('sales_id', $this->sales->id)->where('hari', HariKunjungan::Senin->value)
            ->pluck('toko_id')->sort()->values()->all())->toBe([$b->id, $c->id])
        ->and(PenugasanToko::where('sales_id', $this->sales->id)->where('hari', HariKunjungan::Selasa->value)
            ->pluck('toko_id')->all())->toBe([$d->id]);
});

it('menyembunyikan toko yang sudah dipegang sales/hari lain dari daftar pilihan, tapi tetap menampilkan pilihan slot sendiri', function () {
    $a = buatTokoPT('IDNAH202528000001', 'Toko A');
    $b = buatTokoPT('IDNAH202528000002', 'Toko B');
    $c = buatTokoPT('IDNAH202528000003', 'Toko C');

    $this->service->tetapkan($this->salesLain, HariKunjungan::Senin, [$a->id], $this->admin);
    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$c->id], $this->admin);

    $tersedia = $this->service->tokoTersedia(HariKunjungan::Senin, $this->sales->id)->pluck('id')->all();

    expect($tersedia)->toContain($b->id)
        ->and($tersedia)->toContain($c->id) // milik slot (sales, hari) ini sendiri — tetap tampil
        ->and($tersedia)->not->toContain($a->id);
});

it('sekali toko dijadwalkan di satu hari, tidak bisa masuk hari lain untuk sales yang sama', function () {
    $toko = buatTokoPT('IDNAH202528000001', 'Toko A');

    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$toko->id], $this->admin);
    $hasil = $this->service->tetapkan($this->sales, HariKunjungan::Selasa, [$toko->id], $this->admin);

    expect($hasil['ditambah'])->toBe(0)
        ->and($hasil['ditolak'])->toHaveCount(1)
        ->and(PenugasanToko::where('toko_id', $toko->id)->value('hari'))->toBe(HariKunjungan::Senin);
});

it('jadikanDefault menyimpan jadwal berjalan, restoreDefault mengembalikannya', function () {
    $a = buatTokoPT('IDNAH202528000001', 'Toko A');
    $b = buatTokoPT('IDNAH202528000002', 'Toko B');

    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$a->id], $this->admin);
    $this->service->tetapkan($this->sales, HariKunjungan::Selasa, [$b->id], $this->admin);
    $this->service->jadikanDefault($this->sales);

    // Diubah dulu jadi bentuk lain...
    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [], $this->admin);
    $this->service->tetapkan($this->sales, HariKunjungan::Rabu, [$a->id], $this->admin);

    // ...lalu dikembalikan ke default.
    $hasil = $this->service->restoreDefault($this->sales, $this->admin);

    expect($hasil['dipulihkan'])->toBe(2)
        ->and(PenugasanToko::where('sales_id', $this->sales->id)->where('toko_id', $a->id)->value('hari'))
        ->toBe(HariKunjungan::Senin)
        ->and(PenugasanToko::where('sales_id', $this->sales->id)->where('toko_id', $b->id)->value('hari'))
        ->toBe(HariKunjungan::Selasa);
});

it('jadikanDefault menimpa default lama sepenuhnya, bukan menambah', function () {
    $a = buatTokoPT('IDNAH202528000001', 'Toko A');
    $b = buatTokoPT('IDNAH202528000002', 'Toko B');

    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$a->id], $this->admin);
    $this->service->jadikanDefault($this->sales);

    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [], $this->admin);
    $this->service->tetapkan($this->sales, HariKunjungan::Selasa, [$b->id], $this->admin);
    $this->service->jadikanDefault($this->sales);

    $hasil = $this->service->restoreDefault($this->sales, $this->admin);

    expect($hasil['dipulihkan'])->toBe(1)
        ->and(PenugasanToko::where('sales_id', $this->sales->id)->pluck('toko_id')->all())->toBe([$b->id]);
});

it('restoreDefault melaporkan toko yang sudah direbut sales lain sejak default disimpan, bukan merebutnya paksa', function () {
    $toko = buatTokoPT('IDNAH202528000001', 'Toko A');

    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$toko->id], $this->admin);
    $this->service->jadikanDefault($this->sales);

    // Toko dilepas dari sales, lalu direbut sales lain.
    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [], $this->admin);
    $this->service->tetapkan($this->salesLain, HariKunjungan::Rabu, [$toko->id], $this->admin);

    $hasil = $this->service->restoreDefault($this->sales, $this->admin);

    expect($hasil['dipulihkan'])->toBe(0)
        ->and($hasil['ditolak'])->toHaveCount(1)
        ->and(PenugasanToko::where('toko_id', $toko->id)->value('sales_id'))->toBe($this->salesLain->id);
});

it('restoreDefault menolak kalau belum pernah ada default tersimpan', function () {
    expect(fn () => $this->service->restoreDefault($this->sales, $this->admin))
        ->toThrow(RuntimeException::class);
});

it('jumlahPerSales dan jumlahPerHariUntukSales menghitung dengan benar', function () {
    $a = buatTokoPT('IDNAH202528000001', 'Toko A');
    $b = buatTokoPT('IDNAH202528000002', 'Toko B');
    $c = buatTokoPT('IDNAH202528000003', 'Toko C');

    $this->service->tetapkan($this->sales, HariKunjungan::Senin, [$a->id, $b->id], $this->admin);
    $this->service->tetapkan($this->sales, HariKunjungan::Selasa, [$c->id], $this->admin);

    expect($this->service->jumlahPerSales()[$this->sales->id])->toBe(3);

    $perHari = $this->service->jumlahPerHariUntukSales($this->sales->id);

    expect($perHari[HariKunjungan::Senin->value])->toBe(2)
        ->and($perHari[HariKunjungan::Selasa->value])->toBe(1)
        ->and($perHari[HariKunjungan::Rabu->value])->toBe(0);
});

it('ubahMaksPerHari menyimpan batas baru dan menolak nilai tidak valid', function () {
    $this->service->ubahMaksPerHari(5);

    expect($this->service->maksPerHari())->toBe(5)
        ->and(fn () => $this->service->ubahMaksPerHari(0))->toThrow(RuntimeException::class);
});
