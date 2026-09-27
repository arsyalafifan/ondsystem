<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarik Freezer — kebalikan dari NOO (lihat create_noos_table): toko yang
 * SUDAH jadi mitra berhenti kerja sama, freezernya diambil kembali ke
 * gudang, dan tokonya dinonaktifkan.
 *
 * Jauh lebih ringkas dari `noos` karena tokonya sudah ada — `toko_id` wajib
 * terisi sejak baris ini dibuat (tidak seperti NOO yang barulah membuat toko
 * setelah disetujui), tidak ada data toko yang perlu disalin ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarik_freezers', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30);
            $table->string('status', 20)->default('order')->index();

            $table->foreignId('toko_id')->constrained('tokos')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('alasan');

            $table->foreignId('diajukan_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('diajukan_at');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();
            $table->foreignId('ditolak_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('ditolak_at')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->foreignId('diselesaikan_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('selesai_at')->nullable();

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['depot_id', 'kode']);
            $table->index(['status', 'diajukan_at']);
        });

        Schema::create('tarik_freezer_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarik_freezer_id')->constrained('tarik_freezers')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('jenis', 50);
            $table->string('path');

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['tarik_freezer_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarik_freezer_fotos');
        Schema::dropIfExists('tarik_freezers');
    }
};
