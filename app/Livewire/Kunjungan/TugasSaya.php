<?php

namespace App\Livewire\Kunjungan;

use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\PeriodeKunjungan;
use App\Models\Toko;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Daftar toko yang menjadi tanggungan sales minggu ini, beserta status
 * kunjungannya masing-masing.
 */
class TugasSaya extends Component
{
    #[Url(as: 'q')]
    public string $cari = '';

    #[Url(as: 'saring')]
    public string $saringStatus = '';

    #[Computed]
    public function periode(): PeriodeKunjungan
    {
        return app(PeriodeKunjunganService::class)->periodeBerjalan();
    }

    /** @return Collection<int, Toko> */
    #[Computed]
    public function tokos(): Collection
    {
        return app(KunjunganService::class)
            ->tanggungan(auth()->user(), $this->periode)
            ->filter(function ($toko): bool {
                if ($this->cari !== '') {
                    $cocok = str_contains(mb_strtolower($toko->nama), mb_strtolower($this->cari))
                        || str_contains(mb_strtolower((string) $toko->kode), mb_strtolower($this->cari))
                        || str_contains(mb_strtolower((string) $toko->alamat), mb_strtolower($this->cari));

                    if (! $cocok) {
                        return false;
                    }
                }

                if ($this->saringStatus === '') {
                    return true;
                }

                $kunjungan = $toko->kunjungans->first();

                return $this->saringStatus === 'belum'
                    ? $kunjungan === null
                    : $kunjungan?->status->value === $this->saringStatus;
            })
            ->values();
    }

    #[Computed]
    public function progres(): array
    {
        $baris = $this->periode->periodeSales()
            ->with('kunjungans')
            ->where('sales_id', auth()->id())
            ->first();

        return $baris?->progres ?? [
            'target' => 0, 'target_efektif' => 0, 'selesai' => 0,
            'tutup' => 0, 'menunggu' => 0, 'berjalan' => 0, 'belum' => 0, 'persen' => 0,
        ];
    }

    /** @return Collection<int, Kunjungan> */
    #[Computed]
    public function riwayat(): Collection
    {
        return Kunjungan::query()
            ->with(['toko:id,nama', 'periode:id,kode,tanggal_mulai,tanggal_selesai'])
            ->where('sales_id', auth()->id())
            ->where('periode_kunjungan_id', '!=', $this->periode->id)
            ->whereIn('status', [StatusKunjungan::Selesai, StatusKunjungan::TutupDisetujui])
            ->latest('id')
            ->limit(15)
            ->get();
    }

    public function render()
    {
        return view('livewire.kunjungan.tugas-saya')->title(__('kunjungan.judul_tugas'));
    }
}
