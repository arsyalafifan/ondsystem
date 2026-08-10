# Panduan Menjalankan OND System

Aplikasi Laravel 13 + Livewire untuk sistem pemesanan & routing pengiriman,
memakai database MySQL. Panduan ini ditulis untuk yang baru pertama kali
menjalankan project Laravel.

---

## 1. Yang Harus Diinstall Dulu

| Software | Kegunaan | Link Download |
|---|---|---|
| **Git** | Untuk clone (download) kode dari GitHub | [git-scm.com](https://git-scm.com/downloads) |
| **PHP 8.3+** | Bahasa pemrograman utama aplikasi ini | lihat opsi di bawah |
| **Composer** | Package manager PHP (install library Laravel) | [getcomposer.org](https://getcomposer.org/download/) |
| **Node.js (LTS)** | Untuk build tampilan (CSS/JS) | [nodejs.org](https://nodejs.org) |
| **MySQL** | Database tempat data toko/pesanan disimpan | lihat opsi di bawah |

### Cara termudah (disarankan untuk pemula)

Daripada install PHP, Composer, dan MySQL satu per satu secara manual, pakai
paket all-in-one:

- **Windows** — install **[Laragon](https://laragon.org/download/)**: sekali
  install sudah dapat PHP, Composer, MySQL, dan Node sekaligus.
- **Mac** — install **[Herd](https://herd.laravel.com/)**: buatan tim Laravel
  sendiri, otomatis menyediakan PHP dan cocok untuk Laravel. Untuk MySQL,
  tambahkan lewat Herd atau pakai [DBngin](https://dbngin.com/).

Setelah salah satu di atas terpasang, buka terminal dan cek semuanya sudah
ada:

```bash
php -v
composer -V
node -v
npm -v
mysql --version
```

Kalau semua muncul versinya (bukan error "command not found"), lanjut ke
langkah berikutnya.

---

## 2. Clone Kode dari GitHub

Di terminal, pindah ke folder tempat menyimpan project (misal Desktop), lalu:

```bash
git clone https://github.com/arsyalafifan/ondsystem.git
cd ondsystem
```

---

## 3. Install Dependency Aplikasi

```bash
composer install
npm install
```

Ini mendownload semua library bantuan yang dipakai aplikasi. Bisa memakan
beberapa menit tergantung koneksi internet.

---

## 4. Siapkan File Konfigurasi (.env)

```bash
cp .env.example .env
php artisan key:generate
```

Perintah kedua membuat kunci keamanan unik untuk aplikasimu.

---

## 5. Buat Database

Buka MySQL (di Laragon klik tombol "Database", atau lewat terminal MySQL
langsung), lalu jalankan:

```sql
CREATE DATABASE ondsystem;
```

Buka file **`.env`** dengan text editor, cari bagian berikut dan sesuaikan
dengan setting MySQL kamu:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ondsystem
DB_USERNAME=root
DB_PASSWORD=
```

> Kalau pakai Laragon, biasanya `DB_USERNAME=root` dan `DB_PASSWORD`
> dikosongkan — itu sudah default-nya.

---

## 6. Isi Struktur & Data Contoh ke Database

```bash
php artisan migrate --seed
php artisan storage:link
```

`migrate --seed` membuat semua tabel database sekaligus mengisi data contoh
(akun user, toko, produk) supaya aplikasi langsung bisa dicoba.

---

## 7. Jalankan Aplikasinya

```bash
composer dev
```

Perintah ini otomatis menyalakan server web, antrian (queue), dan proses
build tampilan sekaligus dalam satu terminal. Biarkan terminal ini tetap
terbuka selama memakai aplikasi.

Setelah muncul tulisan server berjalan, buka browser ke:

```
http://localhost:8000
```

---

## 8. Login dengan Akun Contoh

Password semua akun contoh: **`password`**

| Peran | Email |
|---|---|
| Admin | `admin@ondsystem.test` |
| Sales | `sales@ondsystem.test` |
| Sales | `sales2@ondsystem.test` |
| Driver | `driver@ondsystem.test` |
| Driver | `driver2@ondsystem.test` |

---

## 9. Menguji Fitur Kunjungan Sales di HP

Fitur kunjungan sales memakai **kamera** (memindai QR freezer dan memotret
enam bukti kunjungan). Ada dua hal yang perlu diketahui sebelum mencobanya
dari HP.

### Kenapa membuka lewat IP komputer tidak bisa membuka kamera

Peramban hanya mengizinkan akses kamera pada halaman yang **aman**, yaitu
HTTPS atau `localhost`. Alamat seperti `http://192.168.1.2:8000` bukan
keduanya, jadi kamera diblokir — biasanya tanpa pesan yang jelas, tombolnya
sekadar tidak bereaksi.

Selain itu, `composer dev` menjalankan Vite yang membuat berkas `public/hot`.
Selama berkas itu ada, tampilan mengambil CSS/JS dari `localhost:5173` — dan
dari HP, `localhost` berarti HP itu sendiri. Akibatnya halaman terbuka tanpa
gaya sama sekali.

### Cara 1 — Terowongan HTTPS (paling mudah, Android & iPhone)

Sekali pasang:

```bash
brew install cloudflared        # Mac
# Windows: winget install --id Cloudflare.cloudflared
```

Setiap kali mau menguji, buka **dua terminal**:

```bash
# Terminal 1 — bangun aset lalu jalankan server
composer mobile
```

```bash
# Terminal 2 — buat alamat HTTPS
cloudflared tunnel --url http://localhost:8000
```

Terminal kedua akan menampilkan alamat seperti:

```
https://sesuatu-acak-di-sini.trycloudflare.com
```

Buka alamat itu di HP. Kamera langsung bisa dipakai karena alamatnya HTTPS.

Beberapa catatan:

- Alamatnya **berubah setiap kali** `cloudflared` dijalankan ulang. Itu wajar
  untuk keperluan uji coba.
- Alamat ini bisa dibuka siapa saja yang punya tautannya, jadi jangan
  dibiarkan menyala saat tidak dipakai. Tekan `Ctrl + C` untuk menutupnya.
- Gunakan `composer mobile`, **bukan** `composer dev`. Perintah itu membangun
  aset lebih dulu dan membuka server ke jaringan, jadi tampilannya tidak
  kehilangan CSS.

### Cara 2 — Tanpa HP dan tanpa kamera sama sekali

Untuk mencoba alur kunjungan dari laptop, nyalakan mode uji di `.env`:

```env
APP_ENV=local
VISIT_MODE_UJI=true
```

Lalu jalankan `php artisan config:clear` dan buka menu **Mulai Kunjungan**
sebagai sales. Akan muncul panel ungu berisi:

- kotak untuk menempel isi QR, lengkap dengan pilihan toko tanggungan Anda;
- tombol **Gambar contoh** pada tiap jenis foto;
- tombol **Isi keenam foto sekaligus**.

Yang digantikan hanya langkah membidik kamera. Penguraian QR, aturan
penolakan, pemberian watermark, dan perhitungan progres tetap berjalan persis
seperti aslinya, sehingga hasil ujinya bisa dipercaya.

Gambar contohnya sengaja bertuliskan **"CONTOH UJI - BUKAN FOTO ASLI"** agar
langsung ketahuan bila sampai tercampur dengan data sungguhan.

> Mode uji hanya hidup ketika `APP_ENV=local`. Di server sungguhan penanda
> ini diabaikan, jadi tidak ada jalan memasukkan foto selain lewat kamera.

### Cara 3 — Kabel USB (khusus Android, tanpa pasang apa pun)

1. Di HP: **Setelan → Opsi pengembang → USB debugging** dinyalakan.
2. Sambungkan HP ke komputer dengan kabel USB.
3. Di komputer, buka Chrome lalu ketik `chrome://inspect/#devices`.
4. Klik **Port forwarding…**, isi `8000` → `localhost:8000`, centang
   *Enable port forwarding*.
5. Di HP, buka `http://localhost:8000`.

Karena di HP alamatnya menjadi `localhost`, peramban menganggapnya aman dan
kamera bisa dipakai. Cara ini tidak butuh internet, tapi tidak tersedia untuk
iPhone.

---

## Catatan Tambahan

- **Peta**: fitur peta memakai layanan gratis (OpenStreetMap/OSRM), jadi
  butuh koneksi internet saat memakai fitur routing.
- **Mematikan aplikasi**: tekan `Ctrl + C` di terminal tempat `composer dev`
  berjalan.
- **Menjalankan lagi di lain waktu**: setelah setup pertama selesai, cukup
  masuk ke folder project lalu jalankan `composer dev` — tidak perlu ulangi
  langkah 3–6.
- **Menguji dari HP**: pakai `composer mobile`, bukan `composer dev`. Lihat
  langkah 9.

## Kalau Ada Error

- **`php: command not found`** — PHP belum terpasang / belum masuk PATH.
  Pastikan Laragon/Herd sudah dijalankan.
- **`SQLSTATE... Access denied`** — cek kembali `DB_USERNAME`/`DB_PASSWORD`
  di `.env` sesuai setting MySQL-mu.
- **`could not find driver`** — ekstensi PHP untuk MySQL (`pdo_mysql`) belum
  aktif — biasanya sudah otomatis aktif di Laragon/Herd.
- **Halaman di HP tampil polos tanpa warna** — dua kemungkinan:
  1. Berkas `public/hot` masih ada dari `composer dev` sebelumnya. Hentikan
     `composer dev`, lalu jalankan `composer mobile`.
  2. `TRUSTED_PROXIES` belum diisi di `.env`. Tanpa itu, aplikasi tidak tahu
     permintaannya datang lewat HTTPS, lalu menuliskan alamat aset berawalan
     `http://` pada halaman `https://`. Peramban memblokir campuran seperti
     itu, jadi CSS dan JavaScript tidak termuat sama sekali — halaman terbuka
     tapi tampil polos. Isi `TRUSTED_PROXIES=127.0.0.1,::1` lalu jalankan
     `php artisan config:clear`.
- **Tombol kamera tidak bereaksi di HP** — alamatnya belum HTTPS. Lihat
  langkah 9, Cara 1.
- **Kamera minta izin lalu ditolak terus** — buka setelan situs di peramban
  HP, hapus izin untuk alamat itu, lalu muat ulang halaman.
- **Di iPhone kamera tidak muncul dan tidak ada pesan apa pun** — Safari
  kadang menahan pemutaran video sampai layar disentuh sekali lagi. Sentuh
  bagian gambar kameranya. Kalau pemindai sudah berjalan tapi tidak ada
  getaran saat QR terbaca, itu wajar: iPhone tidak punya getaran di peramban,
  penggantinya bunyi pendek.
- **QR tidak terbaca, gambar buram, kamera tidak mau fokus** — padahal aplikasi
  kamera bawaan HP baik-baik saja. Penyebabnya hampir selalu sama: HP punya
  beberapa kamera belakang, dan peramban memilih lensa ultra-lebar yang
  fokusnya tetap sehingga tidak bisa menajamkan objek dekat.

  Yang bisa dilakukan, berurutan:
  1. **Sentuh gambar** di layar pemindai untuk memfokuskan ke titik itu.
  2. Tekan tombol **🔄** di pojok kanan atas untuk berpindah lensa. Pilihan ini
     diingat, jadi cukup sekali.
  3. Dekatkan HP sampai QR memenuhi kotak bidik.
  4. Kalau ruangannya gelap, nyalakan **🔦**.
