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
| Sales  | `sales2@ondsystem.test`  |
| Driver | `driver@ondsystem.test`  |
| Driver | `driver2@ondsystem.test` |

Seeder mengisi 5 wilayah, 8 produk, dan 114 toko di sekitar Jakarta, lalu
membagi toko berfreezer kepada kedua sales sebagai tanggungan kunjungan bulan
berjalan. Sebagian kecil toko sengaja dibiarkan tanpa koordinat dan tanpa nomor
aset freezer, supaya alur pelengkapan data ikut bisa dicoba.

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

### Memilih toko saat input pesanan

Dua jalan, dan keduanya melewati pemeriksaan yang sama — toko harus aktif dan
belum punya pesanan berjalan:

- **Ketik** — nama, kode, alamat, atau **nomor aset freezer** (`IDNAH…`).
  Nomor aset dirapikan lebih dulu menjadi huruf besar tanpa spasi, jadi
  `idnah 2025 2800 4381` sama saja dengan `IDNAH202528004381`. Karena nomor
  aset unik, mengetik nomor lengkap menyisakan tepat satu toko; kecocokan
  persis juga dinaikkan ke urutan teratas.
- **Pindai QR** — kamera membaca QR pada freezer, nomor asetnya dicocokkan
  dengan `tokos.asset_id`, dan tokonya langsung terpilih. Stiker yang sama
  dipakai untuk kunjungan sales, jadi satu QR berlaku untuk kedua keperluan.

Toko yang masih punya pesanan berjalan ditolak **sejak pemindaian**, bukan
setelah seluruh produk terisi.

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

## Visit Sales — kunjungan rutin sales ke toko

Selain distribusi barang, sistem ini memantau kunjungan rutin sales ke toko.
Transaksinya berjenjang: **periode mingguan → sales → kunjungan per toko →
foto bukti**.

```
Admin   Penugasan Toko          tetapkan daftar toko per sales (bulanan, maks 120)
        ↓
Sistem  Periode mingguan        dibuka otomatis tiap Senin, ditutup Sabtu
        ↓
Sales   Pindai QR freezer   ──► toko dikenali dari nomor asetnya
        Ambil 6 foto        ──► kamera langsung, watermark dibubuhkan server
        Selesaikan          ──► kunjungan tercatat
        ↓
Admin   Pantau progres, tinjau laporan toko tutup
```

Periode lama tidak pernah dihapus — hitungan dimulai dari nol tiap Senin, tapi
riwayat minggu-minggu sebelumnya tetap bisa dibuka.

### Pengenalan toko lewat QR code freezer

Kunjungan hanya bisa dimulai dengan memindai QR yang tertempel pada freezer.
Tidak ada jalan memilih toko dari daftar, karena QR itulah bukti bahwa sales
benar-benar berdiri di depan freezer yang bersangkutan.

Isi QR berbentuk daftar berlabel bahasa Mandarin:

```
客户名称：IDN Halocoko
资产编号：IDNAH202528004381
产品型号：SD-280
```

Yang dipakai adalah nomor aset (`资产编号`), yang dicocokkan dengan kolom
`tokos.asset_id`. Perhatikan titik dua lebar `：` (U+FF1A) yang berbeda dari
titik dua biasa — [`PenguraiQr`](app/Services/Kunjungan/PenguraiQr.php)
menerima keduanya, juga QR yang hanya berisi nomor asetnya saja.

### Enam foto bukti

| Foto | Kapan diambil |
| ---- | ------------- |
| Sales di depan toko | saat tiba, papan nama toko terlihat |
| Freezer sebelum dibersihkan | sebelum menyentuh apa pun |
| Freezer sesudah dibersihkan | dari sudut yang sama, agar bisa dibandingkan |
| Spanduk toko | seluruh spanduk masuk bingkai |
| Flag hanger toko | cukup jauh agar posisi pemasangan terlihat |
| Suhu freezer | dekat, sampai angkanya terbaca |

**Foto tidak bisa diunggah dari galeri.** Aplikasi membaca aliran kamera
langsung lewat `getUserMedia`, bukan `<input type="file">` — atribut `capture`
pada input berkas hanya berupa saran bagi peramban, dan di banyak ponsel
pemakainya tetap bisa memilih gambar lama.

**Pemilihan lensa.** Ponsel masa kini punya beberapa kamera belakang, dan
`facingMode: environment` boleh dijawab dengan lensa mana pun — banyak
perangkat menjawabnya dengan ultra-lebar yang fokusnya tetap dan tidak akan
pernah bisa menajamkan stiker QR dari dekat. Karena itu
[`kamera-util.js`](resources/js/kamera-util.js) memilih lensa utama sejak
awal, menyalakan autofokus menerus, dan menyediakan tombol ganti lensa,
senter, serta sentuh-untuk-fokus. Pilihan lensanya diingat di perangkat.

