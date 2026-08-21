<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Awalnya kolom ini disiapkan tanpa trait SoftDeletes (tidak ada jalur
     * kode yang menghapus Pesanan; pembatalan selalu lewat StatusPesanan::
     * Cancel). Sejak App\Models\Pesanan memakai SoftDeletes, kolom ini
     * sudah aktif dipakai — lihat komentar di model itu untuk penjagaan
     * yang menyertainya (pesanan yang sudah masuk rute tidak boleh
     * dihapus lewat ->delete() Eloquent) dan kodePesanan() di
     * PesananService yang memakai withTrashed() agar kode tidak dipakai
     * ulang oleh baris yang di-soft-delete.
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
