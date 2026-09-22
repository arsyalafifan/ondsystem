<?php

namespace App\Enums;

/**
 * Dua antrean routing yang berjalan berdampingan tapi tidak pernah bercampur.
 *
 * `Reguler` mengantar dus es krim ke mitra yang sudah jalan; `Noo` mengantar
 * FREEZER ke calon mitra baru yang baru disetujui. Muatannya beda satuan
 * (dus vs unit), kapasitas mobilnya beda, dan pekerjaan driver di tokonya
 * pun beda — karena itu batch-nya dipisah sejak awal, bukan disaring
 * belakangan di tampilan.
 */
enum JenisRouting: string
{
    case Reguler = 'reguler';
    case Noo = 'noo';

    public function label(): string
    {
        return __('routing.jenis_'.$this->value);
    }
}
