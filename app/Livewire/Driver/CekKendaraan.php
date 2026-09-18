<?php

namespace App\Livewire\Driver;

use App\Enums\JenisCatatanBbm;
use App\Enums\LevelBahanBakar;
use App\Models\CatatanBbm;
use App\Models\Kendaraan;
use App\Services\Kendaraan\CatatanBbmService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Cek kondisi kendaraan (KM + bahan bakar) di tiga titik — lihat
 * App\Enums\JenisCatatanBbm. Ini gerbang WAJIB sebelum driver bisa masuk ke
 * layar pengiriman (App\Livewire\Driver\DaftarKunjungan): PilihMobil
 * selalu mengarahkan ke sini dulu, dan lanjutKePengiriman() menolak selama
 * catatan keberangkatan belum ada.
 *
 * Catatan kembali TIDAK memblokir layar pengiriman (status kendaraan tetap
 * berubah 'selesai' otomatis begitu semua kunjungan tuntas, lihat
 * PengirimanService::segarkanKendaraan()) — cukup diingatkan lewat badge di
 * sini dan banner di layar pengiriman. Tapi arah sebaliknya TERKUNCI: baru
 * bisa dicatat SETELAH kendaraan berstatus 'selesai', supaya foto "kembali"
 * tidak pernah dijepret padahal pengantarannya belum sungguh tuntas — lihat
 * CatatanBbmService::kembali().
 */
class CekKendaraan extends Component
{
    public Kendaraan $kendaraan;

    /** Modal yang sedang terbuka: '', 'berangkat', 'kembali', atau 'pengisian'. */
    public string $modal = '';

    // --- Berangkat / kembali ---
    public string $km = '';

    public string $levelBbm = '';

    /** Data URL dari kamera — belum diunggah sampai tombol Simpan ditekan. */
    public ?string $fotoBbm = null;

    // --- Pengisian bahan bakar (opsional) ---
    public ?string $fotoStruk = null;

    public ?string $fotoSebelum = null;

    public ?string $fotoSesudah = null;

    public string $liter = '';

    public string $biaya = '';

    public string $catatanPengisian = '';

    public function mount(Kendaraan $kendaraan): void
    {
        // Aturan akses sama seperti DaftarKunjungan::mount().
        if (auth()->user()->isDriver()
            && $kendaraan->driver_id !== null
            && $kendaraan->driver_id !== auth()->id()) {
            abort(403, __('driver.mobil_dibawa_lain'));
        }

        $this->kendaraan = $kendaraan;
    }

    #[Computed]
    public function melihatSebagaiAdmin(): bool
    {
        return ! auth()->user()->isDriver();
    }

    /** @return list<LevelBahanBakar> */
    #[Computed]
    public function levelBbmOptions(): array
    {
        return LevelBahanBakar::cases();
    }

    /** @return Collection<int, CatatanBbm> */
    #[Computed]
    public function pengisians(): Collection
    {
        return $this->kendaraan->pengisianBbms()->with('dicatatOleh:id,name')->get();
    }

    public function bukaModal(string $jenis): void
    {
        $this->pastikanBisaBertindak();

        if ($jenis === 'berangkat' && $this->kendaraan->catatanBerangkat !== null) {
            return;
        }

        if ($jenis === 'kembali' && $this->kendaraan->catatanKembali !== null) {
            return;
        }

        // Baru masuk akal begitu seluruh kunjungan tuntas — lihat alasannya
        // di CatatanBbmService::kembali(), yang menjaga aturan yang sama
        // sebagai lapisan terakhir kalau tombol ini entah bagaimana tetap
        // terpicu (mis. state lama di browser).
        if ($jenis === 'kembali' && $this->kendaraan->status !== 'selesai') {
            $this->dispatch('notifikasi', pesan: __('kendaraan.galat_kembali_sebelum_selesai'), jenis: 'error');

            return;
        }

        $this->reset(['km', 'levelBbm', 'fotoBbm', 'fotoStruk', 'fotoSebelum', 'fotoSesudah', 'liter', 'biaya', 'catatanPengisian']);
        $this->modal = in_array($jenis, ['berangkat', 'kembali', 'pengisian'], true) ? $jenis : '';
        $this->resetValidation();
    }

    public function tutupModal(): void
    {
        $this->reset(['modal', 'km', 'levelBbm', 'fotoBbm', 'fotoStruk', 'fotoSebelum', 'fotoSesudah', 'liter', 'biaya', 'catatanPengisian']);
    }

