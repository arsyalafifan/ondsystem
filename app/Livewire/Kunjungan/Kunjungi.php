<?php

namespace App\Livewire\Kunjungan;

use App\Enums\JenisFotoKunjungan;
use App\Enums\StatusKunjungan;
use App\Enums\SumberFotoKunjungan;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Kunjungan;
use App\Models\Toko;
use App\Services\Kunjungan\GambarContoh;
use App\Services\Kunjungan\GambarDataUrl;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use App\Support\ModeUji;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Layar kerja sales di lapangan.
 *
 * Kunjungan biasanya dimulai dengan memindai QR code pada freezer — itulah
 * bukti bahwa sales benar-benar berdiri di depan freezer yang bersangkutan,
 * jadi pindai tetap jadi cara bawaan. Tapi tidak semua toko sudah punya
 * stiker QR/nomor aset, jadi disediakan juga pencarian ketik sebagai jalan
 * cadangan — dibatasi hanya pada toko yang sudah ditugaskan admin ke sales
 * ini, memakai aturan yang sama persis dengan hasil pindai (lihat
 * KunjunganService::mulai()).
 */
class Kunjungi extends Component
{
    use MembutuhkanDepotTerkunci;
    use WithFileUploads;

    /** 'pindai' saat menunggu QR, 'kunjungan' saat mengambil foto. */
    public string $tahap = 'pindai';

    /** 'pindai' atau 'ketik', hanya dipakai selagi tahap 'pindai'. */
    public string $caraPilihToko = 'pindai';

    public string $cariToko = '';

    public ?int $kunjunganId = null;

    public string $catatan = '';

    public string $catatanTutup = '';

    public bool $formTutupTerbuka = false;

    public ?float $lat = null;

    public ?float $lng = null;

    public ?int $akurasi = null;

    /** Jenis foto yang sedang dibidik, dipakai untuk menandai tombolnya. */
    public ?string $jenisSedangDiambil = null;

    /** Isi QR yang ditempel manual. Hanya dipakai saat mode uji menyala. */
    public string $qrManual = '';

    /**
     * Berkas yang dipilih sales dari galeri, sebagai jalan keluar ketika
     * kamera tidak bisa diakses. Jenis fotonya dibawa terpisah karena satu
     * input berkas dipakai bergantian untuk kelima jenis.
     */
    public $berkasUnggahan = null;

    public ?string $jenisUnggahan = null;

    public function mount(): void
    {
        // Kunjungan yang belum tuntas dilanjutkan, supaya sales yang aplikasinya
        // tertutup di tengah jalan tidak kehilangan foto yang sudah diambil.
        $berjalan = Kunjungan::query()
            ->where('sales_id', auth()->id())
            ->where('status', StatusKunjungan::Berjalan)
            ->latest('id')
            ->first();

        if ($berjalan !== null) {
            $this->kunjunganId = $berjalan->id;
            $this->tahap = 'kunjungan';
        }
    }

    #[Computed]
    public function kunjungan(): ?Kunjungan
    {
        return $this->kunjunganId === null
            ? null
            : Kunjungan::with(['toko.wilayah:id,nama', 'fotos'])->find($this->kunjunganId);
    }

    #[Computed]
    public function jenisFoto(): array
    {
        return JenisFotoKunjungan::urut();
    }

    #[Computed]
    public function modeUji(): bool
    {
        return ModeUji::aktif();
    }

    /**
     * Toko tanggungan yang belum dikunjungi, beserta isi QR-nya. Dipakai mode
     * uji untuk mengisi kotak tempel dengan satu klik.
     *
     * @return array<int, array{nama: string, qr: string}>
     */
    #[Computed]
    public function contohQr(): array
    {
        if (! ModeUji::aktif()) {
            return [];
        }

        $periode = app(PeriodeKunjunganService::class)->periodeBerjalan();

        return app(KunjunganService::class)
            ->tanggungan(auth()->user(), $periode)
            ->filter(fn ($toko) => $toko->asset_id !== null && $toko->perluDikunjungi())
            ->take(15)
            ->map(fn ($toko) => [
                'nama' => $toko->nama,
                // Bentuknya persis seperti QR yang tercetak pada freezer.
                'qr' => "客户名称：{$toko->freezer_pelanggan}\n资产编号：{$toko->asset_id}\n产品型号：{$toko->freezer_tipe}",
            ])
            ->values()
            ->all();
    }

