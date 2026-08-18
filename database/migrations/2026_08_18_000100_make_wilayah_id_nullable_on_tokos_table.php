<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Toko boleh diimpor sebelum wilayahnya diketahui — jadi wilayah_id
        // wajib bisa kosong sementara, sampai dilengkapi lewat upload
        // berikutnya atau formulir edit. Toko tanpa wilayah tetap tidak bisa
        // ikut routing maupun dipesan; itu dijaga di lapisan aplikasi.
        Schema::table('tokos', function (Blueprint $table) {
            $table->foreignId('wilayah_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->foreignId('wilayah_id')->nullable(false)->change();
        });
    }
};
