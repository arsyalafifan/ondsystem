<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akses gudang per akun: satu akun bisa dipakai di beberapa gudang, bukan
 * lagi satu akun terpisah per gudang. Superadmin tidak butuh baris di sini
 * — ia selalu bisa ke semua gudang.
 *
 * `users.depot_id` tetap ada, tapi artinya bergeser jadi GUDANG DEFAULT
 * (tempat pengguna mendarat setelah login), bukan satu-satunya gudang.
 *
 * Diisi dari `users.depot_id` yang sudah ada, jadi setiap akun langsung
 * punya akses ke gudang yang selama ini ia pakai — tidak ada yang berubah
 * bagi pengguna saat migrasi ini jalan. Hanya menambah tabel baru, aman
 * dijalankan tanpa downtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depot_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['depot_id', 'user_id']);
            $table->index('user_id');
        });

        DB::table('depot_user')->insertUsing(
            ['depot_id', 'user_id', 'created_at', 'updated_at'],
            DB::table('users')
                ->whereNotNull('depot_id')
                ->select('depot_id', 'id', DB::raw('CURRENT_TIMESTAMP'), DB::raw('CURRENT_TIMESTAMP')),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('depot_user');
    }
};
