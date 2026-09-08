<?php

namespace Database\Factories;

use App\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depot>
 */
class DepotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('DEPOT???')),
            'nama' => 'Depot '.fake()->city(),
            // Tetap (bukan acak) — sama seperti bawaan config('ond.depot.*')
            // sebelum multi-depot. Banyak test lama menaruh toko di
            // koordinat tetap dekat titik ini, mengasumsikan depot ada di
            // sini; koordinat depot yang acak antar-run membuat urutan
            // sapuan rute (sweep algorithm, berbasis sudut dari depot) ikut
            // berubah-ubah dan test jadi flaky. Test yang sengaja butuh
            // depot di lokasi lain (mis. DepotIsolationTest) boleh override
            // lewat ->state(['lat' => ..., 'lng' => ...]).
            'lat' => -6.2,
            'lng' => 106.816666,
            'service_minutes' => 10,
            'jam_berangkat' => '08:00:00',
            'max_toko' => 25,
            'max_dus' => 220,
            'min_dus_per_toko' => 5,
            'aktif' => true,
        ];
    }
}
