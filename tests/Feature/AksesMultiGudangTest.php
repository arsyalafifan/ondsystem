<?php

use App\Enums\HariKunjungan;
use App\Enums\PeranPengguna;
use App\Livewire\Auth\Login;
use App\Livewire\Depot\DaftarDepot;
use App\Models\Depot;
use App\Models\PenugasanToko;
use App\Models\Scopes\DepotScope;
use App\Models\Toko;
use App\Models\User;
use App\Services\Pengguna\GabungAkunGanda;
use App\Support\DepotContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * Satu akun bisa dipakai di beberapa gudang (tabel depot_user, diatur di
 * User Admin). Login tidak lagi memilih gudang: pengguna langsung masuk ke
 * gudang default-nya (atau gudang pertama menurut nomor urut), dan hanya
 * pengguna dengan lebih dari satu gudang yang melihat pemilih gudang.
 */
beforeEach(function () {
    $this->depot->update(['urutan' => 1, 'nama' => 'Gudang Alpha']);
    $this->depotB = Depot::factory()->create(['kode' => 'DEPOTB', 'nama' => 'Gudang Beta', 'urutan' => 2]);

    Toko::create(['kode' => 'TK-A1', 'nama' => 'Toko Alpha', 'alamat' => 'Jl. A']);
    DepotContext::jalankanSebagai($this->depotB, function () {
        Toko::create(['kode' => 'TK-B1', 'nama' => 'Toko Beta', 'alamat' => 'Jl. B']);
    });
});

function masukSebagai(User $pengguna): void
{
    Livewire::test(Login::class)
        ->set('email', $pengguna->email)
        ->set('password', 'password')
        ->call('masuk')
        ->assertHasNoErrors();
}

it('form login tidak lagi meminta gudang', function () {
    $this->get(route('masuk'))->assertOk()->assertDontSee('depotId')->assertSee('password');
});

it('pengguna satu gudang langsung masuk ke gudangnya tanpa pemilih gudang', function () {
    $admin = DepotContext::jalankanSebagai($this->depotB, fn () => User::factory()->create(['role' => PeranPengguna::Admin]));

    masukSebagai($admin);

    $this->get(route('master.toko'))
        ->assertOk()
        ->assertSee('Toko Beta')
        ->assertDontSee('Toko Alpha')
        ->assertDontSee(route('depot.ganti'));
});

it('pengguna multi gudang masuk ke gudang default-nya dan melihat pemilih gudang', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin, 'depot_id' => $this->depotB->id]);
    $admin->depots()->sync([$this->depot->id, $this->depotB->id]);

    masukSebagai($admin);

    $this->get(route('master.toko'))
        ->assertOk()
        ->assertSee('Toko Beta')
        ->assertDontSee('Toko Alpha')
        ->assertSee(route('depot.ganti'))
        ->assertSee('Gudang Alpha')
        ->assertDontSee(__('umum.semua_depot'));
});

it('tanpa gudang default, pengguna masuk ke gudang yang diizinkan dengan nomor urut terkecil', function () {
    $depotC = Depot::factory()->create(['kode' => 'DEPOTC', 'nama' => 'Gudang Gamma', 'urutan' => 3]);
    $admin = User::factory()->create(['role' => PeranPengguna::Admin, 'depot_id' => null]);
    $admin->depots()->sync([$depotC->id, $this->depotB->id]);

    expect($admin->depotAwal()->id)->toBe($this->depotB->id);

    // Nomor urut diubah → ikut berubah, karena default-nya "otomatis".
    $depotC->update(['urutan' => 0]);

    expect($admin->fresh()->depotAwal()->id)->toBe($depotC->id);
});

it('gudang default yang aksesnya sudah dicabut tidak lagi dipakai', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin, 'depot_id' => $this->depotB->id]);
    $admin->depots()->sync([$this->depot->id]);

    expect($admin->depotAwal()->id)->toBe($this->depot->id);
});

it('pengguna multi gudang bisa pindah ke gudang lain yang diizinkan', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $admin->depots()->sync([$this->depot->id, $this->depotB->id]);

    masukSebagai($admin);
    $this->get(route('master.toko'))->assertSee('Toko Alpha')->assertDontSee('Toko Beta');

    $this->post(route('depot.ganti'), ['depot_id' => $this->depotB->id])->assertRedirect();

    $this->get(route('master.toko'))->assertSee('Toko Beta')->assertDontSee('Toko Alpha');
});

it('pengguna tidak bisa pindah ke gudang yang tidak diizinkan atau ke semua gudang', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin]);

    $this->actingAs($admin)->post(route('depot.ganti'), ['depot_id' => $this->depotB->id])->assertForbidden();
    $this->actingAs($admin)->post(route('depot.ganti'), ['depot_id' => 'semua'])->assertForbidden();
});

