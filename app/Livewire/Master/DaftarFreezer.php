<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Freezer;
use App\Support\DepotContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DaftarFreezer extends Component
{
    use MembutuhkanDepotTerkunci;
    use WithFileUploads;
    use WithPagination;

    public string $cari = '';

    public string $filterStatus = '';

    // --- Formulir Tambah / Edit ---
    public bool $formTerbuka = false;

    public ?int $freezerId = null;

    public string $idn = '';

    public string $tipe = '';

    public string $keterangan = '';

    public bool $aktif = true;

    // --- Konfirmasi Hapus ---
    public ?int $konfirmasiHapus = null;

    // --- Impor CSV / Excel ---
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

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function freezers()
    {
        return Freezer::query()
            ->withCount('tokos')
            ->cari($this->cari)
            ->when($this->filterStatus !== '', fn ($q) => $q->where('aktif', (bool) $this->filterStatus))
            ->orderBy('idn')
            ->paginate(25);
    }

    // ------------------------------------------------------------------
    // Formulir CRUD
    // ------------------------------------------------------------------

    public function buatBaru(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $freezer = Freezer::findOrFail($id);

        $this->freezerId = $freezer->id;
        $this->idn = $freezer->idn;
        $this->tipe = $freezer->tipe;
        $this->keterangan = $freezer->keterangan ?? '';
        $this->aktif = (bool) $freezer->aktif;
        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['freezerId', 'idn', 'tipe', 'keterangan']);
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $depotId = DepotContext::currentOrFail()->id;

        $this->idn = mb_strtoupper(preg_replace('/\s+/', '', (string) $this->idn));

        $data = $this->validate([
            'idn' => [
                'required', 'string', 'max:50',
                Rule::unique('freezers', 'idn')->ignore($this->freezerId)->where('depot_id', $depotId),
            ],
            'tipe' => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:1000',
            'aktif' => 'boolean',
        ], [], [
            'idn' => __('master.idn_freezer'),
            'tipe' => __('master.tipe_freezer'),
            'keterangan' => __('umum.keterangan'),
        ]);

        Freezer::updateOrCreate(['id' => $this->freezerId], [
            'idn' => $data['idn'],
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?: null,
            'aktif' => $data['aktif'] ?? true,
        ]);

        $this->formTerbuka = false;
        $this->resetForm();

        $this->dispatch('notifikasi', pesan: __('master.sukses_simpan_freezer'), jenis: 'sukses');
    }

    public function hapus(int $id): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $freezer = Freezer::findOrFail($id);
        $freezer->delete();

        $this->konfirmasiHapus = null;
        $this->dispatch('notifikasi', pesan: __('master.sukses_hapus_freezer'), jenis: 'sukses');
    }

    // ------------------------------------------------------------------
    // Ekspor Excel & Unduh Contoh
    // ------------------------------------------------------------------

    public function unduhExcel()
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $freezers = Freezer::query()->orderBy('idn')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['idn', 'tipe', 'keterangan', 'status'], null, 'A1');

        $baris = 2;
        foreach ($freezers as $freezer) {
            $sheet->fromArray([
                null, // idn — ditulis eksplisit sebagai string di bawah
                $freezer->tipe,
                $freezer->keterangan,
                $freezer->aktif ? 'aktif' : 'nonaktif',
            ], null, "A{$baris}");

            $sheet->setCellValueExplicit("A{$baris}", (string) $freezer->idn, DataType::TYPE_STRING);
            $baris++;
        }

        $barisTerakhir = max(1, $baris - 1);
        $sheet->getStyle("A1:A{$barisTerakhir}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'D') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'freezer-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function unduhContohExcel()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['idn', 'tipe', 'keterangan', 'status'], null, 'A1');
        $sheet->fromArray(['IDNAH202528004381', 'SD-200', 'Freezer Kaca Geser 200L', 'aktif'], null, 'A2');
        $sheet->fromArray(['IDNAH202528004382', 'CF-300', 'Chest Freezer 300L', 'aktif'], null, 'A3');

        $sheet->getStyle('A1:A100')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'D') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'contoh-import-freezer.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ------------------------------------------------------------------
    // Impor CSV / Excel
    // ------------------------------------------------------------------

    public function mulaiImporCsv(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $this->validate([
            'berkasCsv' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
        ], [
            'berkasCsv.required' => __('master.pilih_csv_dulu'),
            'berkasCsv.mimes' => __('master.harus_csv'),
        ]);

        $jalur = $this->berkasCsv->getRealPath();
        $ekstensi = mb_strtolower((string) $this->berkasCsv->getClientOriginalExtension());

        $semuaBaris = in_array($ekstensi, ['xlsx', 'xls'], true)
            ? $this->bacaBarisExcel($jalur)
            : $this->bacaBarisCsv($jalur);

        $judul = array_shift($semuaBaris);

        if ($judul === null) {
            $this->addError('berkasCsv', __('master.berkas_kosong'));

            return;
        }

        $judul = array_map(
            fn ($k) => str_replace([' ', '-'], '_', mb_strtolower(trim($this->keUtf8((string) $k)))),
            $judul,
        );

        $barisNormal = array_values(array_map(
            fn ($baris) => $this->normalisasiBaris($baris),
            $semuaBaris,
        ));

        if ($barisNormal === []) {
            $this->addError('berkasCsv', __('master.berkas_kosong'));

            return;
        }

        $isiJson = json_encode([
            'judul' => $judul,
            'baris' => $barisNormal,
            'idn_dilihat' => [],
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if ($isiJson === false) {
            $this->addError('berkasCsv', __('master.gagal_urai_csv'));

            return;
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put("impor-freezer/{$token}.json", $isiJson);

        $this->imporToken = $token;
        $this->imporOffset = 0;
        $this->imporTotal = count($barisNormal);
        $this->imporBaru = 0;
        $this->imporDiperbarui = 0;
        $this->imporDilewati = [];
        $this->imporCatatan = [];
        $this->hasilImpor = null;

        $this->imporBerjalan = true;
    }

    public function lanjutkanImporCsv(): void
    {
        if (! $this->imporBerjalan || $this->imporToken === null) {
            return;
        }

        $jalurBerkas = "impor-freezer/{$this->imporToken}.json";

        if (! Storage::disk('local')->exists($jalurBerkas)) {
            $this->imporBerjalan = false;
            $this->dispatch('notifikasi', pesan: __('master.sesi_impor_kedaluwarsa'), jenis: 'error');

            return;
        }

        $tersimpan = json_decode((string) Storage::disk('local')->get($jalurBerkas), true);

        if (! is_array($tersimpan) || ! isset($tersimpan['baris'], $tersimpan['judul'])) {
            $this->imporBerjalan = false;
            Storage::disk('local')->delete($jalurBerkas);
            $this->dispatch('notifikasi', pesan: __('master.gagal_urai_csv'), jenis: 'error');

            return;
        }

        $judul = $tersimpan['judul'];
        $idnDilihat = $tersimpan['idn_dilihat'] ?? [];
        $batch = array_slice($tersimpan['baris'], $this->imporOffset, $this->ukuranBatchImpor);

        if ($batch === []) {
            $this->selesaikanImpor();

            return;
        }

        $depotId = DepotContext::currentOrFail()->id;
        $freezerSemua = Freezer::query()->select(['id', 'depot_id', 'idn', 'tipe', 'keterangan', 'aktif'])->get();
        $freezerPerIdn = $freezerSemua->keyBy('idn');

        $baru = 0;
        $diperbarui = 0;
        $dilewati = [];
        $catatan = [];
        $nomor = $this->imporOffset + 1;

        DB::transaction(function () use (
            $batch, $judul, &$idnDilihat, &$freezerPerIdn, $depotId,
            &$baru, &$diperbarui, &$dilewati, &$catatan, &$nomor,
        ): void {
            foreach ($batch as $baris) {
                $nomor++;

                if (count(array_filter($baris, fn ($n) => trim((string) $n) !== '')) === 0) {
                    continue;
                }

                $data = array_combine($judul, array_pad(array_slice($baris, 0, count($judul)), count($judul), null));

                $idnMantah = trim((string) (
                    $data['idn']
                    ?? $data['nomor_asset']
                    ?? $data['no_asset']
                    ?? $data['asset_id']
                    ?? $data['kode_asset']
                    ?? $data['nomor_aset']
                    ?? ''
                ));
                $idn = mb_strtoupper(preg_replace('/\s+/', '', $idnMantah));

                $tipe = trim((string) (
                    $data['tipe']
                    ?? $data['tipe_freezer']
                    ?? $data['jenis_freezer']
                    ?? $data['model']
                    ?? ''
                ));

                $keterangan = trim((string) (
                    $data['keterangan']
                    ?? $data['catatan']
                    ?? $data['note']
                    ?? ''
                ));

                $statusMentah = mb_strtolower(trim((string) (
                    $data['status']
                    ?? $data['aktif']
                    ?? ''
                )));
                $aktif = ! in_array($statusMentah, ['nonaktif', 'tidak aktif', '0', 'false', 'inactive'], true);

                if ($idn === '') {
                    $dilewati[] = __('master.lewat_idn_kosong', ['nomor' => $nomor]);

                    continue;
                }

                if ($tipe === '') {
                    $dilewati[] = __('master.lewat_tipe_kosong', ['nomor' => $nomor, 'idn' => $idn]);

                    continue;
                }

                // Cek duplikasi di dalam file impor itu sendiri
                if (isset($idnDilihat[$idn])) {
                    $dilewati[] = __('master.lewat_idn_duplikat_file', [
                        'nomor' => $nomor,
                        'idn' => $idn,
                        'pertama' => $idnDilihat[$idn],
                    ]);

                    continue;
                }

                $idnDilihat[$idn] = $nomor;

                /** @var Freezer|null $freezerLama */
                $freezerLama = $freezerPerIdn[$idn] ?? null;

                if ($freezerLama) {
                    $freezerLama->update([
                        'tipe' => $tipe,
                        'keterangan' => $keterangan ?: $freezerLama->keterangan,
                        'aktif' => $aktif,
                    ]);
                    $diperbarui++;
                } else {
                    $freezerBaru = Freezer::create([
                        'depot_id' => $depotId,
                        'idn' => $idn,
                        'tipe' => $tipe,
                        'keterangan' => $keterangan ?: null,
                        'aktif' => $aktif,
                    ]);
                    $freezerPerIdn[$idn] = $freezerBaru;
                    $baru++;
                }
            }
        });

        // Simpan idn_dilihat yang sudah terakumulasi
        $tersimpan['idn_dilihat'] = $idnDilihat;
        Storage::disk('local')->put($jalurBerkas, json_encode($tersimpan, JSON_INVALID_UTF8_SUBSTITUTE));

        $this->imporBaru += $baru;
        $this->imporDiperbarui += $diperbarui;
        $this->imporDilewati = [...$this->imporDilewati, ...$dilewati];
        $this->imporCatatan = [...$this->imporCatatan, ...$catatan];
        $this->imporOffset += count($batch);

        if ($this->imporOffset >= $this->imporTotal) {
            $this->selesaikanImpor();
        }
    }

    private function selesaikanImpor(): void
    {
        if ($this->imporToken !== null) {
            Storage::disk('local')->delete("impor-freezer/{$this->imporToken}.json");
        }

        $this->hasilImpor = [
            'baru' => $this->imporBaru,
            'diperbarui' => $this->imporDiperbarui,
            'dilewati' => $this->imporDilewati,
            'catatan' => $this->imporCatatan,
        ];

        $this->imporBerjalan = false;
        $this->imporToken = null;

        $this->dispatch('notifikasi', pesan: __('master.impor_selesai'), jenis: 'sukses');
    }

    public function batalkanImporCsv(): void
    {
        if ($this->imporToken !== null) {
            Storage::disk('local')->delete("impor-freezer/{$this->imporToken}.json");
        }

        $this->reset(['imporBerjalan', 'imporToken', 'imporOffset', 'imporTotal', 'imporBaru', 'imporDiperbarui', 'imporDilewati', 'imporCatatan']);
    }

    private function bacaBarisCsv(string $jalur): array
    {
        $tangan = fopen($jalur, 'r');
        $baris = [];

        while (($satu = fgetcsv($tangan)) !== false) {
            $baris[] = $satu;
        }

        fclose($tangan);

        return $baris;
    }

    private function bacaBarisExcel(string $jalur): array
    {
        return IOFactory::load($jalur)->getSheet(0)->toArray(null, true, false, false);
    }

    private function normalisasiBaris(array $baris): array
    {
        return array_map(function ($nilai) {
            if (is_float($nilai) && fmod($nilai, 1.0) === 0.0) {
                return number_format($nilai, 0, '', '');
            }

            return $this->keUtf8(trim((string) $nilai));
        }, $baris);
    }

    private function keUtf8(string $nilai): string
    {
        if ($nilai === '' || mb_check_encoding($nilai, 'UTF-8')) {
            return $nilai;
        }

        return mb_convert_encoding($nilai, 'UTF-8', 'Windows-1252');
    }

    public function render()
    {
        return view('livewire.master.daftar-freezer')->title(__('master.judul_freezer'));
    }
}
