<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master freezer: katalog fisik freezer (nomor asset / IDN dan tipenya)
 * per depot. IDN dibuat unik per depot agar tidak ada freezer dengan
 * nomor stiker yang sama dalam satu gudang operasional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freezers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('idn', 50);
            $table->string('tipe', 100);
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['depot_id', 'idn']);
            $table->index('idn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freezers');
    }
};
