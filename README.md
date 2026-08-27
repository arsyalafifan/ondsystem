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

### Daftar Pesanan — penyaring penginput dan kolom Update By | Date

Selain status, wilayah, tanggal, dan pencarian teks, daftar pesanan bisa
disaring per **penginput** — dropdown-nya hanya berisi user yang PERNAH
menginput pesanan (bukan seluruh sales aktif), supaya tidak penuh pilihan
kosong. Ringkasan status di bagian atas ikut mengikuti penyaring ini, jadi
admin bisa langsung melihat berapa pesanan per status untuk satu sales.

Dua kolom tambahan pada tabel:

- **Tanggal Diinput** — `created_at` pesanan, terpisah dari kolom penyaring
  "Tanggal" yang menyaring `tanggal` (tanggal target pengiriman, bisa
  berbeda dari kapan pesanannya sungguh diketik).
- **Update By | Date** — aktor dan waktu tindakan TERAKHIR yang tercatat
  pada pesanan itu (`Pesanan::pembaruTerakhir()`). Pesanan tidak punya satu
  kolom "diubah oleh" yang umum — tiap tindakan (disetujui, dibatalkan,
  dilunasi) punya pasangan aktor+waktunya sendiri di kolom terpisah; yang
  paling baru di antaranya itulah yang ditampilkan. Kalau belum ada satu
  pun tindakan lanjutan, kolom ini jatuh kembali ke penginput dan waktu
  pesanan dibuat — persis seperti yang diminta: "kalau tidak ada berarti
  penginputnya". Pemanggil wajib memuat keempat relasi (pembuat, pemroses,
  pembatal, dilunasiOleh) lebih dulu; mode ketat model melempar galat kalau
  belum, alih-alih memicu kueri N+1 diam-diam untuk tiap baris tabel.

### Tiga keputusan driver di lapangan

Rencana di kantor jarang selamat bertemu kenyataan di jalan. Selain navigasi,
driver punya tiga tindakan pada setiap toko di daftarnya:

- **Konfirmasi & unggah nota** — mengunggah foto nota **tidak pernah**
  langsung menuntaskan pesanan begitu saja. Sebelum foto tersimpan, driver
  wajib melihat checklist tiap produk (bawaannya sudah terisi penuh sesuai
  pesanan) dan mengoreksi baris yang tidak jadi diambil toko. Kalau seluruh
  angka dibiarkan apa adanya, hasilnya pengiriman penuh biasa; begitu satu
  baris saja dikurangi, otomatis jadi kurang-kirim — pesanan tetap SELESAI,
  ditandai `kurang_kirim`, dan sisanya menjadi muatan yang boleh
  dikampaskan. Ini yang mencegah kasus "toko cuma ambil sebagian tapi
  driver lupa mengoreksi, jadi seluruhnya tercatat terjual" — dulu jalur
  unggah biasa dan *coret nota* adalah dua tombol terpisah; sekarang
  checklist ini satu-satunya jalan menuntaskan pengiriman. Tiap baris juga
  punya kotak centang tersendiri di sebelah kiri, dan **tombol Simpan
  terkunci sampai semua baris dicentang** — bukan sekadar validasi jumlah,
  tapi memaksa driver benar-benar melihat satu per satu, bukan mempercayai
  angka bawaan begitu saja tanpa dilihat. Ceklisnya kosong lagi setiap kali
  modal dibuka untuk toko yang baru.
- **Cancel** — toko tidak bisa dikirimi sama sekali (tutup, pindah,
  menolak). Alasannya sama dengan daftar alasan pembatalan milik admin.
  Kewajiban driver atas toko itu dianggap tuntas, tapi dusnya **tidak**
  terhitung terkirim — barangnya masih di mobil.
- **Kampas** — menyalurkan sisa muatan (dari checklist yang dikurangi atau
  dari toko yang di-cancel) ke toko lain di jalan. Tokonya dipilih dengan
  cara yang sama seperti input pesanan (ketik atau pindai QR), tapi di sini
  **semua toko boleh dipilih**, termasuk yang masih punya pesanan berjalan.
  Tidak ada batas minimal 5 dus. Toko yang dipilih otomatis masuk ke daftar
  kunjungan mobil itu.

Jatah kampas dihitung **per produk**, bukan sebagai satu angka gelondongan:
2 toko batal berisi 5 dus air dan 5 dus teh memberi jatah 5 air + 5 teh, bukan
10 dus bebas. Tanpa itu driver bisa menjanjikan barang yang tidak ada di
mobilnya.

Kampas satu-satunya dari ketiga tindakan ini yang **menambah** toko baru ke
urutan kunjungan (cancel dan checklist tidak mengubah daftar sama sekali) —
`PengirimanService::kampas()` karena itu memanggil
`RoutingService::hitungUlang()` setelah menyimpan stop-nya, supaya garis
rute (geometry) dan jarak/ETA tiap toko ikut dihitung ulang. Tanpa ini toko
kampas tetap masuk daftar dan progres, tapi **penandanya di peta terlihat
lepas dari garis rute** — rute yang digambar masih rute lama, sebelum toko
itu ditambahkan, karena kolom `geometry` tidak pernah disentuh ulang.

Di baliknya, checklist ini memanggil salah satu dari dua service yang sudah
ada — `PesananService::selesaikanPengiriman()` kalau tidak ada baris yang
dikurangi, atau `PengirimanService::coretNota()` begitu ada. Keduanya tidak
diubah sama sekali oleh penggabungan ini; yang berubah hanya
`DaftarKunjungan::simpanKonfirmasi()` yang sekarang memutuskan mana yang
dipanggil berdasarkan isian checklist.

### Progres pengiriman dihitung dari dus

Penyebutnya adalah muatan yang dibawa mobil saat berangkat (`target_dus`),
dan pembilangnya adalah dus yang benar-benar sampai ke toko. Penyebut ini
sengaja tidak ikut menyusut saat ada pembatalan: kalau ia menyusut, mobil yang
membatalkan separuh rutenya akan terlihat 100% padahal separuh muatannya
pulang lagi. Menghitung berdasarkan jumlah toko punya cacat yang sama —
toko batal terhitung tuntas, sehingga kekurangan kiriman tersembunyi.

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

