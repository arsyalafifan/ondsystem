<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transfer stok antar gudang penyimpanan (depots.gudang_penyimpanan).
 *
 * Sengaja TIDAK punya kolom `depot_id` tunggal seperti kebanyakan tabel
 * ber-BerDepot: satu baris di sini "milik" DUA depot sekaligus (asal dan
 * tujuan), keduanya harus bisa melihatnya. Keanggotaan depotnya karena itu
 * diputuskan lewat App\Models\TransferStok::saringDepot() (implements
 * App\Models\Concerns\DisaringDepotSendiri), bukan DepotScope bawaan —
 * sama seperti App\Models\User yang bisa dipakai di banyak gudang.
 *
 * `kode` karena itu juga unik GLOBAL, bukan per depot — tidak ada satu
 * depot yang "memiliki" penomorannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_stoks', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('status', 20)->default('dikirim')->index();

            $table->foreignId('depot_asal_id')->constrained('depots')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('depot_tujuan_id')->constrained('depots')->cascadeOnUpdate()->restrictOnDelete();

            $table->foreignId('dikirim_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('dikirim_at');
            $table->text('catatan_kirim')->nullable();

            $table->foreignId('diterima_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('diterima_at')->nullable();
            $table->text('catatan_terima')->nullable();

            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->text('alasan_batal')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'dikirim_at']);
        });

        Schema::create('transfer_stok_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_stok_id')->constrained('transfer_stoks')->cascadeOnUpdate()->cascadeOnDelete();

            // Dua produk berbeda baris (katalog per depot, lihat Produk) untuk
            // barang fisik yang sama — disamakan lewat kode saat transfer
            // dikirim, lalu kedua id-nya disimpan di sini supaya penerimaan
            // tidak perlu mencocokkan ulang lewat kode (yang bisa saja
            // berubah belakangan di salah satu gudang).
            $table->foreignId('produk_asal_id')->constrained('produks')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('produk_tujuan_id')->constrained('produks')->cascadeOnUpdate()->restrictOnDelete();

            $table->unsignedInteger('jumlah_kirim');
            // Null sampai gudang tujuan menerima — lihat ket_status_dikirim.
            $table->unsignedInteger('jumlah_terima')->nullable();

            $table->timestamps();
        });

        Schema::create('transfer_stok_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_stok_id')->constrained('transfer_stoks')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('diunggah_oleh')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_stok_fotos');
        Schema::dropIfExists('transfer_stok_items');
        Schema::dropIfExists('transfer_stoks');
    }
};
