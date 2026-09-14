<?php

use App\Enums\PeranPengguna;
use App\Livewire\Hr\DaftarKaryawan;
use App\Models\Department;
use App\Models\Depot;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\User;
use App\Support\DepotContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->department = Department::create(['kode' => 'D1', 'nama' => 'Finance']);
    $this->jabatan = Jabatan::create(['kode' => 'J1', 'nama' => 'Staff']);
});

/** Data form minimal yang lengkap & valid — tiap tes menimpa field yang mau dirusak. */
function dataKaryawanValid(array $override = []): array
{
    static $n = 0;
    $n++;

    return array_merge([
        'kodeKaryawan' => "EMP-{$n}",
        'namaLengkap' => "Karyawan Uji {$n}",
        'nik' => str_pad((string) (3000000000000000 + $n), 16, '0', STR_PAD_LEFT),
        'jenisKelamin' => 'L',
        'tanggalLahir' => '1995-01-01',
        'noHp' => '081234567890',
        'alamatDomisili' => 'Jl. Uji No. 1',
        'departmentId' => (string) test()->department->id,
        'jabatanId' => (string) test()->jabatan->id,
        'tanggalMasuk' => today()->toDateString(),
        'statusKaryawan' => 'tetap',
        'gajiPokok' => '5000000',
        // BUKAN Depot::query()->first() — migrasi create_depots_table
        // sendiri menyisipkan satu depot bawaan (id=1) untuk kompatibilitas
        // data pra-multi-depot, jadi "depot pertama di tabel" BUKAN depot
        // ambient milik tes ini. DepotContext (dikunci TestCase::setUp() ke
        // $this->depot) yang benar-benar menunjuk depot tes yang sedang
        // berjalan — `test()->depot` sendiri tidak bisa dipakai langsung
        // dari fungsi package-level begini karena itu properti `protected`.
        'depotId' => (string) DepotContext::currentOrFail()->id,
    ], $override);
}

function isiForm($component, array $data)
{
    foreach ($data as $properti => $nilai) {
        $component->set($properti, $nilai);
    }

    return $component;
}

it('menolak akses selain Admin, Hr, dan Superadmin', function () {
    $sales = User::factory()->create(['role' => PeranPengguna::Sales]);

    $this->actingAs($sales)->get(route('hr.karyawan'))->assertForbidden();
});

it('menyimpan karyawan dengan seluruh field wajib', function () {
    $data = dataKaryawanValid();

    isiForm(
        Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
        $data,
    )->call('simpan')->assertHasNoErrors();

    $karyawan = Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first();

    expect($karyawan)->not->toBeNull()
        ->and($karyawan->nama_lengkap)->toBe($data['namaLengkap'])
        ->and($karyawan->department_id)->toBe($this->department->id)
        ->and($karyawan->jabatan_id)->toBe($this->jabatan->id)
        ->and($karyawan->depot_id)->toBe($this->depot->id)
        ->and($karyawan->aktif)->toBeTrue();
});

it('menolak simpan tanpa field wajib', function (string $field) {
    $data = dataKaryawanValid([$field => '']);

    isiForm(
        Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
        $data,
    )->call('simpan')->assertHasErrors($field);
})->with([
    'kodeKaryawan', 'namaLengkap', 'nik', 'tanggalLahir', 'noHp', 'alamatDomisili',
    'departmentId', 'jabatanId', 'tanggalMasuk', 'gajiPokok', 'depotId',
]);

it('menolak kode_karyawan yang sudah dipakai', function () {
    $pertama = dataKaryawanValid();
    isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), $pertama)->call('simpan');

    $kedua = dataKaryawanValid(['kodeKaryawan' => $pertama['kodeKaryawan']]);
    isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), $kedua)
        ->call('simpan')->assertHasErrors('kodeKaryawan');
});

it('menolak nik yang sudah dipakai', function () {
    $pertama = dataKaryawanValid();
    isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), $pertama)->call('simpan');

    $kedua = dataKaryawanValid(['nik' => $pertama['nik']]);
    isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), $kedua)
        ->call('simpan')->assertHasErrors('nik');
});

it('mewajibkan tanggal berakhir kontrak hanya ketika status kontrak', function () {
    $data = dataKaryawanValid(['statusKaryawan' => 'kontrak']);

    isiForm(
        Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
        $data,
    )->call('simpan')->assertHasErrors('tanggalBerakhirKontrak');
});

it('menerima tanggal berakhir kontrak ketika status kontrak dan tanggalnya diisi', function () {
    $data = dataKaryawanValid([
        'statusKaryawan' => 'kontrak',
        'tanggalBerakhirKontrak' => today()->addYear()->toDateString(),
    ]);

    isiForm(
        Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
        $data,
    )->call('simpan')->assertHasNoErrors();

    expect(Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first()->tanggal_berakhir_kontrak)->not->toBeNull();
});

it('mengosongkan tanggal berakhir kontrak otomatis saat status diubah balik ke tetap', function () {
    $component = Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)
        ->set('statusKaryawan', 'kontrak')
        ->set('tanggalBerakhirKontrak', today()->addYear()->toDateString())
        ->set('statusKaryawan', 'tetap');

    expect($component->get('tanggalBerakhirKontrak'))->toBe('');
});

