<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Freezer menjadi satu katalog untuk SEMUA gudang, dan satu IDN
 * hanya boleh dipasang di SATU toko di seluruh sistem.
 *
 * Ini membalik keputusan 2026_09_10_000100_asset_id_toko_unik_per_depot
 * ("nomor stiker yang sama SAH muncul di dua depot"): kini IDN adalah
 * identitas fisik satu freezer, jadi tidak boleh melekat di dua baris toko
 * sekaligus — walau tokonya kebetulan tercatat di dua gudang. Gudang tempat
 * sebuah IDN berada ditentukan dari toko yang memegangnya, bukan disimpan
 * di baris freezer.
 *
 * Migrasi berhenti dengan pesan jelas kalau data yang ada masih bentrok
 * (IDN ganda), alih-alih menghapus atau memilih salah satu secara diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        $idnFreezerGanda = DB::table('freezers')
            ->select('idn')->groupBy('idn')->havingRaw('count(*) > 1')->pluck('idn');

        if ($idnFreezerGanda->isNotEmpty()) {
            throw new RuntimeException(
                'Master Freezer tidak bisa dijadikan global: IDN berikut terdaftar di lebih dari satu gudang — '
                .'rapikan dulu (sisakan satu): '.$idnFreezerGanda->implode(', ')
            );
        }

        $idnTokoGanda = DB::table('tokos')
            ->whereNotNull('asset_id')
            ->select('asset_id')->groupBy('asset_id')->havingRaw('count(*) > 1')->pluck('asset_id');

        if ($idnTokoGanda->isNotEmpty()) {
            throw new RuntimeException(
                'IDN toko tidak bisa dijadikan unik global: IDN berikut dipakai lebih dari satu toko (lintas gudang) — '
                .'rapikan dulu (sisakan satu toko): '.$idnTokoGanda->implode(', ')
            );
        }

        Schema::table('freezers', function (Blueprint $table) {
            $table->dropForeign(['depot_id']);
            $table->dropUnique(['depot_id', 'idn']);
            $table->dropIndex(['idn']);
            $table->dropColumn('depot_id');
            $table->unique('idn');
        });

        Schema::table('tokos', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'asset_id']);
            $table->unique('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropUnique(['asset_id']);
            $table->unique(['depot_id', 'asset_id']);
        });

        Schema::table('freezers', function (Blueprint $table) {
            $table->dropUnique(['idn']);
            $table->foreignId('depot_id')->nullable()->after('id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
        });

        // Gudang asal dipulihkan sebisanya: gudang toko pemegangnya, kalau
        // belum terpasang ke toko maka gudang pertama.
        $depotPertama = DB::table('depots')->orderBy('id')->value('id');
        foreach (DB::table('freezers')->get(['id', 'idn']) as $freezer) {
            $depotToko = DB::table('tokos')->where('asset_id', $freezer->idn)->value('depot_id');
            DB::table('freezers')->where('id', $freezer->id)->update(['depot_id' => $depotToko ?? $depotPertama]);
        }

        Schema::table('freezers', function (Blueprint $table) {
            $table->unique(['depot_id', 'idn']);
            $table->index('idn');
        });
    }
};
