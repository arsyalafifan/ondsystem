<?php

namespace App\Services\Pengguna;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Menggabungkan akun ganda — satu orang yang dulu dibuatkan akun terpisah
 * untuk tiap gudang (email sama, beda gudang) — menjadi satu akun dengan
 * akses ke semua gudang asalnya.
 *
 * Akun yang dipertahankan: superadmin bila ada di kelompok itu (supaya
 * aksesnya tidak turun), selain itu akun tertua. Seluruh riwayat akun lain
 * (pesanan, kunjungan, penugasan, kendaraan, dst.) dipindahkan ke akun itu,
 * lalu akun lainnya dihapus. Kata sandi & peran yang berlaku adalah milik
 * akun yang dipertahankan — perbedaan peran dilaporkan supaya bisa dicek.
 *
 * Kolom yang menunjuk ke `users` DITEMUKAN dari foreign key di skema, bukan
 * daftar tetap — tabel baru yang kelak menunjuk ke `users` otomatis ikut
 * dipindahkan tanpa perlu ingat memperbarui kelas ini.
 *
 * Tiap kelompok dikerjakan dalam transaksinya sendiri: satu kelompok yang
 * gagal tidak ikut membatalkan kelompok lain, dan dilaporkan.
 */
class GabungAkunGanda
{
    /**
     * Kelompok email kembar beserta akun yang akan dipertahankan/digabung,
     * tanpa mengubah apa pun.
     *
     * @return list<array{email: string, simpan: object, gabung: list<object>, peran_berbeda: bool}>
     */
    public function rencana(): array
    {
        $kembar = DB::table('users')
            ->select(DB::raw('LOWER(email) as email_kecil'))
            ->groupBy(DB::raw('LOWER(email)'))
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email_kecil');

        return $kembar->map(function (string $email): array {
            $akun = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderByRaw("CASE WHEN role = 'superadmin' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'role', 'depot_id', 'aktif']);

            return [
                'email' => $email,
                'simpan' => $akun->first(),
                'gabung' => $akun->slice(1)->values()->all(),
                'peran_berbeda' => $akun->pluck('role')->unique()->count() > 1,
            ];
        })->values()->all();
    }

    /**
     * @return array{digabung: int, gagal: list<array{email: string, pesan: string}>, kelompok: list<array<string, mixed>>}
     */
    public function jalankan(): array
    {
        $rencana = $this->rencana();
        $kolomPengguna = $this->kolomYangMenunjukPengguna();
        $digabung = 0;
        $gagal = [];

        foreach ($rencana as $kelompok) {
            try {
                DB::transaction(fn () => $this->gabungkan($kelompok['simpan'], $kelompok['gabung'], $kolomPengguna));
                $digabung += count($kelompok['gabung']);
            } catch (Throwable $e) {
                $gagal[] = ['email' => $kelompok['email'], 'pesan' => $e->getMessage()];
            }
        }

        return ['digabung' => $digabung, 'gagal' => $gagal, 'kelompok' => $rencana];
    }

    /**
     * @param  list<object>  $gabung
     * @param  Collection<int, array{tabel: string, kolom: string}>  $kolomPengguna
     */
    private function gabungkan(object $simpan, array $gabung, Collection $kolomPengguna): void
    {
        $idLama = array_map(fn (object $u) => $u->id, $gabung);

        // Akses gudang = gabungan akses semua akunnya, termasuk gudang
        // default akun-akun lama.
        $depotIds = DB::table('depot_user')->whereIn('user_id', [$simpan->id, ...$idLama])->pluck('depot_id')
            ->merge(collect([$simpan, ...$gabung])->pluck('depot_id')->filter())
            ->unique();

        foreach ($depotIds as $depotId) {
            DB::table('depot_user')->insertOrIgnore([
                'depot_id' => $depotId,
                'user_id' => $simpan->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('depot_user')->whereIn('user_id', $idLama)->delete();

        // Satu orang hanya bisa punya satu profil karyawan (user_id unik).
        // Kalau akun yang dipertahankan belum tertaut, profil pertama milik
        // akun lama yang dipindahkan; sisanya dilepas tautannya.
        $sudahTertaut = DB::table('karyawans')->where('user_id', $simpan->id)->exists();

        foreach (DB::table('karyawans')->whereIn('user_id', $idLama)->orderBy('id')->pluck('id') as $karyawanId) {
            DB::table('karyawans')->where('id', $karyawanId)->update(['user_id' => $sudahTertaut ? null : $simpan->id]);
            $sudahTertaut = true;
        }

        foreach ($kolomPengguna as ['tabel' => $tabel, 'kolom' => $kolom]) {
            DB::table($tabel)->whereIn($kolom, $idLama)->update([$kolom => $simpan->id]);
        }

        DB::table('sessions')->whereIn('user_id', $idLama)->delete();

        // Tetap bisa masuk selama salah satu akunnya masih aktif.
        if (collect($gabung)->contains(fn (object $u) => (bool) $u->aktif)) {
            DB::table('users')->where('id', $simpan->id)->update(['aktif' => true]);
        }

        DB::table('users')->whereIn('id', $idLama)->delete();
    }

    /** @return Collection<int, array{tabel: string, kolom: string}> */
    private function kolomYangMenunjukPengguna(): Collection
    {
        $dikecualikan = ['depot_user', 'karyawans'];

        return collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $tabel) => in_array($tabel, $dikecualikan, true))
            ->flatMap(fn (string $tabel) => collect(Schema::getForeignKeys($tabel))
                ->filter(fn (array $fk) => $fk['foreign_table'] === 'users')
                ->map(function (array $fk) use ($tabel): array {
                    if (count($fk['columns']) !== 1) {
                        throw new RuntimeException("Foreign key majemuk ke users di {$tabel} belum didukung.");
                    }

                    return ['tabel' => $tabel, 'kolom' => $fk['columns'][0]];
                }))
            ->values();
    }
}
