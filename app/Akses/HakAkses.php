<?php

namespace App\Akses;

use App\Enums\CakupanData;
use App\Enums\PeranPengguna;
use App\Models\HakAksesPeran;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Menjawab "boleh / data mana" untuk satu pengguna, dari bawaan di
 * DaftarAkses ditimpa pengecualian di tabel `hak_akses_perans`.
 *
 * Superadmin selalu boleh semua dan selalu melihat semua data — tidak
 * pernah ikut diatur, supaya tidak ada yang bisa terkunci di luar sistem.
 */
final class HakAkses
{
    public const KUNCI_CACHE = 'hak_akses_peran';

    /** @var array<string, array<string, array{boleh: bool, cakupan: string}>>|null */
    private ?array $pengecualian = null;

    /** @return array<string, array<string, array{boleh: bool, cakupan: string}>> [peran][menu] */
    public function pengecualian(): array
    {
        return $this->pengecualian ??= $this->muat();
    }

    /**
     * Galat kueri (mis. kode baru sudah jalan tapi migrasinya belum) dianggap
     * "belum ada pengecualian" = akses bawaan, bukan halaman error. Hasil
     * yang gagal tidak ikut disimpan ke cache.
     */
    private function muat(): array
    {
        try {
            return Cache::rememberForever(self::KUNCI_CACHE, fn () => HakAksesPeran::query()
                ->get(['peran', 'menu', 'boleh', 'cakupan'])
                ->groupBy('peran')
                ->map(fn ($baris) => $baris->mapWithKeys(fn (HakAksesPeran $b) => [
                    $b->menu => ['boleh' => $b->boleh, 'cakupan' => $b->cakupan->value],
                ])->all())
                ->all());
        } catch (QueryException) {
            return [];
        }
    }

