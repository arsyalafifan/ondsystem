<?php

namespace App\Livewire\Freezer;

use App\Models\Depot;
use App\Models\Freezer;
use App\Services\Freezer\FreezerGudangService;
use App\Services\Kunjungan\PenguraiQr;
use App\Support\DepotContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Mencatat di gudang mana sebuah freezer yang BELUM terpasang di toko
 * sedang disimpan: pindai QR-nya (atau ketik IDN-nya), lihat infonya,
 * pilih gudang, simpan.
 *
 * Sengaja tidak terkunci ke satu depot aktif (beda dari layar NOO/Tarik):
 * Master Freezer berlaku untuk semua gudang, dan petugas boleh mencatat
 * freezer di gudang mana pun yang dipilihnya. Gudang aktif saat ini hanya
 * dipakai sebagai pilihan awal.
 */
class FreezerKeGudang extends Component
{
    /** 'pindai' atau 'ketik'. */
    public string $cara = 'pindai';

    public string $idnKetik = '';

    /** IDN freezer yang sedang ditampilkan; null = belum ada yang dipindai. */
    public ?string $idn = null;

    public string $depotId = '';

    #[Computed]
    public function freezer(): ?Freezer
    {
        return $this->idn === null
            ? null
            : Freezer::query()
                ->with([
                    'toko' => fn ($q) => $q->select('id', 'kode', 'nama', 'asset_id', 'depot_id'),
                    'toko.depot:id,nama',
                    'depotSimpan:id,nama',
                    'pencatatGudang:id,name',
                ])
                ->where('idn', $this->idn)
                ->first();
    }

    /** @return Collection<int, Depot> */
    #[Computed]
    public function opsiGudang(): Collection
    {
        return Depot::aktif()->berurutan()->get(['id', 'nama']);
    }

    public function gantiCara(string $cara): void
    {
        $this->cara = in_array($cara, ['pindai', 'ketik'], true) ? $cara : 'pindai';
    }

    public function pindaiQr(string $isi, PenguraiQr $pengurai): void
    {
        $hasil = $pengurai->urai($isi);

        if ($hasil === null) {
            $this->tolak(__('kunjungan.galat_qr_tidak_terbaca'));

            return;
        }

        $this->tampilkan($hasil->assetId);
    }

    public function cariKetik(): void
    {
        $idn = mb_strtoupper(preg_replace('/\s+/', '', $this->idnKetik) ?? '');

        if ($idn === '') {
            return;
        }

        $this->tampilkan($idn);
    }

    private function tampilkan(string $idn): void
    {
        $freezer = Freezer::query()->where('idn', $idn)->first();

        if ($freezer === null) {
            $this->tolak(__('freezer_gudang.galat_tidak_terdaftar', ['idn' => $idn]));

            return;
        }

        $this->idn = $freezer->idn;
        $this->depotId = (string) ($freezer->depot_simpan_id ?? DepotContext::current()?->id ?? auth()->user()->depotAwal()?->id ?? '');
        $this->resetValidation();
        unset($this->freezer);
    }

    public function ulang(): void
    {
        $this->reset(['idn', 'idnKetik', 'depotId']);
        $this->resetValidation();
        unset($this->freezer);

        $this->dispatch('qr-freezer-ditolak');
    }

    public function simpan(FreezerGudangService $service): void
    {
        $freezer = $this->freezer;

        if ($freezer === null) {
            return;
        }

        $this->validate([
            'depotId' => ['required', Rule::exists('depots', 'id')->where('aktif', true)],
        ], [], ['depotId' => __('freezer_gudang.atr_gudang')]);

        $depot = Depot::findOrFail((int) $this->depotId);

        try {
            $service->catat($freezer, $depot, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $idn = $freezer->idn;
        $this->ulang();

        $this->dispatch('notifikasi', pesan: __('freezer_gudang.notif_tersimpan', ['idn' => $idn, 'gudang' => $depot->nama]));
    }

    /** QR/IDN yang ditolak boleh dipindai ulang — tanpa ini kode yang sama dianggap sudah terbaca. */
    private function tolak(string $pesan): void
    {
        $this->dispatch('notifikasi', pesan: $pesan, jenis: 'error');
        $this->dispatch('qr-freezer-ditolak');
    }

    public function render()
    {
        return view('livewire.freezer.freezer-ke-gudang')->title(__('freezer_gudang.judul'));
    }
}
