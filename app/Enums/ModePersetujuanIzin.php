<?php

namespace App\Enums;

enum ModePersetujuanIzin: string
{
    /** Siapa pun di daftar approver (peran/orang) boleh memutuskan. */
    case ApproverUmum = 'approver_umum';

    /** Atasan langsung karyawan; tanpa atasan yang sah → approver umum. */
    case Atasan = 'atasan';

    public function label(): string
    {
        return __('izin.mode_'.$this->value);
    }

    public function keterangan(): string
    {
        return __('izin.ket_mode_'.$this->value);
    }
}
