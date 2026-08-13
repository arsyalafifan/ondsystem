<?php

return [
    'status_pending' => 'Menunggu',
    'status_lunas' => 'Lunas',
    'status_belum_lunas' => 'Belum Lunas',

    'galat_bukan_selesai' => 'Pesanan :kode belum berstatus SELESAI, belum bisa diproses pembayarannya.',

    // Pelunasan
    'judul_pelunasan' => 'Pelunasan',
    'ket_pelunasan' => 'Tandai toko yang sudah membayar, per mobil per hari. Hanya pesanan berstatus SELESAI yang bisa diproses.',
    'tombol_lunas' => 'Lunas',
    'tombol_belum_lunas' => 'Belum Lunas',
    'tombol_lunas_semua' => 'Lunasi Sisanya',
    'konfirmasi_lunas_judul' => 'Tandai Lunas',
    'konfirmasi_lunas_teks' => 'Tandai toko :toko lunas sebesar :nilai?',
    'konfirmasi_belum_lunas_judul' => 'Tandai Belum Lunas',
    'konfirmasi_belum_lunas_teks' => 'Toko :toko akan dipindah ke daftar Belum Lunas dan ditagih kemudian.',
    'konfirmasi_lunas_semua_judul' => 'Lunasi Sisa Mobil Ini',
    'konfirmasi_lunas_semua_teks' => 'Semua toko yang belum diputuskan di :mobil akan ditandai lunas. Toko yang sudah ditandai Belum Lunas tidak berubah.',
    'notif_lunas' => 'Toko :toko ditandai lunas.',
    'notif_belum_lunas' => 'Toko :toko dipindah ke Belum Lunas.',
    'notif_lunas_semua' => ':jumlah toko ditandai lunas.',
    'tidak_ada_selesai' => 'Belum ada pesanan SELESAI pada tanggal ini.',
    'tagihan' => 'Tagihan',
    'terkumpul' => 'Terkumpul',
    'menunggu_keputusan' => 'Menunggu keputusan',
    'sudah_tuntas' => 'Sudah tuntas',

    // Belum Lunas
    'judul_belum_lunas' => 'Belum Lunas',
    'ket_belum_lunas' => 'Toko yang belum membayar, dari hari apa pun. Menandai lunas menghitung tagihannya ke pendapatan hari ini.',
    'kosong_belum_lunas' => 'Tidak ada toko yang belum lunas.',
    'tanggal_kirim' => 'Tanggal Kirim',
    'mobil_asal' => 'Mobil Asal',

    // Pendapatan
    'judul_pendapatan' => 'Pendapatan',
    'ket_pendapatan' => 'Rekap pendapatan menurut tanggal pelunasan, bukan tanggal pengiriman.',
    'mode_hari' => 'Harian',
    'mode_bulan' => 'Bulanan',
    'mode_rentang' => 'Rentang',
    'mode_semua' => 'Semua',
    'dari_tanggal' => 'Dari Tanggal',
    'sampai_tanggal' => 'Sampai Tanggal',
    'total_pendapatan' => 'Total Pendapatan',
    'total_transaksi' => 'Total Transaksi',
    'kosong_pendapatan' => 'Belum ada pendapatan pada rentang ini.',
];
