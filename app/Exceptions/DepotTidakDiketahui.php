<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar begitu ada kode yang butuh konteks depot (baca/tulis model
 * yang pakai App\Models\Concerns\BerDepot) tapi konteksnya belum
 * ditetapkan sama sekali (App\Support\ModeDepot::BelumDitentukan).
 *
 * Sengaja jadi exception tersendiri, bukan RuntimeException polos —
 * supaya gampang di-grep di log dan langsung ketahuan penyebabnya begitu
 * dilihat, tanpa perlu menelusuri stack trace panjang. Ini juga jawaban
 * utama untuk kebutuhan "gagal keras, bukan diam-diam bocor data":
 * query yang lupa menyiapkan konteks depot akan meledak di sini, bukan
 * mengembalikan data tanpa filter.
 */
class DepotTidakDiketahui extends RuntimeException
{
    public static function saatQuery(string $model): self
    {
        return new self("Konteks depot belum ditetapkan saat mengakses model {$model}.");
    }

    public static function saatMenulis(string $model): self
    {
        return new self("Konteks depot belum ditetapkan saat membuat baris baru {$model}.");
    }
}
