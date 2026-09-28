<?php

namespace App\Services\TarikFreezer;

use App\Enums\JenisBuktiTarikFreezer;
use App\Enums\StatusPesanan;
use App\Enums\StatusStop;
use App\Enums\StatusTarikFreezer;
use App\Models\Depot;
use App\Models\Freezer;
use App\Models\PenugasanToko;
use App\Models\Pesanan;
use App\Models\TarikFreezer;
use App\Models\Toko;
use App\Models\User;
use App\Services\Freezer\FreezerGudangService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Aturan main Tarik Freezer: pengajuan sales, keputusan admin, dan
 * berakhirnya kemitraan — kebalikan dari App\Services\Noo\NooService.
 *
 * Titik paling menentukan di sini adalah selesaikan(): di situlah toko
 * benar-benar berhenti jadi mitra. Toko itu SENGAJA tetap aktif dengan
 * freezer masih terpasang sampai driver menuntaskan pengambilan di
 * lapangan — status & IDN-nya tidak boleh berubah di sistem sebelum
 * freezernya sungguh lepas secara fisik dari toko (lihat juga NooService,
 * yang punya alasan simetris: toko NOO baru aktif setelah freezer TERPASANG).
 */
class TarikFreezerService
{
    public function __construct(
        private readonly BuktiTarikFreezerService $bukti,
        private readonly FreezerGudangService $gudang,
    ) {}

    /**
     * Toko yang boleh diajukan penarikan freezernya: aktif, punya freezer
     * terpasang, tidak sedang ada pengajuan lain yang masih berjalan, dan
     * tidak punya pesanan yang masih berjalan (dus yang belum terkirim tidak
     * boleh menuju toko yang freezernya justru mau diambil).
     *
     * Sales hanya melihat tanggungannya sendiri — persis pembatasan yang
     * sama dengan Toko\LengkapiData::tokoBolehDisentuh(); admin tidak
     * dibatasi.
     */
    public function tokoBolehDiajukan(User $pengguna): Builder
    {
        $tokoAdaPesananAktif = Pesanan::query()
            ->whereIn('status', StatusPesanan::aktif())
            ->select('toko_id');

        return Toko::query()
            ->aktif()
            ->whereNotNull('asset_id')
            ->whereDoesntHave('tarikFreezers', fn ($q) => $q->berjalan())
            ->whereNotIn('id', $tokoAdaPesananAktif)
            ->when($pengguna->isSales(), fn ($q) => $q->whereIn(
                'id',
                PenugasanToko::query()->where('sales_id', $pengguna->id)->pluck('toko_id'),
            ));
    }

    /** @throws RuntimeException bila toko tidak (lagi) boleh diajukan */
    public function ajukan(int $tokoId, string $alasan, User $sales): TarikFreezer
    {
        $toko = $this->tokoBolehDiajukan($sales)->find($tokoId);

        if ($toko === null) {
            throw new RuntimeException(__('tarik_freezer.galat_toko_tidak_valid'));
        }

        if (trim($alasan) === '') {
            throw new RuntimeException(__('tarik_freezer.galat_alasan_wajib'));
        }

        return TarikFreezer::create([
            'kode' => $this->kodeBerikutnya(),
            'status' => StatusTarikFreezer::Order,
            'toko_id' => $toko->id,
            'alasan' => $alasan,
            'diajukan_oleh' => $sales->id,
            'diajukan_at' => now(),
        ]);
    }

    /** @throws RuntimeException bila statusnya bukan lagi ORDER */
    public function setujui(TarikFreezer $tarikFreezer, User $admin): void
    {
        if ($tarikFreezer->status !== StatusTarikFreezer::Order) {
            throw new RuntimeException(__('tarik_freezer.galat_bukan_order', ['kode' => $tarikFreezer->kode]));
        }

        $tarikFreezer->update([
            'status' => StatusTarikFreezer::Process,
            'disetujui_oleh' => $admin->id,
            'disetujui_at' => now(),
        ]);
    }

