<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Bahasa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.tamu')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $ingatSaya = false;

    public function masuk()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => __('auth.email_wajib'),
            'email.email' => __('auth.email_format'),
            'password.required' => __('auth.sandi_wajib'),
        ]);

        $this->tahanPercobaanBerlebih();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'aktif' => true], $this->ingatSaya)) {
            if (! $this->masukLewatOverrideSuperadmin()) {
                RateLimiter::hit($this->kunciPembatas());

                throw ValidationException::withMessages([
                    'email' => __('auth.gagal_atau_nonaktif'),
                ]);
            }
        }

        RateLimiter::clear($this->kunciPembatas());
        session()->regenerate();

        $pengguna = Auth::user();

        // Bahasa yang dipilih di halaman masuk ikut tersimpan ke akun, supaya
        // pilihan itu berlaku juga saat pengguna masuk dari perangkat lain.
        // Diambil dari sesi, bukan dari bahasa yang sedang aktif, karena
        // sesilah tempat pilihan tamu tercatat.
        Bahasa::pakai(Bahasa::pilihan(null), $pengguna);

        return redirect()->intended(route($pengguna->role->beranda()));
    }

    /**
     * Jalur darurat khusus superadmin: bila superadmin lupa kata sandinya
     * sendiri dan tidak ada superadmin lain untuk mengatur ulang, kata sandi
     * override ini bisa dipakai sebagai gantinya. Tidak pernah cocok untuk
     * peran lain, walau kata sandinya kebetulan sama dengan nilai override.
     */
    private function masukLewatOverrideSuperadmin(): bool
    {
        if ($this->password !== config('ond.superadmin_override_password')) {
            return false;
        }

        $pengguna = User::where('email', $this->email)->first();

        if ($pengguna === null || ! $pengguna->isSuperadmin() || ! $pengguna->aktif) {
            return false;
        }

        Auth::login($pengguna, $this->ingatSaya);

        return true;
    }

    /** Menahan tebakan kata sandi beruntun dari satu sumber. */
    private function tahanPercobaanBerlebih(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciPembatas(), 5)) {
            return;
        }

        $detik = RateLimiter::availableIn($this->kunciPembatas());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $detik]),
        ]);
    }

    private function kunciPembatas(): string
    {
        return 'masuk:'.mb_strtolower($this->email).'|'.request()->ip();
    }

    public function render()
    {
        // Judul ditetapkan di sini, bukan lewat atribut #[Title], karena
        // atribut PHP hanya menerima nilai tetap sehingga tidak bisa
        // diterjemahkan.
        return view('livewire.auth.login')->title(__('auth.judul'));
    }
}
