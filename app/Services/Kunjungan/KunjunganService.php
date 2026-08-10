<?php

namespace App\Services\Kunjungan;

use App\Enums\JenisFotoKunjungan;
use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\KunjunganFoto;
use App\Models\PenugasanSales;
use App\Models\PeriodeKunjungan;
use App\Models\PeriodeSales;
use App\Models\Toko;
use App\Models\User;
use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Aturan main kunjungan sales.
 *
 * Tiga penjagaan yang menentukan mutu data:
 *
 *  1. Satu toko hanya boleh dikunjungi sekali dalam satu periode mingguan.
 *  2. Sales hanya boleh mengunjungi toko yang ditugaskan admin kepadanya.
 *  3. Kunjungan baru dianggap selesai setelah keenam foto wajib terkumpul.
 *
 * Toko yang dilaporkan tutup tidak diselesaikan sendiri oleh sales — laporan
 * itu menunggu pembenaran admin. Setelah dibenarkan, toko tersebut keluar dari
 * penyebut target minggu itu, sehingga sales tidak dirugikan oleh keadaan yang
 * bukan kendalinya.
 */
class KunjunganService
{
    public function __construct(
        private readonly PeriodeKunjunganService $periodeService,
        private readonly PenandaFoto $penandaFoto,
        private readonly PenguraiQr $penguraiQr,
    ) {}

    /**
     * Memulai kunjungan dari hasil pemindaian QR freezer.
     *
     * @throws RuntimeException bila QR tidak terbaca, toko tidak dikenal,
     *                          bukan tanggungan sales ini, atau sudah
     *                          dikunjungi orang lain minggu ini.
     */
    public function mulaiDariQr(string $isiQr, User $sales): Kunjungan
    {
        $hasil = $this->penguraiQr->urai($isiQr);

        if ($hasil === null) {
            throw new RuntimeException(__('kunjungan.galat_qr_tidak_terbaca'));
        }

        $toko = Toko::where('asset_id', $hasil->assetId)->first();

        if ($toko === null) {
            throw new RuntimeException(__('kunjungan.galat_aset_tidak_dikenal', ['aset' => $hasil->assetId]));
        }

        return $this->mulai($toko, $sales, $hasil->assetId);
    }

    /** @throws RuntimeException */
    public function mulai(Toko $toko, User $sales, ?string $assetIdTerpindai = null): Kunjungan
    {
        if (! $toko->aktif) {
            throw new RuntimeException(__('kunjungan.galat_toko_nonaktif', ['nama' => $toko->nama]));
        }

        $periode = $this->periodeService->periodeBerjalan();

        if (! $this->ditugaskan($toko, $sales, $periode)) {
            throw new RuntimeException(__('kunjungan.galat_bukan_tanggungan', ['nama' => $toko->nama]));
        }

        return DB::transaction(function () use ($toko, $sales, $periode, $assetIdTerpindai): Kunjungan {
            $adaSebelumnya = Kunjungan::query()
                ->where('periode_kunjungan_id', $periode->id)
                ->where('toko_id', $toko->id)
                ->lockForUpdate()
                ->first();

            if ($adaSebelumnya !== null) {
                // Melanjutkan kunjungan sendiri yang belum tuntas itu wajar —
                // sales bisa saja menutup aplikasi di tengah pengambilan foto.
                if ($adaSebelumnya->sales_id === $sales->id && $adaSebelumnya->status === StatusKunjungan::Berjalan) {
                    return $adaSebelumnya;
                }

                if ($adaSebelumnya->status === StatusKunjungan::TutupDitolak) {
                    $adaSebelumnya->update([
                        'sales_id' => $sales->id,
                        'status' => StatusKunjungan::Berjalan,
                        'mulai_at' => now(),
                    ]);

                    return $adaSebelumnya;
                }

                throw new RuntimeException(__('kunjungan.galat_sudah_dikunjungi', [
                    'nama' => $toko->nama,
                    'sales' => $adaSebelumnya->sales_id === $sales->id
                        ? __('kunjungan.oleh_anda')
                        : $adaSebelumnya->sales()->value('name'),
                ]));
            }

            $periodeSales = PeriodeSales::firstOrCreate(
                ['periode_kunjungan_id' => $periode->id, 'sales_id' => $sales->id],
                ['target_toko' => $this->jumlahTanggungan($sales, $periode)],
            );

            try {
                return Kunjungan::create([
                    'periode_kunjungan_id' => $periode->id,
                    'periode_sales_id' => $periodeSales->id,
                    'sales_id' => $sales->id,
                    'toko_id' => $toko->id,
                    'status' => StatusKunjungan::Berjalan,
                    'asset_id_terpindai' => $assetIdTerpindai,
                    'mulai_at' => now(),
                ]);
            } catch (QueryException $e) {
                // Jaring terakhir bila dua permintaan lolos bersamaan: batasan
                // unik di basis data yang menolaknya, bukan aplikasi.
                throw new RuntimeException(__('kunjungan.galat_sudah_dikunjungi', [
                    'nama' => $toko->nama,
                    'sales' => __('kunjungan.sales_lain'),
                ]), previous: $e);
            }
        });
    }

