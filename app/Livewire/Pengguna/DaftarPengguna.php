<?php

namespace App\Livewire\Pengguna;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DaftarPengguna extends Component
{
    public ?int $penggunaId = null;

    public bool $formTerbuka = false;

    public string $name = '';

    public string $email = '';

    public string $role = 'sales';

    public string $no_hp = '';

    public bool $aktif = true;

    public ?int $konfirmasiReset = null;

    #[Computed]
    public function penggunas()
    {
        return User::query()->orderBy('name')->get();
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
        $u = User::findOrFail($id);

        $this->penggunaId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->role = $u->role->value;
        $this->no_hp = $u->no_hp ?? '';
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
        $this->reset(['penggunaId', 'name', 'email', 'no_hp']);
        $this->role = 'sales';
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        if (! auth()->user()->isSuperadmin()) {
            abort(403);
        }

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->penggunaId)],
            'role' => ['required', Rule::enum(PeranPengguna::class)],
            'no_hp' => 'nullable|string|max:20',
        ], [], [
            'name' => __('pengguna.atr_nama'),
            'email' => __('pengguna.atr_email'),
            'role' => __('pengguna.atr_peran'),
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
            // Akun baru langsung memakai kata sandi standar; tidak ada
            // kolom kata sandi di formulir ini sama sekali.
            User::create([...$atribut, 'password' => Hash::make('password')]);
        } else {
            User::whereKey($this->penggunaId)->update($atribut);
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

        $u = User::findOrFail($id);
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
