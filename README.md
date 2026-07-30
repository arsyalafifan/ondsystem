# OND System — Sistem Pemesanan & Distribusi

Aplikasi Laravel 13 + Livewire 3 untuk mengelola pesanan toko, menyusun rute
pengiriman secara otomatis, dan memantau progres driver di lapangan.

Inti masalah yang diselesaikan: penyusunan rute yang selama ini dikerjakan
manual di mapmarker.app — menempel penanda satu per satu, membagi toko ke
mobil dengan perkiraan, lalu menebak urutan kunjungan. Pekerjaan itu sekarang
selesai dengan satu tombol, dan hasilnya masih bisa disunting admin.

---

## Menjalankan

```bash
composer install
npm install

cp .env.example .env          # sesuaikan bagian DB_* dan DEPOT_*
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

composer dev                  # server + queue + vite + log, sekaligus
```

Buka http://localhost:8000.

Akun contoh dari seeder (kata sandi semuanya `password`):

| Peran  | Email                    |
| ------ | ------------------------ |
| Admin  | `admin@ondsystem.test`   |
| Sales  | `sales@ondsystem.test`   |
| Driver | `driver@ondsystem.test`  |
| Driver | `driver2@ondsystem.test` |

Seeder mengisi 5 wilayah, 8 produk, dan 114 toko di sekitar Jakarta. Sekitar 8%
toko sengaja dibiarkan tanpa koordinat supaya alur pelengkapan titik ikut bisa
dicoba.

---

## Alur kerja

```
Sales/Admin   Input Pesanan ─────► ORDER
Admin         Review        ─────► PROCESS
Admin         Generate Routing     (draft: mobil + urutan kunjungan terbentuk)
Admin         Setujui Routing ───► DELIVERY
Driver        Kirim + upload nota► SELESAI
```

Pembatalan bisa dilakukan pada status ORDER, PROCESS, dan DELIVERY selama foto
nota belum diunggah.

### Perlakuan stok

Stok dipisah menjadi dua angka supaya pembatalan tidak pernah merusak catatan
gudang:

- **`stok`** — barang yang benar-benar ada di rak. Baru berkurang saat driver
  mengunggah foto nota.
- **`stok_reserved`** — barang yang sudah dijanjikan ke pesanan berjalan. Naik
  begitu pesanan dibuat, turun saat pesanan batal atau terkirim.

Yang bisa dipesan adalah selisih keduanya. Setiap pergerakan tercatat di tabel
`stok_mutasis`.

---

## Cara kerja mesin routing

Persoalannya adalah _Capacitated Vehicle Routing Problem_: membagi N toko ke
sejumlah mobil dengan batas muatan, lalu mengurutkan kunjungan tiap mobil
sependek mungkin. Dikerjakan dalam empat tahap.

**1. Pisah per wilayah** — satu mobil tidak melintasi dua wilayah, sesuai
pembagian tanggung jawab lapangan. Bisa dimatikan lewat centang di halaman
Generate Routing bila mobil boleh menyeberang.

**2. Bagi menjadi muatan mobil** ([`PengelompokKendaraan`](app/Services/Routing/PengelompokKendaraan.php))
— algoritma _sweep_: tiap toko diberi sudut dilihat dari depot, diurutkan
memutar, lalu dipotong menjadi juring. Hasilnya area yang menyatu per mobil,
sama seperti cara admin membagi manual.

Dua hal yang dijaga di sini:

- Jumlah mobil ditekan seminimal mungkin — satu mobil tambahan berarti satu
  driver dan satu tangki solar tambahan.
- Isinya dibuat sebanding. Pemotongan naif menyisakan mobil yang hanya
  mengangkut dua toko; itu dicegah dengan memeriksa "apakah sisanya masih muat
  di mobil berikutnya?" sebelum sebuah juring ditutup. Titik awal pemotongan
  juga dicoba pada banyak posisi, lalu diambil yang kelompoknya paling rapat.

**3. Urutkan kunjungan** ([`PengurutKunjungan`](app/Services/Routing/PengurutKunjungan.php))
— tetangga terdekat untuk urutan awal, lalu diperbaiki dengan 2-opt (membongkar
jalur yang saling menyilang) dan Or-opt (memindahkan satu sampai tiga toko ke
posisi yang lebih masuk akal).

**4. Ambil jarak jalan sebenarnya** — matriks jarak dan garis rute diminta ke
OSRM, sehingga angka kilometer dan perkiraan jam tiba mengikuti jalan, bukan
garis lurus.

### Ketika OSRM tidak bisa dihubungi

Perhitungan tetap jalan memakai jarak haversine dikali faktor kelokan jalan.
Rute yang dihasilkan sedikit kurang presisi tapi urutannya tetap layak pakai,
dan halaman memberi tahu admin bahwa angkanya perkiraan. Operasional tidak
pernah berhenti karena layanan luar mati.

