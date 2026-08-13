<?php

return [
    'status_pending' => '待處理',
    'status_lunas' => '已付款',
    'status_belum_lunas' => '未付款',

    'galat_bukan_selesai' => '訂單 :kode 尚未完成，無法處理其付款。',

    // Pelunasan
    'judul_pelunasan' => '結算',
    'ket_pelunasan' => '按車輛、按天標記哪些商店已付款。只有已完成的訂單才能處理。',
    'tombol_lunas' => '已付款',
    'tombol_belum_lunas' => '未付款',
    'tombol_lunas_semua' => '結清剩餘',
    'konfirmasi_lunas_judul' => '標記為已付款',
    'konfirmasi_lunas_teks' => '將商店 :toko 標記為已付款 :nilai？',
    'konfirmasi_belum_lunas_judul' => '標記為未付款',
    'konfirmasi_belum_lunas_teks' => '商店 :toko 將移至未付款清單，稍後再收款。',
    'konfirmasi_lunas_semua_judul' => '結清此車輛剩餘款項',
    'konfirmasi_lunas_semua_teks' => ':mobil 中所有尚未決定的商店都會被標記為已付款。已標記為未付款的商店不會改變。',
    'notif_lunas' => '商店 :toko 已標記為已付款。',
    'notif_belum_lunas' => '商店 :toko 已移至未付款清單。',
    'notif_lunas_semua' => '已將 :jumlah 家商店標記為已付款。',
    'tidak_ada_selesai' => '此日期尚無已完成的訂單。',
    'tagihan' => '帳單',
    'terkumpul' => '已收款',
    'menunggu_keputusan' => '等待處理',
    'sudah_tuntas' => '已全部處理',

    // Belum Lunas
    'judul_belum_lunas' => '未付款',
    'ket_belum_lunas' => '尚未付款的商店，不分日期。標記已付款會將帳單計入今天的收入。',
    'kosong_belum_lunas' => '沒有未付款的商店。',
    'tanggal_kirim' => '配送日期',
    'mobil_asal' => '原車輛',

    // Pendapatan
    'judul_pendapatan' => '收入',
    'ket_pendapatan' => '按結算日期而非配送日期統計的收入彙總。',
    'mode_hari' => '每日',
    'mode_bulan' => '每月',
    'mode_rentang' => '區間',
    'mode_semua' => '全部',
    'dari_tanggal' => '起始日期',
    'sampai_tanggal' => '結束日期',
    'total_pendapatan' => '總收入',
    'total_transaksi' => '總交易數',
    'kosong_pendapatan' => '此區間尚無收入。',
];
