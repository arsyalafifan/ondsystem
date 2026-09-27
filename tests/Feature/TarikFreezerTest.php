<?php

use App\Enums\HariKunjungan;
use App\Enums\JenisBuktiTarikFreezer;
use App\Enums\JenisRouting;
use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Enums\StatusTarikFreezer;
use App\Livewire\Driver\DaftarKunjungan;
use App\Livewire\TarikFreezer\DaftarTarikFreezer;
use App\Livewire\TarikFreezer\Persetujuan;
use App\Livewire\TarikFreezer\RoutingTarikFreezer;
use App\Models\Freezer;
use App\Models\PenugasanToko;
use App\Models\Pesanan;
use App\Models\RoutingBatch;
use App\Models\TarikFreezer;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\RoutingService;
use App\Services\TarikFreezer\BuktiTarikFreezerService;
use App\Services\TarikFreezer\TarikFreezerService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Ujung ke ujung Tarik Freezer: sales mengajukan, admin memutuskan,
 * dirutekan, dan driver menuntaskan pengambilan di lapangan — kebalikan
 * dari NOO. Yang paling dijaga di sini: toko TETAP aktif dan IDN-nya TETAP
 * terpasang sampai driver benar-benar mengonfirmasi dengan foto, bukan
 * lebih awal saat admin menyetujui atau merutekan.
 */
beforeEach(function () {
    Storage::fake(BuktiTarikFreezerService::DISK);

    $this->admin = User::factory()->create(['role' => PeranPengguna::Admin]);
    $this->sales = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->salesLain = User::factory()->create(['role' => PeranPengguna::Sales]);
    $this->driver = User::factory()->create(['role' => PeranPengguna::Driver]);

    $this->wilayah = Wilayah::create(['kode' => 'W1', 'nama' => 'Wilayah Satu']);
    Freezer::create(['idn' => 'IDNAH-TARIK-001', 'tipe' => 'SD-200']);

    $this->toko = Toko::create([
        'kode' => 'TK-TARIK1', 'nama' => 'Toko Tarik Uji', 'wilayah_id' => $this->wilayah->id,
        'alamat' => 'Jl. Uji', 'latitude' => -6.21, 'longitude' => 106.83, 'sumber_koordinat' => 'manual',
        'aktif' => true, 'asset_id' => 'IDNAH-TARIK-001', 'freezer_tipe' => 'SD-200',
    ]);

    PenugasanToko::create([
        'toko_id' => $this->toko->id, 'sales_id' => $this->sales->id,
        'hari' => HariKunjungan::Senin, 'ditugaskan_oleh' => $this->admin->id,
    ]);
});

/** Data URL kecil untuk mengisi foto bukti di tes. */
function gambarTarikUji(): string
{
    $gambar = imagecreatetruecolor(50, 50);
    ob_start();
    imagejpeg($gambar);
    $isi = (string) ob_get_clean();
    imagedestroy($gambar);

    return 'data:image/jpeg;base64,'.base64_encode($isi);
}

/** Mengajukan tarik freezer untuk $toko atas nama $sales, lalu mengembalikan baris TarikFreezer. */
function ajukanTarikUji(User $sales, Toko $toko, string $alasan = 'Toko tutup permanen'): TarikFreezer
{
    Livewire::actingAs($sales)->test(DaftarTarikFreezer::class)
        ->call('buatBaru')
        ->set('tokoId', $toko->id)
        ->set('alasan', $alasan)
        ->call('simpan');

    return TarikFreezer::where('toko_id', $toko->id)->latest('id')->firstOrFail();
}

