<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokos', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->foreignId('wilayah_id')->constrained('wilayahs')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('alamat');
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kota')->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('nama_pemilik')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Asal koordinat: hasil geocoding otomatis, ditaruh manual di peta,
            // atau belum ada sama sekali. Toko tanpa koordinat tidak bisa
            // ikut proses routing.
            $table->enum('sumber_koordinat', ['belum', 'geocode', 'manual'])->default('belum')->index();
            $table->timestamp('geocoded_at')->nullable();
            $table->text('geocode_catatan')->nullable();

            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['wilayah_id', 'aktif']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokos');
    }
};
