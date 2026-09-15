<?php

use App\Akses\HakAkses;
use App\Enums\JenisPesanan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\HakAkses\KelolaHakAkses;
use App\Livewire\Hr\DaftarKaryawan;
use App\Livewire\Pesanan\DaftarPesanan;
use App\Models\Department;
use App\Models\HakAksesPeran;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\DepotContext;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    foreach (PeranPengguna::cases() as $peran) {
        $this->{$peran->value} = User::factory()->create(['role' => $peran]);
    }
});

function aturAkses(User $superadmin, string $peran, array $isian): void
{
    $uji = Livewire::actingAs($superadmin)->test(KelolaHakAkses::class)->call('pilihPeran', $peran);

    foreach ($isian as $menu => $nilai) {
        foreach ($nilai as $kolom => $isi) {
            $uji->set('isian.'.KelolaHakAkses::kunciIsian($menu).'.'.$kolom, $isi);
        }
    }

    $uji->call('simpan')->assertHasNoErrors();
}

it('hanya superadmin yang bisa membuka Hak Akses Management', function () {
    foreach (['admin', 'sales', 'driver', 'hr'] as $peran) {
        $this->actingAs($this->{$peran})->get(route('hak-akses.kelola'))->assertForbidden();
    }

    $this->actingAs($this->superadmin)->get(route('hak-akses.kelola'))
        ->assertOk()
        ->assertSee(__('nav.hak_akses'));
});

it('mencabut akses menyembunyikan menu dan menolak rutenya, lalu kembali ke bawaan', function () {
    aturAkses($this->superadmin, 'sales', ['ond.lengkapi_data_toko' => ['boleh' => false]]);

    expect(HakAksesPeran::count())->toBe(1);

    $this->actingAs($this->sales)->get(route('toko.lengkapi-data'))->assertForbidden();
    $this->actingAs($this->sales)->get(route('pesanan.buat'))->assertOk()
        ->assertDontSee(route('toko.lengkapi-data'));

    // Mengembalikan centang ke bawaannya menghapus barisnya — tidak ada
    // pengecualian yang tertinggal.
    aturAkses($this->superadmin, 'sales', ['ond.lengkapi_data_toko' => ['boleh' => true]]);

    expect(HakAksesPeran::count())->toBe(0);
    $this->actingAs($this->sales)->get(route('toko.lengkapi-data'))->assertOk();
});

it('memberi akses menu baru membuat aplikasinya muncul', function () {
    aturAkses($this->superadmin, 'hr', ['ond.master_wilayah' => ['boleh' => true]]);

    $halaman = $this->actingAs($this->hr)->get(route('master.wilayah'))->assertOk();

    $halaman->assertSee(__('nav.aplikasi_ond'))
        ->assertSee(route('master.wilayah'))
        ->assertDontSee(route('master.toko'));
});

it('kembalikan ke bawaan menghapus seluruh pengecualian peran itu saja', function () {
    aturAkses($this->superadmin, 'sales', ['ond.tugas' => ['boleh' => false]]);
    aturAkses($this->superadmin, 'admin', ['ond.pos' => ['boleh' => false]]);

    Livewire::actingAs($this->superadmin)->test(KelolaHakAkses::class)
        ->call('pilihPeran', 'sales')
        ->call('kembalikanBawaan');

    expect(HakAksesPeran::pluck('peran')->all())->toBe(['admin']);
    $this->actingAs($this->sales)->get(route('kunjungan.tugas'))->assertOk();
    $this->actingAs($this->admin)->get(route('pos.kasir'))->assertForbidden();
});

it('beranda pindah ke menu pertama yang masih boleh bila beranda bawaan dicabut', function () {
    aturAkses($this->superadmin, 'sales', ['ond.input_pesanan' => ['boleh' => false]]);

    $this->actingAs($this->sales)->get('/')->assertRedirect(route('kunjungan.kunjungi'));
});

