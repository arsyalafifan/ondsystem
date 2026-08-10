<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Target kunjungan
    |--------------------------------------------------------------------------
    | Batas jumlah toko yang boleh ditugaskan kepada satu sales, sekaligus
    | menjadi target kunjungannya dalam satu minggu.
    */

    'maks_toko_per_sales' => (int) env('VISIT_MAKS_TOKO_PER_SALES', 120),

    /*
    |--------------------------------------------------------------------------
    | Hari kerja
    |--------------------------------------------------------------------------
    | Periode kunjungan berjalan Senin sampai Sabtu, lalu dimulai lagi dari nol
    | pada Senin berikutnya. Angka mengikuti ISO-8601: 1 = Senin, 7 = Minggu.
    */

    'hari_mulai' => 1,
    'hari_selesai' => 6,

    /*
    |--------------------------------------------------------------------------
    | Foto bukti kunjungan
    |--------------------------------------------------------------------------
    | Keenam foto ini wajib ada sebelum kunjungan bisa diselesaikan. Urutannya
    | menentukan urutan pengambilan di layar sales.
    */

    'foto_wajib' => [
        'sales_depan_toko',
        'freezer_sebelum',
        'freezer_sesudah',
        'spanduk',
        'flag_hanger',
        'suhu_freezer',
    ],

    'foto' => [
        // Lebar maksimal setelah diperkecil. Foto kamera ponsel bisa 4000px
        // lebih, jauh melebihi kebutuhan untuk bukti kunjungan.
        'lebar_maks' => (int) env('VISIT_FOTO_LEBAR_MAKS', 1280),
        'mutu_jpeg' => (int) env('VISIT_FOTO_MUTU', 82),
        'ukuran_maks_kb' => (int) env('VISIT_FOTO_UKURAN_MAKS_KB', 8192),
        'disk' => env('VISIT_FOTO_DISK', 'public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lokasi
    |--------------------------------------------------------------------------
    | Titik GPS dicatat bila peramban mengizinkan. Kunjungan tidak diblokir
    | ketika izin ditolak — sinyal GPS memang sering buruk di dalam ruko —
    | tetapi kunjungan tanpa lokasi ditandai agar admin bisa menelusurinya.
    |
    | 'jarak_wajar_m' adalah batas selisih antara titik pengambilan foto dan
    | koordinat toko yang masih dianggap masuk akal.
    */

    'lokasi' => [
        'wajib' => (bool) env('VISIT_LOKASI_WAJIB', false),
        'jarak_wajar_m' => (int) env('VISIT_JARAK_WAJAR_M', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode uji
    |--------------------------------------------------------------------------
    | Menyalakan tombol "gambar contoh" pada layar kunjungan, sehingga alur
    | enam foto bisa dicoba tanpa kamera. Hanya berlaku ketika APP_ENV=local;
    | di lingkungan lain penandanya diabaikan. Lihat App\Support\ModeUji.
    */

    'mode_uji' => (bool) env('VISIT_MODE_UJI', false),

    /*
    |--------------------------------------------------------------------------
    | Pengenal aset pada QR code freezer
    |--------------------------------------------------------------------------
    | Isi QR berbentuk daftar berlabel bahasa Mandarin, misalnya:
    |
    |   客户名称：IDN Halocoko
    |   资产编号：IDNAH202528004381
    |   产品型号：SD-280
    |
    | Label di bawah ini dipakai untuk menemukan nomor asetnya.
    */

    'qr' => [
        'label_aset' => ['资产编号', '資產編號', 'asset id', 'asset no', 'nomor aset'],
        'label_pelanggan' => ['客户名称', '客戶名稱', 'customer', 'nama pelanggan'],
        'label_model' => ['产品型号', '產品型號', 'model', 'tipe'],
        // Bentuk nomor aset yang sah, dipakai saat QR hanya berisi nomornya.
        'pola_aset' => '/^[A-Z]{2,6}[A-Z0-9]{8,24}$/i',
    ],

];
