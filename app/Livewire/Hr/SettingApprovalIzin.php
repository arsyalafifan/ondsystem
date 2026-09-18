<?php

namespace App\Livewire\Hr;

use App\Enums\ModePersetujuanIzin;
use App\Enums\PeranPengguna;
use App\Models\Karyawan;
use App\Models\PengaturanIzin;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use App\Services\Izin\ApproverIzin;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Setting Approval Izin & Lembur: siapa yang memutuskan pengajuan izin,
 * sakit, dan lembur, plus batas lembur yang diakui per hari.
 * Aturan penentuannya di App\Services\Izin\ApproverIzin.
 */
class SettingApprovalIzin extends Component
{
    public string $mode = 'approver_umum';

    /** @var list<string> */
    public array $peran = [];

    /** @var list<int> */
    public array $pengguna = [];

    public string $penggunaDipilih = '';

    /** Batas lembur yang diakui per hari, dalam JAM (boleh pecahan, mis. 1,5). */
    public string $maksLemburJam = '1';

    public function mount(): void
    {
        $pengaturan = PengaturanIzin::ambil();

        $this->mode = $pengaturan->mode->value;
        $this->peran = $pengaturan->peran();
        $this->pengguna = $pengaturan->idPengguna();
        $this->maksLemburJam = rtrim(rtrim(number_format($pengaturan->maks_lembur_menit / 60, 2, '.', ''), '0'), '.');
    }

    /** Superadmin tidak perlu dipilih — ia selalu boleh & jadi cadangan terakhir. */
    #[Computed]
    public function peranCases(): array
    {
        return array_values(array_filter(PeranPengguna::cases(), fn (PeranPengguna $p) => $p !== PeranPengguna::Superadmin));
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function semuaPengguna(): Collection
    {
        return User::withoutGlobalScope(DepotScope::class)
            ->where('aktif', true)
            ->where('role', '!=', PeranPengguna::Superadmin)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    /**
     * Approver umum menurut isian form SAAT INI (belum tersimpan) — supaya
     * HR melihat dampaknya sebelum menekan Simpan.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function pratinjau(): Collection
    {
        return User::withoutGlobalScope(DepotScope::class)
            ->where('aktif', true)
            ->where(fn ($q) => $q->whereIn('role', $this->peran)->orWhereIn('id', $this->pengguna))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /** Karyawan aktif tanpa atasan — di mode atasan, pengajuan mereka jatuh ke approver umum. */
    #[Computed]
    public function jumlahTanpaAtasan(): int
    {
        return Karyawan::query()->where('aktif', true)->whereNull('atasan_id')->count();
    }

    public function tambahPengguna(): void
    {
        $id = (int) $this->penggunaDipilih;

        if ($id > 0 && ! in_array($id, $this->pengguna, true) && $this->semuaPengguna->contains('id', $id)) {
            $this->pengguna[] = $id;
        }

        $this->penggunaDipilih = '';
        unset($this->pratinjau);
    }

    public function hapusPengguna(int $id): void
    {
        $this->pengguna = array_values(array_filter($this->pengguna, fn (int $p) => $p !== $id));
        unset($this->pratinjau);
    }

    public function updatedPeran(): void
    {
        unset($this->pratinjau);
    }

    public function simpan(ApproverIzin $approver): void
    {
        $data = $this->validate([
            'mode' => ['required', Rule::enum(ModePersetujuanIzin::class)],
            'peran' => 'array',
            'peran.*' => [Rule::in(array_map(fn (PeranPengguna $p) => $p->value, $this->peranCases))],
            'pengguna' => 'array',
            'pengguna.*' => 'integer|exists:users,id',
            'maksLemburJam' => 'required|numeric|min:0.25|max:12',
        ], [], ['maksLemburJam' => __('lembur.atr_maks_lembur')]);

        PengaturanIzin::ambil()->update([
            'mode' => $data['mode'],
            'approver_peran' => array_values(array_unique($data['peran'] ?? [])),
            'approver_pengguna' => array_values(array_unique(array_map('intval', $data['pengguna'] ?? []))),
            'maks_lembur_menit' => (int) round((float) $data['maksLemburJam'] * 60),
            'diubah_oleh' => auth()->id(),
        ]);

        $approver->lupakan();

        $this->dispatch('notifikasi', pesan: __('izin.setting_tersimpan'));
    }

    public function render()
    {
        return view('livewire.hr.setting-approval-izin')->title(__('izin.judul_setting'));
    }
}
