<?php

namespace App\Services\Kunjungan;

use App\Enums\JenisFotoKunjungan;
use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\PenugasanToko;
use App\Models\PeriodeSales;
use App\Models\Toko;
use App\Models\User;
use App\Services\Peta\Geo;
use App\Services\Peta\Koordinat;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Menerima kunjungan yang dikerjakan sales saat tidak ada jaringan.
 *
 * Bentuknya sengaja SATU ARAH — perangkat mengirim apa yang sudah terjadi,
 * server tidak pernah mengirim balik perubahan untuk digabungkan. Itu membuat
 * seluruh persoalan sinkronisasi dua arah (siapa menang kalau dua sisi
 * mengubah hal yang sama) tidak pernah muncul sama sekali.
 *
 * Tiap kunjungan diproses dalam transaksinya sendiri, jadi satu kiriman yang
 * bermasalah tidak ikut menggagalkan kunjungan lain dalam kiriman yang sama —
 * sales di lapangan bisa saja menahan belasan kunjungan sekaligus, dan
 * kehilangan semuanya gara-gara satu foto rusak jelas tidak bisa diterima.
 *
 * Empat kemungkinan jawaban untuk tiap kunjungan, dan perangkat memakainya
 * untuk memutuskan nasib antreannya:
 *
 *  - `diterima`  tersimpan. Perangkat boleh menghapusnya dari antrean.
 *  - `duplikat`  sudah pernah masuk sebelumnya (sinyal putus setelah server
 *                menyimpan tapi sebelum jawabannya sampai). Sama seperti
 *                diterima — hapus dari antrean, jangan kirim ulang.
 *  - `konflik`   tokonya sudah tertangani orang lain pada periode itu.
 *                Berhenti mencoba, tunjukkan ke sales.
 *  - `ditolak`   melanggar aturan (kedaluwarsa, bukan tanggungannya, foto
 *                kurang). Mengirim ulang tidak akan mengubah apa pun.
 */
