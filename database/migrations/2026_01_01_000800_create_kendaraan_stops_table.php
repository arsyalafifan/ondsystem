<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu kunjungan toko dalam rute sebuah kendaraan.
        Schema::create('kendaraan_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kendaraan_id')->constrained('kendaraans')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('pesanan_id')->constrained('pesanans')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('toko_id')->constrained('tokos')->cascadeOnUpdate()->restrictOnDelete();

            $table->unsignedInteger('urutan');           // urutan kunjungan, mulai 1
            $table->unsignedInteger('total_dus')->default(0);
            $table->unsignedInteger('jarak_dari_sebelumnya_m')->default(0);
            $table->unsignedInteger('durasi_dari_sebelumnya_s')->default(0);
            $table->time('eta')->nullable();

            $table->enum('status', ['pending', 'selesai', 'gagal'])->default('pending')->index();
            $table->string('foto_nota')->nullable();
            $table->text('catatan_driver')->nullable();
            $table->timestamp('selesai_at')->nullable();

            $table->timestamps();

            // Satu pesanan hanya boleh ada di satu rute.
            $table->unique('pesanan_id');
            $table->index(['kendaraan_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendaraan_stops');
    }
};