it('akses yang dicabut saat sesi berjalan langsung berlaku di permintaan berikutnya', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $admin->depots()->sync([$this->depot->id, $this->depotB->id]);

    masukSebagai($admin);
    $this->post(route('depot.ganti'), ['depot_id' => $this->depotB->id]);
    $this->get(route('master.toko'))->assertSee('Toko Beta');

    $admin->depots()->sync([$this->depot->id]);

    $this->get(route('master.toko'))->assertSee('Toko Alpha')->assertDontSee('Toko Beta');
});

it('superadmin tetap bisa ke semua gudang dan pilihan semua gudang', function () {
    $superadmin = User::factory()->superadmin()->create();

    masukSebagai($superadmin);

    $this->get(route('master.toko'))->assertSee('Toko Alpha')->assertSee(__('umum.semua_depot'));

    $this->post(route('depot.ganti'), ['depot_id' => 'semua']);

    $this->get(route('master.toko'))->assertSee('Toko Alpha')->assertSee('Toko Beta');
});

it('pengguna multi gudang terlihat di daftar pengguna tiap gudang yang diizinkan', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $sales->depots()->sync([$this->depot->id, $this->depotB->id]);
    $salesA = User::factory()->create(['role' => PeranPengguna::Sales]);

    expect(User::sales()->pluck('id')->all())->toContain($sales->id, $salesA->id);

    DepotContext::jalankanSebagai($this->depotB, function () use ($sales, $salesA) {
        expect(User::sales()->pluck('id')->all())->toContain($sales->id)->not->toContain($salesA->id);
    });
});

it('pengguna tanpa gudang aktif sama sekali tidak bisa masuk', function () {
    $admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    Depot::query()->update(['aktif' => false]);

    Livewire::test(Login::class)
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('masuk')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('kelola depot menyimpan nomor urut, depot baru otomatis paling akhir', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($superadmin)
        ->test(DaftarDepot::class)
        ->call('sunting', $this->depotB->id)
        ->assertSet('urutan', '2')
        ->set('urutan', '1')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($this->depotB->fresh()->urutan)->toBe(1);

    Livewire::actingAs($superadmin)
        ->test(DaftarDepot::class)
        ->call('buatBaru')
        ->set('kode', 'DEPOTBARU')
        ->set('nama', 'Gudang Baru')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Depot::where('kode', 'DEPOTBARU')->first()->urutan)->toBe(2);
});

it('menggabungkan akun ganda per gudang menjadi satu akun beserta riwayat dan aksesnya', function () {
    // Meniru data sebelum migrasi email unik global: satu orang punya akun
    // terpisah di tiap gudang.
    Schema::table('users', fn ($t) => $t->dropUnique(['email']));

    $lama = User::factory()->create(['role' => PeranPengguna::Sales, 'email' => 'budi@ond.test', 'aktif' => false]);
    $baru = DepotContext::jalankanSebagai($this->depotB, function () {
        $u = User::factory()->create(['role' => PeranPengguna::Sales, 'email' => 'BUDI@ond.test']);
        PenugasanToko::create([
            'toko_id' => Toko::where('kode', 'TK-B1')->value('id'),
            'sales_id' => $u->id,
            'hari' => HariKunjungan::Senin,
            'ditugaskan_oleh' => $u->id,
        ]);

        return $u;
    });

    $rencana = app(GabungAkunGanda::class)->rencana();

    expect($rencana)->toHaveCount(1)
        ->and($rencana[0]['simpan']->id)->toBe($lama->id);

    $hasil = app(GabungAkunGanda::class)->jalankan();

    expect($hasil['digabung'])->toBe(1)
        ->and($hasil['gagal'])->toBe([])
        ->and(User::withoutGlobalScope(DepotScope::class)->find($baru->id))->toBeNull();

    $akun = User::withoutGlobalScope(DepotScope::class)->find($lama->id);

    expect($akun->aktif)->toBeTrue()
        ->and($akun->depots()->pluck('depots.id')->sort()->values()->all())->toBe([$this->depot->id, $this->depotB->id])
        ->and(DB::table('penugasan_tokos')->where('sales_id', $lama->id)->count())->toBe(1)
        ->and(DB::table('penugasan_tokos')->where('ditugaskan_oleh', $lama->id)->count())->toBe(1);
});

it('perintah gabung akun ganda hanya menampilkan rencana tanpa --jalankan', function () {
    Schema::table('users', fn ($t) => $t->dropUnique(['email']));

    User::factory()->create(['email' => 'kembar@ond.test']);
    DepotContext::jalankanSebagai($this->depotB, fn () => User::factory()->create(['email' => 'kembar@ond.test']));

    $this->artisan('pengguna:gabung-akun-ganda')->assertSuccessful();
    expect(DB::table('users')->where('email', 'kembar@ond.test')->count())->toBe(2);

    $this->artisan('pengguna:gabung-akun-ganda --jalankan')->assertSuccessful();
    expect(DB::table('users')->where('email', 'kembar@ond.test')->count())->toBe(1);
});
