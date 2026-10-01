<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tidak semua depot adalah gudang penyimpanan fisik (sebagian murni kantor
 * operasional sales/driver tanpa stok). Penanda ini menentukan siapa yang
 * boleh muncul sebagai pilihan asal/tujuan di menu Transfer Stok — lihat
 * App\Services\TransferStok\TransferStokService.
 *
 * Default MATI untuk semua depot yang sudah ada: ini keputusan sadar per
 * gudang (Kelola Depot), bukan sesuatu yang otomatis dianggap benar untuk
 * gudang yang kebetulan sudah ada sebelum fitur ini dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->boolean('gudang_penyimpanan')->default(false)->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->dropColumn('gudang_penyimpanan');
        });
    }
};
