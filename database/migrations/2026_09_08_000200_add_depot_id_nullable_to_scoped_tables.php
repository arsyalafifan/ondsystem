<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 dari rollout multi-depot (lihat rencana di
 * ~/.claude/plans/tranquil-wondering-mango.md): kolom depot_id ditambahkan
 * nullable, tanpa foreign key dan tanpa mengubah unique constraint yang ada.
 *
 * Sengaja ditaruh di ujung tiap tabel (tanpa ->after(...)) supaya memenuhi
 * syarat ADD COLUMN instan di MySQL 8.0 — kalau ditaruh di tengah, MySQL
 * terpaksa menulis ulang seluruh tabel dan mengunci sebentar. Belum ada
 * baris yang membaca/menulis kolom ini di tahap ini, jadi deploy tahap ini
 * tidak mengubah perilaku aplikasi sama sekali.
 *
 * Constraint NOT NULL, unique composite, dan foreign key baru dipasang di
 * migrasi "tighten" belakangan, setelah kode yang menegakkan depot_id
 * (DepotScope dkk) sudah berjalan beberapa hari tanpa masalah di produksi.
 */
return new class extends Migration
{
    private array $tabel = [
        'users',
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
        foreach ($this->tabel as $nama) {
            Schema::table($nama, function (Blueprint $table) {
                $table->foreignId('depot_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tabel) as $nama) {
            Schema::table($nama, function (Blueprint $table) {
                $table->dropColumn('depot_id');
            });
        }
    }
};