Aturan yang mengikat ketiga tindakan lapangan: **`stok` hanya berkurang
sebanyak dus yang benar-benar diterima toko.** Dus yang dibatalkan, dicoret,
atau tidak jadi dikampaskan pulang bersama mobilnya, jadi kunciannya dilepas
tapi angka stoknya utuh. Kampas memotong `stok` tanpa menyentuh
`stok_reserved`, karena barangnya sudah lepas kunci sejak pembatalan.

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

### Tanggal keberangkatan berbeda dari tanggal dibuat

Routing sering disiapkan lebih awal dari hari mobil sungguhan berangkat, jadi
**Tanggal keberangkatan** adalah kolom wajib tersendiri di halaman Generate
Routing — bukan otomatis hari ini. `RoutingBatch.tanggal` menyimpan tanggal
ini, terpisah dari `created_at` (kapan batch-nya dibuat). Dashboard memakai
kolom ini (`whereDate('tanggal', ...)`) untuk memutuskan mobil mana yang
"jalan hari ini", jadi routing yang dibuat untuk minggu depan tidak akan
nyasar muncul di dashboard hari ini.

Bawaannya terisi hari ini (kasus paling umum), tapi admin bebas
menggantinya. Panggilan terprogram (`RoutingService::generate()`) tetap
memakai hari ini sebagai bawaan bila `tanggalKeberangkatan` tidak diisi.

### Menetapkan driver dari layar Generate Routing

Sebelumnya satu-satunya cara sebuah mobil punya driver adalah driver itu
sendiri membuka menu Pilih Mobil dan menekan "Ambil" — kalau admin (atau
superadmin, yang lolos dari semua batasan peran) membuka layar itu duluan
untuk sekadar melihat-lihat, mobilnya ikut "terambil" ke akun mereka dan
driver aslinya terkunci keluar sama sekali.

Sekarang tiap kartu mobil di halaman Generate Routing punya pilihan
**Driver** sendiri (`RoutingService::ubahDriver()`). Begitu ditetapkan,
hanya akun itu yang bisa membuka mobil tersebut di menu Pilih Mobil driver.
Bisa diisi/diganti sejak routing baru saja di-generate — **tidak dibatasi
hanya saat masih draft** seperti penyuntingan lain di halaman ini — sampai
ada satu saja kunjungan yang tuntas (upload nota, coret nota, atau
dibatalkan di lapangan) di mobil itu. Begitu itu terjadi, pilihannya
terkunci (tampil sebagai teks biasa, bukan lagi `<select>`) — tanggung
jawab driver atas mobil itu sudah melekat dan tidak boleh dialihkan
diam-diam.

Dua penjagaan lain: akun yang dipilih harus benar berperan Driver (bukan
sekadar siapa saja yang bisa membuka halamannya), dan tidak sedang membawa
mobil aktif lain (`status` `siap`/`jalan`) **pada tanggal keberangkatan yang
sama** — patokannya `RoutingBatch.tanggal`, bukan sekadar status
kendaraannya. Seorang driver boleh terdaftar di beberapa mobil yang sama-sama
masih aktif selama tanggal berangkatnya berbeda (mis. mobil hari ini dan
mobil untuk lusa); yang dicegah cuma dua mobil pada hari yang sama. Memilih
"Belum ditentukan" mengosongkannya lagi, berguna kalau mobil kadung terambil
ke akun yang salah (persis kasus admin/superadmin di atas) — tidak perlu
lagi turun ke `tinker` untuk membukanya.

### Menyunting draf routing

Hasil otomatis jarang sempurna, jadi selama batch masih berstatus draft
(belum disetujui) admin bisa menyesuaikannya di halaman Generate Routing:

- **Geser urutan** — naik/turunkan satu toko dalam rute mobil yang sama.
- **Pindahkan ke mobil lain** — memindahkan toko ke kendaraan lain di batch
  yang sama; kedua rute (asal dan tujuan) dihitung ulang jaraknya.
- **Keluarkan dari rute** — toko tidak jadi masuk mobil mana pun.
  Pesanannya kembali ke antrean "siap dirutekan", sama seperti sebelum
  Generate Routing ditekan — bukan sekadar dipindah, tapi benar-benar
  keluar dari batch ini. Dipakai ketika satu toko memang tidak seharusnya
  ikut dikirim hari itu.
- **Hitung ulang otomatis** — menyusun ulang urutan kunjungan satu mobil
  dari awal (TSP), tanpa mengubah isinya.
- **Hapus mobil kosong** — muncul begitu sebuah mobil tidak berisi toko
  sama sekali (mis. setelah semua isinya dikeluarkan atau dipindah satu
  per satu).

