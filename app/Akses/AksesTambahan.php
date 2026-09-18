<?php

namespace App\Akses;

use App\Models\User;

/**
 * Akses menu yang ditentukan DATA, bukan peran — mis. Persetujuan Izin
 * terbuka untuk siapa pun yang ditunjuk sebagai approver, walau perannya
 * sendiri tidak memberi akses. Dipasang lewat kunci `akses_tambahan` di
 * App\Akses\DaftarAkses; hanya MENAMBAH akses, tidak pernah mencabut.
 */
interface AksesTambahan
{
    public function bolehBukaMenu(User $pengguna): bool;
}
