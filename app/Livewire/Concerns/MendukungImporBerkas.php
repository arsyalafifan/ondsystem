<?php

namespace App\Livewire\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

trait MendukungImporBerkas
{
    public bool $imporTerbuka = false;

    public $berkasCsv = null;

    public bool $imporBerjalan = false;

    public ?string $imporToken = null;

    public int $imporOffset = 0;

    public int $imporTotal = 0;

    public int $imporBaru = 0;

    public int $imporDiperbarui = 0;

    public array $imporDilewati = [];

    public array $imporCatatan = [];

    public ?array $hasilImpor = null;

    protected int $ukuranBatchImpor = 100;

    protected function bacaBarisCsv(string $jalur): array
    {
        $tangan = fopen($jalur, 'r');
        $baris = [];

        while (($satu = fgetcsv($tangan)) !== false) {
            $baris[] = $satu;
        }

        fclose($tangan);

        return $baris;
    }

    protected function bacaBarisExcel(string $jalur): array
    {
        return IOFactory::load($jalur)->getSheet(0)->toArray(null, true, false, false);
    }

    protected function normalisasiBaris(array $baris): array
    {
        return array_map(function ($nilai) {
            if (is_float($nilai) && fmod($nilai, 1.0) === 0.0) {
                return number_format($nilai, 0, '', '');
            }

            return $this->keUtf8(trim((string) $nilai));
        }, $baris);
    }

    protected function keUtf8(string $nilai): string
    {
        if ($nilai === '' || mb_check_encoding($nilai, 'UTF-8')) {
            return $nilai;
        }

        return mb_convert_encoding($nilai, 'UTF-8', 'Windows-1252');
    }

    protected function tanggalAtauNull(mixed $nilai): ?string
    {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            return null;
        }

        if (is_numeric($nilai) && (float) $nilai > 10000 && (float) $nilai < 70000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $nilai)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($nilai)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function batalkanImporBerkas(string $folder = 'impor'): void
    {
        if ($this->imporToken !== null) {
            Storage::disk('local')->delete("{$folder}/{$this->imporToken}.json");
        }

        $this->reset([
            'imporBerjalan', 'imporToken', 'imporOffset', 'imporTotal',
            'imporBaru', 'imporDiperbarui', 'imporDilewati', 'imporCatatan',
        ]);
    }
}
