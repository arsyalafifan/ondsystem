<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu batch = satu kali admin menekan "Generate Routing".
        // Batch menampung beberapa kendaraan sekaligus.
        Schema::create('routing_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->date('tanggal')->index();
            $table->enum('status', ['draft', 'disetujui', 'dibatalkan'])->default('draft')->index();

            $table->unsignedInteger('total_kendaraan')->default(0);
            $table->unsignedInteger('total_toko')->default(0);
            $table->unsignedInteger('total_dus')->default(0);
            $table->unsignedInteger('total_jarak_m')->default(0);
            $table->unsignedInteger('total_durasi_s')->default(0);

            // Parameter yang dipakai saat generate, disimpan agar hasil bisa
            // ditelusuri kalau aturan berubah di kemudian hari.
            $table->unsignedInteger('max_toko')->default(25);
            $table->unsignedInteger('max_dus')->default(220);
            $table->string('sumber_jarak', 20)->default('osrm');

            $table->foreignId('dibuat_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_batches');
    }
};
