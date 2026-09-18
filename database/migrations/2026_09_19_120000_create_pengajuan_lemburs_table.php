<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan lembur — wajib diajukan SEBELUM jam mulai (pre-approval), di
 * luar jam kerja normal, diputuskan dengan aturan yang sama seperti izin
 * (App\Services\Izin\ApproverIzin).
 *
 * `tanggal` = tanggal kerja; `jam_selesai` boleh lebih kecil dari
 * `jam_mulai` untuk lembur yang melewati tengah malam (`lintas_hari`).
 * `menit_maks` adalah SNAPSHOT batas lembur per hari saat diajukan —
 * setting boleh berubah belakangan tanpa mengubah riwayat.
 *
 * Jam yang DIAKUI tidak disimpan: dihitung dari min(diajukan, batas, yang
 * benar-benar dijalani menurut absen masuk/pulang) — lihat
 * App\Services\Lembur\PengajuanLemburService::hitung(). Absen pulang bisa
 * datang belakangan, jadi nilai tersimpan akan cepat basi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_lemburs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained()->cascadeOnDelete();

            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('lintas_hari')->default(false);
            $table->unsignedSmallInteger('menit_diajukan');
            $table->unsignedSmallInteger('menit_maks');
            $table->text('tugas');

            $table->string('status', 20)->default('menunggu');
            $table->foreignId('diajukan_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_at')->nullable();
            $table->text('catatan_keputusan')->nullable();

            $table->timestamps();

            $table->index(['karyawan_id', 'tanggal']);
            $table->index(['tanggal', 'status']);
        });

        Schema::table('pengaturan_izins', function (Blueprint $table) {
            $table->unsignedSmallInteger('maks_lembur_menit')->default(60)->after('approver_pengguna');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_izins', fn (Blueprint $table) => $table->dropColumn('maks_lembur_menit'));
        Schema::dropIfExists('pengajuan_lemburs');
    }
};