describe('pengajuan sales', function () {
    it('sales bisa mengajukan tarik freezer untuk toko tanggungannya', function () {
        $tarik = ajukanTarikUji($this->sales, $this->toko);

        expect($tarik->status)->toBe(StatusTarikFreezer::Order)
            ->and($tarik->kode)->toStartWith('TF-')
            ->and($tarik->diajukan_oleh)->toBe($this->sales->id)
            ->and($tarik->alasan)->toBe('Toko tutup permanen');

        // Belum ada efek apa pun ke toko sampai driver menuntaskan.
        expect($this->toko->fresh()->aktif)->toBeTrue()
            ->and($this->toko->fresh()->asset_id)->toBe('IDNAH-TARIK-001');
    });

    it('toko tanggungan sales lain tidak muncul di pilihan dan ditolak server', function () {
        $komponen = Livewire::actingAs($this->salesLain)->test(DaftarTarikFreezer::class)->call('buatBaru');

        expect(collect($komponen->instance()->opsiToko)->pluck('value'))->not->toContain((string) $this->toko->id);

        $komponen->set('tokoId', $this->toko->id)->set('alasan', 'Coba toko orang lain')->call('simpan');

        expect(TarikFreezer::where('toko_id', $this->toko->id)->exists())->toBeFalse();
    });

    it('admin bisa mengajukan untuk toko mana pun', function () {
        $tarik = ajukanTarikUji($this->admin, $this->toko);

        expect($tarik->diajukan_oleh)->toBe($this->admin->id);
    });

    it('menolak toko yang sedang punya pesanan aktif', function () {
        Pesanan::create([
            'kode' => 'PSN-TARIK1', 'toko_id' => $this->toko->id, 'wilayah_id' => $this->wilayah->id,
            'dibuat_oleh' => $this->admin->id, 'status' => StatusPesanan::Order, 'jenis' => 'normal',
            'tanggal' => today(), 'total_dus' => 5, 'total_nilai' => 50_000,
        ]);

        expect(collect(Livewire::actingAs($this->sales)->test(DaftarTarikFreezer::class)->instance()->opsiToko)->pluck('value'))
            ->not->toContain((string) $this->toko->id);
    });

    it('menolak toko yang sudah punya pengajuan tarik lain yang masih berjalan', function () {
        ajukanTarikUji($this->sales, $this->toko);

        $komponen = Livewire::actingAs($this->sales)->test(DaftarTarikFreezer::class)
            ->call('buatBaru')
            ->set('tokoId', $this->toko->id)
            ->set('alasan', 'Pengajuan kedua')
            ->call('simpan');

        expect(TarikFreezer::where('toko_id', $this->toko->id)->count())->toBe(1);
    });

    it('menolak toko yang tidak punya freezer terpasang', function () {
        $tokoTanpaFreezer = Toko::create([
            'kode' => 'TK-TARIK2', 'nama' => 'Toko Tanpa Freezer', 'wilayah_id' => $this->wilayah->id,
            'alamat' => 'Jl. Lain', 'aktif' => true,
        ]);
        PenugasanToko::create([
            'toko_id' => $tokoTanpaFreezer->id, 'sales_id' => $this->sales->id,
            'hari' => HariKunjungan::Selasa, 'ditugaskan_oleh' => $this->admin->id,
        ]);

        expect(collect(Livewire::actingAs($this->sales)->test(DaftarTarikFreezer::class)->instance()->opsiToko)->pluck('value'))
            ->not->toContain((string) $tokoTanpaFreezer->id);
    });

    it('menolak alasan kosong', function () {
        Livewire::actingAs($this->sales)->test(DaftarTarikFreezer::class)
            ->call('buatBaru')
            ->set('tokoId', $this->toko->id)
            ->set('alasan', '')
            ->call('simpan')
            ->assertHasErrors('alasan');

        expect(TarikFreezer::count())->toBe(0);
    });
});

