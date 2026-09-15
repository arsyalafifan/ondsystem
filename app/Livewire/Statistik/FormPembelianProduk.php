<?php

namespace App\Livewire\Statistik;

use App\Services\Statistik\EksporFormPembelianProduk;
use App\Services\Statistik\RekapPembelianHarian;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Form Pembelian Produk Harian Outlet — tampilan di layar dan hasil
 * ekspor Excel memakai data yang PERSIS sama (RekapPembelianHarian),
 * jadi yang dilihat admin selalu sama dengan yang diunduh.
 */
class FormPembelianProduk extends Component
{
    public const MODE = ['hari', 'bulan', 'tahun', 'rentang', 'semua'];

    #[Url(as: 'mode')]
    public string $mode = 'bulan';

    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'bln')]
    public string $bulan = '';

    #[Url(as: 'thn')]
    public string $tahun = '';

    #[Url(as: 'dari')]
    public string $dariTanggal = '';

    #[Url(as: 'sampai')]
    public string $sampaiTanggal = '';

    public function mount(): void
    {
        $this->mode = in_array($this->mode, self::MODE, true) ? $this->mode : 'bulan';
        $this->tanggal = $this->tanggal ?: today()->toDateString();
        $this->bulan = $this->bulan ?: today()->format('Y-m');
        $this->tahun = $this->tahun ?: (string) today()->year;
        $this->dariTanggal = $this->dariTanggal ?: today()->startOfMonth()->toDateString();
        $this->sampaiTanggal = $this->sampaiTanggal ?: today()->toDateString();
    }

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['mode', 'tanggal', 'bulan', 'tahun', 'dariTanggal', 'sampaiTanggal'], true)) {
            unset($this->rekap);
        }
    }

    /**
     * Rentang tanggal dari filter yang aktif — null untuk mode "Semua".
     * Isian yang tidak bisa dibaca (URL diketik tangan, kotak dikosongkan)
     * jatuh kembali ke hari ini, bukan melempar galat ke layar.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function rentang(): array
    {
        $baca = function (string $nilai, string $format): CarbonImmutable {
            try {
                return CarbonImmutable::createFromFormat($format, $nilai) ?: CarbonImmutable::today();
            } catch (Throwable) {
                return CarbonImmutable::today();
            }
        };

        return match ($this->mode) {
            'hari' => [$h = $baca($this->tanggal, 'Y-m-d')->startOfDay(), $h],
            'bulan' => [($b = $baca($this->bulan.'-01', 'Y-m-d'))->startOfMonth(), $b->endOfMonth()->startOfDay()],
            'tahun' => [
                ($t = $baca(max(2000, min(2100, (int) $this->tahun)).'-01-01', 'Y-m-d'))->startOfYear(),
                $t->endOfYear()->startOfDay(),
            ],
            'rentang' => collect([
                $baca($this->dariTanggal, 'Y-m-d')->startOfDay(),
                $baca($this->sampaiTanggal, 'Y-m-d')->startOfDay(),
            ])->sort()->values()->all(),
            default => [null, null],
        };
    }

    #[Computed]
    public function rekap(): array
    {
        [$dari, $sampai] = $this->rentang();

        return app(RekapPembelianHarian::class)->susun($this->mode, $dari, $sampai);
    }

    public function unduhExcel()
    {
        $spreadsheet = app(EksporFormPembelianProduk::class)->buat($this->rekap);

        $nama = sprintf(
            'form-pembelian-produk-%s-sd-%s.xlsx',
            $this->rekap['dari']->toDateString(),
            $this->rekap['sampai']->toDateString(),
        );

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nama, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render()
    {
        return view('livewire.statistik.form-pembelian-produk')->title(__('statistik.judul_form_pembelian'));
    }
}
