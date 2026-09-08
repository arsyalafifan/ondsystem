<?php

namespace App\Livewire\Pengguna;

use App\Enums\PeranPengguna;
use App\Models\Depot;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar superadmin SATU-SATUNYA yang sengaja menampilkan akun dari SEMUA
 * depot sekaligus, terlepas dari mode "Terkunci"/"Semua Depot" yang sedang
 * aktif — setiap query User di sini lewat withoutGlobalScope(DepotScope),
 * persis seperti Depot sendiri tidak pernah di-scope. Kalau tidak begini,
 * superadmin yang sedang terkunci ke satu depot tidak akan pernah bisa
 * mengelola akun depot lain dari sini.
 */
class DaftarPengguna extends Component
{
    use WithPagination;

    #[Url]
    public string $cari = '';

    #[Url]
    public string $filterDepot = '';

    #[Url]
    public string $filterPeran = '';

    public ?int $penggunaId = null;

    public bool $formTerbuka = false;

    public string $name = '';

    public string $email = '';

    public string $role = 'sales';

    public string $no_hp = '';

    public string $depotIdForm = '';

    public bool $aktif = true;

    public ?int $konfirmasiReset = null;

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['cari', 'filterDepot', 'filterPeran'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function penggunas()
    {
        return User::withoutGlobalScope(DepotScope::class)
            ->with('depot:id,nama')
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->cari}%")
                ->orWhere('email', 'like', "%{$this->cari}%")))
            ->when($this->filterDepot === 'tanpa_depot', fn ($q) => $q->whereNull('depot_id'))
            ->when(
                $this->filterDepot !== '' && $this->filterDepot !== 'tanpa_depot',
                fn ($q) => $q->where('depot_id', $this->filterDepot),
            )
            ->when($this->filterPeran !== '', fn ($q) => $q->where('role', $this->filterPeran))
            ->orderBy('name')
            ->paginate(20);
    }

    /** @return Collection<int, Depot> */
    #[Computed]
    public function depotAktif()
    {
        return Depot::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    /** Daftar depot untuk filter — termasuk yang nonaktif, supaya akun lama tetap tertelusur. */
    #[Computed]
    public function depotUntukFilter()
    {
        return Depot::query()->orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function peranCases(): array
    {
        return PeranPengguna::cases();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $u = User::withoutGlobalScope(DepotScope::class)->findOrFail($id);

        $this->penggunaId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->role = $u->role->value;
        $this->no_hp = $u->no_hp ?? '';
        $this->depotIdForm = $u->depot_id === null ? '' : (string) $u->depot_id;
        $this->aktif = $u->aktif;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['penggunaId', 'name', 'email', 'no_hp', 'depotIdForm']);
        $this->role = 'sales';
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        if (! auth()->user()->isSuperadmin()) {
            abort(403);
        }

        $peranDipilih = PeranPengguna::from($this->role);
        $butuhDepot = $peranDipilih !== PeranPengguna::Superadmin;

        // Email unik per-depot sejak Stage 4 (kolom generated
        // depot_kunci_unik), BUKAN unik global — Rule::unique polos di
        // kolom email saja akan salah menolak email yang kebetulan sudah
        // dipakai di DEPOT LAIN, padahal itu sah. Cakupan pengecekannya
        // harus persis meniru constraint database: sesama superadmin
        // (depot_id NULL) untuk peran superadmin, atau sesama depot yang
        // dipilih untuk peran lain.
        $emailRule = Rule::unique('users', 'email')
            ->ignore($this->penggunaId)
            ->where(fn ($query) => $butuhDepot
                ? $query->where('depot_id', $this->depotIdForm !== '' ? (int) $this->depotIdForm : 0)
                : $query->whereNull('depot_id'));

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', $emailRule],
            'role' => ['required', Rule::enum(PeranPengguna::class)],
            'no_hp' => 'nullable|string|max:20',
            'depotIdForm' => [$butuhDepot ? 'required' : 'nullable', 'nullable', 'exists:depots,id'],
        ], [], [
            'name' => __('pengguna.atr_nama'),
            'email' => __('pengguna.atr_email'),
            'role' => __('pengguna.atr_peran'),
            'depotIdForm' => __('pengguna.atr_depot'),
        ]);

        $isBaru = $this->penggunaId === null;

        $atribut = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'no_hp' => $this->no_hp ?: null,
            'aktif' => $this->aktif,
        ];

        if ($isBaru) {
            // depot_id SELALU disebut eksplisit di sini — dipilih dari
            // formulir, bukan mengandalkan konteks depot yang sedang aktif
            // di sesi superadmin (yang sekarang bisa dalam mode apapun,
            // sejak layar ini menampilkan semua depot sekaligus).
            $atribut['depot_id'] = $butuhDepot ? (int) $data['depotIdForm'] : null;

            User::withoutGlobalScope(DepotScope::class)
                ->create([...$atribut, 'password' => Hash::make('password')]);
        } else {
            // depot_id akun yang sudah ada SENGAJA tidak diubah lewat form
            // ini — cuma dipilih saat pembuatan. Memindahkan akun ke depot
            // lain berarti riwayat lama (pesanan, kunjungan) tetap tercatat
            // di depot asalnya, jadi bisa membingungkan kalau dilakukan
            // diam-diam lewat form biasa.
            User::withoutGlobalScope(DepotScope::class)
                ->whereKey($this->penggunaId)
                ->update($atribut);
        }

        $this->tutupForm();
        unset($this->penggunas);

        $this->dispatch('notifikasi', pesan: $isBaru
            ? __('pengguna.pengguna_tersimpan', ['sandi' => 'password'])
            : __('pengguna.pengguna_diperbarui'));
    }

    public function resetSandi(int $id): void
    {
        if (! auth()->user()->isSuperadmin()) {
            abort(403);
        }

        $u = User::withoutGlobalScope(DepotScope::class)->findOrFail($id);
        $u->update(['password' => Hash::make('password')]);

        $this->konfirmasiReset = null;
        unset($this->penggunas);

        $this->dispatch('notifikasi', pesan: __('pengguna.sandi_direset', ['nama' => $u->name]));
    }

    public function render()
    {
        return view('livewire.pengguna.daftar-pengguna')->title(__('pengguna.judul'));
    }
}