    public function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
        $this->pengecualian = null;
    }

    public function bawaan(PeranPengguna $peran, string $menu): bool
    {
        return in_array($peran->value, DaftarAkses::menu($menu)['peran'] ?? [], true);
    }

    public function bolehPeran(PeranPengguna $peran, string $menu): bool
    {
        if (DaftarAkses::menu($menu) === null) {
            return false;
        }

        if ($peran === PeranPengguna::Superadmin) {
            return true;
        }

        return $this->pengecualian()[$peran->value][$menu]['boleh'] ?? $this->bawaan($peran, $menu);
    }

    public function cakupanPeran(PeranPengguna $peran, string $menu): CakupanData
    {
        if ($peran === PeranPengguna::Superadmin || ! (DaftarAkses::menu($menu)['cakupan_data'] ?? false)) {
            return CakupanData::Semua;
        }

        return CakupanData::tryFrom($this->pengecualian()[$peran->value][$menu]['cakupan'] ?? '') ?? CakupanData::Semua;
    }

    public function boleh(User $pengguna, string $menu): bool
    {
        return $this->bolehPeran($pengguna->role, $menu);
    }

    /** @param list<string> $menu */
    public function bolehSalahSatu(User $pengguna, array $menu): bool
    {
        foreach ($menu as $kunci) {
            if ($this->boleh($pengguna, $kunci)) {
                return true;
            }
        }

        return false;
    }

    public function cakupan(User $pengguna, string $menu): CakupanData
    {
        return $this->cakupanPeran($pengguna->role, $menu);
    }

    /**
     * Apakah menu ditampilkan di sidebar & pemilih aplikasi. Sama dengan
     * boleh(), kecuali menu bertanda `tanpa_superadmin` tidak ditampilkan
     * untuk superadmin (walau rutenya tetap boleh ia buka).
     */
    public function tampil(User $pengguna, string $menu): bool
    {
        if ($pengguna->role === PeranPengguna::Superadmin && (DaftarAkses::menu($menu)['tanpa_superadmin'] ?? false)) {
            return false;
        }

        return $this->boleh($pengguna, $menu);
    }

    /** @return list<string> kunci aplikasi yang punya minimal satu menu yang tampil */
    public function aplikasiUntuk(User $pengguna): array
    {
        return array_values(array_filter(
            array_keys(DaftarAkses::APLIKASI),
            fn (string $aplikasi) => $this->ruteAplikasi($pengguna, $aplikasi) !== null,
        ));
    }

    /**
     * Pohon menu sidebar satu aplikasi, bentuknya sama seperti array menu
     * lama di layout: ['rute','label','ikon'] atau ['label','ikon','anak'].
     *
     * @return list<array<string, mixed>>
     */
    public function menuUntuk(User $pengguna, string $aplikasi): array
    {
        $peran = $pengguna->role->value;
        $pohon = [];
        $posisiGrup = [];

        foreach ($this->urutkan($peran, DaftarAkses::menuAplikasi($aplikasi)) as $kunci) {
            if (! $this->tampil($pengguna, $kunci)) {
                continue;
            }

            $menu = DaftarAkses::MENU[$kunci];
            $item = [
                'rute' => $menu['rute'],
                'label' => __($menu['label_peran'][$peran] ?? $menu['label']),
                'ikon' => $menu['ikon'],
            ];

            if (! isset($menu['grup'])) {
                $pohon[] = $item;

                continue;
            }

            if (! isset($posisiGrup[$menu['grup']])) {
                $grup = DaftarAkses::GRUP[$menu['grup']];
                $posisiGrup[$menu['grup']] = count($pohon);
                $pohon[] = ['label' => __($grup['label']), 'ikon' => $grup['ikon'], 'anak' => []];
            }

            $pohon[$posisiGrup[$menu['grup']]]['anak'][] = $item;
        }

        return $pohon;
    }

    /**
     * @param  list<string>  $kunci
     * @return list<string>
     */
    private function urutkan(string $peran, array $kunci): array
    {
        $khusus = array_flip(DaftarAkses::URUTAN_PERAN[$peran] ?? []);
        $bawaan = array_flip($kunci);

        usort($kunci, fn (string $a, string $b) => [$khusus[$a] ?? PHP_INT_MAX, $bawaan[$a]] <=> [$khusus[$b] ?? PHP_INT_MAX, $bawaan[$b]]);

        return $kunci;
    }

    /**
     * Aplikasi yang sedang dibuka: dari menu pemilik rute sekarang (lewat
     * middleware `akses:` rutenya). Rute di luar menu mana pun (mis. ganti
     * kata sandi) memakai aplikasi pertama milik pengguna.
     */
    public function aplikasiAktif(User $pengguna): string
    {
        foreach (request()->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'akses:')) {
                continue;
            }

            foreach (explode(',', substr($middleware, 6)) as $kunci) {
                if (($menu = DaftarAkses::menu($kunci)) !== null && $this->boleh($pengguna, $kunci)) {
                    return $menu['aplikasi'];
                }
            }
        }

        return $this->aplikasiUntuk($pengguna)[0] ?? array_key_first(DaftarAkses::APLIKASI);
    }

    /** Rute pertama yang tampil di satu aplikasi (beranda peran bila termasuk). */
    public function ruteAplikasi(User $pengguna, string $aplikasi): ?string
    {
        $utama = $pengguna->role->beranda();
        $pertama = null;

        foreach ($this->urutkan($pengguna->role->value, DaftarAkses::menuAplikasi($aplikasi)) as $kunci) {
            if (! $this->tampil($pengguna, $kunci)) {
                continue;
            }

            if (DaftarAkses::MENU[$kunci]['rute'] === $utama) {
                return $utama;
            }

            $pertama ??= DaftarAkses::MENU[$kunci]['rute'];
        }

        return $pertama;
    }

    /** Halaman awal setelah masuk: beranda bawaan peran selama masih boleh dibuka. */
    public function beranda(User $pengguna): string
    {
        foreach (DaftarAkses::MENU as $kunci => $menu) {
            if ($menu['rute'] === $pengguna->role->beranda() && $this->boleh($pengguna, $kunci)) {
                return $menu['rute'];
            }
        }

        foreach ($this->aplikasiUntuk($pengguna) as $aplikasi) {
            if (($rute = $this->ruteAplikasi($pengguna, $aplikasi)) !== null) {
                return $rute;
            }
        }

        return 'akun.kata-sandi';
    }
}
