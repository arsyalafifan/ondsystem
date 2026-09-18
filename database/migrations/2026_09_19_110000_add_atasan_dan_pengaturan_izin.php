<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa yang memutuskan pengajuan izin — lihat App\Services\Izin\ApproverIzin.
 *
 * - `karyawans.atasan_id`: atasan langsung (sesama karyawan), dipakai mode
 *   "atasan langsung". Menunjuk ke KARYAWAN, bukan akun, karena struktur
 *   organisasi tetap ada walau atasannya belum punya akun; persetujuannya
 *   lewat akun yang tertaut ke karyawan atasan itu.
 * - `pengaturan_izins`: satu baris setting global (HR lintas gudang).
 *   Bawaannya persis perilaku sebelumnya — mode approver umum, semua HR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->foreignId('atasan_id')->nullable()->after('shift_id')->constrained('karyawans')->nullOnDelete();
        });

        Schema::create('pengaturan_izins', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 20)->default('approver_umum');
            $table->json('approver_peran');
            $table->json('approver_pengguna');
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('pengaturan_izins')->insert([
            'mode' => 'approver_umum',
            'approver_peran' => json_encode(['hr']),
            'approver_pengguna' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_izins');

        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atasan_id');
        });
    }
};