    /**
     * Menyimpan satu foto bukti. Foto yang jenisnya sudah ada akan diganti,
     * karena sales boleh mengulang bidikan yang kurang jelas.
     */
    public function simpanFoto(
        Kunjungan $kunjungan,
        JenisFotoKunjungan $jenis,
        string $isiGambar,
        ?float $lat = null,
        ?float $lng = null,
        ?int $akurasi = null,
    ): KunjunganFoto {
        if ($kunjungan->status !== StatusKunjungan::Berjalan) {
            throw new RuntimeException(__('kunjungan.galat_tidak_berjalan'));
        }

        $kunjungan->loadMissing(['toko', 'sales']);

        $hasil = $this->penandaFoto->simpan(
            isiGambar: $isiGambar,
            toko: $kunjungan->toko,
            sales: $kunjungan->sales,
            lat: $lat,
            lng: $lng,
            akurasi: $akurasi,
        );

        return DB::transaction(function () use ($kunjungan, $jenis, $hasil, $lat, $lng, $akurasi): KunjunganFoto {
            $lama = $kunjungan->fotos()->where('jenis', $jenis->value)->first();

            if ($lama !== null) {
                Storage::disk(config('visit.foto.disk'))->delete($lama->path);
                $lama->delete();
            }

            return $kunjungan->fotos()->create([
                'jenis' => $jenis,
                'path' => $hasil['path'],
                'diambil_at' => $hasil['diambil_at'],
                'latitude' => $lat,
                'longitude' => $lng,
                'akurasi_m' => $akurasi,
                'lebar' => $hasil['lebar'],
                'tinggi' => $hasil['tinggi'],
                'ukuran_byte' => $hasil['ukuran'],
            ]);
        });
    }

    /** @throws RuntimeException bila masih ada foto wajib yang belum diambil */
    public function selesaikan(
        Kunjungan $kunjungan,
        ?float $lat = null,
        ?float $lng = null,
        ?int $akurasi = null,
        ?string $catatan = null,
    ): Kunjungan {
        $kunjungan->loadMissing(['fotos', 'toko']);

        if ($kunjungan->status !== StatusKunjungan::Berjalan) {
            throw new RuntimeException(__('kunjungan.galat_tidak_berjalan'));
        }

        if (! $kunjungan->foto_lengkap) {
            $kurang = array_map(fn (JenisFotoKunjungan $j) => $j->label(), $kunjungan->foto_kurang);

            throw new RuntimeException(__('kunjungan.galat_foto_kurang', ['daftar' => implode(', ', $kurang)]));
        }

        $kunjungan->update([
            'status' => StatusKunjungan::Selesai,
            'selesai_at' => now(),
            'latitude' => $lat,
            'longitude' => $lng,
            'akurasi_m' => $akurasi,
            'jarak_dari_toko_m' => $this->jarakKeToko($kunjungan->toko, $lat, $lng),
            'catatan_sales' => $catatan,
        ]);

        return $kunjungan;
    }

