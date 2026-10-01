<?php

namespace App\Services\PengantaranRider;

use App\Enums\JenisBuktiPengiriman;
use App\Enums\StatusPengantaranRider;
use App\Enums\StatusPesanan;
use App\Models\PengantaranRider;
use App\Models\Pesanan;
use App\Models\User;
use App\Services\PesananService;
use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use App\Support\DepotContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aturan main pengantaran rider: menandai pesanan untuk rider (bukan
 * routing kendaraan), pool pengambilan per gudang, dan penyelesaiannya.
 *
 * Beda dari NOO/Tarik Freezer/Transfer Stok, tidak ada konsep urutan/rute
 * sama sekali — begitu ditandai untuk rider, pesanan LANGSUNG Delivery dan
 * masuk pool (status Tersedia). Siapa saja rider di gudang yang sama bisa
 * mengambilnya (ambil()), bukan ditugaskan admin seperti driver dipilihkan
 * kendaraan.
 */
class PengantaranRiderService
{
    public function __construct(
        private readonly PesananService $pesananService,
        private readonly BuktiPengantaranRiderService $buktiService,
    ) {}

    /** Jarak toko pesanan dari gudang yang sedang aktif, dalam meter. Null kalau koordinat gudang/toko kosong. */
    public function jarakM(Pesanan $pesanan): ?float
    {
        $pesanan->loadMissing('toko:id,latitude,longitude');
        $toko = $pesanan->toko;

        if ($toko === null || $toko->latitude === null || $toko->longitude === null) {
            return null;
        }

        $depot = DepotContext::currentOrFail();

        if ($depot->lat === null || $depot->lng === null) {
            return null;
        }

        return Geo::haversine(
            new Koordinat((float) $depot->lat, (float) $depot->lng),
            new Koordinat((float) $toko->latitude, (float) $toko->longitude),
        );
    }

    /** Toko pesanan ini masuk radius rider gudang yang sedang aktif. */
    public function dalamRadius(Pesanan $pesanan): bool
    {
        $depot = DepotContext::currentOrFail();

        if ($depot->radius_rider_km === null || $depot->radius_rider_km <= 0) {
            return false;
        }

        $jarakM = $this->jarakM($pesanan);

        return $jarakM !== null && $jarakM <= $depot->radius_rider_km * 1000;
    }

    /** @throws RuntimeException bila pesanan bukan status Order */
    public function tandaiUntukRider(Pesanan $pesanan, User $admin): PengantaranRider
    {
        return DB::transaction(function () use ($pesanan, $admin): PengantaranRider {
            $this->pesananService->setujuiLangsungDelivery($pesanan, $admin);

            return PengantaranRider::create([
                'pesanan_id' => $pesanan->id,
                'status' => StatusPengantaranRider::Tersedia,
                'ditandai_oleh' => $admin->id,
                'ditandai_at' => now(),
            ]);
        });
    }

    /**
     * Membatalkan penandaan rider selama belum diambil siapa pun, lalu
     * mengembalikan pesanan ke jalur normal (Process, ikut routing
     * kendaraan) — jalan keluar kalau pesanan nyangkut di pool tanpa ada
     * rider yang mengambilnya.
     *
     * @throws RuntimeException bila sudah diambil rider (harus lepas() dulu)
     */
    public function alihkanKeDriver(PengantaranRider $pengantaran, User $admin): void
    {
        if ($pengantaran->status !== StatusPengantaranRider::Tersedia) {
            throw new RuntimeException(__('pengantaran_rider.galat_bukan_tersedia'));
        }

        DB::transaction(function () use ($pengantaran, $admin): void {
            $pesanan = $pengantaran->pesanan()->lockForUpdate()->first();

            if ($pesanan->status !== StatusPesanan::Delivery) {
                throw new RuntimeException(__('pesanan.galat_bukan_delivery', ['kode' => $pesanan->kode]));
            }

            // Setara isi PesananService::setujui() tapi tanpa guard "harus
            // Order"-nya — di sini pesanan datang dari Delivery (sudah
            // ditandai rider), bukan dari Order biasa.
            $pesanan->update([
                'status' => StatusPesanan::Process,
                'diproses_oleh' => $admin->id,
                'diproses_at' => now(),
                'dikirim_at' => null,
            ]);

            $pengantaran->delete();
        });
    }

    /** @throws RuntimeException bila sudah diambil, atau rider sedang punya pengantaran aktif lain */
    public function ambil(PengantaranRider $pengantaran, User $rider): void
    {
        if ($pengantaran->status !== StatusPengantaranRider::Tersedia) {
            throw new RuntimeException(__('pengantaran_rider.galat_bukan_tersedia'));
        }

        if (PengantaranRider::where('rider_id', $rider->id)->where('status', StatusPengantaranRider::Diambil)->exists()) {
            throw new RuntimeException(__('pengantaran_rider.galat_sudah_punya_aktif'));
        }

        $pengantaran->update([
            'status' => StatusPengantaranRider::Diambil,
            'rider_id' => $rider->id,
            'diambil_at' => now(),
        ]);
    }

    /** @throws RuntimeException bila bukan rider yang sama yang mengambilnya */
    public function lepas(PengantaranRider $pengantaran, User $rider): void
    {
        if ($pengantaran->status !== StatusPengantaranRider::Diambil || $pengantaran->rider_id !== $rider->id) {
            throw new RuntimeException(__('pengantaran_rider.galat_bukan_diambil'));
        }

        $pengantaran->update([
            'status' => StatusPengantaranRider::Tersedia,
            'rider_id' => null,
            'diambil_at' => null,
            'dilepas_at' => now(),
        ]);
    }

    /**
     * Menyimpan konfirmasi penerimaan sekaligus mengunggah nota — setara
     * PesananService::selesaikanPengiriman() tapi untuk rider.
     *
     * @param  array<int, int>  $jumlahDiterima  dikunci pada id item pesanan
     * @param  array<int, array{jenis: JenisBuktiPengiriman, path: string, catatan: ?string}>  $buktiFoto
     *
     * @throws RuntimeException bila bukan rider yang sama yang mengambilnya, atau jumlah di bawah minimal
     */
    public function selesaikan(
        PengantaranRider $pengantaran,
        array $jumlahDiterima,
        string $pathFotoNota,
        User $rider,
        ?string $catatan,
        array $buktiFoto,
    ): void {
        if ($pengantaran->status !== StatusPengantaranRider::Diambil || $pengantaran->rider_id !== $rider->id) {
            throw new RuntimeException(__('pengantaran_rider.galat_bukan_diambil'));
        }

        DB::transaction(function () use ($pengantaran, $jumlahDiterima, $pathFotoNota, $rider, $catatan, $buktiFoto): void {
            $this->pesananService->selesaikanTanpaKendaraan($pengantaran->pesanan, $jumlahDiterima, $rider);

            $pengantaran->update([
                'status' => StatusPengantaranRider::Selesai,
                'foto_nota' => $pathFotoNota,
                'catatan_rider' => $catatan,
                'selesai_at' => now(),
            ]);

            $this->buktiService->simpanSemua($pengantaran, $buktiFoto);
        });
    }
}
