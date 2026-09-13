<?php

return [

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
    | Kelima foto ini wajib ada sebelum kunjungan bisa diselesaikan. Urutannya
    | menentukan urutan pengambilan di layar sales.
    |
    | 'flag_hanger' sengaja TIDAK disertakan di sini lagi (dinonaktifkan,
    | bukan dihapus dari App\Enums\JenisFotoKunjungan) — kunjungan lama yang
    | sudah pernah menyimpan foto berjenis itu tetap valid dan tetap tampil
    | apa adanya, ia hanya tidak lagi dituntut untuk kunjungan baru.
    */

    'foto_wajib' => [
        'sales_depan_toko',
        'freezer_sebelum',
        'freezer_sesudah',
        'spanduk',
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
    | Kunjungan offline
    |--------------------------------------------------------------------------
    | Sales di daerah tanpa sinyal mengerjakan kunjungannya di perangkat,
    | lalu mengirimnya menyusul. Waktu kunjungan otomatis berasal dari jam
    | PONSEL — yang bisa diubah sendiri oleh pemakainya — jadi batas di bawah
    | ini yang menjaganya tetap masuk akal.
    |
    | 'toleransi_maju_menit' memberi kelonggaran untuk jam ponsel yang
    | melenceng sedikit ke depan; lebih dari itu ditolak. 'maks_umur_hari'
    | membatasi seberapa lama sebuah kunjungan boleh ditahan sebelum
    | dikirim — cukup panjang untuk perjalanan luar kota yang tertunda,
    | tapi tidak membuka pintu bagi kunjungan "susulan" berbulan-bulan.
    |
    | 'maks_per_kiriman' membatasi jumlah kunjungan per permintaan, karena
    | tiap kunjungan membawa foto-fotonya sekaligus. Perangkat sebaiknya
    | mengirim satu per satu supaya tidak menabrak post_max_size PHP.
    */

    'offline' => [
        'toleransi_maju_menit' => (int) env('VISIT_OFFLINE_TOLERANSI_MAJU_MENIT', 60),
        'maks_umur_hari' => (int) env('VISIT_OFFLINE_MAKS_UMUR_HARI', 14),
        'maks_per_kiriman' => (int) env('VISIT_OFFLINE_MAKS_PER_KIRIMAN', 5),

        // Saklar utama mode offline di perangkat: service worker hanya
        // didaftarkan ketika ini menyala, dan HANYA untuk peran sales.
        // Sengaja mati secara bawaan supaya penyalaannya jadi keputusan
        // sadar, bukan efek samping penempatan kode.
        //
        // Mematikannya kembali TIDAK perlu deploy: halaman selalu diambil
        // network-first, jadi begitu perangkat sales daring sekali saja, ia
        // membaca tanda mati ini lalu mencabut service worker-nya sendiri
        // dan membuang cache-nya. Itulah kill-switch yang dijanjikan saat
        // risiko PWA dibahas — tidak ada perangkat yang bisa terkunci di
        // versi lama tanpa jalan pulang.
        'pwa_aktif' => (bool) env('VISIT_PWA_AKTIF', false),
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
