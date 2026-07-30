<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan setiap pergerakan stok supaya selisih gudang bisa ditelusuri.
        Schema::create('stok_mutasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('pesanan_id')->nullable()->constrained('pesanans')->nullOnDelete();
            $table->enum('tipe', ['reserve', 'release', 'keluar', 'masuk', 'penyesuaian']);
            $table->integer('jumlah');              // positif menambah, negatif mengurangi
            $table->integer('stok_sesudah')->default(0);
            $table->integer('reserved_sesudah')->default(0);
            $table->string('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['produk_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_mutasis');
    }
};
