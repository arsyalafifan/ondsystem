<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            // Nomor aset freezer yang tercetak pada QR code di badan freezer.
            // Inilah yang dipindai sales di lapangan untuk mengenali tokonya,
            // jadi harus unik dan boleh kosong selama freezer belum terpasang.
            $table->string('asset_id', 40)->nullable()->unique()->after('kode');
            $table->string('freezer_tipe', 40)->nullable()->after('asset_id');
            $table->string('freezer_pelanggan', 100)->nullable()->after('freezer_tipe');
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropUnique(['asset_id']);
            $table->dropColumn(['asset_id', 'freezer_tipe', 'freezer_pelanggan']);
        });
    }
};
