<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokos', function (Blueprint $table): void {
            $table->string('provinsi', 255)->nullable()->after('kota');
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table): void {
            $table->dropColumn('provinsi');
        });
    }
};
