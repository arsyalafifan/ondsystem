<?php

namespace App\Livewire\PengantaranRider;

use App\Enums\JenisBuktiPengiriman;
use App\Enums\StatusPengantaranRider;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\PengantaranRider;
use App\Services\PengantaranRider\BuktiPengantaranRiderService;
use App\Services\PengantaranRider\PengantaranRiderService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Layar kerja rider: pool pesanan yang ditandai admin untuk diantar rider
 * (bukan rute kendaraan — lihat App\Services\PengantaranRider\PengantaranRiderService),
 * diambil sendiri oleh rider mana pun di gudang yang sama, satu pesanan
 * aktif per rider pada satu waktu.
 *
 * Konfirmasi selesainya meniru persis App\Livewire\Driver\DaftarKunjungan
 * (checklist item+jumlah, foto nota wajib, bukti foto wajib sesuai jenis,
 * tanda tangan toko sebagai pengganti foto freezer disusun) — lihat
 * docblock komponen itu untuk alasan lengkap tiap aturan buktinya.
 */
class DaftarPengantaranRider extends Component
{
    use MembutuhkanDepotTerkunci, WithFileUploads;

    // --- Konfirmasi penerimaan & unggah nota ---
    public ?int $pengantaranKonfirmasi = null;

    /** @var array<int, int|string> jumlah yang BENAR-BENAR diambil toko, dikunci pada id item pesanan */
    public array $jumlahKonfirmasi = [];

    /** @var array<int, bool> */
    public array $dicekKonfirmasi = [];

    public $fotoNota;

    public string $catatanRider = '';

    /** @var array<string, ?string> data URL dari kamera, dikunci pada JenisBuktiPengiriman->value */
    public array $buktiFoto = [];

    public bool $tokoSusunSendiri = false;

    public ?string $tandaTanganToko = null;

    public string $namaPenandatanganToko = '';

    public function mount(): void
    {
        $this->pastikanDepotTerkunci();
    }

    /** @return Collection<int, PengantaranRider> */
    #[Computed]
    public function pool()
    {
        return PengantaranRider::tersedia()->with('pesanan.toko:id,nama,alamat')->latest('ditandai_at')->get();
    }

    /** Pengantaran yang sedang dibawa rider ini — paling banyak satu, lihat PengantaranRiderService::ambil(). */
    #[Computed]
    public function aktif(): ?PengantaranRider
    {
        return PengantaranRider::where('rider_id', auth()->id())
            ->where('status', StatusPengantaranRider::Diambil)
            ->with('pesanan.items.produk', 'pesanan.toko')
            ->first();
    }

