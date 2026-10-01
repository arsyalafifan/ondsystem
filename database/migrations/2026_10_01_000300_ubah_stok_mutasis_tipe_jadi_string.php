<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `tipe` di `stok_mutasis` masih ENUM database (dibuat sebelum
 * konvensi "ENUM cuma menambah friksi" diputuskan — lihat
 * ubah_jenis_kunjungan_fotos_jadi_string.php untuk alasan lengkapnya).
 * Transfer Stok butuh dua nilai baru (`transfer_keluar`, `transfer_masuk` —
 * lihat App\Enums\JenisMutasiStok), dan menambahnya ke daftar ENUM MySQL
 * berarti migrasi skema lagi setiap kali daftar jenis mutasi berubah.
 * Diganti jadi VARCHAR sekali untuk selamanya: validitas nilainya sudah
 * dijaga di level aplikasi lewat cast JenisMutasiStok.
 *
 * Tidak ada doctrine/dbal di proyek ini, jadi Blueprint::change() tidak
 * bisa dipakai. MySQL diubah lewat ALTER MODIFY langsung. SQLite tidak
 * mendukung pengubahan CHECK constraint pada kolom yang sudah ada sama
 * sekali — satu-satunya jalan adalah membangun ulang tabelnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->bangunUlangUntukSqlite();

            return;
        }

        DB::statement('ALTER TABLE stok_mutasis MODIFY tipe VARCHAR(20) NOT NULL');
    }

    public function down(): void
    {
        $lama = ['reserve', 'release', 'keluar', 'masuk', 'penyesuaian'];

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->bangunUlangUntukSqlite($lama);

            return;
        }

        $daftar = collect($lama)->map(fn ($v) => "'{$v}'")->implode(',');
        DB::statement("ALTER TABLE stok_mutasis MODIFY tipe ENUM({$daftar}) NOT NULL");
    }

    /**
     * Skema di bawah ini HARUS mencerminkan tabel `stok_mutasis` yang
     * sesungguhnya sekarang (bukan cuma migrasi pembuatnya) — `kendaraan_id`
     * (add_kendaraan_id_to_stok_mutasis_table) dan `depot_id` NOT NULL
     * (tighten_depot_id_constraints) ditambahkan belakangan lewat migrasi
     * terpisah. Rebuild yang melewatkan salah satunya diam-diam MEMBUANG
     * kolom itu beserta isinya.
     */
    private function bangunUlangUntukSqlite(?array $tipeEnum = null): void
    {
        Schema::create('stok_mutasis_baru', function (Blueprint $table) use ($tipeEnum) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('pesanan_id')->nullable()->constrained('pesanans')->nullOnDelete();
            $table->foreignId('kendaraan_id')->nullable()->constrained('kendaraans')->nullOnDelete();

            if ($tipeEnum === null) {
                $table->string('tipe', 20);
            } else {
                $table->enum('tipe', $tipeEnum);
            }

            $table->integer('jumlah');
            $table->integer('stok_sesudah')->default(0);
            $table->integer('reserved_sesudah')->default(0);
            $table->string('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['produk_id', 'created_at']);
        });

        DB::statement('
            INSERT INTO stok_mutasis_baru
                (id, produk_id, pesanan_id, kendaraan_id, tipe, jumlah, stok_sesudah, reserved_sesudah, keterangan, user_id, depot_id, created_at, updated_at)
            SELECT
                id, produk_id, pesanan_id, kendaraan_id, tipe, jumlah, stok_sesudah, reserved_sesudah, keterangan, user_id, depot_id, created_at, updated_at
            FROM stok_mutasis
        ');

        Schema::drop('stok_mutasis');
        Schema::rename('stok_mutasis_baru', 'stok_mutasis');
    }
};
