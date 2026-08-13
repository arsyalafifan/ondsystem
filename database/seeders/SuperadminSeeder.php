<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
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

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Superadmin',
                'password' => Hash::make($password),
                'role' => PeranPengguna::Superadmin,
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
