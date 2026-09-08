<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4 dari rollout multi-depot (lihat ~/.claude/plans/tranquil-wondering-mango.md
 * §5): mengunci apa yang selama ini cuma "seharusnya selalu terisi" (dijaga
 * kode) menjadi benar-benar tidak mungkin salah di level database — NOT
 * NULL, unique composite per depot, dan foreign key ke `depots`.
 *
 * Aman dijalankan sekarang karena:
 * - Backfill Stage 2 + susulan sudah membuat SEMUA baris (kecuali superadmin
 *   di `users`) terisi depot_id — diverifikasi ulang manual di produksi
 *   sebelum migrasi ini ditulis, nol baris NULL yang tersisa.
 * - Hanya ada SATU depot yang ada sekarang, jadi kolom yang dulu unique
 *   GLOBAL (kode toko, kode produk, dst) otomatis tetap unique begitu
 *   diganti jadi unique PER DEPOT — tidak ada baris yang bisa bentrok.
 * - Semua tabel yang disentuh masih berukuran kecil (di bawah 10 ribu
 *   baris), jadi ALTER TABLE di MySQL 8 (algoritma INPLACE) selesai
 *   sub-detik per tabel, bukan mengunci lama.
 *
 * `tokos.asset_id` SENGAJA tetap unique GLOBAL (bukan per depot) — nomor
 * fisik stiker QR freezer, satu barang fisik tidak mungkin ada di 2 depot.
 */
return new class extends Migration
{
    /** Semua tabel BerDepot KECUALI `users`, yang punya aturan generated-column sendiri di bawah. */
    private array $tabelBiasa = [
        'wilayahs',
        'tokos',
        'produks',
        'pesanans',
        'pesanan_items',
        'routing_batches',
        'kendaraans',
        'kendaraan_stops',
        'stok_mutasis',
        'penugasan_sales',
        'penugasan_tokos',
        'penugasan_toko_defaults',
        'periode_kunjungans',
        'periode_sales',
        'kunjungans',
        'kunjungan_fotos',
        'promos',
        'promo_produk',
        'pengaturan_kunjungans',
    ];

    public function up(): void
    {
        // 1. NOT NULL untuk depot_id di semua tabel biasa.
        foreach ($this->tabelBiasa as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->unsignedBigInteger('depot_id')->nullable(false)->change();
            });
        }

        // 2. Unique GLOBAL lama diganti unique PER DEPOT.
        Schema::table('wilayahs', function (Blueprint $table) {
            $table->dropUnique('wilayahs_kode_unique');
            $table->unique(['depot_id', 'kode']);
        });

        Schema::table('tokos', function (Blueprint $table) {
            $table->dropUnique('tokos_kode_unique');
            $table->unique(['depot_id', 'kode']);
            // asset_id TIDAK disentuh — tetap unique global, lihat docblock di atas.
        });

        Schema::table('produks', function (Blueprint $table) {
            $table->dropUnique('produks_kode_unique');
            $table->dropUnique('produks_barcode_unique');
            $table->unique(['depot_id', 'kode']);
            $table->unique(['depot_id', 'barcode']);
        });

        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropUnique('pesanans_kode_unique');
            $table->unique(['depot_id', 'kode']);
        });

        Schema::table('routing_batches', function (Blueprint $table) {
            $table->dropUnique('routing_batches_kode_unique');
            $table->unique(['depot_id', 'kode']);
        });

        Schema::table('periode_kunjungans', function (Blueprint $table) {
            $table->dropUnique('periode_kunjungans_kode_unique');
            $table->dropUnique('periode_kunjungans_tahun_minggu_unique');
            $table->unique(['depot_id', 'kode']);
            $table->unique(['depot_id', 'tahun', 'minggu']);
        });

        // Baris tunggal per depot (dulu baris tunggal GLOBAL) — lihat
        // docblock migrasi create_pengaturan_kunjungans_table.
        Schema::table('pengaturan_kunjungans', function (Blueprint $table) {
            $table->unique('depot_id');
        });

        // 3. Foreign key ke `depots` untuk semua tabel biasa. restrictOnDelete
        // supaya depot tidak bisa dihapus selama masih punya data anak —
        // sengaja tidak ada alur "hapus depot" di aplikasi ini sama sekali.
        foreach ($this->tabelBiasa as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->foreign('depot_id')->references('id')->on('depots')
                    ->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        // 4. `users`: depot_id TETAP nullable (superadmin) — kolom generated
        // dipakai supaya "email unik per depot, KECUALI sesama superadmin
        // yang tetap unik global" bisa diungkapkan lewat satu unique index
        // biasa. COALESCE(depot_id, 0) membuat semua baris superadmin
        // "berkumpul" di satu nilai (0) yang sama — 0 tidak pernah jadi
        // depots.id asli (auto-increment mulai dari 1) — sementara user
        // biasa tetap terisolasi per depot yang sesungguhnya.
        $driver = Schema::getConnection()->getDriverName();
        $tipeKolom = $driver === 'sqlite' ? 'INTEGER' : 'BIGINT UNSIGNED';

        DB::statement("ALTER TABLE users ADD COLUMN depot_kunci_unik {$tipeKolom} GENERATED ALWAYS AS (COALESCE(depot_id, 0)) STORED");

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['depot_kunci_unik', 'email']);
            $table->foreign('depot_id')->references('id')->on('depots')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['depot_id']);
            $table->dropUnique(['depot_kunci_unik', 'email']);
            $table->unique('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('depot_kunci_unik');
        });

        foreach (array_reverse($this->tabelBiasa) as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropForeign(['depot_id']);
            });
        }

        Schema::table('pengaturan_kunjungans', function (Blueprint $table) {
            $table->dropUnique(['depot_id']);
        });

        Schema::table('periode_kunjungans', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'tahun', 'minggu']);
            $table->dropUnique(['depot_id', 'kode']);
            $table->unique(['tahun', 'minggu']);
            $table->unique('kode');
        });

        Schema::table('routing_batches', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'kode']);
            $table->unique('kode');
        });

        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'kode']);
            $table->unique('kode');
        });

        Schema::table('produks', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'barcode']);
            $table->dropUnique(['depot_id', 'kode']);
            $table->unique('barcode');
            $table->unique('kode');
        });

        Schema::table('tokos', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'kode']);
            $table->unique('kode');
        });

        Schema::table('wilayahs', function (Blueprint $table) {
            $table->dropUnique(['depot_id', 'kode']);
            $table->unique('kode');
        });

        foreach ($this->tabelBiasa as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->unsignedBigInteger('depot_id')->nullable()->change();
            });
        }
    }
};
