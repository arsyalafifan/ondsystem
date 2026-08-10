# -*- coding: utf-8 -*-
"""Kunci untuk pemilihan toko lewat nomor aset dan pindai QR di input pesanan."""
import pathlib, re

BAHASA = ['id', 'en', 'zh_CN', 'zh_TW']
AKAR = pathlib.Path(__file__).resolve().parent.parent

BARU = {
 'cara_ketik': ['Ketik', 'Type', '输入', '輸入'],
 'cara_pindai': ['Pindai QR', 'Scan QR', '扫码', '掃碼'],
 'pindai_qr_toko': ['Pindai QR freezer toko', 'Scan the store’s freezer QR', '扫描门店冰柜二维码', '掃描門店冰櫃 QR code'],
 'ket_pindai_qr_toko': [
   'Arahkan kamera ke QR pada freezer. Nomor asetnya dicocokkan dengan master toko, dan tokonya langsung terpilih.',
   'Point the camera at the QR on the freezer. Its asset ID is matched against the store master and the store is selected automatically.',
   '将镜头对准冰柜上的二维码。系统会用资产编号匹配商店主数据并自动选中该门店。',
   '將鏡頭對準冰櫃上的 QR code。系統會用資產編號比對商店主檔並自動選取該門店。'],
 'notif_toko_dari_qr': [
   'Toko terpilih dari QR: :nama (:aset).',
   'Store selected from QR: :nama (:aset).',
   '已通过二维码选中门店：:nama（:aset）。',
   '已透過 QR code 選取門店：:nama（:aset）。'],
 'toko_tanpa_aset': ['tanpa nomor aset', 'no asset ID', '无资产编号', '無資產編號'],
}

# Teks yang diperbarui, bukan ditambahkan.
DIPERBARUI = {
 'cari_toko': [
   'Ketik nama, kode, alamat, atau nomor aset (IDNAH…)…',
   'Type a name, code, address, or asset ID (IDNAH…)…',
   '输入名称、编号、地址或资产编号（IDNAH…）…',
   '輸入名稱、編號、地址或資產編號（IDNAH…）…'],
}

def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"

for i, kode in enumerate(BAHASA):
    jalur = AKAR / 'lang' / kode / 'pesanan.php'
    teks = jalur.read_text(encoding='utf-8')

    for k, v in DIPERBARUI.items():
        teks = re.sub(
            r"^(\s*'" + re.escape(k) + r"' => ).*?,$",
            lambda m: m.group(1) + php_str(v[i]) + ",",
            teks, count=1, flags=re.MULTILINE | re.DOTALL,
        )

    sisip = ""
    for k, v in BARU.items():
        if ("'" + k + "'") in teks:
            continue
        sisip += "    " + php_str(k) + " => " + php_str(v[i]) + ",\n"

    if sisip:
        teks = re.sub(r"\n\];\s*$", "\n" + sisip + "];\n", teks)

    jalur.write_text(teks, encoding='utf-8')
    print("%-6s +%d kunci, %d diperbarui" % (kode, len(BARU), len(DIPERBARUI)))
