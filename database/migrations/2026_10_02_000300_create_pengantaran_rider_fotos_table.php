<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti pengiriman rider — mirip persis `stop_fotos` (lihat docblock
 * migrasinya), bedanya cuma induknya `pengantaran_riders` bukan
 * `kendaraan_stops`. `jenis` memakai nilai App\Enums\JenisBuktiPengiriman
 * yang SAMA dengan driver, bukan daftar jenis baru — rider dan driver
 * berbagi aturan bukti yang identik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengantaran_rider_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengantaran_rider_id')->constrained('pengantaran_riders')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('jenis', 50);
            $table->string('path');
            $table->text('catatan')->nullable();

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['pengantaran_rider_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengantaran_rider_fotos');
    }
};
