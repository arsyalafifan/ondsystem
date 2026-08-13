<?php

return [
    'status_pending' => 'Pending',
    'status_lunas' => 'Paid',
    'status_belum_lunas' => 'Unpaid',

    'galat_bukan_selesai' => 'Order :kode is not COMPLETED yet, its payment can\'t be processed.',

    // Pelunasan
    'judul_pelunasan' => 'Settlement',
    'ket_pelunasan' => 'Mark which stores have paid, per vehicle per day. Only COMPLETED orders can be processed.',
    'tombol_lunas' => 'Paid',
    'tombol_belum_lunas' => 'Unpaid',
    'tombol_lunas_semua' => 'Settle the Rest',
    'konfirmasi_lunas_judul' => 'Mark as Paid',
    'konfirmasi_lunas_teks' => 'Mark store :toko as paid for :nilai?',
    'konfirmasi_belum_lunas_judul' => 'Mark as Unpaid',
    'konfirmasi_belum_lunas_teks' => 'Store :toko will move to the Unpaid list and be billed later.',
    'konfirmasi_lunas_semua_judul' => 'Settle the Rest of This Vehicle',
    'konfirmasi_lunas_semua_teks' => 'Every undecided store in :mobil will be marked paid. Stores already marked Unpaid are left unchanged.',
    'notif_lunas' => 'Store :toko marked as paid.',
    'notif_belum_lunas' => 'Store :toko moved to Unpaid.',
    'notif_lunas_semua' => ':jumlah stores marked as paid.',
    'tidak_ada_selesai' => 'No COMPLETED orders on this date yet.',
    'tagihan' => 'Bill',
    'terkumpul' => 'Collected',
    'menunggu_keputusan' => 'Awaiting decision',
    'sudah_tuntas' => 'Fully settled',

    // Belum Lunas
    'judul_belum_lunas' => 'Unpaid',
    'ket_belum_lunas' => 'Stores that haven\'t paid, from any day. Marking paid counts its bill toward today\'s revenue.',
    'kosong_belum_lunas' => 'No unpaid stores.',
    'tanggal_kirim' => 'Delivery Date',
    'mobil_asal' => 'Original Vehicle',

    // Pendapatan
    'judul_pendapatan' => 'Revenue',
    'ket_pendapatan' => 'Revenue recap by settlement date, not delivery date.',
    'mode_hari' => 'Daily',
    'mode_bulan' => 'Monthly',
    'mode_rentang' => 'Range',
    'mode_semua' => 'All Time',
    'dari_tanggal' => 'From Date',
    'sampai_tanggal' => 'To Date',
    'total_pendapatan' => 'Total Revenue',
    'total_transaksi' => 'Total Transactions',
    'kosong_pendapatan' => 'No revenue in this range yet.',
];