    /**
     * Sales melaporkan toko dalam keadaan tutup. Laporan ini belum mengubah
     * apa pun pada target sampai admin membenarkannya.
     */
    public function ajukanTokoTutup(Kunjungan $kunjungan, string $catatan, ?float $lat = null, ?float $lng = null): Kunjungan
    {
        if ($kunjungan->status !== StatusKunjungan::Berjalan) {
            throw new RuntimeException(__('kunjungan.galat_tidak_berjalan'));
        }

        if (trim($catatan) === '') {
            throw new RuntimeException(__('kunjungan.galat_catatan_tutup_wajib'));
        }

        $kunjungan->loadMissing('toko');

        $kunjungan->update([
            'status' => StatusKunjungan::TutupDiajukan,
            'catatan_sales' => $catatan,
            'latitude' => $lat,
            'longitude' => $lng,
            'jarak_dari_toko_m' => $this->jarakKeToko($kunjungan->toko, $lat, $lng),
            'selesai_at' => now(),
        ]);

        return $kunjungan;
    }

    /** Admin membenarkan laporan tutup; toko keluar dari target minggu ini. */
    public function setujuiTokoTutup(Kunjungan $kunjungan, User $admin, ?string $catatan = null): void
    {
        $this->pastikanMenunggu($kunjungan);

        $kunjungan->update([
            'status' => StatusKunjungan::TutupDisetujui,
            'catatan_admin' => $catatan,
            'ditinjau_oleh' => $admin->id,
            'ditinjau_at' => now(),
        ]);
    }

    /** Admin menolak laporan tutup; toko kembali wajib dikunjungi. */
    public function tolakTokoTutup(Kunjungan $kunjungan, User $admin, ?string $catatan = null): void
    {
        $this->pastikanMenunggu($kunjungan);

        $kunjungan->update([
            'status' => StatusKunjungan::TutupDitolak,
            'catatan_admin' => $catatan,
            'ditinjau_oleh' => $admin->id,
            'ditinjau_at' => now(),
        ]);
    }

    /**
     * Toko yang menjadi tanggungan sales pada periode ini, lengkap dengan
     * kunjungannya bila sudah ada.
     *
     * @return Collection<int, Toko>
     */
    public function tanggungan(User $sales, PeriodeKunjungan $periode)
    {
        return Toko::query()
            ->whereIn('id', $this->idTanggungan($sales, $periode))
            ->with([
                'wilayah:id,nama',
                'kunjungans' => fn ($q) => $q->where('periode_kunjungan_id', $periode->id)->with('fotos'),
            ])
            ->orderBy('nama')
            ->get();
    }

    private function ditugaskan(Toko $toko, User $sales, PeriodeKunjungan $periode): bool
    {
        return PenugasanSales::query()
            ->where('sales_id', $sales->id)
            ->where('toko_id', $toko->id)
            ->whereDate('bulan', $this->bulanPeriode($periode))
            ->exists();
    }

    /** @return Collection<int, int> */
    private function idTanggungan(User $sales, PeriodeKunjungan $periode)
    {
        return PenugasanSales::query()
            ->where('sales_id', $sales->id)
            ->whereDate('bulan', $this->bulanPeriode($periode))
            ->pluck('toko_id');
    }

    private function jumlahTanggungan(User $sales, PeriodeKunjungan $periode): int
    {
        return $this->idTanggungan($sales, $periode)->count();
    }

    /**
     * Penugasan disusun per bulan, sedangkan periode berjalan per minggu.
     * Minggu yang melintasi pergantian bulan memakai bulan hari Seninnya,
     * supaya satu periode tidak pernah memakai dua daftar penugasan.
     */
    private function bulanPeriode(PeriodeKunjungan $periode): string
    {
        return $periode->tanggal_mulai->copy()->startOfMonth()->toDateString();
    }

    private function jarakKeToko(Toko $toko, ?float $lat, ?float $lng): ?int
    {
        if ($lat === null || $lng === null || $toko->latitude === null || $toko->longitude === null) {
            return null;
        }

        return (int) round(Geo::haversine(
            new Koordinat($lat, $lng),
            new Koordinat((float) $toko->latitude, (float) $toko->longitude),
        ));
    }

    private function pastikanMenunggu(Kunjungan $kunjungan): void
    {
        if ($kunjungan->status !== StatusKunjungan::TutupDiajukan) {
            throw new RuntimeException(__('kunjungan.galat_bukan_pengajuan_tutup'));
        }
    }
}
