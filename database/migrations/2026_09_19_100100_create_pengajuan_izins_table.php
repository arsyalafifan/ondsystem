<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan izin/sakit karyawan dan keputusan HR atasnya.
 *
 * Satu baris = satu pengajuan, boleh rentang tanggal (izin penuh & sakit);
 * izin setengah hari selalu satu tanggal. `jumlah_hari` dan `dibayar`
 * adalah SNAPSHOT saat diajukan — hari kerja posisi dan kebijakan jenis
 * izin boleh berubah belakangan, riwayat tidak ikut berubah. Keduanya
 * disiapkan untuk sistem cuti/payroll yang menyusul: `jenis` sengaja
 * string (bukan enum DB) supaya jenis `cuti` tinggal ditambah di kode.
 *
 * `lampiran` disimpan di disk PRIVAT (bukan public) — surat dokter adalah
 * data kesehatan, hanya boleh dibuka pemiliknya dan HR lewat rute yang
 * diperiksa hak aksesnya.
 *
 * Tanpa BerDepot, mengikuti `karyawans`/`absensis` yang lintas gudang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained()->cascadeOnDelete();

            $table->string('jenis', 20);
            $table->string('porsi', 20);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->decimal('jumlah_hari', 5, 1);
            $table->boolean('dibayar');

            $table->text('alasan');
            $table->string('lampiran')->nullable();
            $table->string('lampiran_nama')->nullable();

            $table->string('status', 20)->default('menunggu');
            $table->foreignId('diajukan_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_at')->nullable();
            $table->text('catatan_keputusan')->nullable();

            $table->timestamps();

            $table->index(['karyawan_id', 'tanggal_mulai', 'tanggal_selesai']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izins');
    }
};
