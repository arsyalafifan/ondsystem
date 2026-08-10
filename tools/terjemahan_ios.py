# -*- coding: utf-8 -*-
"""Kunci untuk penanganan Safari iOS yang menolak memutar video kamera."""
import pathlib, re

BAHASA = ['id', 'en', 'zh_CN', 'zh_TW']
AKAR = pathlib.Path(__file__).resolve().parent.parent

T = {
 'sentuh_untuk_mulai': [
   'Sentuh gambar untuk memulai kamera.',
   'Tap the preview to start the camera.',
   '点击画面以启动摄像头。',
   '點擊畫面以啟動鏡頭。'],
 'sentuh_untuk_mulai_ket': [
   'Safari di iPhone kadang menahan kamera sampai layar disentuh sekali lagi.',
   'Safari on iPhone sometimes holds the camera until you tap once more.',
   'iPhone 上的 Safari 有时需要再点击一次才会启动摄像头。',
   'iPhone 上的 Safari 有時需要再點擊一次才會啟動鏡頭。'],
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