class SinkronOffline
{
    public function __construct(
        private readonly PeriodeKunjunganService $periodeService,
        private readonly PenandaFoto $penandaFoto,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $daftar
     * @return array<int, array{uuid_klien: string, status: string, pesan: ?string, kunjungan_id: ?int}>
     */
    public function sinkronkan(array $daftar, User $sales): array
    {
        return array_map(fn (array $item): array => $this->satuKunjungan($item, $sales), $daftar);
    }

    /** @param array<string, mixed> $item */
    private function satuKunjungan(array $item, User $sales): array
    {
        $berkasTertulis = [];

        try {
            // Closure biasa dengan `use (&...)`, BUKAN arrow function: arrow
            // function menangkap variabel by value, sehingga daftar berkas
            // yang ditulis di dalam transaksi tidak akan pernah terlihat oleh
            // penanganan galat di bawah — dan foto yatimnya lolos terus.
            return DB::transaction(function () use ($item, $sales, &$berkasTertulis): array {
                return $this->proses($item, $sales, $berkasTertulis);
            });
        } catch (QueryException $e) {
            // Dua perangkat mengirim toko yang sama nyaris bersamaan: batasan
            // unik di basis data yang menahannya, bukan pemeriksaan di atas.
            $this->bersihkanBerkas($berkasTertulis);

            return $this->hasil($item, 'konflik', __('kunjungan.galat_sinkron_bentrok'));
        } catch (RuntimeException $e) {
            // Foto yang sempat ditulis sebelum penolakan harus ikut dibuang —
            // baris basis datanya dibatalkan transaksi, tapi berkas di disk
            // tidak, dan yatim seperti itu tidak akan pernah terhapus sendiri.
            $this->bersihkanBerkas($berkasTertulis);

            return $this->hasil($item, 'ditolak', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $berkasTertulis
     */
    private function proses(array $item, User $sales, array &$berkasTertulis): array
    {
        $sudahPernah = Kunjungan::where('uuid_klien', $item['uuid_klien'])->first();

        if ($sudahPernah !== null) {
            return $this->hasil($item, 'duplikat', __('kunjungan.sinkron_duplikat'), $sudahPernah->id);
        }

        $toko = $this->tokoTanggungan($item['toko_id'], $sales);
        $mulaiAt = $this->waktuSah($item['mulai_at']);
        $selesaiAt = $this->waktuSah($item['selesai_at'] ?? $item['mulai_at']);
        $status = $this->statusSah($item);

        // Foto diperiksa lengkap SEBELUM satu berkas pun ditulis, supaya
        // kunjungan yang memang belum tuntas tidak meninggalkan sampah.
        $fotos = $item['fotos'] ?? [];

        if ($status === StatusKunjungan::Selesai) {
            $this->pastikanFotoLengkap($fotos);
        }

        // Periode ditentukan dari KAPAN kunjungan dikerjakan, bukan kapan
        // dikirim. Kunjungan Sabtu sore yang baru terkirim Senin pagi tetap
        // masuk hitungan minggu lalu, bukan minggu berjalan.
        $periode = $this->periodeService->periodeUntuk($mulaiAt);

        $adaSebelumnya = Kunjungan::query()
            ->where('periode_kunjungan_id', $periode->id)
            ->where('toko_id', $toko->id)
            ->lockForUpdate()
            ->first();

        if ($adaSebelumnya !== null && ! $this->bisaDipakaiUlang($adaSebelumnya, $sales)) {
            return $this->hasil($item, 'konflik', __('kunjungan.galat_sudah_dikunjungi', [
                'nama' => $toko->nama,
                'sales' => $adaSebelumnya->sales_id === $sales->id
                    ? __('kunjungan.oleh_anda')
                    : $adaSebelumnya->sales()->value('name'),
            ]));
        }

        $periodeSales = PeriodeSales::firstOrCreate(
            ['periode_kunjungan_id' => $periode->id, 'sales_id' => $sales->id],
            ['target_toko' => PenugasanToko::where('sales_id', $sales->id)->count()],
        );

        $atribut = [
            'periode_kunjungan_id' => $periode->id,
            'periode_sales_id' => $periodeSales->id,
            'sales_id' => $sales->id,
            'toko_id' => $toko->id,
            'status' => $status,
            'uuid_klien' => $item['uuid_klien'],
            'asset_id_terpindai' => $item['asset_id_terpindai'] ?? null,
            'mulai_at' => $mulaiAt,
            'selesai_at' => $selesaiAt,
            'disinkronkan_at' => CarbonImmutable::now(),
            'latitude' => $item['latitude'] ?? null,
            'longitude' => $item['longitude'] ?? null,
            'akurasi_m' => $item['akurasi_m'] ?? null,
            'jarak_dari_toko_m' => $this->jarakKeToko($toko, $item['latitude'] ?? null, $item['longitude'] ?? null),
            'catatan_sales' => $item['catatan_sales'] ?? null,
        ];

        if ($adaSebelumnya === null) {
            $kunjungan = Kunjungan::create($atribut);
        } else {
            $adaSebelumnya->update($atribut);
            $kunjungan = $adaSebelumnya;
        }

        foreach ($fotos as $foto) {
            $this->simpanFoto($kunjungan, $toko, $sales, $foto, $berkasTertulis);
        }

        return $this->hasil($item, 'diterima', null, $kunjungan->id);
    }

    /**
     * Kunjungan yang sudah ada boleh ditimpa hanya dalam dua keadaan: milik
     * sales ini sendiri yang belum tuntas (ia mulai daring lalu kehilangan
     * sinyal di tengah jalan), atau laporan tutup yang sudah ditolak admin
     * (tokonya memang wajib dikunjungi lagi — lihat Toko::perluDikunjungi()).
     */
    private function bisaDipakaiUlang(Kunjungan $kunjungan, User $sales): bool
    {
        if ($kunjungan->status === StatusKunjungan::TutupDitolak) {
            return true;
        }

        return $kunjungan->status === StatusKunjungan::Berjalan
            && $kunjungan->sales_id === $sales->id;
    }

    private function tokoTanggungan(mixed $tokoId, User $sales): Toko
    {
        $toko = Toko::find($tokoId);

        if ($toko === null) {
            throw new RuntimeException(__('kunjungan.galat_sinkron_toko_hilang'));
        }

        if (! $toko->aktif) {
            throw new RuntimeException(__('kunjungan.galat_toko_nonaktif', ['nama' => $toko->nama]));
        }

        $ditugaskan = PenugasanToko::where('sales_id', $sales->id)
            ->where('toko_id', $toko->id)
            ->exists();

        if (! $ditugaskan) {
            throw new RuntimeException(__('kunjungan.galat_bukan_tanggungan', ['nama' => $toko->nama]));
        }

        return $toko;
    }

    /**
     * Jam kunjungan berasal dari ponsel sales, yang bisa diubah sendiri oleh
     * pemakainya. Dua pagar ini yang menjaganya tetap masuk akal: tidak boleh
     * dari masa depan (di luar kelonggaran jam yang melenceng sedikit), dan
     * tidak boleh terlalu tua untuk mencegah kunjungan "susulan" yang
     * sebenarnya tidak pernah terjadi.
     */
    private function waktuSah(mixed $nilai): CarbonImmutable
    {
        try {
            // Peramban mengirim waktu berakhiran Z (UTC). Tanpa dipindahkan
            // ke zona aplikasi, nilainya tersimpan apa adanya sementara
            // seluruh waktu lain di basis data memakai waktu lokal — dan
            // kunjungan offline akan tampil tujuh jam lebih pagi daripada
            // yang sebenarnya, termasuk pada watermark fotonya.
            $waktu = CarbonImmutable::parse((string) $nilai)
                ->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            throw new RuntimeException(__('kunjungan.galat_sinkron_waktu_tidak_sah'));
        }

        $sekarang = CarbonImmutable::now();
        $toleransi = (int) config('visit.offline.toleransi_maju_menit');
        $maksUmur = (int) config('visit.offline.maks_umur_hari');

        if ($waktu->greaterThan($sekarang->addMinutes($toleransi))) {
            throw new RuntimeException(__('kunjungan.galat_sinkron_waktu_maju'));
        }

        if ($waktu->lessThan($sekarang->subDays($maksUmur))) {
            throw new RuntimeException(__('kunjungan.galat_sinkron_kedaluwarsa', ['hari' => $maksUmur]));
        }

        return $waktu;
    }

    /** @param array<string, mixed> $item */
    private function statusSah(array $item): StatusKunjungan
    {
        $status = StatusKunjungan::tryFrom((string) ($item['status'] ?? ''));

        // Hanya dua keadaan tuntas yang masuk akal dikerjakan offline.
        // 'berjalan' tidak dikirim (belum selesai, tetap di perangkat), dan
        // keputusan tutup disetujui/ditolak adalah wewenang admin.
        if (! in_array($status, [StatusKunjungan::Selesai, StatusKunjungan::TutupDiajukan], true)) {
            throw new RuntimeException(__('kunjungan.galat_sinkron_status'));
        }

        if ($status === StatusKunjungan::TutupDiajukan && trim((string) ($item['catatan_sales'] ?? '')) === '') {
            throw new RuntimeException(__('kunjungan.galat_catatan_tutup_wajib'));
        }

        return $status;
    }

    /** @param array<int, array<string, mixed>> $fotos */
    private function pastikanFotoLengkap(array $fotos): void
    {
        $dikirim = array_map(fn (array $f): string => (string) ($f['jenis'] ?? ''), $fotos);

        $kurang = array_values(array_filter(
            JenisFotoKunjungan::urut(),
            fn (JenisFotoKunjungan $j): bool => ! in_array($j->value, $dikirim, true),
        ));

        if ($kurang !== []) {
            throw new RuntimeException(__('kunjungan.galat_foto_kurang', [
                'daftar' => implode(', ', array_map(fn (JenisFotoKunjungan $j): string => $j->label(), $kurang)),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $foto
     * @param  array<int, string>  $berkasTertulis
     */
    private function simpanFoto(Kunjungan $kunjungan, Toko $toko, User $sales, array $foto, array &$berkasTertulis): void
    {
        $jenis = JenisFotoKunjungan::tryFrom((string) ($foto['jenis'] ?? ''));

        if ($jenis === null) {
            throw new RuntimeException(__('kunjungan.galat_jenis_foto'));
        }

        $isi = GambarDataUrl::dekode((string) ($foto['gambar'] ?? ''));

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $diambilAt = $this->waktuSah($foto['diambil_at'] ?? $kunjungan->mulai_at);
        $lat = isset($foto['latitude']) ? (float) $foto['latitude'] : null;
        $lng = isset($foto['longitude']) ? (float) $foto['longitude'] : null;
        $akurasi = isset($foto['akurasi_m']) ? (int) $foto['akurasi_m'] : null;

        $hasil = $this->penandaFoto->simpan(
            isiGambar: $isi,
            toko: $toko,
            sales: $sales,
            lat: $lat,
            lng: $lng,
            akurasi: $akurasi,
            diambilAt: $diambilAt,
        );

        $berkasTertulis[] = $hasil['path'];

        // Kunjungan yang ditimpa (mis. laporan tutup yang ditolak admin) bisa
        // masih menyimpan foto lama dengan jenis yang sama. Diganti, bukan
        // ditumpuk — sama seperti alur daring.
        $lama = $kunjungan->fotos()->where('jenis', $jenis->value)->first();

        if ($lama !== null) {
            Storage::disk(config('visit.foto.disk'))->delete($lama->path);
            $lama->delete();
        }

        $kunjungan->fotos()->create([
            'jenis' => $jenis,
            'path' => $hasil['path'],
            'diambil_at' => $hasil['diambil_at'],
            'disinkronkan_at' => CarbonImmutable::now(),
            'latitude' => $lat,
            'longitude' => $lng,
            'akurasi_m' => $akurasi,
            'lebar' => $hasil['lebar'],
            'tinggi' => $hasil['tinggi'],
            'ukuran_byte' => $hasil['ukuran'],
        ]);
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

    /** @param array<int, string> $paths */
    private function bersihkanBerkas(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(config('visit.foto.disk'))->delete($path);
        }
    }

    /** @param array<string, mixed> $item */
    private function hasil(array $item, string $status, ?string $pesan = null, ?int $kunjunganId = null): array
    {
        return [
            'uuid_klien' => (string) ($item['uuid_klien'] ?? ''),
            'status' => $status,
            'pesan' => $pesan,
            'kunjungan_id' => $kunjunganId,
        ];
    }
}
