<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depots', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('service_minutes')->default(10);
            $table->time('jam_berangkat')->default('08:00:00');
            $table->unsignedSmallInteger('max_toko')->default(25);
            $table->unsignedSmallInteger('max_dus')->default(220);
            $table->unsignedSmallInteger('min_dus_per_toko')->default(5);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        // Depot pertama dibuat dari nilai config('ond.depot'/'kendaraan'/
        // 'min_dus_per_toko') yang sedang aktif di environment ini — supaya
        // depot produksi yang sudah live (Perawang) langsung dapat nilai
        // yang benar tanpa perlu diisi ulang manual, dan environment lokal
        // ikut dapat 1 depot default dari nilai dev-nya sendiri.
        $nama = (string) config('ond.depot.nama', 'Depot Utama');
        $kode = Str::upper(Str::limit(Str::slug($nama, '-'), 20, ''));

        DB::table('depots')->insert([
            'kode' => $kode,
            'nama' => $nama,
            'lat' => config('ond.depot.lat'),
            'lng' => config('ond.depot.lng'),
            'service_minutes' => (int) config('ond.depot.service_minutes', 10),
            'jam_berangkat' => Carbon::parse(config('ond.depot.jam_berangkat', '08:00'))->format('H:i:s'),
            'max_toko' => (int) config('ond.kendaraan.max_toko', 25),
            'max_dus' => (int) config('ond.kendaraan.max_dus', 220),
            'min_dus_per_toko' => (int) config('ond.min_dus_per_toko', 5),
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('depots');
    }
};
