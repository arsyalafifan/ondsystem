<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanans')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('jumlah_dus');
            // Harga disalin saat pesanan dibuat agar riwayat tidak ikut berubah
            // ketika harga produk diperbarui.
            $table->decimal('harga_satuan', 14, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['pesanan_id', 'produk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_items');
    }
};