it('mengunggah foto karyawan dan foto KTP ke disk public', function () {
    $data = dataKaryawanValid();

    isiForm(
        Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
        $data,
    )
        ->set('fotoKaryawan', UploadedFile::fake()->image('foto.jpg'))
        ->set('fotoKtp', UploadedFile::fake()->image('ktp.jpg'))
        ->call('simpan')
        ->assertHasNoErrors();

    $karyawan = Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first();

    expect($karyawan->foto_karyawan)->not->toBeNull()
        ->and($karyawan->foto_ktp)->not->toBeNull();
    Storage::disk('public')->assertExists($karyawan->foto_karyawan);
    Storage::disk('public')->assertExists($karyawan->foto_ktp);
});

it('menghapus berkas foto lama saat foto diganti', function () {
    $data = dataKaryawanValid();

    $component = isiForm(
        Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
        $data,
    )->set('fotoKaryawan', UploadedFile::fake()->image('foto-lama.jpg'))->call('simpan');

    $karyawan = Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first();
    $pathLama = $karyawan->foto_karyawan;

    Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)
        ->call('sunting', $karyawan->id)
        ->set('fotoKaryawan', UploadedFile::fake()->image('foto-baru.jpg'))
        ->call('simpan');

    Storage::disk('public')->assertMissing($pathLama);
    Storage::disk('public')->assertExists($karyawan->fresh()->foto_karyawan);
});

it('menolak berkas yang bukan gambar', function () {
    $data = dataKaryawanValid();

    isiForm(
        Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
        $data,
    )
        ->set('fotoKaryawan', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
        ->call('simpan')
        ->assertHasErrors('fotoKaryawan');
});

it('pencarian menyaring nama, kode, dan NIK', function () {
    isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), dataKaryawanValid(['namaLengkap' => 'Budi Santoso']))->call('simpan');
    isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), dataKaryawanValid(['namaLengkap' => 'Siti Aminah']))->call('simpan');

    $hasil = Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)
        ->set('cari', 'Budi')
        ->instance()->karyawans();

    expect($hasil->pluck('nama_lengkap')->all())->toBe(['Budi Santoso']);
});

it('menghapus karyawan', function () {
    $data = dataKaryawanValid();
    isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), $data)->call('simpan');

    $karyawan = Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first();

    Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)
        ->call('hapus', $karyawan->id)
        ->assertDispatched('notifikasi');

    expect(Karyawan::find($karyawan->id))->toBeNull()
        ->and(Karyawan::withTrashed()->find($karyawan->id))->not->toBeNull();
});

// =====================================================================
describe('tautan ke akun pengguna', function () {
    it('bisa disimpan tanpa userId — akun belum dibuat', function () {
        $data = dataKaryawanValid();

        isiForm(
            Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
            $data,
        )->call('simpan')->assertHasNoErrors();

        expect(Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first()->user_id)->toBeNull();
    });

    it('menautkan ke akun pengguna yang sudah ada', function () {
        $pengguna = User::factory()->create(['role' => PeranPengguna::Sales]);
        $data = dataKaryawanValid(['userId' => (string) $pengguna->id]);

        isiForm(
            Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
            $data,
        )->call('simpan')->assertHasNoErrors();

        expect(Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first()->user_id)->toBe($pengguna->id);
    });

    it('menolak satu akun ditautkan ke dua karyawan berbeda', function () {
        $pengguna = User::factory()->create(['role' => PeranPengguna::Sales]);

        isiForm(
            Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
            dataKaryawanValid(['userId' => (string) $pengguna->id]),
        )->call('simpan');

        isiForm(
            Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
            dataKaryawanValid(['userId' => (string) $pengguna->id]),
        )->call('simpan')->assertHasErrors('userId');
    });

    it('daftar akun yang bisa ditautkan tidak menawarkan akun yang sudah tertaut ke karyawan lain', function () {
        $sudahTertaut = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Sudah Tertaut']);
        $belumTertaut = User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Belum Tertaut']);

        isiForm(
            Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'),
            dataKaryawanValid(['userId' => (string) $sudahTertaut->id]),
        )->call('simpan');

        $daftar = Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)
            ->instance()->penggunaBelumTertaut();

        expect($daftar->pluck('id')->all())->toContain($belumTertaut->id)
            ->and($daftar->pluck('id')->all())->not->toContain($sudahTertaut->id);
    });

    it('daftar akun yang bisa ditautkan tetap menyertakan akun karyawan yang sedang disunting', function () {
        $pengguna = User::factory()->create(['role' => PeranPengguna::Sales]);
        $data = dataKaryawanValid(['userId' => (string) $pengguna->id]);
        isiForm(Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)->call('buatBaru'), $data)->call('simpan');

        $karyawan = Karyawan::where('kode_karyawan', $data['kodeKaryawan'])->first();

        $daftar = Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)
            ->call('sunting', $karyawan->id)
            ->instance()->penggunaBelumTertaut();

        expect($daftar->pluck('id')->all())->toContain($pengguna->id);
    });

    it('daftar akun yang bisa ditautkan mencakup pengguna dari depot mana pun', function () {
        $depotLain = Depot::factory()->create();

        $penggunaDepotLain = DepotContext::jalankanSebagai(
            $depotLain,
            fn () => User::factory()->create(['role' => PeranPengguna::Sales, 'name' => 'Dari Depot Lain']),
        );

        $daftar = Livewire::actingAs($this->admin)->test(DaftarKaryawan::class)
            ->instance()->penggunaBelumTertaut();

        expect($daftar->pluck('id')->all())->toContain($penggunaDepotLain->id);
    });
});
