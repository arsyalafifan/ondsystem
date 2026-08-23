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
    'konfirmasi_lunas_judul' => 'Mark as Paid',
    'konfirmasi_lunas_teks' => 'Mark store :toko as paid for :nilai?',
    'konfirmasi_belum_lunas_judul' => 'Mark as Unpaid',
    'konfirmasi_belum_lunas_teks' => 'Store :toko will move to the Unpaid list and be billed later.',
    'notif_lunas' => 'Store :toko marked as paid.',
    'notif_belum_lunas' => 'Store :toko moved to Unpaid.',
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
    'nominal_cash' => 'Cash Amount',
    'nominal_transfer' => 'Transfer Amount',
    'nominal_pas' => 'The amount matches the bill exactly.',
    'nominal_kurang' => ':sisa short of matching the bill.',
    'nominal_lebih' => ':lebih over the bill.',
    'galat_nominal_negatif' => 'Cash and transfer amounts cannot be negative.',
    'galat_nominal_tidak_sesuai' => 'Cash + transfer (:total) does not match the bill (:tagihan).',
    'total_cash' => 'Cash',
    'total_transfer' => 'Transfer',
];