    #[Computed]
    public function riwayat()
    {
        return PengantaranRider::where('rider_id', auth()->id())
            ->where('status', StatusPengantaranRider::Selesai)
            ->with('pesanan.toko:id,nama')
            ->latest('selesai_at')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function pengantaranKonfirmasiModel(): ?PengantaranRider
    {
        return $this->pengantaranKonfirmasi === null
            ? null
            : PengantaranRider::where('rider_id', auth()->id())
                ->where('status', StatusPengantaranRider::Diambil)
                ->with('pesanan.items.produk', 'pesanan.toko')
                ->find($this->pengantaranKonfirmasi);
    }

    public function ambil(int $id, PengantaranRiderService $service): void
    {
        try {
            $service->ambil(PengantaranRider::findOrFail($id), auth()->user());
            unset($this->pool, $this->aktif);
            $this->dispatch('notifikasi', pesan: __('pengantaran_rider.notif_diambil'));
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }
    }

    public function lepas(int $id, PengantaranRiderService $service): void
    {
        try {
            $service->lepas(PengantaranRider::findOrFail($id), auth()->user());
            unset($this->pool, $this->aktif);
            $this->dispatch('notifikasi', pesan: __('pengantaran_rider.notif_dilepas'), jenis: 'info');
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');
        }
    }

    // ------------------------------------------------------------------
    // Konfirmasi penerimaan & unggah nota
    // ------------------------------------------------------------------

    public function bukaKonfirmasi(int $id): void
    {
        $pengantaran = PengantaranRider::where('rider_id', auth()->id())
            ->where('status', StatusPengantaranRider::Diambil)
            ->with('pesanan.items')
            ->findOrFail($id);

        $this->pengantaranKonfirmasi = $id;
        $this->fotoNota = null;
        $this->catatanRider = '';
        $this->buktiFoto = [];
        $this->tokoSusunSendiri = false;
        $this->tandaTanganToko = null;
        $this->namaPenandatanganToko = '';

        // Diisi jumlah pesanan semula, jadi rider tinggal mengurangi baris
        // yang memang tidak jadi diambil toko — sama seperti driver.
        $this->jumlahKonfirmasi = $pengantaran->pesanan->items
            ->mapWithKeys(fn ($item) => [$item->id => $item->jumlah_dus])
            ->all();

        $this->dicekKonfirmasi = $pengantaran->pesanan->items
            ->mapWithKeys(fn ($item) => [$item->id => false])
            ->all();

        $this->resetValidation();
    }

    public function tutupKonfirmasi(): void
    {
        $this->reset([
            'pengantaranKonfirmasi', 'jumlahKonfirmasi', 'dicekKonfirmasi', 'fotoNota', 'catatanRider',
            'buktiFoto', 'tokoSusunSendiri', 'tandaTanganToko', 'namaPenandatanganToko',
        ]);
    }

    /** Dipanggil JS begitu satu bidikan bukti pengiriman dijepret. */
    public function terimaBuktiFoto(string $jenis, string $gambar): void
    {
        if (JenisBuktiPengiriman::tryFrom($jenis) !== null) {
            $this->buktiFoto[$jenis] = $gambar;
        }
    }

    public function hapusBuktiFoto(string $jenis): void
    {
        $this->buktiFoto[$jenis] = null;
    }

    public function terimaTandaTangan(string $gambar): void
    {
        $this->tandaTanganToko = $gambar;
    }

    public function hapusTandaTangan(): void
    {
        $this->tandaTanganToko = null;
    }

    public function updatedTokoSusunSendiri(): void
    {
        $this->buktiFoto[JenisBuktiPengiriman::FreezerDisusun->value] = null;
        $this->tandaTanganToko = null;
        $this->namaPenandatanganToko = '';
    }

    /** Sama persis aturan App\Livewire\Driver\DaftarKunjungan::semuaBuktiLengkap(). */
    #[Computed]
    public function semuaBuktiLengkap(): bool
    {
        foreach (JenisBuktiPengiriman::wajibFoto() as $jenis) {
            if (empty($this->buktiFoto[$jenis->value])) {
                return false;
            }
        }

        return $this->tokoSusunSendiri
            ? $this->tandaTanganToko !== null
            : ! empty($this->buktiFoto[JenisBuktiPengiriman::FreezerDisusun->value]);
    }

    #[Computed]
    public function totalKonfirmasi(): int
    {
        return (int) array_sum(array_map('intval', $this->jumlahKonfirmasi));
    }

    #[Computed]
    public function semuaTercekKonfirmasi(): bool
    {
        return $this->dicekKonfirmasi !== [] && ! in_array(false, $this->dicekKonfirmasi, true);
    }

    public function simpanKonfirmasi(PengantaranRiderService $service, BuktiPengantaranRiderService $buktiService): void
    {
        if (! $this->semuaTercekKonfirmasi) {
            $this->dispatch('notifikasi', pesan: __('driver.galat_belum_tercek'), jenis: 'error');

            return;
        }

        if (! $this->semuaBuktiLengkap) {
            $this->dispatch('notifikasi', pesan: __('pengiriman.galat_bukti_belum_lengkap'), jenis: 'error');

            return;
        }

        $this->validate([
            'fotoNota' => 'required|image|max:5120',
            'namaPenandatanganToko' => $this->tokoSusunSendiri ? 'required|string|max:255' : 'nullable',
        ], [
            'fotoNota.required' => __('driver.foto_wajib'),
            'fotoNota.image' => __('driver.foto_harus_gambar'),
            'fotoNota.max' => __('driver.foto_maks'),
            'namaPenandatanganToko.required' => __('pengiriman.galat_nama_penandatangan_wajib'),
        ]);

        $pengantaran = PengantaranRider::where('rider_id', auth()->id())
            ->where('status', StatusPengantaranRider::Diambil)
            ->findOrFail($this->pengantaranKonfirmasi);

        $jumlah = array_map('intval', $this->jumlahKonfirmasi);
        $path = $this->fotoNota->store('nota/'.now()->format('Y-m-d'), 'public');

        // Setiap bukti didekode & disimpan (lengkap dengan watermark) LEBIH
        // DULU — persis pola driver — supaya kalau salah satu rusak, belum
        // ada baris apa pun yang terlanjur berubah status.
        $buktiTersimpan = [];

        try {
            foreach (JenisBuktiPengiriman::wajibFoto() as $jenis) {
                $buktiTersimpan[] = $buktiService->simpanFoto($pengantaran, $jenis, $this->buktiFoto[$jenis->value], auth()->user());
            }

            $buktiTersimpan[] = $this->tokoSusunSendiri
                ? $buktiService->simpanTandaTangan($pengantaran, $this->tandaTanganToko, auth()->user(), trim($this->namaPenandatanganToko))
                : $buktiService->simpanFoto($pengantaran, JenisBuktiPengiriman::FreezerDisusun, $this->buktiFoto[JenisBuktiPengiriman::FreezerDisusun->value], auth()->user());
        } catch (RuntimeException $e) {
            Storage::disk('public')->delete($path);
            Storage::disk(config('visit.foto.disk'))->delete(array_column($buktiTersimpan, 'path'));
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        try {
            $service->selesaikan($pengantaran, $jumlah, $path, auth()->user(), $this->catatanRider ?: null, $buktiTersimpan);
        } catch (RuntimeException $e) {
            Storage::disk('public')->delete($path);
            Storage::disk(config('visit.foto.disk'))->delete(array_column($buktiTersimpan, 'path'));
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $nama = $pengantaran->pesanan->toko->nama ?? '';
        $this->tutupKonfirmasi();
        unset($this->pool, $this->aktif, $this->riwayat);

        $this->dispatch('notifikasi', pesan: __('pengantaran_rider.notif_selesai', ['toko' => $nama]));
    }

    public function render()
    {
        return view('livewire.pengantaran-rider.daftar-pengantaran-rider')->title(__('pengantaran_rider.judul'));
    }
}
