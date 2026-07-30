<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produks', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->string('satuan', 20)->default('dus');
            // Stok tersedia = stok - stok_reserved. Stok baru benar-benar
            // dipotong saat pesanan selesai dikirim.
            $table->unsignedInteger('stok')->default(0);
            $table->unsignedInteger('stok_reserved')->default(0);
            $table->decimal('harga', 14, 2)->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produks');
    }
};
