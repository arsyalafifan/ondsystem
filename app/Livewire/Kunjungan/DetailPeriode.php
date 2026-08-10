<?php

namespace App\Livewire\Kunjungan;

use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\PeriodeKunjungan;
use App\Models\PeriodeSales;
use App\Models\Toko;
use App\Services\Kunjungan\KunjunganService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rincian satu periode: progres tiap sales, lalu kunjungan per toko, sampai
 * foto-foto buktinya. Ketiganya ada di satu halaman agar admin tidak perlu
 * berpindah-pindah saat memeriksa.
 */
class DetailPeriode extends Component
{
    public PeriodeKunjungan $periode;

    /** Sales yang barisnya sedang dibuka. */
    public ?int $salesDibuka = null;

    /** Kunjungan yang foto-fotonya sedang dilihat. */
    public ?int $kunjunganDilihat = null;

    public string $saringStatus = '';

    // --- Peninjauan laporan toko tutup ---
    public ?int $kunjunganDitinjau = null;

    public string $catatanAdmin = '';

    public function mount(PeriodeKunjungan $periode): void
    {
        $this->periode = $periode;
    }

    /** @return Collection<int, PeriodeSales> */
    #[Computed]
    public function barisSales(): Collection
    {
        return $this->periode->periodeSales()
            ->with(['sales:id,name,email', 'kunjungans'])
            ->get()
            ->sortByDesc(fn (PeriodeSales $b) => $b->progres['persen'])
            ->values();
    }

    /** @return Collection<int, Kunjungan> */
    #[Computed]
    public function kunjunganSales(): Collection
    {
        if ($this->salesDibuka === null) {
            return collect();
        }

        return Kunjungan::query()
            ->with(['toko:id,nama,kode,alamat,asset_id,wilayah_id', 'toko.wilayah:id,nama', 'fotos'])
            ->where('periode_kunjungan_id', $this->periode->id)
            ->where('sales_id', $this->salesDibuka)
            ->when($this->saringStatus !== '', fn ($q) => $q->where('status', $this->saringStatus))
            ->orderByDesc('selesai_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Toko yang ditugaskan tapi belum tersentuh sama sekali minggu ini.
     * Ini yang paling berguna bagi admin: sisa pekerjaan, bukan yang sudah beres.
     *
     * @return Collection<int, Toko>
     */
    #[Computed]
    public function tokoBelumDikunjungi(): Collection
    {
        if ($this->salesDibuka === null) {
            return collect();
        }

        $sales = $this->barisSales->firstWhere('sales_id', $this->salesDibuka)?->sales;

        if ($sales === null) {
            return collect();
        }

        return app(KunjunganService::class)
            ->tanggungan($sales, $this->periode)
            ->filter(fn ($toko) => $toko->kunjungans->isEmpty())
            ->values();
    }

    #[Computed]
    public function detailKunjungan(): ?Kunjungan
    {
        return $this->kunjunganDilihat === null
            ? null
            : Kunjungan::with(['toko.wilayah:id,nama', 'sales:id,name', 'fotos', 'peninjau:id,name'])
                ->find($this->kunjunganDilihat);
    }

    #[Computed]
    public function statusTersedia(): array
    {
        return StatusKunjungan::cases();
    }

    public function bukaSales(int $salesId): void
    {
        $this->salesDibuka = $this->salesDibuka === $salesId ? null : $salesId;
        $this->saringStatus = '';

        unset($this->kunjunganSales, $this->tokoBelumDikunjungi);
    }

    public function bukaTinjauan(int $kunjunganId): void
    {
        $this->kunjunganDitinjau = $kunjunganId;
        $this->catatanAdmin = '';
    }

    public function setujuiTutup(KunjunganService $service): void
    {
        $this->putuskan(fn (Kunjungan $k) => $service->setujuiTokoTutup($k, auth()->user(), $this->catatanAdmin ?: null));
    }

    public function tolakTutup(KunjunganService $service): void
    {
        $this->putuskan(fn (Kunjungan $k) => $service->tolakTokoTutup($k, auth()->user(), $this->catatanAdmin ?: null));
    }

    private function putuskan(callable $tindakan): void
    {
        $kunjungan = Kunjungan::find($this->kunjunganDitinjau);

        if ($kunjungan === null) {
            return;
        }

        try {
            $tindakan($kunjungan);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->kunjunganDitinjau = null;
        unset($this->barisSales, $this->kunjunganSales, $this->tokoBelumDikunjungi);

        $this->dispatch('notifikasi', pesan: __('kunjungan.notif_tinjauan_tersimpan'));
    }

    /** Rekap satu periode dalam bentuk CSV, untuk diarsipkan atau dicetak. */
    public function unduhCsv(): StreamedResponse
    {
        $periode = $this->periode;

        return response()->streamDownload(function () use ($periode): void {
            $keluaran = fopen('php://output', 'w');

            fputcsv($keluaran, [
                __('kunjungan.csv_sales'), __('umum.kode'), __('umum.toko'), __('kunjungan.asset_id'),
                __('umum.wilayah'), __('umum.status'), __('kunjungan.csv_waktu'),
                __('kunjungan.csv_jumlah_foto'), __('kunjungan.csv_jarak'), __('umum.catatan'),
            ]);

            $periode->kunjungans()
                ->with(['sales:id,name', 'toko:id,nama,kode,asset_id,wilayah_id', 'toko.wilayah:id,nama'])
                ->withCount('fotos')
                ->chunk(200, function ($kunjungans) use ($keluaran): void {
                    foreach ($kunjungans as $k) {
                        fputcsv($keluaran, [
                            $k->sales->name,
                            $k->toko->kode,
                            $k->toko->nama,
                            $k->toko->asset_id,
                            $k->toko->wilayah?->nama,
                            $k->status->label(),
                            $k->selesai_at?->format('Y-m-d H:i'),
                            $k->fotos_count,
                            $k->jarak_dari_toko_m,
                            $k->catatan_sales,
                        ]);
                    }
                });

            fclose($keluaran);
        }, "kunjungan-{$periode->kode}.csv", ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.kunjungan.detail-periode')
            ->title(__('kunjungan.judul_periode_detail', ['kode' => $this->periode->kode]));
    }
}
