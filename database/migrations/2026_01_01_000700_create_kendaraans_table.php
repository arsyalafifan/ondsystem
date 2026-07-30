<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Mobil 1", "Mobil 2", dst. Dibentuk oleh mesin routing, bukan
        // master data armada. Satu kendaraan = satu rute untuk satu hari.
        Schema::create('kendaraans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_batch_id')->constrained('routing_batches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('wilayah_id')->nullable()->constrained('wilayahs')->nullOnDelete();

            $table->unsignedInteger('nomor');           // 1, 2, 3, ...
            $table->string('nama', 50);                 // "Mobil 1"
            $table->string('warna', 10)->default('#2563eb');

            $table->unsignedInteger('total_toko')->default(0);
            $table->unsignedInteger('total_dus')->default(0);
            $table->unsignedInteger('total_jarak_m')->default(0);
            $table->unsignedInteger('total_durasi_s')->default(0);
            $table->time('jam_berangkat')->nullable();
            $table->time('estimasi_selesai')->nullable();

            // Polyline terenkode dari OSRM untuk menggambar rute di peta.
            $table->longText('geometry')->nullable();

            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diambil_at')->nullable();
            $table->enum('status', ['draft', 'siap', 'jalan', 'selesai'])->default('draft')->index();

            $table->timestamps();

            $table->unique(['routing_batch_id', 'nomor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendaraans');
    }
};
