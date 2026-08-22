<?php

namespace App\Services;

use App\Enums\StatusPesanan;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use App\Models\Pesanan;
use App\Models\RoutingBatch;
use App\Models\User;
use App\Services\Peta\Koordinat;
use App\Services\Routing\HasilRouting;
use App\Services\Routing\MesinRouting;
use App\Services\Routing\RuteKendaraan;
use App\Services\Routing\TitikPengiriman;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menjembatani mesin routing dengan basis data: mengambil pesanan yang siap
 * dikirim, menyusun rutenya, lalu menyimpan hasilnya sebagai kendaraan dan
 * daftar kunjungan yang bisa dilihat dan disunting admin.
 */
class RoutingService
{
    public function __construct(
        private readonly MesinRouting $mesin,
    ) {}

    /**
     * Pesanan berstatus PROCESS yang belum masuk rute mana pun.
     *
     * @param  array<int, int>  $wilayahIds
     * @return Collection<int, Pesanan>
     */
    public function pesananSiapRouting(array $wilayahIds = []): Collection
    {
        return Pesanan::query()
            ->siapRouting()
            ->with(['toko:id,nama,alamat,latitude,longitude,wilayah_id', 'wilayah:id,nama'])
            ->when($wilayahIds !== [], fn ($q) => $q->whereIn('wilayah_id', $wilayahIds))
            ->orderBy('tanggal')
            ->get();
    }

    /**
     * Menjalankan proses routing dan menyimpannya sebagai batch draft.
     *
     * @param  array<int, int>  $wilayahIds  kosong berarti semua wilayah
     *
     * @throws RuntimeException bila tidak ada pesanan yang bisa dirutekan
     */
    public function generate(
        User $admin,
        array $wilayahIds = [],
        ?int $maxToko = null,
        ?int $maxDus = null,
        bool $pisahPerWilayah = true,
        ?CarbonImmutable $tanggalKeberangkatan = null,
    ): RoutingBatch {
        $tanggalKeberangkatan ??= CarbonImmutable::today();
        $maxToko = $maxToko ?? config('ond.kendaraan.max_toko');
        $maxDus = $maxDus ?? config('ond.kendaraan.max_dus');

        $pesanans = $this->pesananSiapRouting($wilayahIds);

        if ($pesanans->isEmpty()) {
            throw new RuntimeException(__('routing.galat_tidak_ada_process'));
        }

        // Toko tanpa koordinat tidak bisa dihitung jaraknya. Pesanannya
        // dilewati, bukan menggagalkan seluruh proses, dan dilaporkan ke
        // admin supaya titiknya bisa dilengkapi.
        [$layak, $tanpaKoordinat] = $pesanans->partition(
            fn (Pesanan $p) => $p->toko->latitude !== null && $p->toko->longitude !== null
        );

        if ($layak->isEmpty()) {
            throw new RuntimeException(__('routing.galat_semua_tanpa_koordinat'));
        }

        $titik = $layak->map(fn (Pesanan $p) => new TitikPengiriman(
            pesananId: $p->id,
            tokoId: $p->toko_id,
            wilayahId: $p->wilayah_id,
            namaToko: $p->toko->nama,
            alamat: $p->toko->alamat,
            dus: $p->total_dus,
            koordinat: new Koordinat((float) $p->toko->latitude, (float) $p->toko->longitude),
        ))->all();

        $hasil = $this->mesin->susun($titik, $this->depot(), $maxToko, $maxDus, $pisahPerWilayah);

        foreach ($tanpaKoordinat as $p) {
            $hasil->peringatan[] = __('routing.peringatan_dilewati', [
                'kode' => $p->kode,
                'toko' => $p->toko->nama,
            ]);
        }

        return $this->simpan($hasil, $admin, $maxToko, $maxDus, $tanggalKeberangkatan);
    }

