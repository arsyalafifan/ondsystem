<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungans')->cascadeOnUpdate()->cascadeOnDelete();

            $table->enum('jenis', [
                'sales_depan_toko',
                'freezer_sebelum',
                'freezer_sesudah',
                'spanduk',
                'flag_hanger',
                'suhu_freezer',
            ]);

            $table->string('path');

            // Nilai yang dicetak sebagai watermark, disimpan terpisah supaya
            // bisa dicocokkan dengan gambar bila keasliannya dipertanyakan.
            $table->timestamp('diambil_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('akurasi_m')->nullable();

            $table->unsignedInteger('lebar')->nullable();
            $table->unsignedInteger('tinggi')->nullable();
            $table->unsignedInteger('ukuran_byte')->nullable();

            $table->timestamps();

            // Satu jenis foto satu kali. Mengambil ulang menimpa yang lama,
            // bukan menumpuk.
            $table->unique(['kunjungan_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_fotos');
    }
};