Toko yang dikeluarkan atau mobil kosong yang dihapus di sini **benar-benar
dibuang** (`forceDelete`), bukan soft delete — draf yang belum disetujui
tidak punya nilai audit, dan soft delete di situ justru berbahaya: begitu
pesanannya di-generate ulang dan mendapat kunjungan baru, `INSERT`-nya akan
bentrok dengan batasan unik `kendaraan_stops.pesanan_id` milik baris lama
yang masih tertinggal. Lihat [Penghapusan data](#penghapusan-data) untuk
penjelasan lengkap pola ini.

### Mencetak nota dan packing list

Dua dokumen tercetak, keduanya lewat pola yang sama — tombol Cetak
(`window.print()`), Unduh PDF (Dompdf, berkas PDF sungguhan dari server,
bukan sekadar "print to PDF" browser), dan Print Direct (perintah ESC/P
mentah dikirim ke printer dot-matrix lewat OND Print Helper, .exe Windows
yang menerjemahkan link `ondprint://` menjadi kiriman byte apa adanya ke
printer — lihat komentar di `NotaPesananController`/`PackingListController`
untuk alasan lengkap kenapa ESC/P mentah dipakai, bukan hasil rasterisasi):

- **Nota** — dari Daftar Pesanan, untuk pesanan berstatus PROCESS/DELIVERY.
- **Packing list** — dari detail batch (Riwayat Routing → Lihat, atau layar
  Generate Routing setelah disetujui), satu per kendaraan. Berisi kop
  perusahaan, ringkasan (nama mobil, jumlah faktur/toko, jumlah dus, dan
  **tanggal keberangkatan** — dari kolom yang sama dengan bagian
  [Tanggal keberangkatan berbeda dari tanggal dibuat](#tanggal-keberangkatan-berbeda-dari-tanggal-dibuat)
  di atas, bukan tanggal batch dibuat), lalu dua tabel: rekap dus per produk
  digabung dari seluruh toko di mobil itu, dan rincian dus per toko. Tiap
  tabel punya kolom Qty (dus yang dimuat) di samping **Dus Terjual** dan
  **Dus Pulang** — keduanya sengaja dikosongkan, diisi tangan di kertas
  untuk rekonsiliasi setelah mobil kembali (idealnya Terjual + Pulang = Qty).
  Hanya bisa dicetak setelah routing **disetujui**
  (bukan draft, karena isinya bisa masih berubah), dan kunjungan jenis
  **kampas tidak ikut terhitung** — itu terjadi di lapangan setelah mobil
  berangkat, bukan bagian dari muatan yang dipacking di gudang; toko yang
  dibatalkan di lapangan tetap tampil apa adanya (dusnya memang sudah
  dimuat sejak berangkat).

Identitas perusahaan pada kop kedua dokumen diambil dari `config/perusahaan.php`
(`nama`, `alamat`, dll — overridable lewat `.env`, prefiks `PERUSAHAAN_*`).

**Link Print Direct sekali pakai** — protokol kustom `ondprint://` dikenal
kadang terpicu dua kali oleh Windows/browser untuk satu klik yang sama
(atau OND Print Helper mengambil URL yang sama dua kali karena sebab lain
di luar jangkauan kode ini), yang tanpa penjagaan berarti isi yang sama
terkirim dua kali ke printer fisik — terlihat seperti "tercetak berkali-kali"
di kertas continuous form. `App\Support\TokenCetakSekaliPakai` menutup celah
ini: tiap kali link `ondprint://` dibentuk, sebuah token acak dibuat lewat
cache (`CACHE_STORE`, bawaannya `database`) dan disertakan dalam tanda
tangan URL-nya. Permintaan pertama ke URL itu memakai sekaligus menghapus
tokennya (`Cache::pull`); permintaan kedua ke URL yang **persis sama**
selalu ditolak (`410 Gone`), apa pun yang menyebabkan permintaan berulang
itu. Reload halaman membentuk link baru dengan token baru — jadi mencetak
ulang yang memang disengaja tetap mudah, cuma percobaan ganda yang tak
disengaja yang dicegah.

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

Cara bawaan memulai kunjungan adalah memindai QR yang tertempel pada freezer —
itulah bukti bahwa sales benar-benar berdiri di depan freezer yang
bersangkutan, bukan sekadar menandai toko selesai dari mana pun.

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

Tidak semua toko sudah ditempeli stiker QR/nomor aset, jadi tersedia juga
pencarian ketik sebagai jalan cadangan — nama, kode, alamat, atau nomor aset,
persis seperti pencarian toko di Input Pesanan. Hasilnya sengaja dibatasi
pada toko yang ditugaskan admin ke sales itu untuk periode berjalan dan
belum dikunjungi, memakai `KunjunganService::mulai()` yang sama persis
dengan jalur pindai — jadi seluruh penjagaan (satu toko satu kunjungan per
minggu, hanya toko tanggungan sendiri) tetap berlaku tanpa QR.

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
  di ponsel, ditambah **peta rute** sendiri (kartu "Peta Rute", tertutup
  secara bawaan supaya tidak menggeser daftar kunjungan yang dipakai
  berulang sepanjang hari) — satu kendaraan saja, milik driver itu, dengan
  penanda diwarnai menurut status kunjungan (abu-abu/hijau/merah), ditambah
  tombol lokasi GPS. Menekan penanda di peta menggulir ke kartu tokonya di
  daftar — kebalikan dari halaman admin, karena di sini yang berguna justru
  arah dari peta ke daftar, bukan sebaliknya.

Bug yang ditemukan sambil membangun peta driver: `@script`/`@endscript`
mendeteksi kode multi-statement lewat regex yang mengecek apakah teksnya
(setelah di-trim) **diawali langsung** oleh `const`/`let`/`if` — komentar
JS di baris pertama membuat deteksi itu gagal, kode diperlakukan sebagai
satu ekspresi tunggal, dan pernyataan berikutnya melempar "Unexpected
token". Jangan taruh komentar sebagai baris pertama di dalam blok
`@script`.

### Melengkapi koordinat toko

Tiga jalan, dari yang paling cepat:

1. **Impor CSV** berisi kolom `latitude`/`longitude`.
2. **Pencarian alamat otomatis** lewat Nominatim, satu per satu atau 20 toko
   sekaligus. Hasil yang cuma setingkat kota ditandai agar dikoreksi manual.
3. **Klik di peta**, untuk alamat gang atau perumahan yang tidak terbaca mesin.

Toko tanpa koordinat dilewati saat routing — bukan menggagalkan prosesnya — dan
dilaporkan ke admin.

**Koordinat toko yang dikoreksi setelah rutenya jadi** — baik lewat form edit
maupun impor CSV — memicu `RoutingService::hitungUlangUntukToko()`: garis
rute dan jarak/ETA tiap kendaraan yang belum berstatus `selesai` dan memuat
toko itu dihitung ulang memakai koordinat barunya. Tanpa ini, `geometry`
kendaraan tetap mengacu ke koordinat lama sementara peta menggambar
penandanya dari koordinat baru (dibaca langsung dari `toko.latitude`/
`longitude`) — keduanya jadi tidak sinkron secara visual walau sopir belum
melakukan aksi lapangan apa pun. Bug nyata ini ditemukan dari kendaraan
produksi yang koordinat semua tokonya diperbarui lewat impor CSV setelah
rutenya disetujui.

Urutan kunjungan **tidak** ikut dihitung ulang (sama seperti perilaku kampas)
— hanya jarak/garis rute untuk urutan yang sudah ada. Kalau koreksi
koordinatnya besar, urutan lama bisa jadi tidak lagi efisien untuk lokasi
barunya; admin perlu menekan hitung ulang rute (atau generate ulang) di
halaman Generate Routing kalau urutannya juga perlu dioptimalkan lagi.

#### Membereskan rute lama yang terlanjur basi

Rute yang dibuat SEBELUM perilaku di atas ada tetap menyimpan garis rute
versi koordinat lama. Untuk membereskannya sekali jalan:

```bash
php artisan rute:perbaiki-geometry --dry-run    # lihat dulu apa saja yang kena
php artisan rute:perbaiki-geometry              # perbaiki garis rutenya saja
php artisan rute:perbaiki-geometry --urutkan-ulang  # sekalian optimalkan urutannya
```

Perintah ini **memeriksa dulu, baru memperbaiki**: garis rute tersimpan
dibaca balik (`Geo::decodePolyline`) lalu tiap toko diukur jaraknya ke garis
itu. Kendaraan yang garis rutenya masih melewati toko-tokonya dilewati, jadi
perintahnya aman dijalankan berulang dan tidak menghujani OSRM tanpa perlu.

Dua penjaga yang penting:

- **Kendaraan yang sudah dijalani sopirnya tidak pernah diurutkan ulang**,
  bahkan dengan `--urutkan-ulang` — sebagian kunjungannya sudah selesai, dan
  mengacak urutannya akan memindahkan toko yang sudah dikirimi barang. Garis
  rutenya tetap diperbaiki, karena itu yang bikin petanya salah.
- **Toko yang tetap jauh dari garis rute setelah dihitung ulang dilaporkan
  terpisah** sebagai koordinat yang patut dicurigai. Ini bukan lagi soal
  garis rute basi: titiknya memang tidak berada di dekat jalan mana pun
  (mis. jatuh di laut), jadi OSRM menempelkannya ke jalan terdekat yang
  jauh — menghitung ulang berapa kali pun tidak akan menutup jarak itu.
  Yang perlu diperbaiki koordinat tokonya, lewat Master Toko.

---

## Penghapusan data

Master data (Toko, Produk, Wilayah, User) tidak pernah benar-benar dihapus —
sejak awal sudah memakai kolom `aktif` untuk disembunyikan dari pemilihan
tanpa kehilangan riwayat pesanan/kunjungan yang mengarah ke sana. Pola ini
tetap dipakai apa adanya.

Yang sebelumnya memakai `->delete()` sungguhan dibagi dua, tergantung apakah
amannya menyalakan soft delete begitu saja:

**Soft delete** — baris bertahan di basis data (kolom `deleted_at`),
tersembunyi dari kueri biasa lewat Eloquent, bisa dipulihkan:

| Tabel             | Dipicu oleh                                                  |
| ----------------- | ------------------------------------------------------------- |
| `wilayahs`         | admin menghapus wilayah lewat Master Data (hanya bila sudah tidak dipakai toko) |
| `routing_batches`  | menghapus draf routing — batch-nya sendiri, sebagai jejak bahwa draf pernah dibuat |
| `kendaraans`       | menghapus satu mobil kosong dari draf routing                |
| `kendaraan_stops`  | admin membatalkan pesanan yang sudah masuk rute (sebelum terkirim) |
| `pesanans`         | menghapus pesanan (mis. data dummy) — lewat `$pesanan->delete()` atau langsung mengedit kolom `deleted_at` di basis data |

Wilayah yang terhapus muncul di bagian "Wilayah terhapus" pada halaman Master
Wilayah, lengkap dengan tombol Pulihkan — satu-satunya penghapusan di atas
yang dipicu langsung lewat tombol admin. Catatan: kolom `kode` tetap unik
terhadap baris yang di-soft-delete, jadi kode yang baru dihapus belum bisa
dipakai wilayah lain sampai dipulihkan atau dihapus permanen.

Pengecualian sengaja: tiga tindakan pada draf routing yang masih belum
disetujui — membuang seluruh draf (`hapusDraft`), mengeluarkan satu toko
dari rute (`keluarkanDariRute`), dan menghapus mobil kosong
(`hapusKendaraanKosong`) — meng-**forceDelete** kendaraan/kunjungannya,
bukan soft delete. Draf yang belum pernah disetujui tidak punya nilai
audit, dan soft delete di situ justru berbahaya — begitu pesanannya
dirutekan ulang dan mendapat kunjungan baru, `INSERT`-nya akan bentrok
dengan batasan unik `kendaraan_stops.pesanan_id` milik baris lama yang
masih "tertinggal". Nomor kendaraan, kode batch, dan kode pesanan juga
sengaja dihitung dengan `withTrashed()` supaya tidak pernah dipakai ulang
oleh baris yang sudah di-soft-delete.

**Menghapus pesanan** — `Pesanan` (`App\Models\Pesanan`) punya penjaga
tambahan: menghapusnya lewat `->delete()` Eloquent **ditolak** kalau
pesanan itu sudah pernah masuk rute (`$pesanan->stop()->exists()`). Alasannya:
banyak layar driver (unggah nota, coret nota, dashboard) mengakses
`$stop->pesanan->...` tanpa null-safe karena pesanan pada sebuah stop
selama ini dijamin selalu ada — menghapus pesanan yang stop-nya masih
menunjuk ke situ akan membuat layar-layar itu error. Penjaga ini **hanya
berlaku lewat kode aplikasi**; mengedit kolom `deleted_at` langsung di
basis data (mis. lewat phpMyAdmin/Adminer untuk membuang data dummy) tetap
melewatinya sepenuhnya — jadi hanya aman dilakukan pada pesanan yang belum
pernah dirutekan (masih ORDER/PROCESS, kolom `stop` kosong).

**Kolom disiapkan, belum dipakai** — `deleted_at` ada di skema, tapi model
belum memakai trait `SoftDeletes`; `->delete()` masih menghapus sungguhan
seperti sebelumnya:

| Tabel              | Kenapa belum aman langsung disalakan |
| ------------------ | ------------------------------------- |
| `penugasan_sales`   | batasan unik (`toko_id`, `bulan`) dipakai sebagai mekanisme deteksi "toko sudah dipegang sales lain" — `PenugasanService::tetapkan()` menangkap `QueryException` dari situ. Baris yang di-soft-delete tetap menghuni batasan unik itu, jadi toko yang sudah dilepas dari satu sales bisa keliru dianggap masih dipegangnya saat ditugaskan ke sales lain. |
| `kunjungan_fotos`   | foto lama dihapus dari disk begitu diulang (`KunjunganService::simpanFoto`), dan `jenis` per kunjungan dibatasi unik satu baris. Menjadikannya soft delete berarti keputusan produk dulu: apakah foto lama tetap disimpan sebagai riwayat, dan bagaimana alur "ambil ulang" bekerja terhadap baris yang di-soft-delete. |

Kolom itu memang belum dipakai, tapi sudah tersedia kalau kelak ada kebutuhan
audit yang memaksa keduanya diselesaikan.

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

## Mengoreksi pesanan yang terlanjur SELESAI

Kasus lapangan: driver seharusnya *coret nota* karena toko cuma mengambil
sebagian, tapi malah mengunggah nota lewat jalur pengiriman penuh. Pesanan
langsung SELESAI dengan seluruh isinya dianggap terkirim — stok fisik sudah
terlanjur keluar penuh dari gudang, dan jumlah yang salah itu ikut masuk ke
Pelunasan.

Jalan perbaikannya lewat SSH ke server, bukan lewat layar admin (belum ada
tombolnya):

```bash
php artisan pesanan:koreksi-item PSN-20260821-0001 P1 7 --admin=admin@ondsystem.test
```

Perintah ini menampilkan ringkasan sebelum/sesudah dan meminta konfirmasi
sebelum menyimpan apa pun. Yang terjadi di baliknya
(`PengirimanService::koreksiItemSetelahSelesai()`):

- `PesananItem.jumlah_dus_terkirim` diperbarui ke jumlah yang benar.
- `Pesanan.kurang_kirim` ditandai `true`, supaya `Pesanan::tagihan()` — yang
  dipakai layar Pelunasan — otomatis menagih sesuai yang benar-benar
  diterima, bukan jumlah pesanan semula.
- `KendaraanStop.total_dus_terkirim` dikurangi sebesar selisihnya.
- Stok fisik (`Produk.stok`) dikembalikan sebesar selisihnya. `stok_reserved`
  tidak disentuh — sudah bernilai nol sejak pesanannya ditutup.
- Selisihnya dicatat di `stok_mutasis` dengan `tipe = 'penyesuaian'`, lengkap
  dengan siapa yang melakukan koreksi.

Hanya berlaku untuk pesanan yang statusnya sudah SELESAI (untuk yang masih
DELIVERY, dorong driver memakai *coret nota* yang sudah ada di aplikasi) dan
menolak jumlah yang melebihi pesanan semula atau yang tidak mengubah apa pun.

### Tampilannya ikut disunting, bukan cuma tagihannya

Mengoreksi data saja tidak cukup kalau layarnya masih menampilkan angka lama.
Tiga tempat yang menampilkan rincian per produk sengaja disunting supaya
konsisten dengan `Pesanan::tagihan()`:

- **Modal "Rincian Pesanan"** (Input Pesanan → klik baris pesanan) — baris
  produk yang `terkirim`-nya 0 dus **disembunyikan**, bukan dihapus dari
  basis data; baris yang terkirim sebagian menampilkan jumlahnya
  (`6 / 10`); dan totalnya mengikuti `tagihan()`, dengan angka semula
  dicoret di atasnya untuk pembanding kalau pesanannya `kurang_kirim`.
- **Nota cetak** (HTML/PDF lewat `NotaPesananController`, dan ESC/P lewat
  `EscpNotaBuilder`) — rumus yang sama diterapkan, tapi jalur cetaknya
  sendiri hanya mengizinkan status PROCESS/DELIVERY
  (`StatusPesanan::bisaDicetak()`), sedangkan `kurang_kirim` baru pernah
  bernilai `true` setelah pesanan SELESAI — jadi kombinasi keduanya
  sebenarnya tidak pernah tercapai lewat rute cetak saat ini. Perbaikan ini
  tetap dipasang supaya rumusnya sudah benar seandainya kelak ada fitur
  cetak-ulang pasca-SELESAI.

Baris `PesananItem` itu sendiri **tidak pernah dihapus** — disembunyikan
di lapisan tampilan saja. Ini yang membuatnya aman: `stok_mutasis`, jatah
kampas, dan seluruh riwayat yang menunjuk ke item itu tetap utuh.

---

## Pelunasan: rincian cash vs transfer

Menandai pesanan lunas (dari layar **Pelunasan** maupun **Belum Lunas**) tidak
lagi sekadar konfirmasi ya/tidak. Admin wajib mengisi **berapa yang dilunasi
cash dan berapa yang transfer** — kedua kolom diisi manual, tidak ada
pembagian otomatis atau tebakan, dan boleh diisi salah satu saja asalkan
jumlahnya. Tombol Proses **terkunci** sampai `cash + transfer` persis sama
dengan tagihan (`Pesanan::tagihan()`, bukan `total_nilai` mentah — supaya
pesanan yang `kurang_kirim` tetap ditagih sesuai yang benar-benar terkirim).

Dicatat di kolom `pesanans.nominal_cash` / `nominal_transfer`
(`PelunasanService::tandaiLunas()`), dan dikosongkan lagi kalau pesanan
ditandai Belum Lunas. Layar **Pendapatan** menjumlahkan keduanya secara
terpisah, jadi selain total pendapatan keseluruhan, sekarang juga terlihat
berapa yang masuk lewat cash dan berapa lewat transfer.

Tombol **"Lunasi Sisanya"** (pelunasan massal satu mobil sekaligus tanpa
rincian per toko) sengaja **dihapus** — tidak konsisten dengan aturan
"nominal harus diisi manual per pesanan". Setiap pelunasan sekarang selalu
lewat satu toko satu isian.

### Titik ribuan saat mengetik nominal

Kedua kolom (Cash/Transfer) menampilkan titik pemisah ribuan sambil diketik
(mis. `1.500.000`) lewat [`formatRibuan()`](resources/js/format-rupiah.js) —
**murni tampilan**. Nilai yang benar-benar dikirim ke Livewire (lewat
`$wire.set()` di setiap ketikan, bukan `wire:model.live`, supaya kolom bisa
diformat sebelum disimpan) selalu angka bersih tanpa titik, jadi
`PelunasanService::tandaiLunas()` dan kolom `nominal_cash`/`nominal_transfer`
di basis data tidak berubah sama sekali. Karakter non-digit (termasuk minus)
otomatis terbuang saat mengetik — nominal cash/transfer memang tidak pernah
berupa desimal atau negatif.

---

## Point of Sale (POS)

Layar tersendiri (menu **Point of Sale**, `/pos`, admin dan sales) untuk
penjualan langsung di tempat — **tidak pernah lewat pengantaran driver sama
sekali**. Dibuat sebagai layar terpisah dari Input Pesanan biasa, bukan opsi
di dalamnya: alur normal punya banyak aturan yang tidak relevan di sini
(batas minimal dus, satu pesanan aktif per toko, reservasi stok menunggu
pengiriman), dan mencampurnya lewat percabangan kondisi akan membuat kedua
alur sama-sama lebih sulit dibaca.

Polanya mengikuti **kampas** (`PengirimanService::kampas()`), bukan pesanan
biasa: barangnya berpindah tangan seketika, jadi begitu disimpan lewat
`PesananService::buatPos()` pesanannya langsung berstatus **SELESAI dan
LUNAS** — tidak ada fase ORDER/PROCESS/DELIVERY, tidak ada reservasi stok
menunggu pengiriman. Bedanya dari kampas: POS **tidak pernah membuat
`KendaraanStop`** sama sekali, karena memang tidak ada kendaraan yang
terlibat — toko tetap dipilih (barangnya tercatat masuk ke toko yang mana),
tapi tidak ada rute.

Tiga aturan yang sengaja beda dari pesanan biasa:

- **Tidak ada minimal pembelian** — mulai dari 1, bukan `min_dus_per_toko`.
- **Dibandingkan dengan stok fisik penuh** (`produks.stok`), bukan
  `stok_tersedia` (stok dikurangi reservasi) — POS menjual langsung dari
  rak, jadi reservasi pesanan pengantaran lain tidak relevan di sini.
- **Toko yang masih punya pesanan pengantaran aktif tetap boleh dilayani** —
  POS tidak bersinggungan dengan routing sama sekali, jadi aturan "satu
  pesanan aktif per toko" (yang ada untuk mencegah konflik rute) tidak
  berlaku.

Pembayarannya langsung diminta saat itu juga (rincian cash/transfer, pola
input yang sama dengan Pelunasan — lihat di atas) dan disimpan dalam
transaksi yang sama dengan pesanannya, bukan lewat `PelunasanService`
terpisah belakangan.

### Barcode: siap dipakai, belum wajib dipakai

Kolom `produks.barcode` (nullable, unik) dan input pindai di layar Kasir
sudah berfungsi penuh — tapi tidak ada produk yang punya barcode sampai
admin melengkapinya lewat **Master Produk**. Sengaja tidak dibangun lewat
kamera: alat pemindai USB/Bluetooth (perangkat POS yang sesungguhnya) bagi
peramban tidak beda dari mengetik cepat lalu menekan Enter, jadi satu kolom
teks biasa + `wire:submit` sudah cukup — tanpa perlu menduplikasi
infrastruktur kamera QR yang sudah ada di
[`resources/js/pemindai-qr.js`](resources/js/pemindai-qr.js) untuk kasus
pakai yang berbeda. Memindai kode yang sama dua kali menambah jumlah baris
yang sudah ada, bukan membuat baris baru — meniru kelaziman kasir
sungguhan: tiap pindai berarti "tambah satu lagi".

### Kategori pendapatan: Pengantaran Driver vs Point of Sale

Layar **Pendapatan** sudah murni berbasis `Pesanan::where('status_bayar',
Lunas)`, jadi penjualan POS otomatis ikut terhitung tanpa perubahan pada
kueri dasarnya. Yang ditambahkan:

- **Kartu ringkasan per kategori** — `JenisPesanan::kategoriPendapatan()`
  mengelompokkan `normal` dan `kampas` sama-sama sebagai `driver` (keduanya
  lewat kendaraan, bedanya cuma cara kunjungannya masuk ke rute), dan `pos`
  sebagai kategorinya sendiri.
- **Riwayat per pesanan** (bukan cuma agregat per hari seperti tabel yang
  sudah ada) — kode pesanan, toko, kategori, rincian cash/transfer, dan
  total, terurut yang paling baru lunas dulu. Tabelnya sendiri bisa disaring
  lewat kode/nama toko, kategori (Pengantaran Driver / Point of Sale), dan
  metode bayar (Cash / Transfer), plus tombol Bersihkan — lihat properti
  `riwayatCari`/`riwayatKategori`/`riwayatMetode` pada `Pendapatan.php`.
  Sengaja hanya menyaring TABEL riwayat, bukan kartu ringkasan/kategori/
  grafik di atasnya — kartu-kartu itu tetap gambaran keseluruhan rentang
  tanggal, tabel riwayat adalah alat cari yang menyaring di dalamnya.
  Penyaring disaring di memori dari `pesanans()` yang sudah diambil untuk
  kartu ringkasan (rentang tanggalnya sudah sama), bukan lewat kueri baru.
  Metode bayar menjawab "transaksi ini ada unsur cash/transfer-nya?", bukan
  "metode tunggalnya apa" — pembayaran campuran (sebagian cash, sebagian
  transfer, seperti yang diizinkan Pelunasan/POS) muncul di KEDUA penyaring.

### Pemilih produk yang bisa dicari (`<x-pilih-cari>`)

Baris produk di **Input Pesanan** dan **POS** memakai
[`<x-pilih-cari>`](resources/views/components/pilih-cari.blade.php) —
kotak teks yang menyaring daftar produk sambil diketik, gaya Select2 —
bukan `<select>` biasa yang mengharuskan menggulir daftar panjang. Dibuat
sendiri lewat Alpine, bukan menambah pustaka (Select2/Choices.js/dsb):
daftar produknya sudah dikirim sekali ke halaman lewat `@js()`, jadi
penyaringan sisi klien saja sudah cukup tanpa permintaan tambahan ke
server. Mendukung navigasi panah atas/bawah + Enter, bukan cuma klik.

Nilai yang benar-benar tersimpan tetap properti Livewire biasa
(`baris.{i}.produk_id`), diperbarui lewat `$wire.set()` setiap kali sebuah
pilihan diklik/di-Enter — bukan `wire:model`, karena kotak teks yang
tampil menunjukkan LABEL produk ("Nama (KODE)"), bukan id-nya. Sinkron
ulang ke label yang benar dijaga lewat `x-effect` yang membaca ulang nilai
server-rendered pada setiap render — pola yang sama dipakai tombol "Semua
Cash"/"Semua Transfer" di layar Kasir untuk kasus yang serupa: properti
Livewire berubah dari INTERAKSI LAIN (bukan mengetik langsung di kotak
itu), jadi tampilannya perlu disegarkan tanpa Livewire tahu-menahu soal
state Alpine lokal.

---

## Pengujian

```bash
php artisan test
```

424 tes, mencakup:

- **[`tests/Feature/PendapatanRiwayatTest.php`](tests/Feature/PendapatanRiwayatTest.php)** —
  penyaring tabel riwayat lewat kode pesanan, nama toko, kategori (`pos`
  vs `driver` — termasuk memastikan rute biasa DAN kampas sama-sama masuk
  `driver`), dan metode bayar (`cash`/`transfer`, termasuk pembayaran
  campuran yang muncul di kedua penyaring metode); pencarian yang tidak
  cocok menampilkan pesan kosong yang berbeda dari benar-benar tidak ada
  transaksi; tombol Bersihkan mengembalikan seluruh transaksi; dan
  penyaring riwayat sengaja tidak mengubah kartu ringkasan kategori di
  atasnya.
- **[`tests/Feature/PosTest.php`](tests/Feature/PosTest.php)** —
  `PesananService::buatPos()` langsung SELESAI+LUNAS tanpa membuat
  `KendaraanStop`, stok fisik berkurang seketika (bukan lewat reservasi),
  tidak menuntut minimal dus, tetap melayani toko yang masih punya pesanan
  pengantaran aktif, menolak stok fisik kurang dan nominal cash+transfer
  yang tidak pas; layar Kasir menyelesaikan penjualan dari awal sampai
  akhir, menambah baris dari barcode (termasuk memindai kode yang sama dua
  kali menambah jumlah, bukan baris baru) dan menolak barcode yang tidak
  dikenali; penjualan POS dan pesanan pengantaran biasa yang lunas
  terkategori benar di Pendapatan (`pos` vs `driver`), termasuk lewat blade
  yang sungguhan dirender dengan BEBERAPA pesanan sekaligus — pelajaran
  yang sama seperti pengujian `rute:perbaiki-geometry` dan
  `DaftarPesananFilterTest`: pelanggaran mode ketat pada relasi `toko` baru
  kelihatan begitu koleksinya lebih dari satu model; dan Master Produk
  menolak barcode yang sudah dipakai produk lain tapi membiarkan dua produk
  sama-sama belum punya barcode; dan `<x-pilih-cari>` bisa memilih produk
  lewat `$wire.set()` end-to-end sampai tersimpan di kedua layar (Kasir
  maupun Input Pesanan), termasuk menampilkan label yang sudah terpilih.
- **[`tests/Feature/DaftarPesananFilterTest.php`](tests/Feature/DaftarPesananFilterTest.php)** —
  penyaring penginput membatasi tabel dan ringkasan status pada satu sales;
  daftar pilihannya hanya berisi user yang pernah menginput pesanan; kolom
  Update By | Date jatuh ke penginput saat belum ada tindakan lanjutan, lalu
  berpindah ke admin yang menyetujui, lalu ke admin yang membatalkan sebagai
  tindakan terbaru; dan `pembaru_terakhir` bisa diakses pada BANYAK baris
  pesanan sekaligus tanpa lazy load (pelajaran yang sama seperti pengujian
  `rute:perbaiki-geometry`: pelanggaran mode ketat baru kelihatan begitu
  koleksinya lebih dari satu model).
- **[`tests/Feature/PerbaikiGeometryRuteTest.php`](tests/Feature/PerbaikiGeometryRuteTest.php)** —
  perintah `rute:perbaiki-geometry`: `decodePolyline` membaca balik apa yang
  ditulis `encodePolyline`, kendaraan yang garis rutenya masih cocok
  dilewati, yang sudah tidak melewati tokonya diperbaiki, `--dry-run` tidak
  mengubah apa pun, kendaraan yang sudah dijalani sopirnya tidak diurutkan
  ulang, dan toko yang tetap jauh dari jalan dilaporkan sebagai koordinat
  mencurigakan (OSRM dipalsukan, karena perhitungan cadangan garis lurus
  selalu lewat tepat di atas tokonya). Tes utamanya sengaja memakai
  BEBERAPA kendaraan: Laravel 13 diam-diam memuat relasi yang kurang ketika
  modelnya cuma satu, jadi pelanggaran mode ketat baru kelihatan begitu
  koleksinya lebih dari satu — versi satu-kendaraan lolos padahal
  perintahnya gagal di pemakaian nyata.
- **[`tests/Feature/TokoKoordinatRoutingTest.php`](tests/Feature/TokoKoordinatRoutingTest.php)** —
  mengoreksi koordinat toko lewat form edit memicu hitung ulang garis rute
  dan jarak kendaraan yang belum selesai dan memuat toko itu; toko yang
  disunting tanpa mengubah koordinatnya tidak memicu apa-apa; kendaraan yang
  sudah berstatus `selesai` tidak disentuh lagi.
- **[`tests/Feature/DriverPetaRuteTest.php`](tests/Feature/DriverPetaRuteTest.php)** —
  peta driver hanya berisi kendaraannya sendiri (bukan seluruh armada),
  kunjungan yang sudah terkirim ditandai centang + hijau, yang dibatalkan di
  lapangan berwarna merah tapi TIDAK bercentang (tuntas secara tanggung
  jawab, bukan sukses terkirim), yang masih pending abu-abu, serta tes
  regresi untuk bug nyata yang ditemukan sambil membangun ini: peta admin
  (`GenerateRouting::dataPeta()`) membandingkan status kunjungan dengan
  string `'selesai'` padahal kolomnya di-cast ke enum `StatusStop` —
  perbandingan itu selalu salah, jadi penanda centang di peta admin tidak
  pernah muncul walau kunjungannya sungguh selesai.
- **[`tests/Feature/CetakPackingListTest.php`](tests/Feature/CetakPackingListTest.php)** —
  hanya bisa dicetak setelah routing disetujui (ditolak untuk sales, driver,
  dan draft), kop menampilkan nama mobil/jumlah faktur/jumlah dus/tanggal
  keberangkatan yang benar, rekap dus per produk digabung dari toko-toko
  berbeda dalam satu mobil, toko tujuan kampas tidak ikut terhitung tapi
  toko yang dibatalkan di lapangan tetap tampil, unduh PDF sungguhan, unduh
  ESC/P mentah, jalur `ondprint://` tanpa sesi login lewat tanda tangan
  sementara (termasuk penolakan saat rusak/kedaluwarsa), dan token sekali
  pakainya menolak permintaan kedua ke URL yang sama persis.
- **[`tests/Feature/CetakNotaTest.php`](tests/Feature/CetakNotaTest.php)** —
  pola yang sama untuk nota pesanan, termasuk token sekali pakai yang sama,
  dan rendering `EscpNotaBuilder` untuk pesanan `kurang_kirim` (dites
  langsung lepas dari gerbang rute, karena kombinasi statusnya saat ini
  tidak pernah tercapai lewat rute cetak).
- **[`tests/Feature/RincianPesananTest.php`](tests/Feature/RincianPesananTest.php)** —
  modal "Rincian Pesanan" menyembunyikan produk yang dikoreksi jadi 0 dus
  diterima, menampilkan jumlah terkirim sebagian apa adanya, menampilkan
  total sesuai `tagihan()` setelah koreksi, dan menampilkan pesanan yang
  tidak dikoreksi persis seperti semula.
- **[`tests/Feature/PelunasanTest.php`](tests/Feature/PelunasanTest.php)** —
  rincian sumber pembayaran: seluruhnya cash, seluruhnya transfer, campuran
  keduanya, penolakan jumlah yang kurang/lebih dari tagihan atau negatif,
  pengosongan rincian saat ditandai Belum Lunas lagi, tombol Proses yang
  terkunci sampai jumlahnya pas lewat layar Pelunasan maupun Belum Lunas,
  dan rekap cash/transfer terpisah di layar Pendapatan.
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
  alur penuh dari PROCESS sampai SELESAI, pemindahan toko antar mobil,
  pembatalan pesanan yang sudah masuk rute, mengeluarkan toko dari rute
  (nomor urut yang rapi, dus/jarak terhitung ulang, dan pesanannya bisa
  dirutekan ulang tanpa bentrok batasan unik), penolakan menyunting draf
  yang sudah disetujui, serta tanggal keberangkatan yang terpisah dari
  tanggal batch dibuat (wajib diisi di halaman, hari ini sebagai bawaan
  bila dipanggil terprogram).
- **[`tests/Feature/RoutingDriverTest.php`](tests/Feature/RoutingDriverTest.php)** —
  menetapkan/mengganti/mengosongkan driver kendaraan, penolakan akun yang
  bukan berperan driver dan driver yang sedang membawa kendaraan aktif lain
  di tanggal keberangkatan yang sama (tapi diizinkan kalau tanggalnya
  berbeda, atau kalau kendaraan sebelumnya sudah berstatus selesai), boleh
  diganti bebas sejak draft dibuat (bukan cuma sebelum disetujui), terkunci
  begitu ada kunjungan yang selesai/dicoret/dibatalkan, dan alur lewat
  komponen Livewire-nya langsung termasuk notifikasi galat saat ditolak.
- **[`tests/Feature/PilihMobilTest.php`](tests/Feature/PilihMobilTest.php)** —
  superadmin/admin yang membuka mobil kosong tidak ikut mengunci
  `driver_id`-nya (beda dari driver sungguhan yang memang menguncinya),
  mobil itu tetap muncul di daftar untuk driver asli, driver kedua tetap
  ditolak kalau mobil sudah benar-benar diambil driver pertama, dan
  `diambil_at` tetap tercatat saat driver membuka mobil yang driver-nya
  sudah ditetapkan admin lebih dulu lewat Generate Routing.
- **[`tests/Feature/PengirimanLapanganTest.php`](tests/Feature/PengirimanLapanganTest.php)** —
  ketiga tindakan driver dan pembukuan stoknya, termasuk garis rute
  (geometry) dan ETA yang dihitung ulang setelah kampas menambah toko baru
  ke urutan kunjungan — dan total jarak batch routing yang ikut diperbarui:
  pembatalan yang menuntaskan
  toko tanpa menambah dus terkirim, coret nota beserta penolakan di bawah
  batas minimal, jatah kampas per produk yang menolak permintaan melebihi sisa
  produk itu meski total jatahnya cukup, dan pemeriksaan bahwa dus yang tidak
  sampai ke mana pun tidak memotong stok gudang. Termasuk cegatan di layar:
  isian yang melebihi jatah dipotong dan diberitahukan sejak diketik, bukan
  baru setelah nota terunggah. Serta koreksi pesanan yang terlanjur SELESAI
  lewat unggah nota penuh yang keliru: stok fisik kembali sebesar selisihnya
  tanpa menyentuh kuncian, `kurang_kirim` ditandai sehingga tagihan Pelunasan
  ikut terkoreksi, jejak mutasi stok tercatat, dan perintah artisan
  `pesanan:koreksi-item` yang membungkusnya (termasuk saat konfirmasinya
  ditolak). Serta alur konfirmasi terpadu lewat komponen Livewire-nya
  langsung: checklist terisi penuh secara bawaan, dibiarkan apa adanya
  menghasilkan pengiriman penuh, satu baris dikurangi menghasilkan
  kurang-kirim dengan sisanya langsung tersedia sebagai jatah kampas, dan
  penolakan (jumlah melebihi pesanan, di bawah batas minimal, foto nota
  kosong) tidak menyimpan atau mengunggah apa pun. Serta ceklis wajibnya:
  modal terbuka dengan seluruh ceklis kosong, satu baris saja belum
  dicentang sudah cukup menolak simpan meski foto sudah diisi, mengizinkan
  simpan begitu semua tercentang, dan ceklis kosong lagi tiap kali modal
  dibuka untuk toko baru.
- **[`tests/Feature/HalamanTest.php`](tests/Feature/HalamanTest.php)** —
  setiap halaman tampil dan setiap peran hanya bisa membuka haknya.
- **[`tests/Feature/KunjunganTest.php`](tests/Feature/KunjunganTest.php)** —
  penguraian QR (termasuk titik dua lebar), periode Senin–Sabtu dan
  pergantiannya, penolakan kunjungan ganda dan toko di luar daftar,
  kelengkapan enam foto, watermark, perhitungan target saat toko tutup, serta
  pencarian ketik untuk toko tanpa stiker QR — termasuk pembatasannya hanya
  pada tanggungan sales yang bersangkutan dan yang belum dikunjungi minggu
  itu.
- **[`tests/Feature/ModeUjiTest.php`](tests/Feature/ModeUjiTest.php)** —
  jalan pintas pengujian mati di luar lingkungan lokal dan mati bila
  penandanya tidak dinyalakan, serta tetap menerapkan seluruh aturan kunjungan
  saat menyala.
- **[`tests/Feature/SoftDeleteTest.php`](tests/Feature/SoftDeleteTest.php)** —
  wilayah yang dihapus bertahan di basis data dan bisa dipulihkan lewat
  layar admin, draf routing yang dibuang membereskan kendaraan dan
  kunjungannya sehingga pesanannya kembali ke antrean, nomor kendaraan dan
  kode batch tidak pernah dipakai ulang oleh baris yang sudah di-soft-delete,
  pesanan yang sudah masuk rute ditolak dihapus lewat Eloquent, dan
  `penugasan_sales`/`kunjungan_fotos` dipastikan masih hard delete seperti
  semula.
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