    private function simpan(HasilRouting $hasil, User $admin, int $maxToko, int $maxDus, CarbonImmutable $tanggalKeberangkatan): RoutingBatch
    {
        return DB::transaction(function () use ($hasil, $admin, $maxToko, $maxDus, $tanggalKeberangkatan): RoutingBatch {
            $batch = RoutingBatch::create([
                'kode' => $this->kodeBatch(),
                'tanggal' => $tanggalKeberangkatan,
                'status' => 'draft',
                'total_kendaraan' => count($hasil->rute),
                'total_toko' => $hasil->totalToko(),
                'total_dus' => $hasil->totalDus(),
                'total_jarak_m' => $hasil->totalJarakM(),
                'total_durasi_s' => $hasil->totalDurasiS(),
                'max_toko' => $maxToko,
                'max_dus' => $maxDus,
                'sumber_jarak' => $hasil->sumberJarak,
                'dibuat_oleh' => $admin->id,
            ]);

            $warna = config('ond.warna_kendaraan');
            $jamBerangkat = CarbonImmutable::parse(config('ond.depot.jam_berangkat'));

            foreach ($hasil->rute as $i => $rute) {
                $kendaraan = $this->simpanKendaraan($batch, $rute, $i + 1, $warna, $jamBerangkat);

                $this->simpanStops($kendaraan, $rute, $jamBerangkat);
            }

            // Relasi dimuat selengkapnya di sini supaya pemanggil bisa
            // langsung menampilkan hasilnya tanpa memicu kueri susulan.
            return $batch->fresh([
                'kendaraans.wilayah',
                'kendaraans.stops.toko',
                'kendaraans.stops.pesanan',
            ]);
        });
    }

    /** @param  array<int, string>  $warna */
    private function simpanKendaraan(
        RoutingBatch $batch,
        RuteKendaraan $rute,
        int $nomor,
        array $warna,
        CarbonImmutable $jamBerangkat,
    ): Kendaraan {
        $totalMenit = $rute->totalDurasiS / 60 + $rute->totalToko() * config('ond.depot.service_minutes');

        return $batch->kendaraans()->create([
            'wilayah_id' => $rute->wilayahId ?: null,
            'nomor' => $nomor,
            'nama' => "Mobil {$nomor}",
            'warna' => $warna[($nomor - 1) % count($warna)],
            'total_toko' => $rute->totalToko(),
            'total_dus' => $rute->totalDus(),
            'total_jarak_m' => (int) round($rute->totalJarakM),
            'total_durasi_s' => (int) round($rute->totalDurasiS),
            'jam_berangkat' => $jamBerangkat->format('H:i:s'),
            'estimasi_selesai' => $jamBerangkat->addMinutes((int) round($totalMenit))->format('H:i:s'),
            'geometry' => $rute->geometry,
            'status' => 'draft',
        ]);
    }

    private function simpanStops(Kendaraan $kendaraan, RuteKendaraan $rute, CarbonImmutable $jamBerangkat): void
    {
        $waktu = $jamBerangkat;
        $bongkarMenit = (int) config('ond.depot.service_minutes');

        foreach ($rute->titik as $i => $titik) {
            $leg = $rute->legs[$i] ?? ['jarak_m' => 0, 'durasi_s' => 0];

            // Waktu tiba = waktu berangkat dari titik sebelumnya + perjalanan.
            $waktu = $waktu->addSeconds((int) round($leg['durasi_s']));

            $kendaraan->stops()->create([
                'pesanan_id' => $titik->pesananId,
                'toko_id' => $titik->tokoId,
                'urutan' => $i + 1,
                'total_dus' => $titik->dus,
                'jarak_dari_sebelumnya_m' => (int) round($leg['jarak_m']),
                'durasi_dari_sebelumnya_s' => (int) round($leg['durasi_s']),
                'eta' => $waktu->format('H:i:s'),
                'status' => 'pending',
            ]);

            // Tambah waktu bongkar sebelum lanjut ke toko berikutnya.
            $waktu = $waktu->addMinutes($bongkarMenit);
        }
    }

