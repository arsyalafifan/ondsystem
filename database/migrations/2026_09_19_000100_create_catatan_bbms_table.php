<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat kondisi kendaraan (KM + bahan bakar): wajib sekali saat berangkat
 * dan sekali saat kembali ke gudang (App\Enums\JenisCatatanBbm), plus nol
 * atau lebih kejadian pengisian bahan bakar di jalan — makanya satu baris
 * per KEJADIAN, bukan satu baris per kendaraan, mengikuti pola yang sama
 * dengan `absensis` (lihat docblock migrasinya).
 *
 * `foto` wajib untuk ketiga jenis (speedometer untuk berangkat/kembali,
 * struk/bukti untuk pengisian). `foto_sebelum`/`foto_sesudah` hanya
 * terisi untuk pengisian, dan sifatnya opsional di sana.
 *
 * BerDepot seperti `kendaraan_stops` — anak `kendaraans` yang juga perlu
 * bisa dikueri langsung ter-scope per depot untuk layar Monitoring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_bbms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('kendaraan_id')->constrained('kendaraans')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('jenis', 20);

            $table->string('foto');
            $table->string('foto_sebelum')->nullable();
            $table->string('foto_sesudah')->nullable();

            $table->unsignedInteger('km')->nullable();
            $table->string('level_bbm', 5)->nullable();

            $table->decimal('liter', 8, 2)->nullable();
            $table->decimal('biaya', 12, 2)->nullable();

            $table->text('catatan')->nullable();
            $table->foreignId('dicatat_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            $table->timestamps();

            $table->index(['kendaraan_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_bbms');
    }
};
