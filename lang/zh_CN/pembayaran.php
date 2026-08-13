<?php

return [
    'status_pending' => '待处理',
    'status_lunas' => '已付款',
    'status_belum_lunas' => '未付款',

    'galat_bukan_selesai' => '订单 :kode 尚未完成，无法处理其付款。',

    // Pelunasan
    'judul_pelunasan' => '结算',
    'ket_pelunasan' => '按车辆、按天标记哪些商店已付款。只有已完成的订单才能处理。',
    'tombol_lunas' => '已付款',
    'tombol_belum_lunas' => '未付款',
    'tombol_lunas_semua' => '结清剩余',
    'konfirmasi_lunas_judul' => '标记为已付款',
    'konfirmasi_lunas_teks' => '将商店 :toko 标记为已付款 :nilai？',
    'konfirmasi_belum_lunas_judul' => '标记为未付款',
    'konfirmasi_belum_lunas_teks' => '商店 :toko 将移至未付款列表，稍后再收款。',
    'konfirmasi_lunas_semua_judul' => '结清此车辆剩余款项',
    'konfirmasi_lunas_semua_teks' => ':mobil 中所有尚未决定的商店都会被标记为已付款。已标记为未付款的商店不会改变。',
    'notif_lunas' => '商店 :toko 已标记为已付款。',
    'notif_belum_lunas' => '商店 :toko 已移至未付款列表。',
    'notif_lunas_semua' => '已将 :jumlah 家商店标记为已付款。',
    'tidak_ada_selesai' => '此日期尚无已完成的订单。',
    'tagihan' => '账单',
    'terkumpul' => '已收款',
    'menunggu_keputusan' => '等待处理',
    'sudah_tuntas' => '已全部处理',

    // Belum Lunas
    'judul_belum_lunas' => '未付款',
    'ket_belum_lunas' => '尚未付款的商店，不分日期。标记已付款会将账单计入今天的收入。',
    'kosong_belum_lunas' => '没有未付款的商店。',
    'tanggal_kirim' => '配送日期',
    'mobil_asal' => '原车辆',

    // Pendapatan
    'judul_pendapatan' => '收入',
    'ket_pendapatan' => '按结算日期而非配送日期统计的收入汇总。',
    'mode_hari' => '每日',
    'mode_bulan' => '每月',
    'mode_rentang' => '区间',
    'mode_semua' => '全部',
    'dari_tanggal' => '起始日期',
    'sampai_tanggal' => '结束日期',
    'total_pendapatan' => '总收入',
    'total_transaksi' => '总交易数',
    'kosong_pendapatan' => '此区间尚无收入。',
];
