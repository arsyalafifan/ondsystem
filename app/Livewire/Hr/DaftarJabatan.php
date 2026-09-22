<?php

namespace App\Livewire\Hr;

use App\Livewire\Concerns\MendukungImporBerkas;
use App\Models\Jabatan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Master Jabatan — sifatnya sama persis dengan DaftarDepartment (lintas-
 * gudang, tidak memakai MembutuhkanDepotTerkunci). Lihat catatan di sana.
 */
class DaftarJabatan extends Component
{
    use MendukungImporBerkas;
    use WithFileUploads;

    public ?int $jabatanId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function jabatans()
    {
        return Jabatan::query()
            ->withCount('karyawans')
            ->orderBy('nama')
            ->get();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $jabatan = Jabatan::findOrFail($id);

        $this->jabatanId = $jabatan->id;
        $this->kode = $jabatan->kode;
        $this->nama = $jabatan->nama;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['jabatanId', 'kode', 'nama']);
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('jabatans', 'kode')->ignore($this->jabatanId)],
            'nama' => 'required|string|max:100',
        ], [], ['kode' => __('hr.atr_kode_jabatan'), 'nama' => __('hr.atr_nama_jabatan')]);

        Jabatan::updateOrCreate(['id' => $this->jabatanId], $data);

        $this->tutupForm();
        unset($this->jabatans);

        $this->dispatch('notifikasi', pesan: __('hr.jabatan_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $jabatan = Jabatan::withCount('karyawans')->findOrFail($id);

        if ($jabatan->karyawans_count > 0) {
            $this->dispatch('notifikasi', pesan: __('hr.jabatan_dipakai', [
                'nama' => $jabatan->nama,
                'jumlah' => $jabatan->karyawans_count,
            ]), jenis: 'error');

            $this->konfirmasiHapus = null;

            return;
        }

        $jabatan->delete();
        $this->konfirmasiHapus = null;
        unset($this->jabatans);

        $this->dispatch('notifikasi', pesan: __('hr.jabatan_dihapus'));
    }

    public function unduhExcel()
    {
        $jabatans = Jabatan::query()->orderBy('nama')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['kode', 'nama'], null, 'A1');

        $baris = 2;
        foreach ($jabatans as $jab) {
            $sheet->fromArray([
                null,
                $jab->nama,
            ], null, "A{$baris}");

            $sheet->setCellValueExplicit("A{$baris}", (string) $jab->kode, DataType::TYPE_STRING);
            $baris++;
        }

        $barisTerakhir = max(1, $baris - 1);
        $sheet->getStyle("A1:A{$barisTerakhir}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'B') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'jabatan-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function unduhContohExcel()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['kode', 'nama'], null, 'A1');
        $sheet->fromArray(['MGR', 'Manager'], null, 'A2');
        $sheet->fromArray(['SPV', 'Supervisor'], null, 'A3');
        $sheet->fromArray(['STF', 'Staff'], null, 'A4');

        $sheet->getStyle('A1:A100')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'B') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'contoh-import-jabatan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function mulaiImporCsv(): void
    {
        $this->validate([
            'berkasCsv' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
        ], [
            'berkasCsv.required' => __('umum.pilih_berkas_dulu'),
            'berkasCsv.mimes' => __('hr.gagal_urai_csv'),
        ]);

        $jalur = $this->berkasCsv->getRealPath();
        $ekstensi = mb_strtolower((string) $this->berkasCsv->getClientOriginalExtension());

        $semuaBaris = in_array($ekstensi, ['xlsx', 'xls'], true)
            ? $this->bacaBarisExcel($jalur)
            : $this->bacaBarisCsv($jalur);

        $judul = array_shift($semuaBaris);

        if ($judul === null) {
            $this->addError('berkasCsv', __('hr.gagal_urai_csv'));

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
            $this->addError('berkasCsv', __('hr.gagal_urai_csv'));

            return;
        }

        $isiJson = json_encode([
            'judul' => $judul,
            'baris' => $barisNormal,
            'kode_dilihat' => [],
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if ($isiJson === false) {
            $this->addError('berkasCsv', __('hr.gagal_urai_csv'));

            return;
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put("impor-jabatan/{$token}.json", $isiJson);

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

        $jalurBerkas = "impor-jabatan/{$this->imporToken}.json";

        if (! Storage::disk('local')->exists($jalurBerkas)) {
            $this->imporBerjalan = false;
            $this->dispatch('notifikasi', pesan: __('hr.sesi_impor_kedaluwarsa'), jenis: 'error');

            return;
        }

        $tersimpan = json_decode((string) Storage::disk('local')->get($jalurBerkas), true);

        if (! is_array($tersimpan) || ! isset($tersimpan['baris'], $tersimpan['judul'])) {
            $this->imporBerjalan = false;
            Storage::disk('local')->delete($jalurBerkas);
            $this->dispatch('notifikasi', pesan: __('hr.gagal_urai_csv'), jenis: 'error');

            return;
        }

        $judul = $tersimpan['judul'];
        $kodeDilihat = $tersimpan['kode_dilihat'] ?? [];
        $batch = array_slice($tersimpan['baris'], $this->imporOffset, $this->ukuranBatchImpor);

        if ($batch === []) {
            $this->selesaikanImpor();

            return;
        }

        $jabatansSemua = Jabatan::query()->select(['id', 'kode', 'nama'])->get();
        $jabatansByKode = $jabatansSemua->keyBy(fn ($j) => mb_strtoupper((string) $j->kode));

        $kolom = array_flip($judul);
        $idxKode = $kolom['kode'] ?? 0;
        $idxNama = $kolom['nama'] ?? 1;

        foreach ($batch as $offsetBatch => $baris) {
            $nomorBaris = $this->imporOffset + $offsetBatch + 2;

            $kode = trim((string) ($baris[$idxKode] ?? ''));
            $nama = trim((string) ($baris[$idxNama] ?? ''));

            if ($kode === '') {
                $this->imporDilewati[] = __('hr.lewat_kode_jab_kosong', ['nomor' => $nomorBaris]);
                continue;
            }

            if ($nama === '') {
                $this->imporDilewati[] = __('hr.lewat_nama_jab_kosong', ['nomor' => $nomorBaris]);
                continue;
            }

            $kodeUpper = mb_strtoupper($kode);

            if (isset($kodeDilihat[$kodeUpper])) {
                $this->imporDilewati[] = __('hr.lewat_kode_jab_duplikat', [
                    'nomor' => $nomorBaris,
                    'kode' => $kode,
                    'pertama' => $kodeDilihat[$kodeUpper],
                ]);
                continue;
            }

            $kodeDilihat[$kodeUpper] = $nomorBaris;

            $jabAda = $jabatansByKode->get($kodeUpper);

            if ($jabAda) {
                $jabAda->update(['nama' => $nama]);
                $this->imporDiperbarui++;
            } else {
                $jabBaru = Jabatan::create([
                    'kode' => $kode,
                    'nama' => $nama,
                ]);
                $jabatansByKode->put($kodeUpper, $jabBaru);
                $this->imporBaru++;
            }
        }

        $this->imporOffset += count($batch);

        $tersimpan['kode_dilihat'] = $kodeDilihat;
        Storage::disk('local')->put($jalurBerkas, json_encode($tersimpan, JSON_INVALID_UTF8_SUBSTITUTE));

        if ($this->imporOffset >= $this->imporTotal) {
            $this->selesaikanImpor();
        }
    }

    private function selesaikanImpor(): void
    {
        if ($this->imporToken !== null) {
            Storage::disk('local')->delete("impor-jabatan/{$this->imporToken}.json");
        }

        $this->imporBerjalan = false;
        $this->hasilImpor = [
            'baru' => $this->imporBaru,
            'diperbarui' => $this->imporDiperbarui,
            'dilewati' => $this->imporDilewati,
        ];

        unset($this->jabatans);

        $this->dispatch('notifikasi', pesan: __('hr.hasil_impor_jabatan', [
            'baru' => $this->imporBaru,
            'diperbarui' => $this->imporDiperbarui,
        ]), jenis: 'sukses');
    }

    public function batalkanImporCsv(): void
    {
        $this->batalkanImporBerkas('impor-jabatan');
    }

    public function render()
    {
        return view('livewire.hr.daftar-jabatan')->title(__('hr.judul_jabatan'));
    }
}
