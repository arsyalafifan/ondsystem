<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bahasa yang tersedia
    |--------------------------------------------------------------------------
    | Kunci array harus sama dengan nama folder di dalam lang/.
    |
    |   nama   : ditampilkan pada pemilih bahasa, ditulis dalam bahasa itu
    |            sendiri supaya bisa dikenali penutur aslinya.
    |   singkat: label pendek untuk tombol pemilih di layar sempit.
    |   html   : nilai atribut lang pada tag <html>, memakai tanda hubung
    |            sesuai BCP 47, bukan garis bawah seperti nama folder.
    |   arah   : arah tulisan. Keempat bahasa ini kiri-ke-kanan, tapi kolom
    |            ini disiapkan agar penambahan bahasa Arab nanti tidak perlu
    |            mengubah tata letak.
    */

    'tersedia' => [
        'id' => [
            'nama' => 'Bahasa Indonesia',
            'singkat' => 'ID',
            'html' => 'id',
            'arah' => 'ltr',
        ],
        'en' => [
            'nama' => 'English',
            'singkat' => 'EN',
            'html' => 'en',
            'arah' => 'ltr',
        ],
        'zh_CN' => [
            'nama' => '简体中文',
            'singkat' => '简',
            'html' => 'zh-Hans',
            'arah' => 'ltr',
        ],
        'zh_TW' => [
            'nama' => '繁體中文',
            'singkat' => '繁',
            'html' => 'zh-Hant',
            'arah' => 'ltr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kunci sesi
    |--------------------------------------------------------------------------
    | Tempat menyimpan pilihan bahasa pengunjung yang belum masuk. Pengguna
    | yang sudah masuk pilihannya tersimpan di kolom users.locale.
    */

    'kunci_sesi' => 'bahasa',

];
