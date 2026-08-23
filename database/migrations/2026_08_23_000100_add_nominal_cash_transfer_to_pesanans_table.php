<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->decimal('nominal_cash', 15, 2)->nullable()->after('dilunasi_oleh');
            $table->decimal('nominal_transfer', 15, 2)->nullable()->after('nominal_cash');
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropColumn(['nominal_cash', 'nominal_transfer']);
        });
    }
};