it('tanpa tabel pengaturan (migrasi belum jalan) semua akses tetap bawaan', function () {
    Schema::drop('hak_akses_perans');
    app(HakAkses::class)->lupakan();

    expect(app(HakAkses::class)->boleh($this->sales, 'ond.pesanan'))->toBeTrue()
        ->and(app(HakAkses::class)->boleh($this->sales, 'ond.pos'))->toBeFalse();

    $this->actingAs($this->sales)->get(route('pesanan.daftar'))->assertOk();
    $this->actingAs($this->sales)->get(route('pos.kasir'))->assertForbidden();
});

it('cakupan data "hanya milik sendiri" menyaring pesanan', function () {
    $salesLain = User::factory()->create(['role' => PeranPengguna::Sales]);
    $wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah']);
    $toko = Toko::create(['kode' => 'TK-HA1', 'nama' => 'Toko HA', 'wilayah_id' => $wilayah->id, 'alamat' => 'Jl. HA']);

    $buat = fn (string $kode, User $pembuat) => Pesanan::create([
        'kode' => $kode, 'toko_id' => $toko->id, 'wilayah_id' => $wilayah->id, 'dibuat_oleh' => $pembuat->id,
        'status' => StatusPesanan::Cancel, 'jenis' => JenisPesanan::Normal, 'tanggal' => today(),
        'total_dus' => 1, 'total_nilai' => 1000,
    ]);

    $milikSaya = $buat('PSN-HA-1', $this->sales);
    $milikLain = $buat('PSN-HA-2', $salesLain);

    $terlihat = fn () => collect(Livewire::actingAs($this->sales)->test(DaftarPesanan::class)->instance()->pesanans->items())->pluck('id')->sort()->values()->all();

    expect($terlihat())->toBe([$milikSaya->id, $milikLain->id]);

    aturAkses($this->superadmin, 'sales', ['ond.pesanan' => ['boleh' => true, 'cakupan' => 'sendiri']]);

    expect($terlihat())->toBe([$milikSaya->id])
        ->and(HakAksesPeran::first()->cakupan->value)->toBe('sendiri');
});

it('cakupan data "hanya milik sendiri" menyaring karyawan', function () {
    $department = Department::create(['kode' => 'D1', 'nama' => 'Dept']);
    $jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Jabatan']);

    $buat = fn (string $kode, ?User $akun) => Karyawan::create([
        'kode_karyawan' => $kode, 'nama_lengkap' => 'Karyawan '.$kode, 'nik' => str_pad($kode, 16, '0', STR_PAD_LEFT),
        'jenis_kelamin' => 'L', 'tanggal_lahir' => '1990-01-01', 'no_hp' => '0812', 'alamat_domisili' => 'Jl.',
        'department_id' => $department->id, 'jabatan_id' => $jabatan->id, 'tanggal_masuk' => '2024-01-01',
        'status_karyawan' => 'tetap', 'gaji_pokok' => 1, 'depot_id' => DepotContext::currentOrFail()->id,
        'user_id' => $akun?->id,
    ]);

    $saya = $buat('K1', $this->hr);
    $buat('K2', null);

    aturAkses($this->superadmin, 'hr', ['hr.karyawan' => ['boleh' => true, 'cakupan' => 'sendiri']]);

    $terlihat = collect(Livewire::actingAs($this->hr)->test(DaftarKaryawan::class)->instance()->karyawans->items())->pluck('id')->all();

    expect($terlihat)->toBe([$saya->id]);
});

it('supervisor bawaannya membuka Pesanan dan Lengkapi Data Toko saja', function () {
    $this->actingAs($this->supervisor)->get('/')->assertRedirect(route('pesanan.daftar'));
    $this->actingAs($this->supervisor)->get(route('pesanan.daftar'))->assertOk();
    $this->actingAs($this->supervisor)->get(route('toko.lengkapi-data'))->assertOk()
        ->assertSee(route('pesanan.daftar'))
        ->assertDontSee(route('pesanan.buat'));
    $this->actingAs($this->supervisor)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($this->supervisor)->get(route('hak-akses.kelola'))->assertForbidden();
});

it('superadmin tidak pernah terkena pengecualian dan tidak ada di matriks', function () {
    expect(KelolaHakAkses::peranBisaDiatur())->not->toContain('superadmin')
        ->and(app(HakAkses::class)->boleh($this->superadmin, 'user_admin.hak_akses'))->toBeTrue();
});
