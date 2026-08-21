<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom disiapkan lebih dulu, sama seperti penugasan_sales dan
     * kunjungan_fotos di 2026_08_21_000100_add_soft_deletes_columns.php.
     *
     * Pesanan sengaja belum memakai trait SoftDeletes: tidak ada satu pun
     * jalur kode yang memanggil ->delete() padanya hari ini — pembatalan
     * selalu lewat perubahan status (StatusPesanan::Cancel), bukan
     * menghapus barisnya, jadi riwayatnya sudah abadi tanpa soft delete.
     * Kolom ini cuma jaga-jaga kalau kelak ada kebutuhan nyata untuk
     * membuang pesanan (mis. salah input, bukan sekadar dibatalkan).
     */
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