describe('persetujuan admin', function () {
    it('admin bisa menyetujui pengajuan', function () {
        $tarik = ajukanTarikUji($this->sales, $this->toko);

        Livewire::actingAs($this->admin)->test(Persetujuan::class)
            ->call('pilih', $tarik->id)
            ->call('setujui')
            ->assertHasNoErrors();

        expect($tarik->fresh()->status)->toBe(StatusTarikFreezer::Process)
            ->and($tarik->fresh()->disetujui_oleh)->toBe($this->admin->id);
    });

    it('admin bisa menolak pengajuan dengan alasan', function () {
        $tarik = ajukanTarikUji($this->sales, $this->toko);

        Livewire::actingAs($this->admin)->test(Persetujuan::class)
            ->call('pilih', $tarik->id)
            ->set('alasanTolak', 'Toko masih mau lanjut jadi mitra')
            ->call('tolak')
            ->assertHasNoErrors();

        expect($tarik->fresh()->status)->toBe(StatusTarikFreezer::Ditolak)
            ->and($tarik->fresh()->alasan_tolak)->toBe('Toko masih mau lanjut jadi mitra');

        // Toko tidak terpengaruh sama sekali oleh penolakan.
        expect($this->toko->fresh()->aktif)->toBeTrue();
    });

    it('menolak menolak tanpa alasan', function () {
        $tarik = ajukanTarikUji($this->sales, $this->toko);

        Livewire::actingAs($this->admin)->test(Persetujuan::class)
            ->call('pilih', $tarik->id)
            ->set('alasanTolak', '')
            ->call('tolak')
            ->assertHasErrors('alasanTolak');

        expect($tarik->fresh()->status)->toBe(StatusTarikFreezer::Order);
    });
});

/** Tarik freezer yang sudah disetujui admin, siap dirutekan. */
function tarikDisetujuiUji(): TarikFreezer
{
    $tarik = ajukanTarikUji(test()->sales, test()->toko);
    app(TarikFreezerService::class)->setujui($tarik, test()->admin);

    return $tarik->fresh();
}

describe('routing tarik freezer', function () {
    it('menyusun rute pengambilan terpisah dari rute pesanan/NOO', function () {
        tarikDisetujuiUji();

        $batch = app(RoutingService::class)->generateTarik($this->admin);

        expect($batch->jenis)->toBe(JenisRouting::Tarik)
            ->and($batch->total_dus)->toBe(1);

        $stop = $batch->fresh()->kendaraans->first()->stops->first();

        expect($stop->jenis)->toBe('tarik')
            ->and($stop->pesanan_id)->toBeNull()
            ->and($stop->noo_id)->toBeNull()
            ->and($stop->tarik_freezer_id)->toBe(TarikFreezer::firstOrFail()->id);
    });

    it('tidak menampilkan rute tarik di layar routing pesanan maupun NOO', function () {
        tarikDisetujuiUji();
        $batch = app(RoutingService::class)->generateTarik($this->admin);

        expect(RoutingBatch::reguler()->pluck('id'))->not->toContain($batch->id)
            ->and(RoutingBatch::noo()->pluck('id'))->not->toContain($batch->id)
            ->and(RoutingBatch::tarik()->pluck('id'))->toContain($batch->id);
    });

    it('menaikkan status ke Delivery begitu rutenya disetujui', function () {
        $tarik = tarikDisetujuiUji();
        $batch = app(RoutingService::class)->generateTarik($this->admin);

        app(RoutingService::class)->setujui($batch, $this->admin);

        expect($tarik->fresh()->status)->toBe(StatusTarikFreezer::Delivery);
    });

    it('bisa disusun lewat layarnya sendiri', function () {
        $tarik = tarikDisetujuiUji();

        $komponen = Livewire::actingAs($this->admin)->test(RoutingTarikFreezer::class);

        expect($komponen->instance()->siapRouting)->toHaveCount(1);

        $komponen->set('terpilih', [$tarik->id])->call('generate');

        expect($komponen->instance()->batches)->toHaveCount(1)
            ->and($komponen->instance()->batches->first()->jenis)->toBe(JenisRouting::Tarik)
            ->and($komponen->instance()->siapRouting)->toHaveCount(0);
    });

    it('toko tanpa koordinat dilewati dari rute dengan peringatan, bukan menggagalkan seluruh proses', function () {
        tarikDisetujuiUji();

        $tokoLain = Toko::create([
            'kode' => 'TK-TARIK3', 'nama' => 'Toko Tanpa Koordinat', 'wilayah_id' => $this->wilayah->id,
            'alamat' => 'Jl. Buntu', 'aktif' => true, 'asset_id' => null,
        ]);
        Freezer::create(['idn' => 'IDNAH-TARIK-002', 'tipe' => 'SD-200']);
        $tokoLain->update(['asset_id' => 'IDNAH-TARIK-002']);
        PenugasanToko::create([
            'toko_id' => $tokoLain->id, 'sales_id' => $this->sales->id,
            'hari' => HariKunjungan::Rabu, 'ditugaskan_oleh' => $this->admin->id,
        ]);
        $tarikTanpaKoordinat = ajukanTarikUji($this->sales, $tokoLain);
        app(TarikFreezerService::class)->setujui($tarikTanpaKoordinat, $this->admin);

        $batch = app(RoutingService::class)->generateTarik($this->admin);

        expect($batch->kendaraans->sum(fn ($k) => $k->stops->count()))->toBe(1)
            ->and($tarikTanpaKoordinat->fresh()->status)->toBe(StatusTarikFreezer::Process);
    });
});

