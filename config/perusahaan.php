<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Perusahaan
    |--------------------------------------------------------------------------
    | Dipakai sebagai kop surat pada nota cetak (lihat resources/views/cetak).
    | Diambil lewat .env agar bisa berubah tanpa deploy ulang.
    */

    'nama' => env('PERUSAHAAN_NAMA', 'PT. ICE CHOCO DUMAI'),
    'tagline' => env('PERUSAHAAN_TAGLINE', 'Distributor Es Cream Halocoko'),
    'alamat' => env('PERUSAHAAN_ALAMAT', 'Kota Dumai - Riau'),
    'telepon' => env('PERUSAHAAN_TELEPON', '085371640422'),
    'email' => env('PERUSAHAAN_EMAIL', 'icechocodumai@gmail.com'),
    'bank' => env('PERUSAHAAN_BANK', 'No Rek: BCA ICE CHOCO DUMAI PT 8085888231'),

];
