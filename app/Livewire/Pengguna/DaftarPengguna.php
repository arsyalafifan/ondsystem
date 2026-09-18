<?php

namespace App\Livewire\Pengguna;

use App\Enums\PeranPengguna;
use App\Models\Depot;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /** Id gudang (string) yang boleh dimasuki akun ini — tabel depot_user. */
    public array $depotAkses = [];

    /** Gudang tujuan setelah login; '' = otomatis gudang pertama menurut nomor urut. */
    public string $depotDefault = '';

    public bool $aktif = true;

    public ?int $konfirmasiReset = null;

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['cari', 'filterDepot', 'filterPeran'], true)) {
            $this->resetPage();
        }
    }

    /** Default yang tidak lagi dicentang kembali ke "otomatis". */
    public function updatedDepotAkses(): void
    {
        if (! in_array($this->depotDefault, $this->depotAkses, true)) {
            $this->depotDefault = '';
        }
    }

    #[Computed]
    public function penggunas()
    {
        return User::withoutGlobalScope(DepotScope::class)
            ->with(['depots' => fn ($q) => $q->berurutan()->select('depots.id', 'depots.nama')])
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->cari}%")
                ->orWhere('email', 'like', "%{$this->cari}%")))
            ->when($this->filterDepot === 'tanpa_depot', fn ($q) => $q->whereDoesntHave('depots'))
            ->when(
                $this->filterDepot !== '' && $this->filterDepot !== 'tanpa_depot',
                fn ($q) => $q->whereHas('depots', fn ($d) => $d->whereKey((int) $this->filterDepot)),
            )
            ->when($this->filterPeran !== '', fn ($q) => $q->where('role', $this->filterPeran))
            ->orderBy('name')
            ->paginate(20);
    }

    /** @return Collection<int, Depot> */
    #[Computed]
    public function depotAktif()
    {
        return Depot::aktif()->berurutan()->get(['id', 'nama']);
    }

    /** Daftar depot untuk filter — termasuk yang nonaktif, supaya akun lama tetap tertelusur. */
    #[Computed]
    public function depotUntukFilter()
    {
        return Depot::query()->berurutan()->get(['id', 'nama']);
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
        $this->depotAkses = $u->depots()->pluck('depots.id')->map(fn ($id) => (string) $id)->all();
        $this->depotDefault = $u->depot_id !== null && in_array((string) $u->depot_id, $this->depotAkses, true)
            ? (string) $u->depot_id
            : '';
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
        $this->reset(['penggunaId', 'name', 'email', 'no_hp', 'depotAkses', 'depotDefault']);
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

        // Email unik global: satu orang = satu akun, akses ke banyak gudang
        // diatur lewat daftar gudang di bawah (bukan akun ganda per gudang).
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->penggunaId)],
            'role' => ['required', Rule::enum(PeranPengguna::class)],
            'no_hp' => 'nullable|string|max:20',
            'depotAkses' => 'array',
            'depotAkses.*' => ['integer', Rule::exists('depots', 'id')],
            'depotDefault' => ['nullable', Rule::in($this->depotAkses)],
        ], [
            'depotDefault.in' => __('pengguna.default_harus_diakses'),
        ], [
            'name' => __('pengguna.atr_nama'),
            'email' => __('pengguna.atr_email'),
            'role' => __('pengguna.atr_peran'),
            'depotAkses' => __('pengguna.atr_akses_gudang'),
            'depotDefault' => __('pengguna.atr_gudang_default'),
        ]);

        $akses = [];

        if ($butuhDepot) {
            $akses = array_values(array_unique(array_map('intval', $data['depotAkses'] ?? [])));

            // Tidak dicentang satu pun → gudang nomor urut pertama.
            if ($akses === []) {
                $pertama = Depot::query()->aktif()->berurutan()->value('id');

                if ($pertama === null) {
                    $this->addError('depotAkses', __('pengguna.belum_ada_gudang'));

                    return;
                }

                $akses = [$pertama];
            }
        }

        $isBaru = $this->penggunaId === null;

        $atribut = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'no_hp' => $this->no_hp ?: null,
            'aktif' => $this->aktif,
            // Disebut eksplisit (termasuk null) supaya BerDepot tidak mengisi
            // depot_id dari gudang yang sedang aktif di sesi superadmin.
            // null = otomatis gudang pertama yang diizinkan.
            'depot_id' => $butuhDepot && $this->depotDefault !== '' ? (int) $this->depotDefault : null,
        ];

        DB::transaction(function () use ($isBaru, $atribut, $akses) {
            $pengguna = $isBaru
                ? User::withoutGlobalScope(DepotScope::class)->create([...$atribut, 'password' => Hash::make('password')])
                : tap(User::withoutGlobalScope(DepotScope::class)->findOrFail($this->penggunaId))->update($atribut);

            // Superadmin selalu bisa ke semua gudang — tidak butuh baris akses.
            $pengguna->depots()->sync($akses);
        });

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