### Hasil pada data contoh

101 pesanan, 2.217 dus, 5 wilayah → 12 mobil dalam ~14 detik dengan OSRM aktif.
Tidak ada mobil yang melewati batas 25 toko maupun 220 dus. Dibanding urutan
kunjungan yang disusun sembarangan, total waktu perjalanan turun sekitar 31%.

---

## Bahasa

Antarmuka tersedia dalam empat bahasa, dengan bahasa Indonesia sebagai bawaan:

| Kode    | Bahasa           | `<html lang>` |
| ------- | ---------------- | ------------- |
| `id`    | Bahasa Indonesia | `id`          |
| `en`    | English          | `en`          |
| `zh_CN` | 简体中文         | `zh-Hans`     |
| `zh_TW` | 繁體中文         | `zh-Hant`     |

Pemilih bahasa ada di bagian bawah menu samping, dan juga di halaman masuk agar
pengguna bisa memilih sebelum mengenali istilah Indonesianya.

**Cara pilihan bahasa diingat.** Pilihan tersimpan di kolom `users.locale`,
jadi ikut ke perangkat mana pun pengguna masuk — driver yang berganti ponsel
tidak perlu memilih ulang. Pengunjung yang belum masuk memakai sesi, dan
pilihannya dipindahkan ke akun begitu ia berhasil masuk.

Selain teks, yang ikut berganti:

- **Angka dan mata uang** — `2.110` / `Rp 1.450.000` dalam bahasa Indonesia,
  `2,110` / `Rp 1,450,000` dalam bahasa Inggris dan Mandarin. Dipakai lewat
  direktif Blade `@angka()` dan `@rupiah()`.
- **Tanggal** — `26 Jul 2026`, `Jul 26, 2026`, `2026年7月26日`. Memakai pola
  bawaan tiap bahasa (`isoFormat('ll')`), bukan pola tetap.
- **Pesan galat validasi**, termasuk nama kolomnya.
- **Nama kendaraan** — "Mobil 1" / "Vehicle 1" / "车辆 1". Nama ini dibentuk
  saat ditampilkan, bukan saat disimpan, supaya rute yang dibuat admin
  berbahasa Indonesia tetap terbaca benar oleh driver berbahasa Mandarin.

Nama toko, produk, dan wilayah adalah data milik pengguna, jadi tidak
diterjemahkan.

### Menambah atau mengubah terjemahan

Berkas terjemahan ada di `lang/{id,en,zh_CN,zh_TW}/`, dibagi per bagian:
`umum`, `nav`, `auth`, `status`, `pesanan`, `routing`, `driver`, `dashboard`,
`master`, `validation`, `pagination`, `passwords`.

Menambah bahasa baru: buat folder `lang/<kode>/`, salin isi `lang/id/`,
terjemahkan, lalu daftarkan kodenya di [`config/bahasa.php`](config/bahasa.php).

Kelengkapan kunci dijaga oleh tes, bukan oleh ketelitian saat menyunting —
[`tests/Feature/BahasaTest.php`](tests/Feature/BahasaTest.php) menggagalkan
build kalau ada kunci yang terisi di satu bahasa tapi terlewat di bahasa lain,
kalau ada nilai yang kosong, atau kalau ada halaman yang menampilkan kunci
mentah seperti `pesanan.judul_buat`. Ini penting karena kunci yang terlewat
tidak menimbulkan galat — ia hanya muncul sebagai teks aneh di layar pengguna.

---

## Peta

Memakai Leaflet + ubin OpenStreetMap — tanpa API key dan tanpa biaya.

- **Halaman Generate Routing** — penanda bernomor urut kunjungan berwarna sesuai
  mobilnya, garis rute mengikuti jalan. Toko bisa dipindah antar mobil, urutan
  digeser naik-turun, atau satu mobil diminta dihitung ulang. Semua perubahan
  langsung memperbarui jarak, durasi, dan ETA.
- **Dashboard** — penanda diwarnai menurut status pesanan, bukan menurut mobil,
  supaya terlihat mana yang sudah beres.
- **Master Toko** — peta pemilih titik: klik atau geser penanda untuk menaruh
  koordinat toko.
- **Layar driver** — tiap toko punya tombol navigasi yang membuka Google Maps
  di ponsel.

### Melengkapi koordinat toko

Tiga jalan, dari yang paling cepat:

1. **Impor CSV** berisi kolom `latitude`/`longitude`.
2. **Pencarian alamat otomatis** lewat Nominatim, satu per satu atau 20 toko
   sekaligus. Hasil yang cuma setingkat kota ditandai agar dikoreksi manual.
3. **Klik di peta**, untuk alamat gang atau perumahan yang tidak terbaca mesin.

