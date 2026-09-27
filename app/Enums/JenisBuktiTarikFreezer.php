<?php

namespace App\Enums;

/**
 * Bukti foto penarikan freezer, diambil driver saat freezernya benar-benar
 * lepas dari toko — jauh lebih ringkas dari App\Enums\JenisBuktiNoo karena
 * tidak ada data toko yang perlu dibuktikan lagi (tokonya sudah lama jadi
 * mitra, bukan calon baru): cukup foto freezernya sendiri dan foto nomor
 * IDN-nya sebagai bukti freezer yang diambil memang yang tercatat di sistem.
 */
enum JenisBuktiTarikFreezer: string
{
    case FotoFreezer = 'foto_freezer';
    case FotoIdn = 'foto_idn';

    public function label(): string
    {
        return __('tarik_freezer.bukti_'.$this->value);
    }

    public function petunjuk(): string
    {
        return __('tarik_freezer.petunjuk_bukti_'.$this->value);
    }

    public function ikon(): string
    {
        return match ($this) {
            self::FotoFreezer => 'cube',
            self::FotoIdn => 'qr-code',
        };
    }

    /**
     * Foto yang wajib dilengkapi driver sebelum penarikan bisa dituntaskan.
     *
     * @return list<self>
     */
    public static function wajibDriver(): array
    {
        return [self::FotoFreezer, self::FotoIdn];
    }
}
