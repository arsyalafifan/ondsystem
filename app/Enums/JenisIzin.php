<?php

namespace App\Enums;

/**
 * Jenis ketidakhadiran yang diajukan karyawan. Kebijakan tiap jenis tinggal
 * di sini — sistem cuti nanti cukup menambah `case Cuti` beserta
 * kebijakannya (dibayar, memotong saldo), tanpa mengubah tabel.
 */
enum JenisIzin: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';

    public function label(): string
    {
        return __('izin.jenis_'.$this->value);
    }

    /**
     * Snapshot untuk payroll yang menyusul. Izin = unpaid leave (keputusan
     * pengguna). Sakit dibayar mengikuti UU Ketenagakerjaan (pekerja sakit
     * tetap berhak upah) — ubah di sini bila kebijakan perusahaan berbeda.
     */
    public function dibayar(): bool
    {
        return $this === self::Sakit;
    }

    public function bolehSetengahHari(): bool
    {
        return $this === self::Izin;
    }

    /**
     * Berapa hari ke belakang tanggal mulai boleh diajukan. Izin direncanakan
     * (paling lambat hari H), sakit sering baru bisa dilaporkan sesudahnya.
     */
    public function batasMundurHari(): int
    {
        return match ($this) {
            self::Izin => 0,
            self::Sakit => 3,
        };
    }
}
