<?php

namespace App\Support;

/**
 * Aplikasi mana yang sedang dibuka di sidebar multi-aplikasi (O&D System /
 * HR System / User Admin / Accounting).
 *
 * Terlepas dari `DepotContext` yang menyimpan pilihannya di sesi, "aplikasi
 * aktif" di sini SENGAJA murni ditentukan dari nama rute yang sedang
 * dibuka — tidak ada state tersimpan. Berpindah aplikasi karena itu cukup
 * berupa tautan biasa ke rute beranda aplikasi tujuan, bukan aksi yang
 * menulis apa pun.
 */
final class AplikasiSaatIni
{
    public static function hr(): bool
    {
        return request()->routeIs('hr.*');
    }

    public static function userAdmin(): bool
    {
        return request()->routeIs(['pengguna.*', 'depot.*']);
    }
}
