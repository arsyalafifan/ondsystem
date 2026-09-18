<?php

namespace App\Models;

use App\Enums\ModePersetujuanIzin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Setting persetujuan izin & lembur (plus batas lembur per hari) — satu
 * baris global. Lihat migrasi
 * `add_atasan_dan_pengaturan_izin` dan App\Services\Izin\ApproverIzin.
 */
#[Fillable(['mode', 'approver_peran', 'approver_pengguna', 'maks_lembur_menit', 'diubah_oleh'])]
class PengaturanIzin extends Model
{
    protected function casts(): array
    {
        return [
            'mode' => ModePersetujuanIzin::class,
            'approver_peran' => 'array',
            'approver_pengguna' => 'array',
            'maks_lembur_menit' => 'integer',
        ];
    }

    /** Baris setting-nya; dibuat dengan nilai bawaan bila belum ada. */
    public static function ambil(): self
    {
        return self::query()->first() ?? self::create([
            'mode' => ModePersetujuanIzin::ApproverUmum,
            'approver_peran' => ['hr'],
            'approver_pengguna' => [],
            'maks_lembur_menit' => 60,
        ]);
    }

    /** @return list<int> */
    public function idPengguna(): array
    {
        return array_values(array_map('intval', $this->approver_pengguna ?? []));
    }

    /** @return list<string> */
    public function peran(): array
    {
        return array_values($this->approver_peran ?? []);
    }
}
