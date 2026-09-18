<?php

namespace App\Livewire\Hr;

use App\Akses\HakAkses;
use App\Enums\CakupanData;
use App\Enums\JenisKelamin;
use App\Enums\StatusKaryawan;
use App\Models\Department;
use App\Models\Depot;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Posisi;
use App\Models\Scopes\DepotScope;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Master Karyawan — lintas-gudang (lihat dokumentasi App\Models\Karyawan).
 * Sengaja TIDAK memakai MembutuhkanDepotTerkunci: Karyawan tidak di-scope
 * per depot, jadi Superadmin dalam mode "Semua Depot" tetap bisa kelola
 * data di sini tanpa perlu mengunci satu depot dulu.
 */
class DaftarKaryawan extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $karyawanId = null;

    public bool $formTerbuka = false;

    public string $cari = '';

    // --- Formulir ---
    public string $kodeKaryawan = '';

    public string $namaLengkap = '';

    public string $nik = '';

    public string $jenisKelamin = 'L';

    public string $tanggalLahir = '';

    public string $noHp = '';

    public string $alamatDomisili = '';

    public string $departmentId = '';

    public string $jabatanId = '';

    public string $posisiId = '';

    /** Kosong = "Normal", yaitu mengikuti jam kerja posisinya. */
    public string $shiftId = '';

    public string $tanggalMasuk = '';

    public string $statusKaryawan = 'tetap';

    public string $tanggalBerakhirKontrak = '';

    public string $gajiPokok = '0';

    public string $noRekening = '';

    public string $npwp = '';

    public string $catatan = '';

    public string $depotId = '';

    public string $userId = '';

    public bool $aktif = true;

    /** @var TemporaryUploadedFile|null */
    public $fotoKaryawan = null;

    /** @var TemporaryUploadedFile|null */
    public $fotoKtp = null;

    /** Path foto yang sudah tersimpan, ditampilkan sebagai preview saat menyunting. */
    public ?string $fotoKaryawanLama = null;

    public ?string $fotoKtpLama = null;

    public ?int $konfirmasiHapus = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    /** Kontrak yang diakhiri tidak punya tanggal berakhir — dikosongkan begitu status diubah balik ke tetap. */
    public function updatedStatusKaryawan(string $nilai): void
    {
        if ($nilai !== StatusKaryawan::Kontrak->value) {
            $this->tanggalBerakhirKontrak = '';
        }
    }

    /**
     * Titik awal kueri karyawan di layar ini — "hanya milik sendiri" di Hak
     * Akses = data karyawan yang tertaut ke akun pengguna ini. Dipakai juga
     * saat mencari karyawan lewat id untuk disunting/dihapus.
     */
    private function kueriKaryawan(): Builder
    {
        return Karyawan::query()->when(
            app(HakAkses::class)->cakupan(auth()->user(), 'hr.karyawan') === CakupanData::Sendiri,
            fn ($q) => $q->where('user_id', auth()->id()),
        );
    }

    #[Computed]
    public function karyawans()
    {
        return $this->kueriKaryawan()
            ->with(['department:id,nama', 'jabatan:id,nama', 'depot:id,nama'])
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama_lengkap', 'like', "%{$this->cari}%")
                ->orWhere('kode_karyawan', 'like', "%{$this->cari}%")
                ->orWhere('nik', 'like', "%{$this->cari}%")))
            ->orderBy('nama_lengkap')
            ->paginate(20);
    }

    #[Computed]
    public function departments()
    {
        return Department::orderBy('nama')->get();
    }

    #[Computed]
    public function jabatans()
    {
        return Jabatan::orderBy('nama')->get();
    }

    #[Computed]
    public function posisis()
    {
        return Posisi::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function shifts()
    {
        return Shift::aktif()->orderBy('jam_masuk')->get(['id', 'nama', 'jam_masuk', 'jam_pulang']);
    }

    #[Computed]
    public function depots()
    {
        return Depot::aktif()->berurutan()->get();
    }

    /**
     * Akun pengguna yang bisa ditautkan — yang belum tertaut ke karyawan
     * lain, DITAMBAH akun yang sedang ditautkan ke karyawan yang sedang
     * disunting sendiri (supaya tidak hilang dari daftar). Lintas depot
     * dengan sengaja (`withoutGlobalScope`), sama seperti DaftarPengguna,
     * karena karyawan yang mau ditautkan bisa berasal dari depot mana pun.
     */
    #[Computed]
    public function penggunaBelumTertaut()
    {
        return User::withoutGlobalScope(DepotScope::class)
            ->where(function ($q): void {
                $q->whereDoesntHave('karyawan');

                if ($this->userId !== '') {
                    $q->orWhere('id', $this->userId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function jenisKelaminCases(): array
    {
        return JenisKelamin::cases();
    }

    #[Computed]
    public function statusKaryawanCases(): array
    {
        return StatusKaryawan::cases();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $karyawan = $this->kueriKaryawan()->findOrFail($id);

        $this->karyawanId = $karyawan->id;
        $this->kodeKaryawan = $karyawan->kode_karyawan;
        $this->namaLengkap = $karyawan->nama_lengkap;
        $this->nik = $karyawan->nik;
        $this->jenisKelamin = $karyawan->jenis_kelamin->value;
        $this->tanggalLahir = $karyawan->tanggal_lahir->toDateString();
        $this->noHp = $karyawan->no_hp;
        $this->alamatDomisili = $karyawan->alamat_domisili;
        $this->departmentId = (string) $karyawan->department_id;
        $this->jabatanId = (string) $karyawan->jabatan_id;
        $this->posisiId = $karyawan->posisi_id === null ? '' : (string) $karyawan->posisi_id;
        $this->shiftId = $karyawan->shift_id === null ? '' : (string) $karyawan->shift_id;
        $this->tanggalMasuk = $karyawan->tanggal_masuk->toDateString();
        $this->statusKaryawan = $karyawan->status_karyawan->value;
        $this->tanggalBerakhirKontrak = $karyawan->tanggal_berakhir_kontrak?->toDateString() ?? '';
        $this->gajiPokok = (string) $karyawan->gaji_pokok;
        $this->noRekening = $karyawan->no_rekening ?? '';
        $this->npwp = $karyawan->npwp ?? '';
        $this->catatan = $karyawan->catatan ?? '';
        $this->depotId = (string) $karyawan->depot_id;
        $this->userId = $karyawan->user_id === null ? '' : (string) $karyawan->user_id;
        $this->aktif = $karyawan->aktif;
        $this->fotoKaryawanLama = $karyawan->foto_karyawan;
        $this->fotoKtpLama = $karyawan->foto_ktp;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset([
            'karyawanId', 'kodeKaryawan', 'namaLengkap', 'nik', 'tanggalLahir',
            'noHp', 'alamatDomisili', 'departmentId', 'jabatanId', 'posisiId', 'shiftId', 'tanggalMasuk',
            'tanggalBerakhirKontrak', 'noRekening', 'npwp', 'catatan', 'depotId',
            'userId', 'fotoKaryawan', 'fotoKtp', 'fotoKaryawanLama', 'fotoKtpLama',
        ]);
        $this->jenisKelamin = 'L';
        $this->statusKaryawan = 'tetap';
        $this->gajiPokok = '0';
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kodeKaryawan' => ['required', 'string', 'max:30', Rule::unique('karyawans', 'kode_karyawan')->ignore($this->karyawanId)],
            'namaLengkap' => 'required|string|max:255',
            'nik' => ['required', 'digits:16', Rule::unique('karyawans', 'nik')->ignore($this->karyawanId)],
            'jenisKelamin' => ['required', Rule::enum(JenisKelamin::class)],
            'tanggalLahir' => 'required|date',
            'noHp' => 'required|string|max:20',
            'alamatDomisili' => 'required|string',
            'departmentId' => 'required|exists:departments,id',
            'jabatanId' => 'required|exists:jabatans,id',
            // Posisi menentukan jam kerja & kondisi absen karyawan ini.
            'posisiId' => 'required|exists:posisis,id',
            'shiftId' => 'nullable|exists:shifts,id',
            'tanggalMasuk' => 'required|date',
            'statusKaryawan' => ['required', Rule::enum(StatusKaryawan::class)],
            'tanggalBerakhirKontrak' => ['nullable', 'date', Rule::requiredIf($this->statusKaryawan === StatusKaryawan::Kontrak->value)],
            'gajiPokok' => 'required|numeric|min:0',
            'noRekening' => 'nullable|string|max:50',
            'npwp' => 'nullable|string|max:30',
            'catatan' => 'nullable|string',
            'depotId' => 'required|exists:depots,id',
            'userId' => ['nullable', 'exists:users,id', Rule::unique('karyawans', 'user_id')->ignore($this->karyawanId)],
            'fotoKaryawan' => 'nullable|image|max:5120',
            'fotoKtp' => 'nullable|image|max:5120',
        ], [], [
            'kodeKaryawan' => __('hr.atr_kode_karyawan'),
            'namaLengkap' => __('hr.atr_nama_lengkap'),
            'nik' => __('hr.atr_nik'),
            'jenisKelamin' => __('hr.atr_jenis_kelamin'),
            'tanggalLahir' => __('hr.atr_tanggal_lahir'),
            'noHp' => __('hr.atr_no_hp'),
            'alamatDomisili' => __('hr.atr_alamat_domisili'),
            'departmentId' => __('hr.atr_department'),
            'jabatanId' => __('hr.atr_jabatan'),
            'posisiId' => __('hr.atr_posisi'),
            'shiftId' => __('hr.atr_shift'),
            'tanggalMasuk' => __('hr.atr_tanggal_masuk'),
            'statusKaryawan' => __('hr.atr_status_karyawan'),
            'tanggalBerakhirKontrak' => __('hr.atr_tanggal_berakhir_kontrak'),
            'gajiPokok' => __('hr.atr_gaji_pokok'),
            'depotId' => __('hr.atr_penempatan'),
            'userId' => __('hr.atr_tautan_akun'),
        ]);

        $berkasTertulis = [];

        try {
            $karyawan = DB::transaction(function () use ($data, &$berkasTertulis): Karyawan {
                $atribut = [
                    'kode_karyawan' => $data['kodeKaryawan'],
                    'nama_lengkap' => $data['namaLengkap'],
                    'nik' => $data['nik'],
                    'jenis_kelamin' => $data['jenisKelamin'],
                    'tanggal_lahir' => $data['tanggalLahir'],
                    'no_hp' => $data['noHp'],
                    'alamat_domisili' => $data['alamatDomisili'],
                    'department_id' => $data['departmentId'],
                    'jabatan_id' => $data['jabatanId'],
                    'posisi_id' => $data['posisiId'],
                    'shift_id' => $data['shiftId'] ?: null,
                    'tanggal_masuk' => $data['tanggalMasuk'],
                    'status_karyawan' => $data['statusKaryawan'],
                    'tanggal_berakhir_kontrak' => $data['tanggalBerakhirKontrak'] ?: null,
                    'gaji_pokok' => $data['gajiPokok'],
                    'no_rekening' => $this->noRekening ?: null,
                    'npwp' => $this->npwp ?: null,
                    'catatan' => $this->catatan ?: null,
                    'depot_id' => $data['depotId'],
                    'user_id' => $data['userId'] ?: null,
                    'aktif' => $this->aktif,
                ];

                $karyawan = Karyawan::updateOrCreate(['id' => $this->karyawanId], $atribut);

                if ($this->fotoKaryawan !== null) {
                    $path = $this->fotoKaryawan->store("karyawan/{$karyawan->id}", 'public');
                    $berkasTertulis[] = $path;

                    if ($this->fotoKaryawanLama !== null) {
                        Storage::disk('public')->delete($this->fotoKaryawanLama);
                    }

                    $karyawan->update(['foto_karyawan' => $path]);
                }

                if ($this->fotoKtp !== null) {
                    $path = $this->fotoKtp->store("karyawan/{$karyawan->id}", 'public');
                    $berkasTertulis[] = $path;

                    if ($this->fotoKtpLama !== null) {
                        Storage::disk('public')->delete($this->fotoKtpLama);
                    }

                    $karyawan->update(['foto_ktp' => $path]);
                }

                return $karyawan;
            });
        } catch (\Throwable $e) {
            foreach ($berkasTertulis as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        $this->tutupForm();
        unset($this->karyawans, $this->penggunaBelumTertaut);

        $this->dispatch('notifikasi', pesan: __('hr.karyawan_tersimpan', ['nama' => $karyawan->nama_lengkap]));
    }

    public function hapus(int $id): void
    {
        $karyawan = $this->kueriKaryawan()->findOrFail($id);
        $nama = $karyawan->nama_lengkap;
        $karyawan->delete();

        $this->konfirmasiHapus = null;
        unset($this->karyawans, $this->penggunaBelumTertaut);

        $this->dispatch('notifikasi', pesan: __('hr.karyawan_dihapus', ['nama' => $nama]));
    }

    public function render()
    {
        return view('livewire.hr.daftar-karyawan')->title(__('hr.judul_karyawan'));
    }
}
