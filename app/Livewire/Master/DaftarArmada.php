<?php

namespace App\Livewire\Master;

use App\Enums\JenisArmada;
use App\Enums\StatusArmada;
use App\Models\Armada;
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

class DaftarArmada extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $cari = '';

    public string $filterJenis = '';

    public string $filterStatus = '';

    // --- Formulir Tambah / Edit ---
    public bool $formTerbuka = false;

    public ?int $armadaId = null;

    public string $plat = '';

    public string $jenis = '';

    public string $status = 'normal';

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

    public function updatedFilterJenis(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function armadas()
    {
        return Armada::query()
            ->cari($this->cari)
            ->when($this->filterJenis !== '', fn ($q) => $q->where('jenis', $this->filterJenis))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('plat')
            ->paginate(25);
    }

    /** @return array<int, JenisArmada> */
    #[Computed]
    public function jenisCases(): array
    {
        return JenisArmada::cases();
    }

    /** @return array<int, StatusArmada> */
    #[Computed]
    public function statusCases(): array
    {
        return StatusArmada::cases();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $armada = Armada::findOrFail($id);

        $this->armadaId = $armada->id;
        $this->plat = $armada->plat;
        $this->jenis = $armada->jenis->value;
        $this->status = $armada->status->value;
        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['armadaId', 'plat', 'jenis']);
        $this->status = 'normal';
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $this->plat = mb_strtoupper(preg_replace('/\s+/', ' ', trim($this->plat)));

        $data = $this->validate([
            'plat' => [
                'required', 'string', 'max:20',
                Rule::unique('armadas', 'plat')->ignore($this->armadaId),
            ],
            'jenis' => ['required', Rule::enum(JenisArmada::class)],
            'status' => ['required', Rule::enum(StatusArmada::class)],
        ], [], [
            'plat' => __('master.atr_plat'),
            'jenis' => __('master.atr_jenis_armada'),
            'status' => __('master.atr_status_armada'),
        ]);

        Armada::updateOrCreate(['id' => $this->armadaId], $data);

        $this->formTerbuka = false;
        $this->resetForm();
        unset($this->armadas);

        $this->dispatch('notifikasi', pesan: __('master.sukses_simpan_armada'), jenis: 'sukses');
    }

    public function hapus(int $id): void
    {
        Armada::findOrFail($id)->delete();

        $this->konfirmasiHapus = null;
        unset($this->armadas);

        $this->dispatch('notifikasi', pesan: __('master.sukses_hapus_armada'), jenis: 'sukses');
    }

    // ------------------------------------------------------------------
    // Ekspor Excel & Unduh Contoh
    // ------------------------------------------------------------------

    public function unduhExcel()
    {
        $armadas = Armada::query()->orderBy('plat')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['plat', 'jenis', 'status'], null, 'A1');

        $baris = 2;
        foreach ($armadas as $armada) {
            $sheet->fromArray([
                null, // plat — ditulis eksplisit sebagai string di bawah
                $armada->jenis->value,
                $armada->status->value,
            ], null, "A{$baris}");

            $sheet->setCellValueExplicit("A{$baris}", (string) $armada->plat, DataType::TYPE_STRING);
            $baris++;
        }

        $barisTerakhir = max(1, $baris - 1);
        $sheet->getStyle("A1:A{$barisTerakhir}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'C') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'kendaraan-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function unduhContohExcel()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['plat', 'jenis', 'status'], null, 'A1');
        $sheet->fromArray(['B 1234 CD', 'berpendingin', 'normal'], null, 'A2');
        $sheet->fromArray(['B 5678 EF', 'bak_terbuka', 'menunggu_perbaikan'], null, 'A3');

        $sheet->getStyle('A1:A100')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'C') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'contoh-import-kendaraan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ------------------------------------------------------------------
    // Impor CSV / Excel
    // ------------------------------------------------------------------

    public function mulaiImporCsv(): void
    {
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
            'plat_dilihat' => [],
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if ($isiJson === false) {
            $this->addError('berkasCsv', __('master.gagal_urai_csv'));

            return;
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put("impor-armada/{$token}.json", $isiJson);

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

        $jalurBerkas = "impor-armada/{$this->imporToken}.json";

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
        $platDilihat = $tersimpan['plat_dilihat'] ?? [];
        $batch = array_slice($tersimpan['baris'], $this->imporOffset, $this->ukuranBatchImpor);

        if ($batch === []) {
            $this->selesaikanImpor();

            return;
        }

        $armadaSemua = Armada::query()->select(['id', 'plat', 'jenis', 'status'])->get();
        $armadaPerPlat = $armadaSemua->keyBy('plat');

        $baru = 0;
        $diperbarui = 0;
        $dilewati = [];
        $catatan = [];
        $nomor = $this->imporOffset + 1;

        DB::transaction(function () use (
            $batch, $judul, &$platDilihat, &$armadaPerPlat,
            &$baru, &$diperbarui, &$dilewati, &$catatan, &$nomor,
        ): void {
            foreach ($batch as $baris) {
                $nomor++;

                if (count(array_filter($baris, fn ($n) => trim((string) $n) !== '')) === 0) {
                    continue;
                }

                $data = array_combine($judul, array_pad(array_slice($baris, 0, count($judul)), count($judul), null));

                $platMentah = trim((string) (
                    $data['plat']
                    ?? $data['plat_kendaraan']
                    ?? $data['nomor_plat']
                    ?? $data['no_polisi']
                    ?? ''
                ));
                $plat = mb_strtoupper(preg_replace('/\s+/', ' ', $platMentah));

                if ($plat === '') {
                    $dilewati[] = __('master.lewat_plat_kosong', ['nomor' => $nomor]);

                    continue;
                }

                // Cek duplikasi di dalam file impor itu sendiri.
                if (isset($platDilihat[$plat])) {
                    $dilewati[] = __('master.lewat_plat_duplikat_file', [
                        'nomor' => $nomor,
                        'plat' => $plat,
                        'pertama' => $platDilihat[$plat],
                    ]);

                    continue;
                }

                $platDilihat[$plat] = $nomor;

                // Jenis WAJIB dikenali — beda dari status, tidak ada nilai
                // bawaan yang masuk akal untuk "jenis kendaraan tidak
                // diketahui", jadi baris ini dilewati seluruhnya.
                $jenisMentah = trim((string) (
                    $data['jenis']
                    ?? $data['jenis_kendaraan']
                    ?? $data['tipe']
                    ?? $data['tipe_kendaraan']
                    ?? ''
                ));
                $jenis = JenisArmada::dariTeks($jenisMentah);

                if ($jenis === null) {
                    $dilewati[] = __('master.lewat_jenis_armada_tidak_dikenal', ['nomor' => $nomor, 'plat' => $plat]);

                    continue;
                }

                // Status opsional: kosong/tidak dikenal TIDAK menggagalkan
                // baris — kendaraan baru jatuh ke bawaan "normal", kendaraan
                // yang sudah ada tetap memakai status lamanya (sama seperti
                // kolom opsional lain di Master Toko).
                $statusMentah = trim((string) ($data['status'] ?? ''));
                $status = StatusArmada::dariTeks($statusMentah);
                $statusTidakDikenal = $statusMentah !== '' && $status === null;

                /** @var Armada|null $armadaLama */
                $armadaLama = $armadaPerPlat[$plat] ?? null;

                if ($armadaLama) {
                    $armadaLama->update([
                        'jenis' => $jenis,
                        'status' => $status ?? $armadaLama->status,
                    ]);
                    $diperbarui++;
                } else {
                    $armadaBaru = Armada::create([
                        'plat' => $plat,
                        'jenis' => $jenis,
                        'status' => $status ?? StatusArmada::Normal,
                    ]);
                    $armadaPerPlat[$plat] = $armadaBaru;
                    $baru++;
                }

                if ($statusTidakDikenal) {
                    $catatan[] = __('master.catatan_status_armada_tidak_dikenal', [
                        'nomor' => $nomor,
                        'plat' => $plat,
                        'nilai' => $statusMentah,
                    ]);
                }
            }
        });

        // Simpan plat_dilihat yang sudah terakumulasi.
        $tersimpan['plat_dilihat'] = $platDilihat;
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
            Storage::disk('local')->delete("impor-armada/{$this->imporToken}.json");
        }

        $this->hasilImpor = [
            'baru' => $this->imporBaru,
            'diperbarui' => $this->imporDiperbarui,
            'dilewati' => $this->imporDilewati,
            'catatan' => $this->imporCatatan,
        ];

        $this->imporBerjalan = false;
        $this->imporToken = null;
        unset($this->armadas);

        $this->dispatch('notifikasi', pesan: __('master.impor_selesai'), jenis: 'sukses');
    }

    public function batalkanImporCsv(): void
    {
        if ($this->imporToken !== null) {
            Storage::disk('local')->delete("impor-armada/{$this->imporToken}.json");
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
        return view('livewire.master.daftar-armada')->title(__('master.judul_armada'));
    }
}
