<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengantaran satu pesanan oleh rider (motor), bukan oleh rute kendaraan —
 * untuk toko yang jaraknya masuk Depot::radius_rider_km. Tidak ada
 * konsep urutan/rute di sini: satu baris langsung "milik" satu pesanan,
 * mulai dari tersedia di pool gudangnya sampai rider menyelesaikannya.
 *
 * `status` SENGAJA `string`, bukan `$table->enum(...)` — pelajaran dari
 * stok_mutasis.tipe yang harus dimigrasi ulang karena ENUM database
 * menolak nilai baru ("Data truncated"). Validitas nilai cukup dijaga di
 * level aplikasi lewat cast App\Enums\StatusPengantaranRider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengantaran_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained('depots')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('pesanan_id')->constrained('pesanans')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('status', 20)->default('tersedia')->index();

            $table->foreignId('ditandai_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('ditandai_at');

            $table->foreignId('rider_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('diambil_at')->nullable();
            $table->timestamp('dilepas_at')->nullable();

            $table->string('foto_nota')->nullable();
            $table->text('catatan_rider')->nullable();
            $table->timestamp('selesai_at')->nullable();

            $table->timestamps();

            // Satu pesanan hanya boleh satu kali ditandai untuk rider.
            $table->unique('pesanan_id');
            $table->index(['depot_id', 'status']);
            $table->index(['rider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengantaran_riders');
    }
};