    /**
     * Menyetujui rute: semua pesanan di dalamnya naik ke status DELIVERY dan
     * kendaraan siap diambil driver.
     */
    public function setujui(RoutingBatch $batch, User $admin): void
    {
        if (! $batch->isDraft()) {
            throw new RuntimeException(__('routing.galat_bukan_draft'));
        }

        DB::transaction(function () use ($batch, $admin): void {
            $pesananIds = KendaraanStop::query()
                ->whereIn('kendaraan_id', $batch->kendaraans()->select('id'))
                ->pluck('pesanan_id');

            Pesanan::whereIn('id', $pesananIds)->update([
                'status' => StatusPesanan::Delivery->value,
                'dikirim_at' => now(),
                'updated_at' => now(),
            ]);

            // Muatan saat berangkat disalin ke target_dus dan tidak pernah
            // berubah lagi. Ia menjadi penyebut persentase pengiriman, supaya
            // toko yang dibatalkan di jalan menurunkan angka itu alih-alih
            // menyusutkan targetnya sendiri.
            $batch->kendaraans()->update([
                'status' => 'siap',
                'target_dus' => DB::raw('total_dus'),
            ]);

            $batch->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $admin->id,
                'disetujui_at' => now(),
            ]);
        });
    }

    /** Membuang rute draft agar pesanannya bisa dirutekan ulang. */
    public function hapusDraft(RoutingBatch $batch): void
    {
        if (! $batch->isDraft()) {
            throw new RuntimeException(__('routing.galat_hanya_draft'));
        }

        DB::transaction(function () use ($batch): void {
            // forceDelete, bukan soft delete, khusus untuk kendaraan dan
            // kunjungan draft ini: draf yang dibuang di sini belum pernah
            // disetujui atau dijalankan, jadi tidak ada riwayat nyata yang
            // hilang. Soft delete di sini justru berbahaya — begitu
            // pesanannya dirutekan ulang dan mendapat kunjungan baru,
            // INSERT-nya akan bentrok dengan batasan unik
            // kendaraan_stops.pesanan_id milik baris lama yang masih
            // "tertinggal" (soft-deleted tetap menghitung untuk batasan
            // unik). Batch-nya sendiri tetap soft delete seperti biasa —
            // itu cuma catatan bahwa sebuah draf pernah dibuat lalu dibuang.
            $kendaraanIds = $batch->kendaraans()->pluck('id');

            KendaraanStop::whereIn('kendaraan_id', $kendaraanIds)->forceDelete();
            Kendaraan::whereIn('id', $kendaraanIds)->forceDelete();
            $batch->delete();
        });
    }

    /**
     * Memindahkan satu toko ke kendaraan lain, lalu menghitung ulang kedua
     * rute yang terpengaruh. Dipakai admin ketika hasil otomatis perlu
     * disesuaikan dengan kondisi lapangan.
     */
    public function pindahStop(KendaraanStop $stop, Kendaraan $tujuan, ?int $posisi = null): void
    {
        $stop->loadMissing('kendaraan');

        $asal = $stop->kendaraan;

        if ($asal->id === $tujuan->id) {
            return;
        }

        if ($asal->routing_batch_id !== $tujuan->routing_batch_id) {
            throw new RuntimeException(__('routing.galat_batch_beda'));
        }

        DB::transaction(function () use ($stop, $asal, $tujuan, $posisi): void {
            $urutanBaru = $posisi ?? (((int) $tujuan->stops()->max('urutan')) + 1);

            $tujuan->stops()->where('urutan', '>=', $urutanBaru)->increment('urutan');

            $stop->update([
                'kendaraan_id' => $tujuan->id,
                'urutan' => $urutanBaru,
            ]);

            $this->rapikanUrutan($asal);
            $this->rapikanUrutan($tujuan);

            $this->hitungUlang($asal->fresh());
            $this->hitungUlang($tujuan->fresh());
        });
    }

    /**
     * Mengeluarkan satu toko dari rute sepenuhnya — pesanannya kembali ke
     * antrean "siap dirutekan" (pesananSiapRouting()), bukan berpindah ke
     * kendaraan lain. Dipakai admin ketika satu toko memang tidak seharusnya
     * ikut dikirim hari itu, tanpa harus memaksakannya masuk ke salah satu
     * mobil yang ada.
     *
     * forceDelete, bukan soft delete: batch ini masih draft, jadi stop-nya
     * belum pernah jadi kunjungan sungguhan — tidak ada riwayat nyata yang
     * hilang. Soft delete di sini justru berbahaya, persis alasan yang sama
     * dengan hapusDraft(): begitu pesanannya dirutekan ulang dan mendapat
     * stop baru, INSERT-nya akan bentrok dengan batasan unik
     * kendaraan_stops.pesanan_id milik baris lama yang masih "tertinggal".
     */
    public function keluarkanDariRute(KendaraanStop $stop): void
    {
        $stop->loadMissing('kendaraan');
        $kendaraan = $stop->kendaraan;

        DB::transaction(function () use ($stop, $kendaraan): void {
            $stop->forceDelete();

            $this->rapikanUrutan($kendaraan);
            $this->hitungUlang($kendaraan->fresh());
        });
    }

    /**
     * Mengubah urutan kunjungan sebuah kendaraan sesuai daftar yang diberikan.
     *
     * @param  array<int, int>  $stopIds  id stop sesuai urutan baru
     */
    public function ubahUrutan(Kendaraan $kendaraan, array $stopIds): void
    {
        DB::transaction(function () use ($kendaraan, $stopIds): void {
            foreach ($stopIds as $i => $id) {
                $kendaraan->stops()->whereKey($id)->update(['urutan' => $i + 1]);
            }

            $this->hitungUlang($kendaraan->fresh());
        });
    }

    /** Menyusun ulang urutan sebuah kendaraan secara otomatis (TSP). */
    public function optimalkanUlang(Kendaraan $kendaraan): void
    {
        $stops = $kendaraan->stops()->with('toko:id,latitude,longitude')->get()
            ->filter(fn (KendaraanStop $s) => $s->toko->latitude !== null);

        if ($stops->count() < 3) {
            return;
        }

        $titik = $stops->map(fn (KendaraanStop $s) => new TitikPengiriman(
            pesananId: $s->pesanan_id,
            tokoId: $s->toko_id,
            wilayahId: (int) $kendaraan->wilayah_id,
            namaToko: '',
            alamat: '',
            dus: $s->total_dus,
            koordinat: new Koordinat((float) $s->toko->latitude, (float) $s->toko->longitude),
        ))->values()->all();

        // Batas kapasitas dibuat sebesar isi kendaraan itu sendiri supaya
        // tidak dipecah, hanya urutannya yang dihitung ulang.
        $hasil = $this->mesin->susun(
            $titik,
            $this->depot(),
            maxToko: count($titik),
            maxDus: PHP_INT_MAX,
            pisahPerWilayah: false,
        );

        if ($hasil->rute === []) {
            return;
        }

        $urutanPesanan = array_map(fn (TitikPengiriman $t) => $t->pesananId, $hasil->rute[0]->titik);

        DB::transaction(function () use ($kendaraan, $urutanPesanan): void {
            foreach ($urutanPesanan as $i => $pesananId) {
                $kendaraan->stops()->where('pesanan_id', $pesananId)->update(['urutan' => $i + 1]);
            }

            $this->hitungUlang($kendaraan->fresh());
        });
    }

    /**
     * Menghitung ulang jarak, durasi, ETA dan garis rute sebuah kendaraan
     * berdasarkan urutan stop yang tersimpan sekarang.
     */
    public function hitungUlang(Kendaraan $kendaraan): void
    {
        $stops = $kendaraan->stops()->with('toko:id,latitude,longitude')->get();

        if ($stops->isEmpty()) {
            $kendaraan->update([
                'total_toko' => 0,
                'total_dus' => 0,
                'total_jarak_m' => 0,
                'total_durasi_s' => 0,
                'geometry' => null,
            ]);

            $this->perbaruiTotalBatch($kendaraan->batch);

            return;
        }

        $koordinat = $stops
            ->filter(fn (KendaraanStop $s) => $s->toko->latitude !== null && $s->toko->longitude !== null)
            ->map(fn (KendaraanStop $s) => new Koordinat((float) $s->toko->latitude, (float) $s->toko->longitude))
            ->values()
            ->all();

        $hasil = $this->mesin->hitungUlang($koordinat, $this->depot());

        $jamBerangkat = CarbonImmutable::parse($kendaraan->jam_berangkat ?? config('ond.depot.jam_berangkat'));
        $waktu = $jamBerangkat;
        $bongkarMenit = (int) config('ond.depot.service_minutes');

        foreach ($stops->values() as $i => $stop) {
            $leg = $hasil['legs'][$i] ?? ['jarak_m' => 0, 'durasi_s' => 0];
            $waktu = $waktu->addSeconds((int) round($leg['durasi_s']));

            $stop->update([
                'jarak_dari_sebelumnya_m' => (int) round($leg['jarak_m']),
                'durasi_dari_sebelumnya_s' => (int) round($leg['durasi_s']),
                'eta' => $waktu->format('H:i:s'),
            ]);

            $waktu = $waktu->addMinutes($bongkarMenit);
        }

        $totalMenit = $hasil['durasi_s'] / 60 + $stops->count() * $bongkarMenit;

        $kendaraan->update([
            'total_toko' => $stops->count(),
            'total_dus' => (int) $stops->sum('total_dus'),
            'total_jarak_m' => (int) round($hasil['jarak_m']),
            'total_durasi_s' => (int) round($hasil['durasi_s']),
            'estimasi_selesai' => $jamBerangkat->addMinutes((int) round($totalMenit))->format('H:i:s'),
            'geometry' => $hasil['geometry'],
        ]);

        $this->perbaruiTotalBatch($kendaraan->batch);
    }

    /** Menutup celah nomor urut setelah stop dipindah atau dihapus. */
    private function rapikanUrutan(Kendaraan $kendaraan): void
    {
        $stops = $kendaraan->stops()->orderBy('urutan')->get();

        foreach ($stops as $i => $stop) {
            if ($stop->urutan !== $i + 1) {
                $stop->update(['urutan' => $i + 1]);
            }
        }
    }

    private function perbaruiTotalBatch(RoutingBatch $batch): void
    {
        $kendaraans = $batch->kendaraans()->get();

        $batch->update([
            'total_kendaraan' => $kendaraans->count(),
            'total_toko' => (int) $kendaraans->sum('total_toko'),
            'total_dus' => (int) $kendaraans->sum('total_dus'),
            'total_jarak_m' => (int) $kendaraans->sum('total_jarak_m'),
            'total_durasi_s' => (int) $kendaraans->sum('total_durasi_s'),
        ]);
    }

    /** Menambah satu kendaraan kosong pada batch draft. */
    public function tambahKendaraan(RoutingBatch $batch): Kendaraan
    {
        // withTrashed(): nomor tidak boleh dipakai ulang meskipun kendaraan
        // kosong sebelumnya sudah dihapus — batasan unik (routing_batch_id,
        // nomor) tetap menghitung baris yang di-soft-delete.
        $nomor = ((int) $batch->kendaraans()->withTrashed()->max('nomor')) + 1;
        $warna = config('ond.warna_kendaraan');

        return $batch->kendaraans()->create([
            'nomor' => $nomor,
            'nama' => "Mobil {$nomor}",
            'warna' => $warna[($nomor - 1) % count($warna)],
            'jam_berangkat' => CarbonImmutable::parse(config('ond.depot.jam_berangkat'))->format('H:i:s'),
            'status' => 'draft',
        ]);
    }

    public function depot(): Koordinat
    {
        return new Koordinat(
            (float) config('ond.depot.lat'),
            (float) config('ond.depot.lng'),
        );
    }

    private function kodeBatch(): string
    {
        $prefix = 'RTG-'.now()->format('Ymd');
        // withTrashed(): kode tidak boleh dipakai ulang meskipun draft
        // sebelumnya di hari yang sama sudah dibuang — kode punya batasan
        // unik yang tetap menghitung baris yang di-soft-delete.
        $urutan = RoutingBatch::withTrashed()->whereDate('created_at', today())->count() + 1;

        return sprintf('%s-%02d', $prefix, $urutan);
    }
}
