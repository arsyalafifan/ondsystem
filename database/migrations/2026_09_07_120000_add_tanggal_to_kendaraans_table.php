<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraans', function (Blueprint $table): void {
            $table->date('tanggal')->nullable()->index()->after('routing_batch_id');
        });

        // Backfill dari batch-nya masing-masing — per-batch, portabel lintas
        // driver basis data (MySQL asli maupun SQLite yang dipakai
        // pengujian), bukan lewat sintaks JOIN UPDATE yang tidak portabel.
        DB::table('routing_batches')->select('id', 'tanggal')->orderBy('id')
            ->chunk(200, function ($batches): void {
                foreach ($batches as $batch) {
                    DB::table('kendaraans')->where('routing_batch_id', $batch->id)
                        ->update(['tanggal' => $batch->tanggal]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('kendaraans', function (Blueprint $table): void {
            $table->dropColumn('tanggal');
        });
    }
};