Toko tanpa koordinat dilewati saat routing — bukan menggagalkan prosesnya — dan
dilaporkan ke admin.

---

## Pengaturan

Semua di `.env`, dibaca lewat [`config/ond.php`](config/ond.php):

| Variabel                                 | Arti                                     |
| ---------------------------------------- | ---------------------------------------- |
| `ROUTING_MAX_TOKO` / `ROUTING_MAX_DUS`   | batas muatan per kendaraan (25 / 220)    |
| `ROUTING_MIN_DUS_PER_TOKO`               | minimal pesanan per toko (5)             |
| `DEPOT_LAT` / `DEPOT_LNG` / `DEPOT_NAMA` | titik gudang, awal dan akhir tiap rute   |
| `DEPOT_SERVICE_MINUTES`                  | waktu bongkar per toko, untuk hitung ETA |
| `DEPOT_JAM_BERANGKAT`                    | jam berangkat default                    |
| `OSRM_URL`                               | server OSRM                              |
| `OSRM_ENABLED`                           | `false` untuk memaksa hitung garis lurus |
| `NOMINATIM_EMAIL`                        | kontak wajib untuk pemakaian Nominatim   |
| `APP_LOCALE`                             | bahasa bawaan (`id`)                     |
| `APP_FALLBACK_LOCALE`                    | cadangan bila kunci belum diterjemahkan (`en`) |

Batas 25 toko / 220 dus juga bisa diubah sesaat di halaman Generate Routing
tanpa menyentuh `.env`.

### Kalau volume sudah besar

Server OSRM dan Nominatim publik punya batas laju pemakaian. Untuk volume
harian yang tinggi, jalankan OSRM sendiri:

```bash
docker run -t -v "${PWD}/data:/data" ghcr.io/project-osrm/osrm-backend \
  osrm-routed --algorithm mld /data/indonesia-latest.osrm
```

lalu ubah `OSRM_URL=http://localhost:5000`. Tidak ada perubahan kode.

---

## Pengujian

```bash
php artisan test
```

91 tes, mencakup:

- **[`tests/Unit/MesinRoutingTest.php`](tests/Unit/MesinRoutingTest.php)** —
  batas muatan tidak pernah dilanggar, tidak ada pesanan hilang atau ganda,
  wilayah tidak tercampur, muatan terbagi sebanding, dan hasilnya tetap ada
  saat OSRM mati.
- **[`tests/Feature/AlurPesananTest.php`](tests/Feature/AlurPesananTest.php)** —
  aturan minimal dus, satu pesanan aktif per toko, dan pengembalian stok.
- **[`tests/Feature/AlurRoutingTest.php`](tests/Feature/AlurRoutingTest.php)** —
  alur penuh dari PROCESS sampai SELESAI, termasuk pemindahan toko antar mobil
  dan pembatalan pesanan yang sudah masuk rute.
- **[`tests/Feature/HalamanTest.php`](tests/Feature/HalamanTest.php)** —
  setiap halaman tampil dan setiap peran hanya bisa membuka haknya.
- **[`tests/Feature/BahasaTest.php`](tests/Feature/BahasaTest.php)** —
  kelengkapan kunci di keempat bahasa, penyimpanan pilihan bahasa, dan setiap
  halaman tampil dalam keempat bahasa tanpa kunci mentah yang bocor.

Mode ketat Eloquent (`shouldBeStrict`) menyala saat pengujian, bukan hanya di
lingkungan pengembangan. Dengan begitu relasi yang lupa di-eager-load dan
atribut yang tidak terambil menggagalkan tes, alih-alih baru terlihat sebagai
galat di layar pengguna.

Tes tidak pernah menghubungi OSRM maupun Nominatim (`OSRM_ENABLED=false` di
`phpunit.xml`), jadi hasilnya konsisten dan cepat.

---

## Peta berkas

```
app/
  Enums/                    StatusPesanan, PeranPengguna
  Services/
    Peta/                   OsrmClient, NominatimGeocoder, Geo, MatriksJarak
    Routing/                MesinRouting, PengelompokKendaraan, PengurutKunjungan
    PesananService.php      aturan pesanan dan perlakuan stok
    RoutingService.php      jembatan mesin routing dengan basis data
  Livewire/
    Pesanan/                input dan daftar pesanan
    Routing/                generate routing dan riwayat
    Driver/                 pilih mobil dan daftar kunjungan
    Master/                 toko, produk, wilayah
  Support/Bahasa.php        pilihan bahasa, angka, dan rupiah
  Http/Middleware/          AturBahasa, PastikanPeran
lang/
  id/ en/ zh_CN/ zh_TW/     terjemahan, kunci identik di keempatnya
resources/js/
  peta-rute.js              peta multi-kendaraan
  peta-pemilih.js           peta pemilih titik toko
```