Pembacaan QR memakai `BarcodeDetector` bawaan peramban bila tersedia — jauh
lebih cepat dan lebih tahan gambar buram. Chrome di Android punya, Safari di
iOS tidak. Tanpa pembaca bawaan, tiap bingkai menjalankan satu sapuan
bergantian: potongan tengah untuk QR yang memenuhi kotak bidik, lalu seluruh
bingkai yang diperkecil untuk kode yang kecil dan agak jauh dari tengah.
Menjalankan keduanya sekaligus pada resolusi penuh membuat lajunya turun
sampai beberapa bingkai per detik di iPhone, dan pemindai terasa seperti tidak
bekerja.

**Perbedaan Safari iOS yang perlu ditangani.** Safari kerap menolak
`video.play()` karena konteks sentuhan dianggap hilang setelah menunggu
`getUserMedia`. Penolakan itu berupa Promise yang gagal: kalau di-await tanpa
penangkap, seluruh penyalaan kamera berhenti diam-diam — tanpa gambar, tanpa
pesan, tanpa jejak di log. Semua pemutaran karena itu lewat `mainkanVideo()`
yang menangkap kegagalannya, melaporkannya ke layar, dan mempersilakan
pengguna memulai ulang dengan menyentuh gambar. Safari juga tidak mengenal
Vibration API sama sekali, jadi tanda kode terbaca berlapis: getaran bila ada,
ditambah bunyi pendek yang berjalan di mana saja.

**Watermark dibubuhkan di server, memakai jam server.** Kalau penandaan
dikerjakan di peramban, sales cukup memundurkan jam ponselnya untuk membuat
foto lama tampak baru — persis hal yang ingin dicegah. Yang tercetak: hari,
tanggal, bulan, tahun, jam sampai detik, nama toko, nomor aset, nama sales,
dan titik GPS bila peramban mengizinkan.

Titik GPS berasal dari peramban karena hanya di sanalah GPS bisa dibaca.
Kunjungan tidak diblokir ketika izin lokasi ditolak — sinyal memang sering
buruk di dalam ruko — tetapi jarak antara titik foto dan koordinat toko ikut
dicatat, dan selisih di atas `VISIT_JARAK_WAJAR_M` ditandai agar admin bisa
memeriksanya.

### Aturan yang dijaga sistem

- **Satu toko satu sales.** Admin tidak bisa menaruh toko yang sama di daftar
  dua sales dalam bulan yang sama; ditolak oleh batasan unik di basis data,
  bukan hanya oleh formulir.
- **Satu toko satu kunjungan per minggu.** Sales kedua yang memindai QR toko
  yang sudah dikunjungi akan ditolak, dengan keterangan siapa yang sudah
  mengunjunginya.
- **Hanya toko yang ditugaskan.** Memindai QR toko di luar daftar tanggungan
  ditolak, sehingga angka target tidak bisa dikaburkan.
- **Enam foto wajib lengkap** sebelum kunjungan bisa diselesaikan.
- **Maksimal 120 toko per sales**, diatur lewat `VISIT_MAKS_TOKO_PER_SALES`.

### Toko tutup

Sales tidak bisa menyatakan sendiri sebuah toko tutup. Ia mengirim laporan
beserta keterangan keadaannya, lalu admin membenarkan atau menolak.

Toko yang laporannya **dibenarkan keluar dari penyebut target minggu itu** —
kalau tanggungannya 120 toko dan 5 di antaranya tutup, progres dihitung dari
115. Sales tidak dirugikan oleh keadaan yang bukan kendalinya. Laporan yang
**ditolak** mengembalikan toko ke daftar wajib kunjung.

### Menguji dari ponsel

Akses kamera memerlukan **HTTPS** di luar `localhost` — syarat peramban, bukan
aplikasi. Membuka lewat IP jaringan lokal (`http://192.168.x.x:8000`) tidak
cukup, dan kamera akan diblokir tanpa pesan yang jelas.

```bash
composer mobile                                  # bangun aset + server 0.0.0.0
cloudflared tunnel --url http://localhost:8000   # terminal lain
```

Alamat `https://…trycloudflare.com` yang muncul bisa dibuka dari HP mana pun.
Pakai `composer mobile`, bukan `composer dev`: yang kedua menyalakan Vite dan
membuat `public/hot`, sehingga aset diarahkan ke `localhost:5173` — alamat yang
dari HP berarti HP itu sendiri.

**Proksi harus dipercaya.** cloudflared menangani HTTPS lalu meneruskannya ke
Laravel sebagai permintaan HTTP biasa. Tanpa `TRUSTED_PROXIES`, Laravel
menyangka halaman diakses lewat http dan menuliskan URL aset berawalan
`http://` di halaman `https://` — peramban memblokirnya sebagai muatan
campuran, dan seluruh CSS serta JavaScript gagal termuat. Gejalanya menipu:
halaman tetap terbuka, hanya tampil polos, dan kamera tidak jalan karena
berkas JS-nya memang tidak pernah sampai.

