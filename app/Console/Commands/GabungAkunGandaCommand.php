<?php

namespace App\Console\Commands;

use App\Services\Pengguna\GabungAkunGanda;
use Illuminate\Console\Command;

/**
 * Menampilkan (dan bila diminta, menjalankan) penggabungan akun ganda —
 * email sama di beberapa gudang — menjadi satu akun. Migrasi
 * `email_pengguna_unik_global` menjalankan penggabungan yang sama secara
 * otomatis; perintah ini untuk melihat dulu apa yang akan terjadi di live
 * sebelum deploy.
 */
class GabungAkunGandaCommand extends Command
{
    protected $signature = 'pengguna:gabung-akun-ganda {--jalankan : Benar-benar menggabungkan (tanpa ini hanya menampilkan rencana)}';

    protected $description = 'Menggabungkan akun dengan email sama di beberapa gudang menjadi satu akun';

    public function handle(GabungAkunGanda $service): int
    {
        $rencana = $service->rencana();

        if ($rencana === []) {
            $this->info('Tidak ada akun ganda.');

            return self::SUCCESS;
        }

        $this->table(
            ['Email', 'Dipertahankan', 'Digabung ke dalamnya', 'Catatan'],
            array_map(fn (array $k) => [
                $k['email'],
                "#{$k['simpan']->id} {$k['simpan']->name} ({$k['simpan']->role})",
                implode(', ', array_map(fn ($u) => "#{$u->id} ({$u->role})", $k['gabung'])),
                $k['peran_berbeda'] ? 'PERAN BERBEDA — cek hasilnya' : '',
            ], $rencana),
        );

        if (! $this->option('jalankan')) {
            $this->line('Ini hanya rencana. Jalankan dengan --jalankan untuk menggabungkan.');

            return self::SUCCESS;
        }

        $hasil = $service->jalankan();

        $this->info("{$hasil['digabung']} akun digabungkan.");

        foreach ($hasil['gagal'] as $g) {
            $this->error("Gagal menggabungkan {$g['email']}: {$g['pesan']}");
        }

        return $hasil['gagal'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