    /**
     * Memproses isi QR yang ditempel manual.
     *
     * Sengaja melewati jalur yang sama persis dengan hasil pemindaian kamera,
     * jadi penguraian QR dan seluruh aturan kunjungan tetap ikut teruji —
     * yang digantikan hanya langkah membidik kameranya.
     */
    public function prosesQrManual(KunjunganService $service): void
    {
        ModeUji::pastikanAktif();

        if (trim($this->qrManual) === '') {
            return;
        }

        $this->prosesQr($this->qrManual, $service);
        $this->qrManual = '';
    }

    #[Computed]
    public function progres(): array
    {
        $periode = app(PeriodeKunjunganService::class)->periodeBerjalan();

        $baris = $periode->periodeSales()
            ->with('kunjungans')
            ->where('sales_id', auth()->id())
            ->first();

        return $baris?->progres ?? [
            'target' => 0, 'target_efektif' => 0, 'selesai' => 0,
            'tutup' => 0, 'menunggu' => 0, 'berjalan' => 0, 'belum' => 0, 'persen' => 0,
        ];
    }

    /** Titik GPS terkini dari peramban. */
    public function catatLokasi(?float $lat = null, ?float $lng = null, ?int $akurasi = null): void
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->akurasi = $akurasi;
    }

    public function gantiCaraPilih(string $cara): void
    {
        $this->caraPilihToko = in_array($cara, ['ketik', 'pindai'], true) ? $cara : 'pindai';
        $this->cariToko = '';
    }

    /**
     * Toko tanggungan yang cocok dengan ketikan, untuk sales yang tokonya
     * belum punya stiker QR/nomor aset.
     *
     * Sengaja dibatasi pada `tanggungan()` (daftar yang sama dipakai
     * `KunjunganService::ditugaskan()`), bukan seluruh toko aktif — supaya
     * hasil pencarian tidak pernah menawarkan toko yang bukan tanggungan
     * sales ini, sekalipun `mulai()` akan menolaknya juga.
     */
    #[Computed]
    public function hasilCariToko(): Collection
    {
        $kata = trim($this->cariToko);

        if (mb_strlen($kata) < 2) {
            return collect();
        }

        $periode = app(PeriodeKunjunganService::class)->periodeBerjalan();
        $kataAtas = mb_strtoupper($kata);
        $aset = mb_strtoupper(preg_replace('/\s+/', '', $kata) ?? '');

        return app(KunjunganService::class)
            ->tanggungan(auth()->user(), $periode)
            // Toko yang sudah punya kunjungan periode ini (selesai, berjalan,
            // atau menunggu persetujuan tutup) tidak perlu ditawarkan lagi;
            // mulai() akan menolaknya dengan pesan yang sama persis. Kecuali
            // yang laporan tutupnya DITOLAK admin — toko itu tetap wajib
            // dikunjungi, jadi harus tetap muncul supaya bisa dimulai lagi
            // (lihat Toko::perluDikunjungi() dan mulai()).
            ->filter(fn (Toko $t) => $t->perluDikunjungi())
            ->filter(fn (Toko $t) => str_contains(mb_strtoupper($t->nama), $kataAtas)
                || str_contains(mb_strtoupper($t->kode), $kataAtas)
                || str_contains(mb_strtoupper((string) $t->alamat), $kataAtas)
                || ($t->asset_id !== null && str_contains(mb_strtoupper($t->asset_id), $aset)))
            ->take(12)
            ->values();
    }

    public function pilihToko(int $tokoId, KunjunganService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $toko = Toko::find($tokoId);

        if ($toko === null) {
            return;
        }

        try {
            $kunjungan = $service->mulai($toko, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->cariToko = '';
        $this->mulaiKunjungan($kunjungan);
    }

    public function prosesQr(string $isi, KunjunganService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            $this->dispatch('qr-ditolak');

            return;
        }

        try {
            $kunjungan = $service->mulaiDariQr($isi, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
            $this->dispatch('qr-ditolak');

            return;
        }

        $this->mulaiKunjungan($kunjungan);
    }

    private function mulaiKunjungan(Kunjungan $kunjungan): void
    {
        $this->kunjunganId = $kunjungan->id;
        $this->tahap = 'kunjungan';
        $this->catatan = '';

        unset($this->kunjungan, $this->progres);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_toko_dikenali', [
            'nama' => $kunjungan->toko->nama,
        ]));
    }

    /**
     * Menerima satu bidikan kamera. Gambar dikirim sebagai data URL, lalu
     * diperkecil dan diberi watermark di server.
     */
    public function simpanJepretan(string $jenis, string $gambar, ?array $lokasi, KunjunganService $service): void
    {
        $kunjungan = $this->kunjungan;

        if ($kunjungan === null) {
            return;
        }

        $jenisFoto = JenisFotoKunjungan::tryFrom($jenis);

        if ($jenisFoto === null) {
            $this->dispatch('notifikasi', pesan: __('kunjungan.galat_jenis_foto'), jenis: 'error');

            return;
        }

        $isi = $this->dekodeDataUrl($gambar);

        if ($isi === null) {
            $this->dispatch('notifikasi', pesan: __('kunjungan.galat_gambar_rusak'), jenis: 'error');

            return;
        }

        try {
            $service->simpanFoto(
                kunjungan: $kunjungan,
                jenis: $jenisFoto,
                isiGambar: $isi,
                lat: $lokasi['lat'] ?? null,
                lng: $lokasi['lng'] ?? null,
                akurasi: isset($lokasi['akurasi']) ? (int) $lokasi['akurasi'] : null,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->jenisSedangDiambil = null;
        unset($this->kunjungan);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_foto_tersimpan', [
            'jenis' => $jenisFoto->label(),
        ]));
    }

    /**
     * Menerima foto dari galeri — jalan keluar ketika kamera tidak bisa
     * diakses sama sekali.
     *
     * Ini sengaja MELONGGARKAN aturan yang dijaga ketat di jalur kamera:
     * berkas dari galeri bisa gambar apa pun dari kapan pun. Dibuka karena
     * kamera yang bermasalah mengunci sales dari seluruh pekerjaannya, dan
     * bukti lemah yang ditandai jujur lebih berguna daripada tidak ada
     * kunjungan sama sekali. Yang menjaganya tetap bisa dipertanggungjawabkan
     * bukan pembatasan di sini, melainkan jejaknya: sumber 'unggah' tercatat
     * di basis data, tercetak pada watermark, dan tampil mencolok di layar
     * admin — lihat SumberFotoKunjungan.
     */
    public function unggahFoto(KunjunganService $service): void
    {
        $kunjungan = $this->kunjungan;

        if ($kunjungan === null || $this->jenisUnggahan === null) {
            return;
        }

        $this->validate([
            'berkasUnggahan' => 'required|image|max:'.(int) config('visit.foto.ukuran_maks_kb'),
        ], [
            'berkasUnggahan.image' => __('kunjungan.galat_unggahan_bukan_gambar'),
            'berkasUnggahan.max' => __('kunjungan.galat_unggahan_kebesaran'),
        ]);

        $jenisFoto = JenisFotoKunjungan::tryFrom($this->jenisUnggahan);

        if ($jenisFoto === null) {
            $this->dispatch('notifikasi', pesan: __('kunjungan.galat_jenis_foto'), jenis: 'error');

            return;
        }

        try {
            $service->simpanFoto(
                kunjungan: $kunjungan,
                jenis: $jenisFoto,
                // Bita ASLI berkasnya, belum disentuh apa pun — EXIF-nya masih
                // utuh di sini dan akan hilang begitu gambarnya digambar ulang.
                isiGambar: file_get_contents($this->berkasUnggahan->getRealPath()),
                sumber: SumberFotoKunjungan::Unggah,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->reset(['berkasUnggahan', 'jenisUnggahan']);
        unset($this->kunjungan);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_foto_diunggah', [
            'jenis' => $jenisFoto->label(),
        ]), jenis: 'info');
    }

    /** Membuka pemilih berkas untuk satu jenis foto tertentu. */
    public function pilihUnggahan(string $jenis): void
    {
        $this->jenisUnggahan = $jenis;
        $this->reset('berkasUnggahan');
        $this->resetValidation();
    }

    public function batalUnggahan(): void
    {
        $this->reset(['berkasUnggahan', 'jenisUnggahan']);
        $this->resetValidation();
    }

    /**
     * Jalan pintas pengujian: mengisi satu foto dengan gambar tiruan, tanpa
     * menyentuh kamera. Melewati pipa penyimpanan yang sama persis dengan foto
     * sungguhan, jadi watermark dan pengecilan gambar ikut teruji.
     */
    public function jepretUji(string $jenis, KunjunganService $service, GambarContoh $gambarContoh): void
    {
        ModeUji::pastikanAktif();

        $kunjungan = $this->kunjungan;
        $jenisFoto = JenisFotoKunjungan::tryFrom($jenis);

        if ($kunjungan === null || $jenisFoto === null) {
            return;
        }

        try {
            $service->simpanFoto(
                kunjungan: $kunjungan,
                jenis: $jenisFoto,
                isiGambar: $gambarContoh->buat($jenisFoto),
                lat: $this->lat,
                lng: $this->lng,
                akurasi: $this->akurasi,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        unset($this->kunjungan);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_foto_tersimpan', [
            'jenis' => $jenisFoto->label(),
        ]), jenis: 'info');
    }

    /** Mengisi seluruh foto wajib sekaligus, agar alur penyelesaian cepat dicoba. */
    public function isiSemuaFotoUji(KunjunganService $service, GambarContoh $gambarContoh): void
    {
        ModeUji::pastikanAktif();

        foreach (JenisFotoKunjungan::urut() as $jenis) {
            $this->jepretUji($jenis->value, $service, $gambarContoh);
        }
    }

    public function selesaikan(KunjunganService $service): void
    {
        $kunjungan = $this->kunjungan;

        if ($kunjungan === null) {
            return;
        }

        try {
            $service->selesaikan(
                kunjungan: $kunjungan,
                lat: $this->lat,
                lng: $this->lng,
                akurasi: $this->akurasi,
                catatan: $this->catatan ?: null,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $nama = $kunjungan->toko->nama;

        $this->reset(['kunjunganId', 'catatan', 'catatanTutup', 'formTutupTerbuka']);
        $this->tahap = 'pindai';

        unset($this->kunjungan, $this->progres);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_selesai', ['nama' => $nama]));
        $this->dispatch('kunjungan-selesai');
    }

    public function ajukanTutup(KunjunganService $service): void
    {
        $kunjungan = $this->kunjungan;

        if ($kunjungan === null) {
            return;
        }

        try {
            $service->ajukanTokoTutup($kunjungan, $this->catatanTutup, $this->lat, $this->lng);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->reset(['kunjunganId', 'catatan', 'catatanTutup', 'formTutupTerbuka']);
        $this->tahap = 'pindai';

        unset($this->kunjungan, $this->progres);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_tutup_diajukan'), jenis: 'info');
        $this->dispatch('kunjungan-selesai');
    }

    /** Kembali memindai tanpa membatalkan kunjungan yang sedang berjalan. */
    public function kembaliPindai(): void
    {
        $this->tahap = 'pindai';
        $this->dispatch('qr-ditolak');
    }

    public function lanjutkanKunjungan(): void
    {
        if ($this->kunjunganId !== null) {
            $this->tahap = 'kunjungan';
        }
    }

    /**
     * Mengubah data URL kamera menjadi isi berkas.
     * Menolak apa pun yang bukan JPEG/PNG atau melebihi batas ukuran.
     */
    private function dekodeDataUrl(string $dataUrl): ?string
    {
        return GambarDataUrl::dekode($dataUrl);
    }

    public function render()
    {
        return view('livewire.kunjungan.kunjungi')->title(__('kunjungan.judul_kunjungi'));
    }
}