Bawaannya sudah benar (`127.0.0.1,::1`) dan dijaga oleh
[`tests/Feature/ProksiTest.php`](tests/Feature/ProksiTest.php).

### Mode uji

Untuk mencoba alur kunjungan tanpa kamera sama sekali, nyalakan di `.env`:

```env
VISIT_MODE_UJI=true
```

Layar sales akan menampilkan kotak tempel isi QR dan tombol gambar contoh
untuk tiap jenis foto. Yang digantikan hanya langkah membidik kamera —
penguraian QR, aturan penolakan, watermark, dan perhitungan progres tetap
berjalan seperti aslinya. Gambar contohnya bertuliskan "CONTOH UJI — BUKAN
FOTO ASLI" agar tidak bisa disamarkan sebagai bukti sungguhan.

Penjagaannya dua lapis — hanya hidup saat `APP_ENV=local` **dan** penandanya
dinyalakan — dan keduanya diperiksa di satu tempat,
[`App\Support\ModeUji`](app/Support/ModeUji.php), supaya tidak mungkin ada
bagian aplikasi yang lupa memeriksa salah satunya. Langkah lengkap untuk
pengujian di ponsel ada di [PANDUAN.md](PANDUAN.md#9-menguji-fitur-kunjungan-sales-di-hp).

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
| `TRUSTED_PROXIES`                        | proksi yang headernya dipercaya (`127.0.0.1,::1`) |
| `VISIT_MAKS_TOKO_PER_SALES`              | batas dan target toko per sales (120)    |
| `VISIT_JARAK_WAJAR_M`                    | selisih GPS yang masih wajar (300 m)     |
| `VISIT_FOTO_LEBAR_MAKS`                  | lebar foto setelah diperkecil (1280 px)  |
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

167 tes, mencakup:

- **[`tests/Unit/MesinRoutingTest.php`](tests/Unit/MesinRoutingTest.php)** —
  batas muatan tidak pernah dilanggar, tidak ada pesanan hilang atau ganda,
  wilayah tidak tercampur, muatan terbagi sebanding, dan hasilnya tetap ada
  saat OSRM mati.
- **[`tests/Feature/AlurPesananTest.php`](tests/Feature/AlurPesananTest.php)** —
  aturan minimal dus, satu pesanan aktif per toko, dan pengembalian stok.
- **[`tests/Feature/PemindaiQrTampilTest.php`](tests/Feature/PemindaiQrTampilTest.php)** —
  penjaga kerusakan yang gagal tanpa jejak: wadah pemindai tidak disembunyikan
  lewat kelas dari server, `video.play()` tidak pernah dipanggil tanpa
  penangkap kegagalan, tanda kode terbaca tidak bergantung pada getaran, dan
  pemberitahuan melayang agar terlihat di mana pun halaman digulir.
- **[`tests/Feature/PilihTokoTest.php`](tests/Feature/PilihTokoTest.php)** —
  pencarian toko lewat nomor aset (lengkap, sepotong, huruf kecil berspasi,
  urutan kecocokan persis) dan pemilihan lewat pindai QR beserta seluruh
  penolakannya.
- **[`tests/Feature/AlurRoutingTest.php`](tests/Feature/AlurRoutingTest.php)** —
  alur penuh dari PROCESS sampai SELESAI, termasuk pemindahan toko antar mobil
  dan pembatalan pesanan yang sudah masuk rute.
- **[`tests/Feature/HalamanTest.php`](tests/Feature/HalamanTest.php)** —
  setiap halaman tampil dan setiap peran hanya bisa membuka haknya.
- **[`tests/Feature/KunjunganTest.php`](tests/Feature/KunjunganTest.php)** —
  penguraian QR (termasuk titik dua lebar), periode Senin–Sabtu dan
  pergantiannya, penolakan kunjungan ganda dan toko di luar daftar,
  kelengkapan enam foto, watermark, serta perhitungan target saat toko tutup.
- **[`tests/Feature/ModeUjiTest.php`](tests/Feature/ModeUjiTest.php)** —
  jalan pintas pengujian mati di luar lingkungan lokal dan mati bila
  penandanya tidak dinyalakan, serta tetap menerapkan seluruh aturan kunjungan
  saat menyala.
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
    Kunjungan/              QR, watermark foto, periode, aturan kunjungan
    PesananService.php      aturan pesanan dan perlakuan stok
    RoutingService.php      jembatan mesin routing dengan basis data
  Livewire/
    Kunjungan/              visit sales: periode, penugasan, layar sales
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
  kamera.js                 pengambilan foto langsung dari kamera
  pemindai-qr.js            pembacaan QR freezer di perangkat
```