    /** Dipanggil JS begitu satu slot foto dijepret. */
    public function terimaFoto(string $slot, string $gambar): void
    {
        match ($slot) {
            'bbm' => $this->fotoBbm = $gambar,
            'struk' => $this->fotoStruk = $gambar,
            'sebelum' => $this->fotoSebelum = $gambar,
            'sesudah' => $this->fotoSesudah = $gambar,
            default => null,
        };
    }

    /** Menghapus satu bidikan supaya driver bisa mengambil ulang. */
    public function hapusFoto(string $slot): void
    {
        match ($slot) {
            'bbm' => $this->fotoBbm = null,
            'struk' => $this->fotoStruk = null,
            'sebelum' => $this->fotoSebelum = null,
            'sesudah' => $this->fotoSesudah = null,
            default => null,
        };
    }

    public function simpanBerangkat(CatatanBbmService $service)
    {
        return $this->simpanCekpoin($service, JenisCatatanBbm::Berangkat);
    }

    public function simpanKembali(CatatanBbmService $service)
    {
        return $this->simpanCekpoin($service, JenisCatatanBbm::Kembali);
    }

    private function simpanCekpoin(CatatanBbmService $service, JenisCatatanBbm $jenis)
    {
        $this->pastikanBisaBertindak();

        $this->validate([
            'km' => 'required|integer|min:0|max:9999999',
            'levelBbm' => ['required', Rule::enum(LevelBahanBakar::class)],
            'fotoBbm' => 'required|string',
        ], [
            'km.required' => __('kendaraan.km_wajib'),
            'levelBbm.required' => __('kendaraan.level_bbm_wajib'),
            'fotoBbm.required' => __('driver.foto_wajib'),
        ]);

        try {
            $jenis === JenisCatatanBbm::Berangkat
                ? $service->berangkat($this->kendaraan, auth()->user(), $this->fotoBbm, (int) $this->km, LevelBahanBakar::from($this->levelBbm))
                : $service->kembali($this->kendaraan, auth()->user(), $this->fotoBbm, (int) $this->km, LevelBahanBakar::from($this->levelBbm));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return null;
        }

        $this->kendaraan->refresh();
        $this->tutupModal();

        $this->dispatch('notifikasi', pesan: __('kendaraan.notif_tersimpan', ['jenis' => $jenis->label()]));

        // Keberangkatan adalah gerbang — begitu tersimpan, langsung diteruskan
        // ke layar pengiriman supaya driver tidak perlu tap tambahan.
        // Kembali/pengisian tetap di layar ini, driver yang menutupnya sendiri.
        if ($jenis === JenisCatatanBbm::Berangkat) {
            return redirect()->route('driver.kunjungan', $this->kendaraan);
        }

        return null;
    }

    public function simpanPengisian(CatatanBbmService $service): void
    {
        $this->pastikanBisaBertindak();

        $this->validate([
            'fotoStruk' => 'required|string',
            'liter' => 'nullable|numeric|min:0|max:99999',
            'biaya' => 'nullable|numeric|min:0|max:999999999',
        ], [
            'fotoStruk.required' => __('kendaraan.struk_wajib'),
        ]);

        try {
            $service->pengisian(
                kendaraan: $this->kendaraan,
                driver: auth()->user(),
                strukDataUrl: $this->fotoStruk,
                sebelumDataUrl: $this->fotoSebelum,
                sesudahDataUrl: $this->fotoSesudah,
                liter: $this->liter !== '' ? (float) $this->liter : null,
                biaya: $this->biaya !== '' ? (float) $this->biaya : null,
                catatan: $this->catatanPengisian !== '' ? $this->catatanPengisian : null,
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->tutupModal();
        unset($this->pengisians);

        $this->dispatch('notifikasi', pesan: __('kendaraan.notif_pengisian_tersimpan'));
    }

    public function lanjutKePengiriman()
    {
        if ($this->kendaraan->catatanBerangkat === null) {
            $this->dispatch('notifikasi', pesan: __('kendaraan.galat_belum_berangkat'), jenis: 'error');

            return null;
        }

        return redirect()->route('driver.kunjungan', $this->kendaraan);
    }

    private function pastikanBisaBertindak(): void
    {
        abort_unless(auth()->user()->isDriver(), 403);
    }

    public function render()
    {
        return view('livewire.driver.cek-kendaraan')->title(__('kendaraan.judul_cek'));
    }
}
