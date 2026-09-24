<?php

namespace App\Livewire\Concerns;

use App\Models\Freezer;
use App\Models\Scopes\DepotScope;
use App\Models\Toko;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Pemilih IDN yang bersumber dari Master Freezer (katalog untuk SEMUA
 * gudang) — dipakai Master Toko, Lengkapi Data Toko, dan pemasangan
 * freezer NOO oleh driver. Satu IDN hanya boleh dipasang di satu toko di
 * seluruh sistem, apa pun gudangnya.
 */
trait PunyaPemilihFreezer
{
    /**
     * Freezer aktif yang masih kosong, ditambah IDN yang sedang dipegang
     * toko ini (kalau ada) supaya pilihan yang sudah tersimpan tetap tampil.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function opsiFreezer(): array
    {
        $milikSendiri = $this->idnTersimpan();

        return Freezer::aktif()
            ->where(fn ($q) => $q->tersedia()->when($milikSendiri, fn ($q) => $q->orWhere('idn', $milikSendiri)))
            ->orderBy('idn')->get()
            ->map(fn (Freezer $f): array => ['value' => $f->idn, 'label' => $f->label])
            ->all();
    }

    /** IDN yang saat ini tersimpan di toko yang sedang dikerjakan (null kalau belum ada). */
    protected function idnTersimpan(): ?string
    {
        return null;
    }

    /**
     * Aturan validasi IDN: wajib terdaftar & aktif di Master Freezer, dan
     * belum dipegang toko lain di gudang mana pun. Keduanya cuma dicek
     * kalau IDN-nya BERUBAH dari yang sudah tersimpan — IDN lama yang sudah
     * tidak ada di Master Freezer tidak menghalangi penyimpanan kolom lain.
     *
     * @return array<int, mixed>
     */
    protected function aturanIdn(?string $idnBaru, ?string $idnSebelum, ?int $tokoId, bool $wajib = false): array
    {
        $aturan = [$wajib ? 'required' : 'nullable', 'string', 'max:40'];

        if ($idnBaru !== null && $idnBaru !== '' && $idnBaru !== $idnSebelum) {
            $aturan[] = Rule::exists('freezers', 'idn')->where('aktif', true);
            $aturan[] = function (string $atribut, mixed $nilai, Closure $gagal) use ($tokoId): void {
                $pemegang = Toko::query()->withoutGlobalScope(DepotScope::class)
                    ->with('depot:id,nama')
                    ->where('asset_id', $nilai)
                    ->when($tokoId !== null, fn ($q) => $q->whereKeyNot($tokoId))
                    ->first(['id', 'nama', 'depot_id']);

                if ($pemegang !== null) {
                    $gagal(__('toko.galat_freezer_dipakai', [
                        'toko' => $pemegang->nama,
                        'gudang' => $pemegang->depot?->nama ?? '—',
                    ]));
                }
            };
        }

        return $aturan;
    }

    protected function freezerUntukIdn(?string $idn): ?Freezer
    {
        return $idn === null || $idn === '' ? null : Freezer::where('idn', $idn)->first();
    }

    protected function idnTidakDikenal(?string $idn): bool
    {
        return $idn !== null && $idn !== '' && ! Freezer::query()->where('idn', $idn)->exists();
    }
}
