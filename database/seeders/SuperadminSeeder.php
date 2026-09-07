<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Satu-satunya data yang perlu ada saat aplikasi pertama kali dijalankan
 * di produksi — bukan data contoh. Superadmin ini yang login pertama kali
 * dan mendaftarkan seluruh pengguna lain lewat aplikasi.
 *
 * Email dan password diambil dari env SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD
 * (dipakai oleh scripts/setup.sh saat deploy non-interaktif) dan jatuh ke
 * prompt interaktif kalau env-nya kosong.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL') ?: $this->command->ask('Email superadmin', 'superadmin@ondsystem.test');

        $passwordDariEnv = env('SUPERADMIN_PASSWORD');
        $password = $passwordDariEnv ?: Str::password(16);

        // Seeder ini dipanggil lewat `artisan db:seed`, tanpa sesi HTTP sama
        // sekali — tidak pernah ada konteks depot yang ditetapkan. User
        // sekarang di-scope App\Models\Scopes\DepotScope, jadi baik langkah
        // "cari yang sudah ada" maupun "buat baru" di updateOrCreate() harus
        // eksplisit melewati scope itu, bukan cuma menambahkan depot_id di
        // data yang ditulis — superadmin memang tidak terikat depot manapun.
        User::withoutGlobalScope(DepotScope::class)->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Superadmin',
                'password' => Hash::make($password),
                'role' => PeranPengguna::Superadmin,
                'depot_id' => null,
                'aktif' => true,
            ],
        );

        $this->command->newLine();
        $this->command->info("Superadmin siap: {$email}");

        if (! $passwordDariEnv) {
            $this->command->warn("Password (dibangkitkan otomatis): {$password}");
            $this->command->warn('Catat sekarang — tidak ditampilkan ulang. Segera login dan ganti password.');
        }
    }
}
