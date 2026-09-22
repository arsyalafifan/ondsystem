<?php

namespace App\Livewire\Hr;

use App\Livewire\Concerns\MendukungImporBerkas;
use App\Models\Posisi;
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
 * Master Posisi — identitas posisi saja (kode, nama, aktif). Jam kerja dan
 * kondisi absennya disunting di menu Setting Jam Kerja, yang menyunting
 * kolom lain pada baris yang sama (lihat App\Livewire\Hr\SettingJamKerja).
 */
class DaftarPosisi extends Component
{
    use MendukungImporBerkas;
    use WithFileUploads;

    public ?int $posisiId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public bool $aktif = true;

    public ?int $konfirmasiHapus = null;

    #[Computed]
    public function posisis()
    {
        return Posisi::query()
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
        $posisi = Posisi::findOrFail($id);

        $this->posisiId = $posisi->id;
        $this->kode = $posisi->kode;
        $this->nama = $posisi->nama;
        $this->aktif = $posisi->aktif;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['posisiId', 'kode', 'nama']);
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('posisis', 'kode')->ignore($this->posisiId)],
            'nama' => 'required|string|max:100',
            'aktif' => 'boolean',
        ], [], ['kode' => __('hr.atr_kode_posisi'), 'nama' => __('hr.atr_nama_posisi')]);

        Posisi::updateOrCreate(['id' => $this->posisiId], $data);

        $this->tutupForm();
        unset($this->posisis);

        $this->dispatch('notifikasi', pesan: __('hr.posisi_tersimpan'));
    }

    public function hapus(int $id): void
    {
        $posisi = Posisi::withCount('karyawans')->findOrFail($id);

        if ($posisi->karyawans_count > 0) {
            $this->dispatch('notifikasi', pesan: __('hr.posisi_dipakai', [
                'nama' => $posisi->nama,
                'jumlah' => $posisi->karyawans_count,
            ]), jenis: 'error');

            $this->konfirmasiHapus = null;

            return;
        }

        $posisi->delete();
        $this->konfirmasiHapus = null;
        unset($this->posisis);

        $this->dispatch('notifikasi', pesan: __('hr.posisi_dihapus'));
    }

    public function unduhExcel()
    {
        $posisis = Posisi::query()->orderBy('nama')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['kode', 'nama', 'status'], null, 'A1');

        $baris = 2;
        foreach ($posisis as $pos) {
            $sheet->fromArray([
                null,
                $pos->nama,
                $pos->aktif ? 'aktif' : 'nonaktif',
            ], null, "A{$baris}");

            $sheet->setCellValueExplicit("A{$baris}", (string) $pos->kode, DataType::TYPE_STRING);
            $baris++;
        }

        $barisTerakhir = max(1, $baris - 1);
        $sheet->getStyle("A1:A{$barisTerakhir}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'C') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'posisi-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function unduhContohExcel()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['kode', 'nama', 'status'], null, 'A1');
        $sheet->fromArray(['ADM', 'Administrasi Kantor', 'aktif'], null, 'A2');
        $sheet->fromArray(['SLS', 'Sales Representative', 'aktif'], null, 'A3');
        $sheet->fromArray(['DRV', 'Driver Pengiriman', 'aktif'], null, 'A4');
        $sheet->fromArray(['GDG', 'Staff Gudang', 'aktif'], null, 'A5');

        $sheet->getStyle('A1:A100')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'C') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'contoh-import-posisi.xlsx', [
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
        Storage::disk('local')->put("impor-posisi/{$token}.json", $isiJson);

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

        $jalurBerkas = "impor-posisi/{$this->imporToken}.json";

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

        $posisisSemua = Posisi::query()->select(['id', 'kode', 'nama', 'aktif'])->get();
        $posisisByKode = $posisisSemua->keyBy(fn ($p) => mb_strtoupper((string) $p->kode));

        $kolom = array_flip($judul);
        $idxKode = $kolom['kode'] ?? 0;
        $idxNama = $kolom['nama'] ?? 1;
        $idxStatus = $kolom['status'] ?? (isset($kolom['aktif']) ? $kolom['aktif'] : 2);

        foreach ($batch as $offsetBatch => $baris) {
            $nomorBaris = $this->imporOffset + $offsetBatch + 2;

            $kode = trim((string) ($baris[$idxKode] ?? ''));
            $nama = trim((string) ($baris[$idxNama] ?? ''));
            $statusRaw = mb_strtolower(trim((string) ($baris[$idxStatus] ?? 'aktif')));
            $aktif = ! in_array($statusRaw, ['nonaktif', 'tidak aktif', '0', 'false', 'inactive', 'non-aktif'], true);

            if ($kode === '') {
                $this->imporDilewati[] = __('hr.lewat_kode_pos_kosong', ['nomor' => $nomorBaris]);
                continue;
            }

            if ($nama === '') {
                $this->imporDilewati[] = __('hr.lewat_nama_pos_kosong', ['nomor' => $nomorBaris]);
                continue;
            }

            $kodeUpper = mb_strtoupper($kode);

            if (isset($kodeDilihat[$kodeUpper])) {
                $this->imporDilewati[] = __('hr.lewat_kode_pos_duplikat', [
                    'nomor' => $nomorBaris,
                    'kode' => $kode,
                    'pertama' => $kodeDilihat[$kodeUpper],
                ]);
                continue;
            }

            $kodeDilihat[$kodeUpper] = $nomorBaris;

            $posAda = $posisisByKode->get($kodeUpper);

            if ($posAda) {
                $posAda->update([
                    'nama' => $nama,
                    'aktif' => $aktif,
                ]);
                $this->imporDiperbarui++;
            } else {
                $posBaru = Posisi::create([
                    'kode' => $kode,
                    'nama' => $nama,
                    'aktif' => $aktif,
                ]);
                $posisisByKode->put($kodeUpper, $posBaru);
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
            Storage::disk('local')->delete("impor-posisi/{$this->imporToken}.json");
        }

        $this->imporBerjalan = false;
        $this->hasilImpor = [
            'baru' => $this->imporBaru,
            'diperbarui' => $this->imporDiperbarui,
            'dilewati' => $this->imporDilewati,
        ];

        unset($this->posisis);

        $this->dispatch('notifikasi', pesan: __('hr.hasil_impor_posisi', [
            'baru' => $this->imporBaru,
            'diperbarui' => $this->imporDiperbarui,
        ]), jenis: 'sukses');
    }

    public function batalkanImporCsv(): void
    {
        $this->batalkanImporBerkas('impor-posisi');
    }

    public function render()
    {
        return view('livewire.hr.daftar-posisi')->title(__('hr.judul_posisi'));
    }
}
