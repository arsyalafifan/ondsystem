<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Atas nama sales siapa" pesanan ini — beda dari `dibuat_oleh`, yang
     * selalu mencatat SIAPA YANG SUNGGUH MENGETIK pesanannya (jejak audit).
     * Dipakai saat admin/superadmin menginput pesanan mewakili seorang
     * sales (mis. sambil memberi bonus): faktur perlu tetap menampilkan
     * nama sales-nya, bukan nama admin yang mengetik. Nullable karena
     * pesanan yang diinput sales sendiri tidak butuh kolom ini sama sekali
     * — `dibuat_oleh` sudah cukup.
     */
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->foreignId('sales_id')->nullable()->after('dibuat_oleh')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropForeign(['sales_id']);
            $table->dropColumn('sales_id');
        });
    }
};
