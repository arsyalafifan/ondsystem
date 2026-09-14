<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `jenis` tadinya ENUM (CHECK constraint) berisi daftar jenis foto
 * yang tetap — begitu daftar jenis foto wajib berubah (freezer dipecah
 * jadi bawah/atas, spanduk+sales digabung, barcode ditambah), kolomnya
 * ikut harus diubah setiap kali. Diganti jadi VARCHAR biasa: validitas
 * nilainya sudah dijaga di level aplikasi lewat cast App\Enums\JenisFotoKunjungan,
 * jadi CHECK constraint di database cuma menambah friksi tanpa manfaat
 * ekstra — perubahan daftar jenis foto berikutnya cukup di enum PHP dan
 * config('visit.foto_wajib'), tidak perlu migrasi skema lagi.
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

        DB::statement('ALTER TABLE kunjungan_fotos MODIFY jenis VARCHAR(40) NOT NULL');
    }

    public function down(): void
    {
        $lama = [
            'sales_depan_toko', 'freezer_sebelum', 'freezer_sesudah',
            'spanduk', 'flag_hanger', 'suhu_freezer',
        ];

        if (DB::connection()->getDriverName() === 'sqlite') {
            // Baris yang nilai `jenis`-nya sudah berupa jenis foto baru
            // ('freezer_bawah_sebelum' dkk.) tidak muat lagi di CHECK
            // constraint lama — turun balik ini cukup untuk skema lokal
            // uji coba, bukan untuk memulihkan data produksi yang sudah
            // dipakai.
            $this->bangunUlangUntukSqlite($lama);

            return;
        }

        $daftar = collect($lama)->map(fn ($v) => "'{$v}'")->implode(',');
        DB::statement("ALTER TABLE kunjungan_fotos MODIFY jenis ENUM({$daftar}) NOT NULL");
    }

    /**
     * Skema di bawah ini HARUS mencerminkan tabel `kunjungan_fotos` yang
     * sesungguhnya sekarang (bukan cuma migrasi pembuatnya) — kolom
     * `deleted_at` (add_soft_deletes_columns) dan `depot_id` (tahap
     * rollout multi-depot: nullable lalu di-NOT NULL-kan + foreign key ke
     * `depots`) ditambahkan belakangan lewat migrasi terpisah. Rebuild
     * yang melewatkan salah satunya diam-diam MEMBUANG kolom itu beserta
     * isinya — sempat kejadian, ketahuan lewat kegagalan test yang
     * menyentuh `depot_id`.
     */
    private function bangunUlangUntukSqlite(?array $jenisEnum = null): void
    {
        Schema::create('kunjungan_fotos_baru', function (Blueprint $table) use ($jenisEnum) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungans')->cascadeOnUpdate()->cascadeOnDelete();

            if ($jenisEnum === null) {
                $table->string('jenis', 40);
            } else {
                $table->enum('jenis', $jenisEnum);
            }

            $table->enum('sumber', ['kamera', 'unggah'])->default('kamera');
            $table->string('path');
            $table->timestamp('diambil_at');
            $table->timestamp('exif_diambil_at')->nullable();
            $table->timestamp('disinkronkan_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('akurasi_m')->nullable();
            $table->unsignedInteger('lebar')->nullable();
            $table->unsignedInteger('tinggi')->nullable();
            $table->unsignedInteger('ukuran_byte')->nullable();
            $table->foreignId('depot_id')->constrained('depots')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['kunjungan_id', 'jenis']);
        });

        DB::statement('
            INSERT INTO kunjungan_fotos_baru
                (id, kunjungan_id, jenis, sumber, path, diambil_at, exif_diambil_at, disinkronkan_at, latitude, longitude, akurasi_m, lebar, tinggi, ukuran_byte, depot_id, deleted_at, created_at, updated_at)
            SELECT
                id, kunjungan_id, jenis, sumber, path, diambil_at, exif_diambil_at, disinkronkan_at, latitude, longitude, akurasi_m, lebar, tinggi, ukuran_byte, depot_id, deleted_at, created_at, updated_at
            FROM kunjungan_fotos
        ');

        Schema::drop('kunjungan_fotos');
        Schema::rename('kunjungan_fotos_baru', 'kunjungan_fotos');
    }
};
