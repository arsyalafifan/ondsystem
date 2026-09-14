<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data karyawan — modul HR System.
 *
 * SENGAJA TIDAK di-scope per depot (tidak ada trait BerDepot di model-nya):
 * ini menu administrasi karyawan lintas-perusahaan, bukan data milik satu
 * gudang. `depot_id` di bawah ("Penempatan") tetap ada sebagai field biasa
 * yang diisi manual dari form — BUKAN hasil auto-stamp dari konteks depot
 * yang sedang aktif di sesi yang login. Field ini nantinya jadi titik
 * jangkar geofencing absensi (validasi jarak maksimal terhadap lat/lng
 * depot yang bersangkutan), bukan sekadar label organisasi.
 *
 * `user_id` menautkan ke akun login yang SUDAH ADA (nullable — banyak
 * karyawan yang datanya diketik sekarang belum tentu akunnya sudah dibuat).
 * Begitu absensi selfie berjalan nanti, praktis setiap karyawan diharapkan
 * punya tautan ini — tapi pembuatan akun barunya sendiri di luar cakupan
 * modul ini (wewenang "User Admin").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->string('kode_karyawan', 30)->unique();
            $table->string('nama_lengkap', 255);
            $table->string('nik', 16)->unique();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->date('tanggal_lahir');
            $table->string('no_hp', 20);
            $table->text('alamat_domisili');
            $table->foreignId('department_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('jabatan_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->date('tanggal_masuk');
            $table->enum('status_karyawan', ['tetap', 'kontrak']);
            $table->date('tanggal_berakhir_kontrak')->nullable();
            $table->decimal('gaji_pokok', 12, 2);
            $table->string('no_rekening', 50)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->text('catatan')->nullable();

            // "Penempatan" — lihat catatan panjang di atas kelas ini.
            $table->foreignId('depot_id')->constrained()->restrictOnUpdate()->restrictOnDelete();

            $table->string('foto_karyawan')->nullable();
            $table->string('foto_ktp')->nullable();

            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawans');
    }
};
