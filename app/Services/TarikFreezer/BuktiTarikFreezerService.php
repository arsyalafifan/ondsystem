<?php

namespace App\Services\TarikFreezer;

use App\Enums\JenisBuktiTarikFreezer;
use App\Models\TarikFreezer;
use App\Models\User;
use App\Services\Kunjungan\GambarDataUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Menyiapkan dan menyimpan bukti foto Tarik Freezer — bentuknya sama persis
 * dengan App\Services\Noo\BuktiNooService, lihat docblock di sana.
 */
class BuktiTarikFreezerService
{
    public const DISK = 'local';

    public function __construct(private readonly PenandaFotoTarikFreezer $penanda) {}

    /**
     * @return array{jenis: JenisBuktiTarikFreezer, path: string}
     *
     * @throws RuntimeException bila gambarnya tidak bisa dibaca
     */
    public function simpanFoto(TarikFreezer $tarikFreezer, JenisBuktiTarikFreezer $jenis, string $gambarDataUrl, User $pengambil): array
    {
        $isi = GambarDataUrl::dekode($gambarDataUrl);

        if ($isi === null) {
            throw new RuntimeException(__('kunjungan.galat_gambar_rusak'));
        }

        $foto = $this->penanda->simpan($isi, $tarikFreezer, $jenis, $pengambil, CarbonImmutable::now());

        return ['jenis' => $jenis, 'path' => $foto['path']];
    }

    /** @param  array<int, array{jenis: JenisBuktiTarikFreezer, path: string}>  $bukti */
    public function simpanSemua(TarikFreezer $tarikFreezer, array $bukti): void
    {
        foreach ($bukti as $satu) {
            $tarikFreezer->fotos()->updateOrCreate(
                ['jenis' => $satu['jenis']],
                ['path' => $satu['path']],
            );
        }
    }

    /** @param  array<int, array{jenis: JenisBuktiTarikFreezer, path: string}>  $bukti */
    public function hapusBerkas(array $bukti): void
    {
        if ($bukti === []) {
            return;
        }

        Storage::disk(self::DISK)->delete(array_column($bukti, 'path'));
    }
}
