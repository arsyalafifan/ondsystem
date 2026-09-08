<?php

namespace App\Support;

/**
 * Status resolusi depot untuk permintaan/job yang sedang berjalan.
 * Lihat App\Support\DepotContext untuk cara nilai ini ditentukan.
 */
enum ModeDepot: string
{
    /** Pengguna biasa, atau superadmin yang memilih satu depot spesifik. */
    case Terkunci = 'terkunci';

    /** Hanya bisa dicapai superadmin — melihat data lintas semua depot sekaligus. */
    case SemuaDepot = 'semua_depot';

    /** Belum ada konteks depot yang ditetapkan sama sekali. */
    case BelumDitentukan = 'belum_ditentukan';
}