    /** @throws RuntimeException bila statusnya bukan lagi ORDER */
    public function tolak(TarikFreezer $tarikFreezer, User $admin, string $alasan): void
    {
        if ($tarikFreezer->status !== StatusTarikFreezer::Order) {
            throw new RuntimeException(__('tarik_freezer.galat_bukan_order', ['kode' => $tarikFreezer->kode]));
        }

        if (trim($alasan) === '') {
            throw new RuntimeException(__('tarik_freezer.galat_alasan_tolak_wajib'));
        }

        $tarikFreezer->update([
            'status' => StatusTarikFreezer::Ditolak,
            'ditolak_oleh' => $admin->id,
            'ditolak_at' => now(),
            'alasan_tolak' => $alasan,
        ]);
    }

    /**
     * Menuntaskan pengambilan freezer di lapangan.
     *
     * Ini titik saat toko benar-benar berhenti jadi mitra: dinonaktifkan,
     * IDN dan tipe freezernya dikosongkan (jadi tersedia lagi di Master
     * Freezer untuk dipasang di toko lain), dan bukti fotonya tersimpan.
     *
     * @param  array<string, string>  $gambar  data URL, dikunci pada JenisBuktiTarikFreezer->value
     *
     * @throws RuntimeException bila statusnya bukan lagi DELIVERY
     */
    public function selesaikan(TarikFreezer $tarikFreezer, array $gambar, User $driver): void
    {
        if ($tarikFreezer->status !== StatusTarikFreezer::Delivery) {
            throw new RuntimeException(__('tarik_freezer.galat_bukan_delivery', ['kode' => $tarikFreezer->kode]));
        }

        $tersimpan = [];

        try {
            DB::transaction(function () use ($tarikFreezer, $gambar, $driver, &$tersimpan): void {
                $toko = $tarikFreezer->toko()->lockForUpdate()->firstOrFail();
                $idnDitarik = $toko->asset_id;

                $toko->update([
                    'aktif' => false,
                    'asset_id' => null,
                    'freezer_tipe' => null,
                ]);

                foreach (JenisBuktiTarikFreezer::wajibDriver() as $jenis) {
                    $tersimpan[] = $this->bukti->simpanFoto($tarikFreezer, $jenis, $gambar[$jenis->value], $driver);
                }

                $this->bukti->simpanSemua($tarikFreezer, $tersimpan);

                $tarikFreezer->stop?->update([
                    'status' => StatusStop::Selesai,
                    'total_dus_terkirim' => 1,
                    'selesai_at' => now(),
                ]);

                // Freezer yang sudah kembali langsung tercatat di gudang rute
                // penarikannya — tanpa ini ia jadi "freezer tanpa toko yang
                // tidak diketahui ada di mana" sampai ada yang memindainya.
                $freezer = $idnDitarik === null ? null : Freezer::query()->where('idn', $idnDitarik)->first();
                $depotRute = Depot::find($tarikFreezer->depot_id);

                if ($freezer !== null && $depotRute?->aktif) {
                    $this->gudang->catat($freezer, $depotRute, $driver);
                }

                $tarikFreezer->update([
                    'status' => StatusTarikFreezer::Selesai,
                    'diselesaikan_oleh' => $driver->id,
                    'selesai_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $this->bukti->hapusBerkas($tersimpan);

            throw $e;
        }
    }

    /**
     * Kode Tarik Freezer harian: TF-Ymd-####. Bentuk dan cara hitungnya
     * sama dengan kode NOO (NooService::kodeBerikutnya()).
     */
    private function kodeBerikutnya(): string
    {
        $hariIni = now()->format('Ymd');
        $urutan = TarikFreezer::withTrashed()->whereDate('created_at', today())->count() + 1;

        return sprintf('TF-%s-%04d', $hariIni, $urutan);
    }
}
