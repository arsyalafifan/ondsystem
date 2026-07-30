<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanans', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->foreignId('toko_id')->constrained('tokos')->cascadeOnUpdate()->restrictOnDelete();
            // Wilayah disalin dari toko saat pesanan dibuat supaya
            // pengelompokan routing tidak berubah kalau toko dipindah wilayah.
            $table->foreignId('wilayah_id')->constrained('wilayahs')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('dibuat_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            $table->enum('status', ['order', 'process', 'delivery', 'selesai', 'cancel'])
                ->default('order')
                ->index();

            $table->date('tanggal')->index();
            $table->unsignedInteger('total_dus')->default(0);
            $table->decimal('total_nilai', 16, 2)->default(0);
            $table->text('catatan')->nullable();

            // Jejak perubahan status.
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamp('dikirim_at')->nullable();
            $table->timestamp('selesai_at')->nullable();

            // Pembatalan.
            $table->string('alasan_cancel')->nullable();
            $table->text('catatan_cancel')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();

            $table->timestamps();

            // Mempercepat pencarian "apakah toko ini punya pesanan aktif".
            $table->index(['toko_id', 'status']);
            $table->index(['status', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanans');
    }
};
