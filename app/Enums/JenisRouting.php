<?php

namespace App\Enums;

/**
 * Tiga antrean routing yang berjalan berdampingan tapi tidak pernah bercampur.
 *
 * `Reguler` mengantar dus es krim ke mitra yang sudah jalan; `Noo` mengantar
 * FREEZER ke calon mitra baru yang baru disetujui; `Tarik` mengambil freezer
 * KEMBALI dari toko yang berhenti jadi mitra — kebalikan arah dari `Noo`,
 * tapi kendalanya sama: muatannya unit (bukan dus), kapasitas mobilnya
 * mengikuti ukuran bak, dan pekerjaan driver di tokonya beda dari pengantaran
 * dus biasa — karena itu batch-nya dipisah sejak awal, bukan disaring
 * belakangan di tampilan.
 */
enum JenisRouting: string
{
    case Reguler = 'reguler';
    case Noo = 'noo';
    case Tarik = 'tarik';

    public function label(): string
    {
        return __('routing.jenis_'.$this->value);
    }
}
