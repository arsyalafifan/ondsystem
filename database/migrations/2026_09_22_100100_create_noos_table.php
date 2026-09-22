<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOO (New Open Outlet) — calon toko mitra baru yang didata sales di
 * lapangan, sejak sebelum ia punya baris di `tokos`.
 *
 * Data tokonya disalin utuh ke sini, bukan langsung membuat `Toko`, karena
 * calon yang ditolak admin tidak boleh pernah mengotori master toko. Baris
 * `tokos` baru lahir saat admin menyetujui (`toko_id` terisi sejak itu), dan
 * sejak saat itu pula data yang berlaku adalah data di `tokos` — kolom di
 * sini menjadi rekaman "apa yang disetujui saat itu", bukan sumber kebenaran
 * yang ikut berubah.
 *
 * `pesanan_id` menunjuk pesanan PERDANA yang terbentuk otomatis saat driver
 * menuntaskan pengantaran. Ia boleh kosong meski NOO sudah selesai: stok
 * gudang bisa saja kurang saat itu, dan kegagalan di gudang tidak boleh
 * menggagalkan pengantaran yang fisiknya sudah terjadi di lapangan —
 * alasannya disimpan di `catatan_pesanan_gagal` supaya admin bisa
 * membuatnya ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noos', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30);
            $table->string('status', 20)->default('order')->index();
            // restrictOnDelete: paket yang pernah dipakai NOO tidak boleh
            // hilang — tanpanya riwayat "toko ini dulu ambil paket apa"
            // ikut lenyap.
            $table->foreignId('paket_noo_id')->constrained('paket_noos')->cascadeOnUpdate()->restrictOnDelete();

            // --- Data toko calon (disalin ke `tokos` saat disetujui) ---
            $table->string('nama');
            $table->text('alamat');
            $table->foreignId('wilayah_id')->constrained('wilayahs')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('telepon', 30);
            $table->string('nama_pemilik');
            $table->string('nik_pemilik', 16);
            $table->string('kategori', 20)->nullable();
            $table->string('freezer_tipe', 40)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('sumber_koordinat', 10)->default('manual');

            // --- Jejak ke data sungguhan yang lahir dari NOO ini ---
            $table->foreignId('toko_id')->nullable()->constrained('tokos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('pesanan_id')->nullable()->constrained('pesanans')->cascadeOnUpdate()->nullOnDelete();
            $table->text('catatan_pesanan_gagal')->nullable();

            // --- Jejak keputusan ---
            $table->foreignId('diajukan_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('diajukan_at');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();
            $table->foreignId('ditolak_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('ditolak_at')->nullable();
            $table->text('alasan_tolak')->nullable();
            // Admin boleh mengoreksi data sales sebelum menyetujui — dicatat
            // supaya perbedaan data lapangan vs data yang akhirnya disetujui
            // tidak pernah jadi perdebatan tanpa jejak.
            $table->foreignId('diubah_admin_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('diubah_admin_at')->nullable();
            $table->foreignId('diselesaikan_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('selesai_at')->nullable();

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['depot_id', 'kode']);
            // Dipakai antrean admin (status + terbaru dulu) dan pemeriksaan
            // jarak 200 m yang menyaring kandidat lewat kotak koordinat.
            $table->index(['status', 'diajukan_at']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('noo_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('noo_id')->constrained('noos')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('jenis', 50);
            $table->string('path');

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            // Satu jenis satu foto. Sales masih boleh mengambil ulang selama
            // NOO-nya belum diajukan, jadi penyimpanannya menimpa baris yang
            // sama, bukan menumpuk.
            $table->unique(['noo_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noo_fotos');
        Schema::dropIfExists('noos');
    }
};
