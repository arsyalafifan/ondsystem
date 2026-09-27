<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun non-superadmin bisa diberi akses ke SEMUA gudang sekaligus (lihat
 * User::bisaAksesSemuaDepot()) — dipakai untuk peran yang perlu memantau
 * lintas gudang tanpa perlu jadi superadmin. Berbeda dari superadmin,
 * akun ini TIDAK otomatis mendapat menu "User Admin" (Kelola Pengguna,
 * Kelola Depot, Hak Akses) — itu tetap dicek lewat isSuperadmin() murni
 * di App\Akses\DaftarAkses, sama sekali tidak menyentuh kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('akses_semua_depot')->default(false)->after('depot_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('akses_semua_depot');
        });
    }
};
