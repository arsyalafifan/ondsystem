<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paket penjualan perdana untuk mitra baru (NOO) — "15+2", "10+1", dan
 * seterusnya. Isinya ditentukan admin, bukan ditanam di kode, supaya paket
 * baru cukup ditambah lewat menu Setting Paket NOO.
 *
 * Jumlah dus disimpan TERPISAH dari rincian produknya (`dus_reguler`/
 * `dus_bonus` di sini, barisnya di `paket_noo_items`) karena keduanya punya
 * peran berbeda: angka di sini yang jadi nama janji ke pemilik toko ("15
 * dus, bonus 2"), sedangkan barisnya yang menentukan es krim apa saja yang
 * benar-benar dikirim. Keduanya dijaga tetap cocok saat disimpan — paket
 * yang jumlah barisnya belum genap sengaja tidak muncul sebagai pilihan
 * sales, bukan diam-diam dikirim timpang.
 *
 * Per depot, sama seperti `produks` yang jadi isinya: katalog tiap gudang
 * berbeda, jadi paketnya pun tidak bisa dipakai bersama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paket_noos', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 60);
            $table->unsignedInteger('dus_reguler');
            $table->unsignedInteger('dus_bonus');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['depot_id', 'nama']);
        });

        Schema::create('paket_noo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_noo_id')->constrained('paket_noos')->cascadeOnUpdate()->cascadeOnDelete();
            // restrictOnDelete: produk yang masih jadi isi paket tidak boleh
            // hilang begitu saja — paketnya akan jadi timpang tanpa ada yang
            // tahu sampai ada NOO yang gagal dibuatkan pesanan perdananya.
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnUpdate()->restrictOnDelete();

            $table->unsignedInteger('jumlah_dus');
            $table->boolean('is_bonus')->default(false);

            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            // Satu produk maksimal dua baris per paket: sekali sebagai dus
            // reguler, sekali sebagai bonus — batasan yang sama persis dengan
            // `pesanan_items`, karena baris-baris inilah yang nanti disalin ke
            // sana saat pesanan perdana dibuat.
            $table->unique(['paket_noo_id', 'produk_id', 'is_bonus']);
        });

        // Disemai di dalam migrasi, mengikuti `pengaturan_izins` dan
        // `pengaturan_kunjungans`: dua paket ini yang disebut di kesepakatan
        // penjualan, jadi admin tinggal memilih produknya, bukan menebak
        // sendiri harus bikin paket apa. Produknya sengaja dibiarkan kosong —
        // katalog tiap depot berbeda dan tidak ada tebakan yang benar di sini.
        foreach (DB::table('depots')->pluck('id') as $depotId) {
            DB::table('paket_noos')->insert([
                [
                    'nama' => '15+2',
                    'dus_reguler' => 15,
                    'dus_bonus' => 2,
                    'urutan' => 1,
                    'aktif' => true,
                    'depot_id' => $depotId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nama' => '10+1',
                    'dus_reguler' => 10,
                    'dus_bonus' => 1,
                    'urutan' => 2,
                    'aktif' => true,
                    'depot_id' => $depotId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_noo_items');
        Schema::dropIfExists('paket_noos');
    }
};
