# OND Print Helper

Aplikasi kecil untuk Windows yang menjembatani tombol **"Cetak Langsung ke
Printer (Windows)"** di web OND System dengan printer dot-matrix fisik
(EPSON LX-310). Tanpa ini, mencetak dari browser selalu lewat proses
rasterisasi (halaman diubah jadi gambar dulu, baru dicetak) yang membuat
hasilnya kurang tajam dibanding aplikasi lama (Accurate). OND Print Helper
mengirim isi nota sebagai **perintah ESC/P asli** langsung ke printer, sama
seperti cara kerja Accurate — tanpa rasterisasi sama sekali.

## Cara kerja singkat

1. Web menghasilkan link khusus berawalan `ondprint://` yang isinya sudah
   ditandatangani server (berlaku 5 menit, tidak bisa dipakai ulang setelah
   nota berganti status).
2. Begitu link itu diklik, Windows menjalankan `OndPrintHelper.exe` dengan
   link tersebut sebagai argumen (persis seperti cara `mailto:` atau
   `zoommtg:` bekerja).
3. `.exe` ini mengambil isi ESC/P dari server, lalu mengirimkannya *mentah*
   ke printer lewat Windows Print Spooler mode **RAW** — bukan dicetak lewat
   dialog cetak biasa.

Tidak ada proses yang berjalan terus di background/system tray — `.exe` ini
hanya aktif sebentar saat dipanggil, lalu keluar sendiri.

## Instalasi (sekali saja, per komputer yang mencetak)

1. Ambil `OndPrintHelper.exe` **dari folder `publish`**, bukan folder
   `bin\Release\net9.0-windows\win-x64\` di luarnya:
   ```
   bin/Release/net9.0-windows/win-x64/publish/OndPrintHelper.exe
   ```
   Ini berkas tunggal berukuran sekitar 113 MB, sudah menyertakan semuanya
   (termasuk .NET runtime) — tidak butuh berkas `.dll` lain di sebelahnya.
   **Kalau ukurannya jauh lebih kecil (ratusan KB saja) atau ada banyak
   `.dll` lain di folder yang sama, berarti salah ambil** — itu berkas dari
   folder `bin\Release\net9.0-windows\win-x64\` (tanpa `publish`), yang
   butuh ~200 berkas `.dll` pendamping dan tidak akan berjalan sendirian
   (gejalanya: Windows bilang "The application to execute does not exist:
   ...\OndPrintHelper.dll").
2. Salin **hanya berkas `.exe` itu saja** ke lokasi permanen di komputer
   tersebut, misalnya `C:\OndPrintHelper\OndPrintHelper.exe`. **Jangan taruh
   di folder Downloads atau folder yang sering dibersihkan** — begitu
   didaftarkan, link cetak akan selalu memanggil persis lokasi ini.
3. Klik dua kali `OndPrintHelper.exe`.
4. Akan muncul jendela **Pengaturan** berisi daftar printer yang terpasang
   di Windows (diambil otomatis) — pilih printer dot-matrix yang dituju
   (mis. `EPSON LX-310 ESC/P`, atau `EPSON LX-310 ESC/P (Copy 1)`, apa pun
   nama persisnya di komputer itu), lalu klik **Simpan**.
5. Selesai — satu klik itu sekaligus mendaftarkan `.exe` ke Windows dan
   menyimpan pilihan printernya. Tombol "Cetak Langsung ke Printer
   (Windows)" di web sekarang akan langsung mencetak dari komputer ini.

Tidak perlu hak admin/UAC — semuanya ditulis ke `HKEY_CURRENT_USER`, bukan
`HKEY_LOCAL_MACHINE`, jadi berlaku untuk akun Windows yang sedang dipakai
instal.

### Nama printer beda-beda per komputer? Tidak masalah

Nama printer **tidak** dikirim dari web/server — setiap komputer menyimpan
pilihannya sendiri lewat jendela Pengaturan di atas. Jadi kalau di satu
komputer printernya terdaftar sebagai `EPSON LX-310 ESC/P` dan di komputer
lain sebagai `EPSON LX-310 ESC/P (Copy 1)`, tidak perlu menyamakan nama
apa pun — cukup pilih printer yang sesuai saat pemasangan di masing-masing
komputer.

### Ganti printer belakangan

Jalankan `OndPrintHelper.exe` lagi kapan saja (klik dua kali seperti biasa)
— jendela Pengaturan yang sama akan muncul, menampilkan pilihan yang
sedang aktif, tinggal pilih printer lain lalu **Simpan**.

### Uninstall

Klik dua kali `OndPrintHelper.exe`, lalu klik tombol **Lepas Pendaftaran**
di jendela Pengaturan. Atau lewat Command Prompt:

```
OndPrintHelper.exe --uninstall
```

Keduanya menghapus pendaftaran `ondprint://` beserta pilihan printer yang
tersimpan dari registry; tidak menghapus berkas `.exe` itu sendiri (hapus
manual kalau memang tidak dipakai lagi).

## Build ulang dari sumber

Sumbernya ada di `print-helper/OndPrintHelper/` (proyek .NET). Build dari
mesin apa pun (termasuk macOS/Linux — tidak perlu Windows untuk build,
hanya untuk menjalankan hasilnya) dengan .NET SDK terpasang:

```bash
cd print-helper/OndPrintHelper
dotnet publish -c Release
```

Hasilnya (satu berkas `.exe` mandiri, sudah menyertakan .NET runtime — tidak
perlu instal .NET terpisah di komputer target) ada di:

```
bin/Release/net9.0-windows/win-x64/publish/OndPrintHelper.exe
```

## Kalau ada masalah saat mencetak

`.exe` ini menampilkan jendela pesan error kalau ada yang gagal (link
kedaluwarsa, printer tidak ditemukan, layanan Print Spooler mati, dll) —
bacaan pesannya sudah dalam Bahasa Indonesia dan menjelaskan penyebabnya.

**Kalau diklik dua kali dan sama sekali tidak muncul jendela apa pun**
(tidak ada pesan error, tidak ada jendela Pengaturan): cek berkas log di
`%LOCALAPPDATA%\OndPrintHelper\error.log` — semua kegagalan, termasuk yang
terjadi sebelum jendela sempat tampil, tercatat di sana beserta rinciannya.
Kalau berkas itu sendiri tidak ada, kemungkinan besar Windows SmartScreen
menghentikan `.exe`-nya sebelum sempat jalan sama sekali (klik kanan
berkasnya → Properties → centang "Unblock" di bagian bawah kalau ada, atau
saat peringatan SmartScreen muncul klik "More info" → "Run anyway").

Tombol **"Unduh ESC/P (.prn)"** di halaman cetak tetap tersedia sebagai
jalur cadangan manual kalau OND Print Helper belum terpasang atau
bermasalah — unduh berkasnya, lalu kirim manual ke printer (mis. lewat
antrean printer "Generic / Text Only" di port yang sama, atau
`copy /b nama-berkas.prn USB001` dari Command Prompt).
