<?php

namespace App\Livewire\HakAkses;

use App\Akses\DaftarAkses;
use App\Akses\HakAkses;
use App\Enums\CakupanData;
use App\Enums\PeranPengguna;
use App\Models\HakAksesPeran;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Hak Akses Management: matriks peran × menu (boleh akses? data mana?).
 *
 * Yang disimpan hanya yang BERBEDA dari bawaan di App\Akses\DaftarAkses —
 * mengembalikan sebuah centang ke bawaannya menghapus barisnya, jadi tabel
 * selalu berisi pengecualian saja. Superadmin tidak pernah muncul di sini:
 * ia selalu punya akses penuh.
 */
class KelolaHakAkses extends Component
{
    #[Url(as: 'peran')]
    public string $peran = 'admin';

    /**
     * Isian formulir per menu. Titik di kunci menu diganti titik dua, karena
     * Livewire membaca titik di wire:model sebagai tingkat array.
     *
     * @var array<string, array{boleh: bool, cakupan: string}>
     */
    public array $isian = [];

    /** @return list<string> */
    public static function peranBisaDiatur(): array
    {
        return array_values(array_map(
            fn (PeranPengguna $p) => $p->value,
            array_filter(PeranPengguna::cases(), fn (PeranPengguna $p) => $p !== PeranPengguna::Superadmin),
        ));
    }

    public static function kunciIsian(string $menu): string
    {
        return str_replace('.', ':', $menu);
    }

    public function mount(): void
    {
        if (! in_array($this->peran, self::peranBisaDiatur(), true)) {
            $this->peran = self::peranBisaDiatur()[0];
        }

        $this->muat();
    }

    public function pilihPeran(string $peran): void
    {
        abort_unless(in_array($peran, self::peranBisaDiatur(), true), 422);

        $this->peran = $peran;
        $this->muat();
    }

    public function aturSemua(string $aplikasi, bool $boleh): void
    {
        foreach (DaftarAkses::menuAplikasi($aplikasi) as $menu) {
            $this->isian[self::kunciIsian($menu)]['boleh'] = $boleh;
        }
    }

    public function bawaan(string $menu): bool
    {
        return app(HakAkses::class)->bawaan(PeranPengguna::from($this->peran), $menu);
    }

    public function diubah(string $menu): bool
    {
        $isian = $this->isian[self::kunciIsian($menu)] ?? ['boleh' => false, 'cakupan' => 'semua'];

        return (bool) $isian['boleh'] !== $this->bawaan($menu)
            || ((bool) $isian['boleh'] && ($isian['cakupan'] ?? 'semua') !== CakupanData::Semua->value);
    }

    /** @return list<array{kunci: string, label: string, ikon: string, baris: list<array<string, mixed>>}> */
    #[Computed]
    public function struktur(): array
    {
        $hasil = [];

        foreach (DaftarAkses::APLIKASI as $kunci => $aplikasi) {
            $baris = array_map(function (string $menu): array {
                $m = DaftarAkses::MENU[$menu];

                return [
                    'menu' => $menu,
                    'isian' => self::kunciIsian($menu),
                    'label' => __($m['label']),
                    'grup' => isset($m['grup']) ? __(DaftarAkses::GRUP[$m['grup']]['label']) : null,
                    'ikon' => $m['ikon'],
                    'cakupan_data' => $m['cakupan_data'] ?? false,
                ];
            }, DaftarAkses::menuAplikasi($kunci));

            $hasil[] = ['kunci' => $kunci, 'label' => __($aplikasi['label']), 'ikon' => $aplikasi['ikon'], 'baris' => $baris];
        }

        return $hasil;
    }

    public function simpan(): void
    {
        $peran = $this->pastikanBoleh();
        $akses = app(HakAkses::class);

        DB::transaction(function () use ($peran, $akses): void {
            foreach (DaftarAkses::MENU as $menu => $definisi) {
                $isian = $this->isian[self::kunciIsian($menu)] ?? null;

                if ($isian === null) {
                    continue;
                }

                $boleh = (bool) $isian['boleh'];
                $cakupan = $boleh && ($definisi['cakupan_data'] ?? false)
                    ? (CakupanData::tryFrom((string) ($isian['cakupan'] ?? '')) ?? CakupanData::Semua)
                    : CakupanData::Semua;

                if ($boleh === $akses->bawaan($peran, $menu) && $cakupan === CakupanData::Semua) {
                    HakAksesPeran::query()->where('peran', $peran->value)->where('menu', $menu)->delete();

                    continue;
                }

                HakAksesPeran::updateOrCreate(
                    ['peran' => $peran->value, 'menu' => $menu],
                    ['boleh' => $boleh, 'cakupan' => $cakupan, 'diubah_oleh' => auth()->id()],
                );
            }
        });

        $akses->lupakan();
        $this->muat();
        $this->dispatch('notifikasi', pesan: __('hak_akses.notif_tersimpan', ['peran' => $peran->label()]));
    }

    public function kembalikanBawaan(): void
    {
        $peran = $this->pastikanBoleh();

        HakAksesPeran::query()->where('peran', $peran->value)->delete();

        app(HakAkses::class)->lupakan();
        $this->muat();
        $this->dispatch('notifikasi', pesan: __('hak_akses.notif_dikembalikan', ['peran' => $peran->label()]));
    }

    private function pastikanBoleh(): PeranPengguna
    {
        abort_unless(app(HakAkses::class)->boleh(auth()->user(), 'user_admin.hak_akses'), 403);
        abort_unless(in_array($this->peran, self::peranBisaDiatur(), true), 422);

        return PeranPengguna::from($this->peran);
    }

    private function muat(): void
    {
        $akses = app(HakAkses::class);
        $peran = PeranPengguna::from($this->peran);

        $this->isian = [];

        foreach (array_keys(DaftarAkses::MENU) as $menu) {
            $this->isian[self::kunciIsian($menu)] = [
                'boleh' => $akses->bolehPeran($peran, $menu),
                'cakupan' => $akses->cakupanPeran($peran, $menu)->value,
            ];
        }
    }

    public function render()
    {
        return view('livewire.hak-akses.kelola-hak-akses')->title(__('hak_akses.judul'));
    }
}
