<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lama istirahat, dibutuhkan untuk menghitung izin setengah hari: separuh
 * jam kerja BERSIH (jam pulang − jam masuk − istirahat). Tanpa kolom ini,
 * izin ½ hari posisi 08:00–17:00 akan dihitung 4,5 jam alih-alih 4 jam.
 *
 * Di shift nullable = ikut posisinya, sama seperti jam masuk/pulang shift
 * menimpa jam posisi. Bawaan 60 menit supaya posisi yang sudah ada langsung
 * punya nilai wajar tanpa perlu disunting satu per satu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posisis', function (Blueprint $table) {
            $table->unsignedSmallInteger('durasi_istirahat_menit')->default(60)->after('jam_pulang');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('durasi_istirahat_menit')->nullable()->after('jam_pulang');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', fn (Blueprint $table) => $table->dropColumn('durasi_istirahat_menit'));
        Schema::table('posisis', fn (Blueprint $table) => $table->dropColumn('durasi_istirahat_menit'));
    }
};
