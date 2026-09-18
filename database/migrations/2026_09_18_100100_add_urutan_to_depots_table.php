<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor urut gudang. Gudang urutan pertama jadi tujuan bawaan pengguna yang
 * belum disetel gudang default-nya, dan urutan ini juga dipakai di setiap
 * daftar/pemilih gudang.
 *
 * Diisi mengikuti urutan nama saat ini — urutan yang selama ini tampil di
 * semua daftar gudang — supaya tidak ada yang tiba-tiba berpindah posisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->unsignedInteger('urutan')->default(0)->after('nama');
        });

        foreach (DB::table('depots')->orderBy('nama')->orderBy('id')->pluck('id')->values() as $i => $id) {
            DB::table('depots')->where('id', $id)->update(['urutan' => $i + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
