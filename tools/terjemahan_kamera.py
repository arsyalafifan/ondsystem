# -*- coding: utf-8 -*-
"""Kunci untuk kontrol kamera (ganti lensa, senter, sentuh-fokus)."""
import pathlib, re

BAHASA = ['id', 'en', 'zh_CN', 'zh_TW']
AKAR = pathlib.Path(__file__).resolve().parent.parent

T = {
 'ganti_lensa': ['Ganti lensa', 'Switch lens', '切换镜头', '切換鏡頭'],
 'ganti_lensa_ket': [
   'Ponsel dengan beberapa kamera belakang kadang memakai lensa ultra-lebar yang tidak bisa fokus dekat. Tekan tombol ini untuk berpindah ke lensa lain.',
   'Phones with several rear cameras sometimes pick an ultra-wide lens that cannot focus up close. Tap this to switch to another lens.',
   '多后置摄像头的手机有时会选用无法近距离对焦的超广角镜头。点此切换到其他镜头。',
   '多後鏡頭的手機有時會選用無法近距離對焦的超廣角鏡頭。點此切換到其他鏡頭。'],
 'senter': ['Senter', 'Flashlight', '手电筒', '手電筒'],
 'sentuh_fokus': ['Sentuh gambar untuk memfokuskan', 'Tap the image to focus', '点击画面对焦', '點擊畫面對焦'],
 'qr_sulit_terbaca': [
   'QR sulit terbaca? Dekatkan sampai kode memenuhi kotak, lalu sentuh layar untuk memfokuskan. Kalau tetap buram, coba ganti lensa.',
   'Hard to read? Move closer until the code fills the box, then tap to focus. If it stays blurry, try switching lens.',
   '难以识别？靠近至二维码填满方框，然后点击对焦。若仍模糊，请尝试切换镜头。',
   '難以辨識？靠近至 QR code 填滿方框，然後點擊對焦。若仍模糊，請嘗試切換鏡頭。'],
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
