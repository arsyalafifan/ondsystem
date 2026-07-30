<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aturan Bisnis Utama
    |--------------------------------------------------------------------------
    | Nilai-nilai ini dipakai oleh validasi pesanan dan mesin routing.
    | Ubah lewat .env agar tidak perlu deploy ulang saat aturan operasional
    | berubah.
    */

    'min_dus_per_toko' => (int) env('ROUTING_MIN_DUS_PER_TOKO', 5),

    'kendaraan' => [
        'max_toko' => (int) env('ROUTING_MAX_TOKO', 25),
        'max_dus' => (int) env('ROUTING_MAX_DUS', 220),
    ],

    /*
    |--------------------------------------------------------------------------
    | Depot / Gudang
    |--------------------------------------------------------------------------
    | Titik awal dan akhir setiap rute kendaraan.
    */

    'depot' => [
        'nama' => env('DEPOT_NAMA', 'Gudang Pusat'),
        'lat' => (float) env('DEPOT_LAT', -6.2),
        'lng' => (float) env('DEPOT_LNG', 106.816666),
        // Waktu bongkar rata-rata per toko, dipakai untuk estimasi jam selesai.
        'service_minutes' => (int) env('DEPOT_SERVICE_MINUTES', 10),
        // Jam berangkat default untuk menghitung ETA tiap toko.
        'jam_berangkat' => env('DEPOT_JAM_BERANGKAT', '08:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OSRM - jarak & durasi jalan sebenarnya
    |--------------------------------------------------------------------------
    | Server demo publik punya rate limit. Kalau volume sudah besar, jalankan
    | OSRM sendiri lewat Docker lalu ganti OSRM_URL ke http://localhost:5000.
    */

    'osrm' => [
        'url' => rtrim((string) env('OSRM_URL', 'https://router.project-osrm.org'), '/'),
        'timeout' => (int) env('OSRM_TIMEOUT', 20),
        // Batas jumlah titik per permintaan /table. Server demo menolak
        // matriks yang terlalu besar, jadi kita pecah bila melebihi ini.
        'max_table_size' => (int) env('OSRM_MAX_TABLE_SIZE', 100),
        'enabled' => (bool) env('OSRM_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nominatim - geocoding alamat menjadi koordinat
    |--------------------------------------------------------------------------
    | Kebijakan pemakaian wajar: maksimal 1 permintaan per detik dan wajib
    | mengirim User-Agent yang bisa dihubungi.
    */

    'nominatim' => [
        'url' => rtrim((string) env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'), '/'),
        'email' => env('NOMINATIM_EMAIL'),
        'country' => env('GEOCODE_COUNTRY', 'id'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 15),
        'rate_limit_ms' => (int) env('NOMINATIM_RATE_LIMIT_MS', 1100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Peta
    |--------------------------------------------------------------------------
    */

    'peta' => [
        'tile_url' => env('MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        'zoom_default' => (int) env('MAP_ZOOM_DEFAULT', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warna kendaraan pada peta dan daftar
    |--------------------------------------------------------------------------
    | Dipakai berulang: Mobil 1 memakai indeks 0, Mobil 2 indeks 1, dst.
    */

    'warna_kendaraan' => [
        '#2563eb', '#f59e0b', '#7c3aed', '#059669', '#dc2626',
        '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5',
    ],

];
