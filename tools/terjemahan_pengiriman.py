# -*- coding: utf-8 -*-
"""Membuat lang/<kode>/pengiriman.php untuk keempat bahasa dari satu tabel sumber."""
import pathlib

BAHASA = ['id', 'en', 'zh_CN', 'zh_TW']
AKAR = pathlib.Path(__file__).resolve().parent.parent

T = {
 # --- Status kunjungan ---
 'status_pending': ['Belum dikirim', 'Not delivered', '未配送', '未配送'],
 'status_selesai': ['Terkirim', 'Delivered', '已送达', '已送達'],
 'status_dibatalkan': ['Dibatalkan', 'Cancelled', '已取消', '已取消'],
 'jenis_normal': ['Rute', 'Route', '路线', '路線'],
 'jenis_kampas': ['Kampas', 'Offload', '甩货', '甩貨'],
 'label_kampas': ['Kampas', 'Offload', '甩货', '甩貨'],
 # --- Ringkasan progres ---
 'dus_terkirim': ['Dus terkirim', 'Boxes delivered', '已送箱数', '已送箱數'],
 'dus_sisa': ['Sisa di mobil', 'Left on the vehicle', '车上剩余', '車上剩餘'],
 'target_dus': ['Target dus', 'Box target', '目标箱数', '目標箱數'],
 'toko_dibatalkan': ['Toko dibatalkan', 'Cancelled stores', '已取消门店', '已取消門店'],
 'ket_progres_dus': [
   'Kemajuan dihitung dari dus yang keluar dari mobil, bukan dari jumlah toko. Sisa yang tidak diampaskan tetap terlihat sebagai kekurangan.',
   'Progress counts boxes that left the vehicle, not the number of stores. Anything not offloaded still shows as a shortfall.',
   '进度按离开车辆的箱数计算，而非门店数量。未甩出的余量仍显示为缺口。',
   '進度按離開車輛的箱數計算，而非門店數量。未甩出的餘量仍顯示為缺口。'],
 # --- Aksi ---
 'aksi_batalkan': ['Batalkan', 'Cancel', '取消', '取消'],
 'aksi_coret': ['Coret nota', 'Amend receipt', '划单', '劃單'],
 'aksi_kampas': ['Kampas sisa', 'Offload leftovers', '甩余货', '甩餘貨'],
 # --- Pembatalan ---
 'judul_batalkan': ['Batalkan Pengiriman ke :toko', 'Cancel Delivery to :toko', '取消配送至 :toko', '取消配送至 :toko'],
 'ket_batalkan': [
   'Toko ini tidak jadi dikirimi. Kewajiban Anda atas toko ini dianggap tuntas, tetapi :dus dus miliknya tetap ada di mobil dan bisa diampaskan ke toko lain.',
   'This store will not be delivered to. Your obligation for it is considered done, but its :dus boxes stay on the vehicle and can be offloaded elsewhere.',
   '该门店不再配送。您对它的责任视为完成，但其 :dus 箱仍留在车上，可甩给其他门店。',
   '該門店不再配送。您對它的責任視為完成，但其 :dus 箱仍留在車上，可甩給其他門店。'],
 'notif_dibatalkan': [
   'Pengiriman ke :toko dibatalkan. :dus dus kini bisa diampaskan.',
   'Delivery to :toko cancelled. :dus boxes are now available to offload.',
   '已取消配送至 :toko，:dus 箱现可甩货。',
   '已取消配送至 :toko，:dus 箱現可甩貨。'],
 # --- Coret nota ---
 'judul_coret': ['Coret Nota — :toko', 'Amend Receipt — :toko', '划单 — :toko', '劃單 — :toko'],
 'ket_coret': [
   'Kurangi jumlah pada baris yang tidak jadi diterima toko. Sisanya masih di mobil dan bisa diampaskan. Stok gudang hanya berkurang sebanyak yang benar-benar diterima.',
   'Reduce the lines the store did not take. The remainder stays on the vehicle and can be offloaded. Warehouse stock only drops by what was actually received.',
   '减少门店未接收的行数。余量留在车上可甩货，仓库库存仅按实收扣减。',
   '減少門店未接收的行數。餘量留在車上可甩貨，倉庫庫存僅按實收扣減。'],
 'dipesan': ['Dipesan', 'Ordered', '订购', '訂購'],
 'diterima': ['Diterima', 'Received', '实收', '實收'],
 'total_diterima': ['Total diterima', 'Total received', '实收合计', '實收合計'],
 'simpan_coret': ['Simpan & Selesaikan', 'Save & Complete', '保存并完成', '儲存並完成'],
 'notif_coret': [
   'Nota :toko dicoret dan pengiriman tercatat. Sisanya bisa diampaskan.',
   'Receipt for :toko amended and the delivery recorded. The remainder can be offloaded.',
   ':toko 的单据已划减并记录配送，余量可甩货。',
   ':toko 的單據已劃減並記錄配送，餘量可甩貨。'],
 'kurang_kirim': ['Kurang kirim', 'Short delivery', '少送', '少送'],
 # --- Kampas ---
 'judul_kampas': ['Kampas Sisa Muatan', 'Offload Leftover Load', '甩出余货', '甩出餘貨'],
 'ket_kampas': [
   'Jual sisa muatan ke toko mana pun di jalan. Toko yang masih punya pesanan berjalan tetap boleh dipilih, dan tidak ada batas minimal dus — yang dijual adalah sisa.',
   'Sell the leftover load to any store on the way. Stores with an open order may still be chosen, and there is no minimum box count — this is leftover stock.',
   '把余货卖给路上任意门店。有进行中订单的门店也可选择，且无最低箱数限制。',
   '把餘貨賣給路上任意門店。有進行中訂單的門店也可選擇，且無最低箱數限制。'],
 'jatah_kampas': ['Sisa yang bisa diampaskan', 'Available to offload', '可甩数量', '可甩數量'],
 'tersedia': ['Tersedia', 'Available', '可用', '可用'],
 'tidak_ada_sisa': ['Belum ada sisa muatan', 'No leftover load yet', '暂无余货', '暫無餘貨'],
 'tidak_ada_sisa_ket': [
   'Sisa muncul setelah ada toko yang dibatalkan atau nota yang dicoret.',
   'Leftovers appear after a store is cancelled or a receipt is amended.',
   '取消门店或划减单据后才会出现余货。',
   '取消門店或劃減單據後才會出現餘貨。'],
 'simpan_kampas': ['Simpan Kampas', 'Save Offload', '保存甩货', '儲存甩貨'],
 'notif_kampas': [
   'Kampas :dus dus ke :toko tercatat.',
   'Offloaded :dus boxes to :toko.',
   '已向 :toko 甩出 :dus 箱。',
   '已向 :toko 甩出 :dus 箱。'],
 'pilih_toko_kampas': ['Toko tujuan kampas', 'Store to offload to', '甩货目标门店', '甩貨目標門店'],
 # --- Galat ---
 'galat_stop_tuntas': [
   'Kunjungan ini sudah selesai atau dibatalkan, jadi tidak bisa diubah lagi.',
   'This stop is already completed or cancelled, so it can no longer be changed.',
   '该站点已完成或已取消，无法再更改。',
   '該站點已完成或已取消，無法再更改。'],
 'galat_kampas_tak_bisa_dibatalkan': [
   'Kunjungan kampas tidak bisa dibatalkan.',
   'An offload stop cannot be cancelled.',
   '甩货站点无法取消。',
   '甩貨站點無法取消。'],
 'galat_coret_melebihi': [
   'Jumlah :produk melebihi yang dipesan (maksimal :maks dus).',
   'The amount for :produk exceeds what was ordered (max :maks boxes).',
   ':produk 的数量超过订购量（最多 :maks 箱）。',
   ':produk 的數量超過訂購量（最多 :maks 箱）。'],
 'galat_coret_tanpa_perubahan': [
   'Tidak ada yang dikurangi. Kalau toko menerima semuanya, pakai tombol upload nota biasa.',
   'Nothing was reduced. If the store took everything, use the normal receipt upload.',
   '没有任何减少。若门店全数接收，请使用普通上传单据。',
   '沒有任何減少。若門店全數接收，請使用普通上傳單據。'],
 'galat_coret_di_bawah_minimal': [
   'Sisa yang diterima :total dus, di bawah batas minimal :min dus per toko. Kalau toko menolak seluruhnya, batalkan saja pengirimannya.',
   'Only :total boxes would be received, below the :min box minimum per store. If the store refuses everything, cancel the delivery instead.',
   '实收仅 :total 箱，低于每店最低 :min 箱。若门店全部拒收，请直接取消配送。',
   '實收僅 :total 箱，低於每店最低 :min 箱。若門店全部拒收，請直接取消配送。'],
 'galat_kampas_kosong': [
   'Isi dulu jumlah dus yang mau diampaskan.',
   'Enter how many boxes you want to offload first.',
   '请先填写要甩出的箱数。',
   '請先填寫要甩出的箱數。'],
 'galat_kampas_melebihi': [
   'Sisa :produk hanya :tersedia dus, tidak cukup untuk :diminta dus.',
   'Only :tersedia boxes of :produk remain, not enough for :diminta.',
   ':produk 仅剩 :tersedia 箱，不足 :diminta 箱。',
   ':produk 僅剩 :tersedia 箱，不足 :diminta 箱。'],
}

def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"

for i, kode in enumerate(BAHASA):
    baris = ["<?php", "", "return ["]
    for k, v in T.items():
        baris.append("    " + php_str(k) + " => " + php_str(v[i]) + ",")
    baris.append("];")
    (AKAR / 'lang' / kode / 'pengiriman.php').write_text("\n".join(baris) + "\n", encoding='utf-8')
    print("%-6s pengiriman.php (%d kunci)" % (kode, len(T)))
