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
| Driver | `driver@ondsystem.test` |
| Driver | `driver2@ondsystem.test` |

---

## Catatan Tambahan

- **Peta**: fitur peta memakai layanan gratis (OpenStreetMap/OSRM), jadi
  butuh koneksi internet saat memakai fitur routing.
- **Mematikan aplikasi**: tekan `Ctrl + C` di terminal tempat `composer dev`
  berjalan.
- **Menjalankan lagi di lain waktu**: setelah setup pertama selesai, cukup
  masuk ke folder project lalu jalankan `composer dev` — tidak perlu ulangi
  langkah 3–6.

## Kalau Ada Error

- **`php: command not found`** — PHP belum terpasang / belum masuk PATH.
  Pastikan Laragon/Herd sudah dijalankan.
- **`SQLSTATE... Access denied`** — cek kembali `DB_USERNAME`/`DB_PASSWORD`
  di `.env` sesuai setting MySQL-mu.
- **`could not find driver`** — ekstensi PHP untuk MySQL (`pdo_mysql`) belum
  aktif — biasanya sudah otomatis aktif di Laragon/Herd.
