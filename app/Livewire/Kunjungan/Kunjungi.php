<?php

namespace App\Livewire\Kunjungan;

use App\Enums\JenisFotoKunjungan;
use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Services\Kunjungan\GambarContoh;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use App\Support\ModeUji;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Layar kerja sales di lapangan.
 *
 * Kunjungan hanya bisa dimulai dengan memindai QR code pada freezer. Tidak ada
 * jalan memilih toko dari daftar, karena QR itulah bukti bahwa sales benar-benar
 * berdiri di depan freezer yang bersangkutan.
 */
class Kunjungi extends Component
{
    /** 'pindai' saat menunggu QR, 'kunjungan' saat mengambil foto. */
    public string $tahap = 'pindai';

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
            ->filter(fn ($toko) => $toko->asset_id !== null && $toko->kunjungans->isEmpty())
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

    public function prosesQr(string $isi, KunjunganService $service): void
    {
        try {
            $kunjungan = $service->mulaiDariQr($isi, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
            $this->dispatch('qr-ditolak');

            return;
        }

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

    /** Mengisi keenam foto sekaligus, agar alur penyelesaian cepat dicoba. */
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
        if (! preg_match('#^data:image/(jpeg|jpg|png);base64,#', $dataUrl, $cocok)) {
            return null;
        }

        $base64 = substr($dataUrl, strlen($cocok[0]));
        $isi = base64_decode($base64, true);

        if ($isi === false || $isi === '') {
            return null;
        }

        if (strlen($isi) > (int) config('visit.foto.ukuran_maks_kb') * 1024) {
            return null;
        }

        return $isi;
    }

    public function render()
    {
        return view('livewire.kunjungan.kunjungi')->title(__('kunjungan.judul_kunjungi'));
    }
}
