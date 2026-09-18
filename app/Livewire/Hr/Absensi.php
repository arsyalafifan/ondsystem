<?php

namespace App\Livewire\Hr;

use App\Enums\JenisAbsensi;
use App\Models\Absensi as ModelAbsensi;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\PengajuanLembur;
use App\Services\Absensi\AturanAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Layar absensi karyawan: foto selfie + titik GPS, dinilai terhadap jam
 * kerja posisinya (atau shift-nya bila disetel HR).
 *
 * Seluruh aturannya ada di App\Services\Absensi\AturanAbsensi — komponen ini
 * hanya menyiapkan tampilan dan meneruskan bidikan kamera.
 */
class Absensi extends Component
{
    /** Hasil absen terakhir, untuk kartu status sesudah menekan tombol. */
    public ?int $absensiTerakhirId = null;

    #[Computed]
    public function karyawan(): ?Karyawan
    {
        return Karyawan::query()
            ->with(['posisi', 'shift', 'depot'])
            ->where('user_id', auth()->id())
            ->first();
    }

    /** @return Collection<string, ModelAbsensi> */
    #[Computed]
    public function hariIni(): Collection
    {
        $karyawan = $this->karyawan;

        return $karyawan === null
            ? collect()
            : app(AturanAbsensi::class)->hariIni($karyawan);
    }

    /** Lembur disetujui hari ini — pengingat absen pulang SESUDAH lembur selesai. */
    #[Computed]
    public function lemburHariIni()
    {
        $karyawan = $this->karyawan;

        return $karyawan === null
            ? collect()
            : PengajuanLembur::query()->where('karyawan_id', $karyawan->id)->disetujui()
                ->whereDate('tanggal', CarbonImmutable::today()->toDateString())->orderBy('jam_mulai')->get();
    }

    /** Izin/sakit yang disetujui HR untuk hari ini, bila ada. */
    #[Computed]
    public function izinHariIni(): ?PengajuanIzin
    {
        $karyawan = $this->karyawan;

        return $karyawan === null ? null : app(AturanAbsensi::class)->izinPada($karyawan, CarbonImmutable::today());
    }

    /**
     * Jenis absen yang boleh ditekan sekarang, berikut alasannya bila
     * terkunci — supaya karyawan tahu kenapa tombolnya mati, bukan sekadar
     * menemukan tombol yang tidak bisa diklik.
     *
     * @return list<array{jenis: JenisAbsensi, sudah: ?ModelAbsensi, bisa: bool, acuan: ?string}>
     */
    #[Computed]
    public function langkah(): array
    {
        $karyawan = $this->karyawan;

        if ($karyawan?->posisi === null) {
            return [];
        }

        $izin = $this->izinHariIni;

        // Izin/sakit sehari penuh: tidak ada yang perlu diabsen hari ini.
        if ($izin !== null && ! $izin->porsi->setengahHari()) {
            return [];
        }

        $aturan = app(AturanAbsensi::class);
        $hariIni = $this->hariIni;
        $tanggal = CarbonImmutable::today();
        $hasil = [];

        foreach (JenisAbsensi::cases() as $jenis) {
            // Setengah hari tidak melewati jam istirahat — lihat AturanAbsensi::catat().
            if ($jenis === JenisAbsensi::Istirahat && (! $karyawan->posisi->pakai_absen_istirahat || $izin !== null)) {
                continue;
            }

            $sudah = $hariIni->get($jenis->value);
            $acuan = $aturan->jamAcuan($karyawan, $jenis, $tanggal, $izin);

            $hasil[] = [
                'jenis' => $jenis,
                'sudah' => $sudah,
                'bisa' => $sudah === null
                    && ($jenis === JenisAbsensi::Masuk || $hariIni->has(JenisAbsensi::Masuk->value)),
                'acuan' => $acuan?->format('H:i'),
            ];
        }

        return $hasil;
    }

    /** Toko tanggungan yang masih boleh dipakai absen — hanya untuk posisi sales. */
    #[Computed]
    public function tokoTersisa(): Collection
    {
        $karyawan = $this->karyawan;

        return $karyawan === null ? collect() : app(AturanAbsensi::class)->tokoTersisa($karyawan);
    }

    #[Computed]
    public function riwayat()
    {
        $karyawan = $this->karyawan;

        return $karyawan === null
            ? collect()
            : ModelAbsensi::query()
                ->where('karyawan_id', $karyawan->id)
                ->with(['depot:id,nama', 'toko:id,nama'])
                ->latest('waktu')
                ->limit(15)
                ->get();
    }

    #[Computed]
    public function absensiTerakhir(): ?ModelAbsensi
    {
        return $this->absensiTerakhirId === null
            ? null
            : ModelAbsensi::with(['depot:id,nama', 'toko:id,nama'])->find($this->absensiTerakhirId);
    }

    /**
     * Menerima satu bidikan kamera (data URL) beserta titik GPS terakhir —
     * jalur yang sama persis dengan foto kunjungan.
     *
     * @param  ?array{lat?: float, lng?: float, akurasi?: int}  $lokasi
     */
    public function simpanJepretan(string $jenis, string $gambar, ?array $lokasi = null): void
    {
        $karyawan = $this->karyawan;

        if ($karyawan === null) {
            $this->dispatch('notifikasi', pesan: __('hr.galat_tanpa_karyawan'), jenis: 'error');

            return;
        }

        $jenisAbsensi = JenisAbsensi::tryFrom($jenis);

        if ($jenisAbsensi === null) {
            return;
        }

        try {
            $absensi = app(AturanAbsensi::class)->catat(
                karyawan: $karyawan,
                jenis: $jenisAbsensi,
                gambarDataUrl: $gambar,
                lat: isset($lokasi['lat']) ? (float) $lokasi['lat'] : null,
                lng: isset($lokasi['lng']) ? (float) $lokasi['lng'] : null,
                akurasi: isset($lokasi['akurasi']) ? (int) $lokasi['akurasi'] : null,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->absensiTerakhirId = $absensi->id;
        unset($this->hariIni, $this->langkah, $this->riwayat, $this->tokoTersisa);

        $this->dispatch('notifikasi', pesan: __('hr.absen_tersimpan', [
            'jenis' => $jenisAbsensi->label(),
            'status' => $absensi->status->label(),
        ]));
    }

    public function render()
    {
        return view('livewire.hr.absensi')->title(__('hr.judul_absensi'));
    }
}
