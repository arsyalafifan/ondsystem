<?php

namespace App\Livewire\Auth;

use App\Models\Depot;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use App\Support\Bahasa;
use App\Support\DepotContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.tamu')]
class Login extends Component
{
    /** Id Depot sebagai string, atau literal 'semua' (khusus superadmin). */
    #[Validate('required|string')]
    public string $depotId = '';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $ingatSaya = false;

    /** @return Collection<int, Depot> */
    #[Computed]
    public function depots(): Collection
    {
        return Depot::query()->aktif()->orderBy('nama')->get();
    }

    public function masuk()
    {
        $this->validate([
            'depotId' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'depotId.required' => __('auth.depot_wajib'),
            'email.required' => __('auth.email_wajib'),
            'email.email' => __('auth.email_format'),
            'password.required' => __('auth.sandi_wajib'),
        ]);

        $this->tahanPercobaanBerlebih();

        $depotDipilih = $this->depotId === 'semua' ? null : Depot::query()->aktif()->find($this->depotId);

        if ($this->depotId !== 'semua' && $depotDipilih === null) {
            RateLimiter::hit($this->kunciPembatas());

            throw ValidationException::withMessages([
                'depotId' => __('auth.depot_tidak_valid'),
            ]);
        }

        // Query manual, bukan Auth::attempt() — perlu mengekspresikan
        // "cocok dengan depot yang dipilih ATAU superadmin (depot_id null,
        // cocok apapun yang dipilih)", sesuatu yang tidak bisa diungkapkan
        // lewat array kredensial biasa. Scope depot dilewati eksplisit
        // karena belum ada konteks depot sama sekali di titik login.
        $pengguna = User::withoutGlobalScope(DepotScope::class)
            ->where('email', $this->email)
            ->where('aktif', true)
            ->where(fn ($q) => $q->whereNull('depot_id')
                ->when($depotDipilih, fn ($q2) => $q2->orWhere('depot_id', $depotDipilih->id)))
            ->first();

        $lolos = $pengguna !== null && Hash::check($this->password, $pengguna->password);

        if (! $lolos && ! $this->cocokOverrideSuperadmin($pengguna)) {
            RateLimiter::hit($this->kunciPembatas());

            throw ValidationException::withMessages([
                'email' => __('auth.gagal_atau_nonaktif'),
            ]);
        }

        RateLimiter::clear($this->kunciPembatas());

        Auth::login($pengguna, $this->ingatSaya);
        session()->regenerate();

        // Dipilih di form login (bukan cuma dibaca session lama) — supaya
        // superadmin yang baru masuk langsung berada di mode yang mereka
        // pilih sendiri, bukan mode terakhir yang kebetulan tersimpan.
        if ($pengguna->isSuperadmin()) {
            session(['depot_aktif' => $this->depotId === 'semua' ? 'semua' : $depotDipilih->id]);
        }

        // TentukanDepot (middleware global) sudah jalan LEBIH DULU di
        // permintaan ini — saat itu Auth::login() belum terjadi, jadi
        // konteksnya masih "tamu" (BelumDitentukan). Tanpa baris ini,
        // penyimpanan sesi di akhir permintaan yang sama (StartSession
        // mencatat user_id pemilik sesi lewat query User yang di-scope)
        // akan meledak DepotTidakDiketahui walau login-nya sendiri sukses.
        if ($this->depotId === 'semua') {
            // Hanya superadmin yang bisa lolos autentikasi di atas dengan
            // pilihan ini — user biasa selalu punya $depotDipilih terisi.
            DepotContext::pakaiSemuaDepot();
        } else {
            DepotContext::pakai($depotDipilih);
        }

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
