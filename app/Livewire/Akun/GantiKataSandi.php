<?php

namespace App\Livewire\Akun;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class GantiKataSandi extends Component
{
    public string $kataSandiLama = '';

    public string $kataSandiBaru = '';

    public string $kataSandiBaru_confirmation = '';

    public function simpan(): void
    {
        $this->validate([
            'kataSandiLama' => ['required', 'current_password'],
            'kataSandiBaru' => ['required', 'confirmed', Password::min(8)],
        ], [
            'kataSandiLama.current_password' => __('auth.kata_sandi_lama_salah'),
        ], [
            'kataSandiLama' => __('auth.kata_sandi_lama'),
            'kataSandiBaru' => __('auth.kata_sandi_baru'),
        ]);

        Auth::user()->update(['password' => Hash::make($this->kataSandiBaru)]);

        $this->reset(['kataSandiLama', 'kataSandiBaru', 'kataSandiBaru_confirmation']);

        $this->dispatch('notifikasi', pesan: __('auth.sandi_diperbarui'));
    }

    public function render()
    {
        return view('livewire.akun.ganti-kata-sandi')->title(__('auth.judul_ganti_sandi'));
    }
}
