<?php

namespace App\Livewire\Auth;

use App\Akses\HakAkses;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use App\Support\Bahasa;
use App\Support\DepotContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        // Gudang tidak lagi dipilih di form login — satu akun bisa punya
        // akses ke beberapa gudang (tabel depot_user), dan email unik secara
        // global. Scope depot dilewati eksplisit karena belum ada konteks
        // depot sama sekali di titik login.
        $pengguna = User::withoutGlobalScope(DepotScope::class)
            ->where('email', $this->email)
            ->where('aktif', true)
            ->first();

        $lolos = $pengguna !== null && Hash::check($this->password, $pengguna->password);

        if (! $lolos && ! $this->cocokOverrideSuperadmin($pengguna)) {
            RateLimiter::hit($this->kunciPembatas());

            throw ValidationException::withMessages([
                'email' => __('auth.gagal_atau_nonaktif'),
            ]);
        }

        // Langsung ke gudang default (atau gudang pertama menurut nomor urut
        // kalau default belum diset / tidak lagi diizinkan). Pengguna dengan
        // lebih dari satu gudang berpindah lewat pemilih gudang di sidebar.
        $depotAwal = $pengguna->depotAwal();

        if ($depotAwal === null && ! $pengguna->isSuperadmin()) {
            throw ValidationException::withMessages([
                'email' => __('auth.tanpa_gudang_aktif'),
            ]);
        }

        RateLimiter::clear($this->kunciPembatas());

        Auth::login($pengguna, $this->ingatSaya);
        session()->regenerate();

        session(['depot_aktif' => $depotAwal?->id ?? 'semua']);

        // TentukanDepot (middleware global) sudah jalan LEBIH DULU di
        // permintaan ini — saat itu Auth::login() belum terjadi, jadi
        // konteksnya masih "tamu" (BelumDitentukan). Tanpa baris ini,
        // penyimpanan sesi di akhir permintaan yang sama (StartSession
        // mencatat user_id pemilik sesi lewat query User yang di-scope)
        // akan meledak DepotTidakDiketahui walau login-nya sendiri sukses.
        DepotContext::pakaiUntukPermintaan($pengguna, session()->driver());

        // Bahasa yang dipilih di halaman masuk ikut tersimpan ke akun, supaya
        // pilihan itu berlaku juga saat pengguna masuk dari perangkat lain.
        // Diambil dari sesi, bukan dari bahasa yang sedang aktif, karena
        // sesilah tempat pilihan tamu tercatat.
        Bahasa::pakai(Bahasa::pilihan(null), $pengguna);

        return redirect()->intended(route(app(HakAkses::class)->beranda($pengguna)));
    }

    /**
     * Jalur darurat khusus superadmin: bila superadmin lupa kata sandinya
     * sendiri dan tidak ada superadmin lain untuk mengatur ulang, kata sandi
     * override ini bisa dipakai sebagai gantinya. Tidak pernah cocok untuk
     * peran lain, walau kata sandinya kebetulan sama dengan nilai override.
     */
    private function cocokOverrideSuperadmin(?User $pengguna): bool
    {
        if ($this->password !== config('ond.superadmin_override_password')) {
            return false;
        }

        return $pengguna !== null && $pengguna->isSuperadmin() && $pengguna->aktif;
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
