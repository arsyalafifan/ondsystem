<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Armada: katalog unit kendaraan fisik untuk SEMUA gudang (sama
 * seperti Master Freezer — lihat migrasi create_freezers_table dan
 * jadikan_master_freezer_dan_idn_toko_global) — plat, jenis unit, dan
 * status perawatannya.
 *
 * BELUM ditautkan ke routing sama sekali — App\Models\Kendaraan ("Mobil 1",
 * "Mobil 2", dst.) tetap dibentuk mesin routing apa adanya sampai data di
 * sini lengkap. Lihat komentar di App\Models\Armada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armadas', function (Blueprint $table) {
            $table->id();
            $table->string('plat', 20);
            $table->string('jenis', 20);
            $table->string('status', 20)->default('normal');
            $table->timestamps();

            $table->unique('plat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armadas');
    }
};