describe('pengambilan oleh driver', function () {
    /** Merutekan dan memberangkatkan tarik freezer yang siap. */
    function rutekanDanBerangkatkanTarikUji(): TarikFreezer
    {
        $tarik = tarikDisetujuiUji();

        $service = app(RoutingService::class);
        $batch = $service->generateTarik(test()->admin);

        $kendaraan = $batch->fresh()->kendaraans->first();
        $kendaraan->update(['driver_id' => test()->driver->id]);

        $service->setujui($batch, test()->admin);

        catatBerangkatKendaraan($kendaraan->fresh(), test()->driver);

        return $tarik->fresh();
    }

    it('menonaktifkan toko dan mengosongkan IDN saat driver menuntaskan pengambilan', function () {
        $tarik = rutekanDanBerangkatkanTarikUji();
        $kendaraan = $tarik->stop->kendaraan;

        // Sampai titik ini toko masih aktif dan freezernya masih terpasang —
        // itulah inti keputusan desainnya: efeknya baru terjadi saat driver
        // benar-benar mengonfirmasi, bukan lebih awal.
        expect($this->toko->fresh()->aktif)->toBeTrue()
            ->and($this->toko->fresh()->asset_id)->toBe('IDNAH-TARIK-001');

        $komponen = Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasiTarik', $tarik->stop->id);

        foreach (JenisBuktiTarikFreezer::wajibDriver() as $jenis) {
            $komponen = $komponen->call('terimaBuktiTarik', $jenis->value, gambarTarikUji());
        }

        $komponen->call('simpanKonfirmasiTarik')->assertHasNoErrors();

        $tokoSegar = $this->toko->fresh();
        expect($tokoSegar->aktif)->toBeFalse()
            ->and($tokoSegar->asset_id)->toBeNull()
            ->and($tokoSegar->freezer_tipe)->toBeNull();

        $tarikSegar = $tarik->fresh(['fotos']);
        expect($tarikSegar->status)->toBe(StatusTarikFreezer::Selesai)
            ->and($tarikSegar->diselesaikan_oleh)->toBe($this->driver->id)
            ->and($tarikSegar->stop->fresh()->status)->toBe(StatusStop::Selesai)
            ->and($tarikSegar->fotos->pluck('jenis')->all())->toBe(JenisBuktiTarikFreezer::wajibDriver());

        // IDN-nya kembali tersedia untuk dipasang di toko lain.
        expect(Freezer::where('idn', 'IDNAH-TARIK-001')->first()->toko)->toBeNull();
    });

    it('menolak menyelesaikan pengambilan sebelum kedua fotonya lengkap', function () {
        $tarik = rutekanDanBerangkatkanTarikUji();
        $kendaraan = $tarik->stop->kendaraan;

        Livewire::actingAs($this->driver)->test(DaftarKunjungan::class, ['kendaraan' => $kendaraan])
            ->call('bukaKonfirmasiTarik', $tarik->stop->id)
            ->call('terimaBuktiTarik', JenisBuktiTarikFreezer::FotoFreezer->value, gambarTarikUji())
            ->call('simpanKonfirmasiTarik');

        expect($tarik->fresh()->status)->toBe(StatusTarikFreezer::Delivery)
            ->and($this->toko->fresh()->aktif)->toBeTrue();
    });
});
