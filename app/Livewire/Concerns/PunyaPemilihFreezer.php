<?php

namespace App\Livewire\Concerns;

use App\Models\Freezer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Dipasang di komponen mana pun yang perlu memilih IDN freezer: Master Toko
 * (tambah/sunting), Lengkapi Data Toko (sales), dan pemasangan freezer NOO
 * (driver). Nomor IDN SELALU bersumber dari Master Freezer — tidak ada lagi
 * yang boleh mengetik bebas — dipilih lewat <x-pilih-cari> yang daftar
 * pilihannya datang dari opsiFreezer() di bawah.
 *
 * `tokos.asset_id` TETAP kolom string biasa, BUKAN foreign key ke
 * `freezers.id` — mengikuti keputusan yang sudah dipatri lewat
 * Freezer::tokos() (dicocokkan lewat asset_id = idn). Yang berubah cuma
 * ATURANNYA: nilai baru harus cocok dengan idn yang benar-benar terdaftar
 * (dan aktif) di Master Freezer, sebelum masuk ke kolom itu.
 */
trait PunyaPemilihFreezer
{
    /** @return array<int, array{value: string, label: string}> */
    #[Computed]
    public function opsiFreezer(): array
    {
        return Freezer::aktif()->orderBy('idn')->get()
            ->map(fn (Freezer $f): array => ['value' => $f->idn, 'label' => $f->label])
            ->all();
    }

    /**
     * Aturan validasi untuk field IDN. $idnSebelum adalah nilai yang SUDAH
     * tersimpan di basis data sebelum penyuntingan ini (null untuk toko
     * baru) — kalau IDN yang dikirim SAMA dengan itu, pengecekan "harus ada
     * di Master Freezer" dilewati sama sekali. Itu yang membuat toko lama
     * dengan IDN tidak valid tetap bisa disimpan (field lainnya, mis. nama)
     * tanpa terhalang oleh IDN yang belum sempat diperbaiki — hanya IDN
     * yang BENAR-BENAR BARU/BERUBAH yang wajib cocok dengan master.
     *
     * @return array<int, mixed>
     */
    protected function aturanIdn(int $depotId, ?string $idnBaru, ?string $idnSebelum, bool $wajib = false): array
    {
        $aturan = [$wajib ? 'required' : 'nullable', 'string', 'max:40'];

        if ($idnBaru !== null && $idnBaru !== '' && $idnBaru !== $idnSebelum) {
            $aturan[] = Rule::exists('freezers', 'idn')->where('depot_id', $depotId)->where('aktif', true);
        }

        return $aturan;
    }

    protected function freezerUntukIdn(?string $idn): ?Freezer
    {
        return $idn === null || $idn === '' ? null : Freezer::where('idn', $idn)->first();
    }

    /**
     * IDN terisi tapi tidak cocok dengan freezer mana pun (aktif ataupun
     * tidak) di Master Freezer — data lama yang belum/tidak lagi valid.
     * Dipakai untuk menampilkan peringatan, BUKAN untuk memblokir simpan.
     */
    protected function idnTidakDikenal(?string $idn): bool
    {
        return $idn !== null && $idn !== '' && ! Freezer::query()->where('idn', $idn)->exists();
    }
}
