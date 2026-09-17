<?php

namespace App\Enums;

/**
 * Di mana sebuah posisi boleh absen. Inilah "kondisi absen" yang dipilih
 * per posisi di Setting Jam Kerja.
 */
enum LokasiAbsensi: string
{
    /** Di depot penempatan karyawan, dalam radius yang disetel. */
    case Depot = 'depot';

    /**
     * Di salah satu toko tanggungan karyawan (lewat akun penggunanya) yang
     * BELUM dikunjungi pada periode kunjungan berjalan — dipakai sales.
     */
    case TokoTanggungan = 'toko_tanggungan';

    /** Tanpa pembatasan jarak; titik GPS tetap direkam sebagai bukti. */
    case Bebas = 'bebas';

    public function label(): string
    {
        return __('hr.lokasi_absen_'.$this->value);
    }

    public function keterangan(): string
    {
        return __('hr.ket_lokasi_absen_'.$this->value);
    }
}
