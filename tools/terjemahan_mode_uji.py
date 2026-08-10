# -*- coding: utf-8 -*-
"""Menambahkan kunci mode uji ke lang/<kode>/kunjungan.php di keempat bahasa."""
import pathlib, re

BAHASA = ['id', 'en', 'zh_CN', 'zh_TW']
AKAR = pathlib.Path(__file__).resolve().parent.parent

T = {
 'mode_uji': ['Mode uji', 'Test mode', '测试模式', '測試模式'],
 'mode_uji_ket': [
   'Hanya muncul di lingkungan lokal saat VISIT_MODE_UJI dinyalakan. Alur, aturan, dan watermark tetap berjalan seperti biasa — yang digantikan hanya langkah membidik kamera.',
   'Only appears on a local environment when VISIT_MODE_UJI is on. The flow, rules and watermarking all still run — only the camera step is replaced.',
   '仅在本地环境且开启 VISIT_MODE_UJI 时出现。流程、规则与水印均照常运行，仅替代了取景拍摄这一步。',
   '僅在本機環境且開啟 VISIT_MODE_UJI 時出現。流程、規則與浮水印均照常運行，僅替代了取景拍攝這一步。'],
 'tempel_qr': ['Tempel isi QR', 'Paste QR content', '粘贴二维码内容', '貼上 QR 內容'],
 'tempel_qr_ket': [
   'Tempel isi QR freezer, atau pilih salah satu toko tanggungan Anda di bawah.',
   'Paste the freezer QR content, or pick one of your assigned stores below.',
   '粘贴冰柜二维码内容，或从下方选择您负责的门店。',
   '貼上冰櫃 QR 內容，或從下方選擇您負責的門店。'],
 'proses_qr': ['Proses', 'Process', '处理', '處理'],
 'pilih_toko_contoh': ['Isi dari toko tanggungan', 'Fill from an assigned store', '从负责门店填入', '從負責門店填入'],
 'foto_contoh': ['Gambar contoh', 'Sample image', '示例图片', '範例圖片'],
 'isi_semua_foto': ['Isi keenam foto sekaligus', 'Fill all six photos', '一次填入六张照片', '一次填入六張照片'],
}

def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"

for i, kode in enumerate(BAHASA):
    jalur = AKAR / 'lang' / kode / 'kunjungan.php'
    teks = jalur.read_text(encoding='utf-8')
    sisip = ""
    for k, v in T.items():
        if ("'" + k + "'") in teks:
            continue
        sisip += "    " + php_str(k) + " => " + php_str(v[i]) + ",\n"
    if sisip:
        teks = re.sub(r"\n\];\s*$", "\n" + sisip + "];\n", teks)
        jalur.write_text(teks, encoding='utf-8')
    print("%-6s +%d kunci" % (kode, len(T)))
