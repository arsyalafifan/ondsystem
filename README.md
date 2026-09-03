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

### Order Ulang / Batalkan — pesanan yang dibatalkan dengan alasan selain toko menolak

Kapan pun sebuah pesanan berakhir dibatalkan dengan alasan **selain**
"Toko membatalkan pesanan" — mis. toko tutup, stok tidak mencukupi,
alamat tidak ditemukan, atau "Lainnya" — situasinya masih ambigu, bukan
penolakan final dari toko. Berlaku **apa pun jalur pembatalannya**: baik
driver yang membatalkan kunjungan di lapangan (lihat "Tiga keputusan
driver di lapangan" di bawah) MAUPUN admin yang membatalkan langsung dari
Daftar Pesanan lewat tombol "Batalkan" biasa — SIAPA yang membatalkan
tidak relevan, cuma ALASANNYA yang menentukan (`Pesanan::bisa_order_ulang`).
Baris pesanan itu menampilkan dua tombol tambahan:

- **Order Ulang** — membuka modal berisi produk & jumlah dus yang SAMA
  seperti pesanan lama (toko-nya tetap sama, tidak perlu dipilih ulang),
  siap disesuaikan. Menyimpannya memanggil `PesananService::buat()` apa
  adanya — aturan yang sama seperti Input Pesanan biasa berlaku penuh
  (stok tersedia, minimal dus, toko tidak lagi punya pesanan aktif lain).
  Kalau ada produk yang stoknya tidak mencukupi, galatnya tampil DI DALAM
  modal yang sama (bukan notifikasi lalu hilang) — admin tinggal
  menunggu stok tersedia, atau langsung mengubah jumlah/produknya di
  sana sampai berhasil, tanpa perlu pindah layar. "Atas nama sales"
  otomatis terisi dari sales yang menginput pesanan lama (kalau memang
  sales, bukan admin lain) — tinggal diganti kalau memang perlu.
- **Batalkan** — menandai FINAL bahwa pesanan ini tidak akan di-order
  ulang lagi, lewat `PesananService::tandaiBatalKarenaToko()`. Cuma
  mengubah `alasan_cancel` jadi "Toko membatalkan pesanan" (alasan
  aslinya disalin ke `catatan_cancel` supaya tidak hilang) — **sama
  sekali tidak menyentuh stok atau siapa yang sungguh membatalkan**
  (`dibatalkan_oleh`/`dibatalkan_at` tetap apa adanya, tidak diganti
  admin yang cuma menandai final). Aman untuk kedua jalur pembatalan:
  stok pesanan yang dibatalkan admin langsung sudah dilepas SEKETIKA saat
  dibatalkan (`PesananService::batalkan()`), sedangkan dus dari
  pembatalan driver di lapangan tetap sepenuhnya mengikuti alur
  kampas/`selesaikanKendaraan()` yang sudah ada — kedua-duanya lepas
  total dari tindakan "Batalkan" ini, yang murni soal pencatatan alasan.

**Bug nyata yang sempat terjadi**: `bisa_order_ulang` awalnya keliru
mensyaratkan baris `KendaraanStop` masih ada — syarat yang cuma benar
untuk jalur driver (`PengirimanService::batalkanDiLapangan()` membiarkan
stop-nya ada berstatus `Dibatalkan`), sedangkan `PesananService::batalkan()`
(admin) MENGHAPUS baris stop-nya sekalian. Akibatnya pesanan yang
dibatalkan admin langsung dengan alasan selain "toko membatalkan
pesanan" tetap tidak menampilkan Order Ulang — padahal alasannya sendiri
sudah memenuhi syarat. Diperbaiki dengan melepas syarat `stop` itu sama
sekali; sekarang cuma status `Cancel` + alasan yang diperiksa, seperti
seharusnya.

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

### Unduh KML — titik toko untuk peta offline saat sinyal hilang

Tombol **"Unduh KML"** di panel Peta Rute layar driver (`/driver/mobil/{kendaraan}`)
mengunduh seluruh titik toko pada rute kendaraan itu sebagai berkas `.kml`
standar — dipakai kalau driver kehilangan sinyal di jalan dan tidak bisa
lagi membuka aplikasi ini sama sekali (Peta Rute bawaan butuh koneksi untuk
memuat ubin peta dari server). Berkasnya bisa dibuka lewat aplikasi peta
offline apa pun yang mendukung impor KML, termasuk **Map Marker** — makanya
sengaja diunduh SEBELUM berangkat, bukan saat sudah tidak ada sinyal.

Dibentuk oleh [`App\Support\KmlRuteBuilder`](app/Support/KmlRuteBuilder.php)
(pola yang sama dengan `EscpNotaBuilder`/`EscpPackingListBuilder`: satu
`build()` statis, dipanggil dari `DaftarKunjungan::unduhKml()` lewat
`response()->streamDownload()` — pola Livewire native yang sama dengan
`GenerateRouting::unduhCsv()`, bukan lewat controller/route terpisah karena
seluruh datanya sudah ada di komponen). Setiap titik toko jadi satu
`Placemark` berwarna sesuai status kunjungannya (`StatusStop::warna()`,
dikonversi ke format warna KML `aabbggrr`) supaya driver tetap bisa
membedakan yang sudah selesai/dibatalkan/belum dikunjungi lewat aplikasi
peta apa pun yang membaca `styleUrl` standar KML — bukan meniru format
`ExtendedData`/`piniconcode` milik Map Marker sendiri, karena KML standar
saja sudah cukup dan bisa diimpor aplikasi peta offline mana pun, tidak
terikat satu merek tertentu. Toko tanpa koordinat dilewati apa adanya, sama
seperti Peta Rute di layar ini sendiri. Urutan koordinat KML SELALU
longitude dulu baru latitude — kebalikan dari kebiasaan "lat,lng" di
tempat lain pada aplikasi ini.

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

### Bonus produk (admin/superadmin)

Khusus akun admin/superadmin, Input Pesanan punya langkah tambahan **"3.
Pilih Bonus Produk & Jumlah Dus"** di antara Pilih Produk dan Catatan (yang
untuk admin/superadmin ikut bergeser jadi nomor 4). Sales sama sekali tidak
melihat langkah ini — tidak ada perubahan apa pun di layarnya.

- **Fungsinya sama seperti Pilih Produk biasa**, tapi harganya **SELALU
  Rp 0** — apa pun produknya dan berapa pun jumlah dusnya. Ini perlakuan
  khusus untuk toko yang berhak mendapat bonus, bukan produk yang kebetulan
  gratis. Stok tetap berkurang sesuai logic mutasi stok yang sama seperti
  item biasa (dikunci saat pesanan dibuat, keluar saat terkirim).
- **Wajib memilih "Sales"** di bawah tabel bonus — karena admin sendiri yang
  menginput (bukan sales), faktur tetap perlu tahu sales mana yang
  bertanggung jawab. Nama ini yang tercetak di kolom Sales pada faktur,
  bukan nama admin yang mengetik (`Pesanan::sales()`, kolom `sales_id`;
  faktur jatuh kembali ke `pembuat` kalau `sales_id` kosong, mis. pesanan
  yang diinput sales sendiri).
- **Produk yang sama boleh dipilih di kedua tabel.** Item A 1 dus di Pilih
  Produk dan item A 1 dus lagi di Pilih Bonus tersimpan sebagai **dua baris
  terpisah** (`pesanan_items.is_bonus`) — di faktur keduanya tercetak sendiri
  sendiri, satu harga normal, satu lagi Disc% 100 dan harga 0. Batasan unik
  pada `pesanan_items` sengaja mencakup `is_bonus` (`pesanan_id, produk_id,
  is_bonus`) supaya kombinasi ini tidak ditolak basis data.
- **Dus bonus ikut dihitung ke total dus** (termasuk batas minimal per
  toko) dan ke pemeriksaan stok — permintaan biasa dan bonus untuk produk
  yang sama diperiksa **gabungan**, bukan dua kali terpisah.
- **Tidak pernah dihitung sebagai Insentif Sales** — pesanan bonus dibuat
  admin (`dibuat_oleh`), bukan sales, jadi otomatis tersaring lewat
  penyaring peran yang sudah ada di `InsentifSales::pesanans()`. Lapis
  pertahanan kedua ada di `perSales()`: dus dengan `is_bonus = true`
  dikecualikan eksplisit dari jumlahnya, sekalipun asumsi di atas berubah.
- **Di Pendapatan tetap terhitung Rp 0** — tidak ada kode khusus yang
  diperlukan di sana: `harga_satuan = 0` pada item bonus mengalir apa
  adanya lewat `PesananItem::terkirim()`/`Pesanan::tagihan()`, yang memang
  sudah digerakkan oleh harga produk, bukan bercabang berdasarkan jenis
  item.
- Keamanan: state komponen (`barisBonus`, `salesId`) tidak pernah dipercaya
  langsung dari sisi klien — `BuatPesanan::simpan()` selalu mengecek ulang
  `auth()->user()->isAdmin()` sebelum mengirim keduanya ke
  `PesananService::buat()`; sales yang memaksa mengisi properti ini tetap
  tidak tersimpan sebagai bonus.

### Toko Belum Pesan 1 Bulan

Tombol di pojok kanan atas Daftar Pesanan (badge oranye menunjukkan
jumlahnya, dobel fungsi sebagai notifikasi) membuka daftar toko yang
kemungkinan butuh ditindaklanjuti sales-nya. Tiga aturan yang menentukan
siapa masuk daftar (`DaftarPesanan::tokoTidakAktifSemua()`):

- **Harus terdaftar di Penugasan Toko BULAN INI** — toko yang tidak jadi
  tanggungan sales mana pun bulan ini tidak pernah muncul di sini, sekalipun
  sudah lama tidak pesan; ini laporan "tanggungan yang terbengkalai", bukan
  "semua toko yang sepi".
- **Belum punya pesanan SELESAI dalam jendela BERGULIR 1 bulan** dari hari
  ini (`selesai_at >= sebulan lalu`), bukan batas bulan kalender — toko yang
  pesanannya tuntas 29 hari lalu tetap dianggap aktif walau kalendernya
  sudah berganti bulan.
- Pesanan yang masih ORDER/PROCESS/DELIVERY (belum tuntas) tidak menghitung
  toko sebagai aktif — cuma pesanan yang BENAR-BENAR selesai yang dianggap.

Dikelompokkan per sales (toko tanggungan A dan tanggungan B tidak tercampur
dalam satu daftar), bisa disaring per sales dan dicari per nama/kode toko.
Badge jumlah pada tombolnya sengaja SELALU menunjukkan angka penuh
tanpa terpengaruh penyaring yang sedang aktif di modal — dihitung dari
`tokoTidakAktifSemua()` (query mentah), bukan `tokoTidakAktif()` (versi
yang sudah disaring).

### Perlakuan stok

Stok dipisah menjadi dua angka supaya pembatalan tidak pernah merusak catatan
gudang:

- **`stok`** — barang yang benar-benar ada di rak. Baru berkurang saat dus itu
  benar-benar keluar mobil untuk selamanya (diterima toko, atau diampaskan).
- **`stok_reserved`** — barang yang sudah dijanjikan (masih di gudang MAUPUN
  masih di dalam mobil). Naik begitu pesanan dibuat, turun hanya saat dus itu
  benar-benar kembali ke gudang atau benar-benar keluar untuk selamanya.

Yang bisa dipesan adalah selisih keduanya (`stok_tersedia`). Setiap
pergerakan tercatat di tabel `stok_mutasis`.

Aturan yang mengikat ketiga tindakan lapangan: **`stok` hanya berkurang
sebanyak dus yang benar-benar diterima toko atau diampaskan — dan
`stok_reserved` mengikuti aturan yang SAMA persis, bukan aturan sendiri.**

Ini memperbaiki bug nyata yang dilaporkan pengguna: versi sebelumnya melepas
`stok_reserved` SEKETIKA sebuah toko dibatalkan atau dicoret notanya —
padahal dus-nya sendiri belum pulang ke gudang, masih fisik di dalam mobil,
kampas-eligible. `stok_tersedia` (stok dikurangi reservasi) jadi naik seolah
barangnya sudah ada di rak, padahal masih di jalan — toko lain bisa
dijanjikan barang yang secara fisik belum bisa diambil, menciptakan selisih
antara stok sistem dan stok aktual di gudang. Aturan yang benar:

- **Batal di lapangan** — kuncian SENGAJA TIDAK dilepas sama sekali. Dus-nya
  masih di mobil, kampas-eligible, jadi tetap terkunci.
- **Coret nota** — `stok` dan `stok_reserved` sama-sama berkurang HANYA
  sebesar yang benar-benar diterima toko. Sisanya (dipesan dikurangi
  diterima) tetap terkunci, sama seperti batal.
- **Kampas** — di sinilah kuncian yang tertunda dari batal/coret akhirnya
  lepas, karena barulah saat inilah dus itu benar-benar meninggalkan mobil
  untuk selamanya (ke toko kampas, bukan ke toko tujuan pesanan semula).
  `stok` dan `stok_reserved` berkurang bersamaan, sebesar yang diampaskan.

Lihat `PengirimanService::keluarkanStok()` — sekarang cuma satu angka
(`$jumlah`) yang dipakai untuk keduanya, bukan dua parameter terpisah seperti
sebelumnya, persis karena keduanya SELALU sama besar sejak perbaikan ini.

### Riwayat Mutasi — menelusuri kenapa stok sebuah produk berubah

Tombol **"Riwayat Mutasi"** di Master Produk (`DaftarProduk`, satu per baris
produk) membuka daftar seluruh baris `stok_mutasis` produk itu — terurut
paling baru dulu, bisa disaring per **jenis mutasi** dan **rentang
tanggal**. Tiap baris menunjukkan jumlah perubahan (hijau untuk
positif/masuk, merah untuk negatif/keluar), stok fisik & terkunci
SESUDAH mutasi itu, keterangannya, kolom **Terkait** (kode pesanan atau
nama kendaraan yang memicunya — kosong untuk penyesuaian manual), dan
siapa yang melakukannya.

Kolom `tipe` sendiri sebelumnya cuma kolom `enum` DB polos tanpa padanan
label — ditambah `App\Enums\JenisMutasiStok` (lima kasus: `Reserve`,
`Release`, `Keluar`, `Masuk`, `Penyesuaian`, persis nilai yang sudah
dipakai di seluruh `PesananService`/`PengirimanService`/`DaftarProduk`
sebelumnya) dengan `label()`/`badge()`, mengikuti pola yang sama dengan
`StatusStop`/`StatusPesanan`. Perubahan ini AMAN terhadap kode lama:
setiap `StokMutasi::create(['tipe' => 'masuk', ...])` yang sudah ada di
seluruh basis kode tetap jalan apa adanya — cast enum Eloquent menerima
string mentah saat ditulis, cuma sisi baca (`$mutasi->tipe`) yang berubah
dari string polos jadi instance enum.

### Menutup sisa kampas yang tidak habis (admin/superadmin)

Sisa yang terkunci itu ("masih di mobil, kampas-eligible") tidak otomatis
pernah lepas kalau driver tidak menghabiskannya lewat kampas hari itu juga.
**Admin dan superadmin bisa melihat SEMUA kendaraan, baik yang sudah
ditetapkan drivernya maupun yang masih kosong** — lewat menu **Pengiriman
Driver** (`/driver`, sama seperti yang dibuka driver untuk memilih mobilnya
sendiri) atau tautan "👁" di kartu kendaraan pada Generate Routing setelah
rute disetujui — tapi hanya untuk memantau.

Dua lapis yang menjaga ini, dan keduanya penting — kesalahan yang pernah
terjadi di sini justru salah satu lapisnya kelewatan:

- **Rutenya sendiri** perlu peran admin secara eksplisit
  (`peran:driver,admin` di `routes/web.php`) — `PastikanPeran` HANYA
  membebaskan superadmin secara otomatis, admin biasa TIDAK ikut lolos
  kalau rutenya cuma `peran:driver`. Kedua rute (`/driver` dan
  `/driver/mobil/{kendaraan}`) perlu keduanya disebut.
- **`PilihMobil::kendaraans()`** (daftar kendaraan di `/driver`) menyaring
  ke "belum diambil siapa pun atau sudah milik saya sendiri" — aturan yang
  benar untuk DRIVER (tidak boleh melihat mobil driver lain), tapi salah
  untuk admin, yang justru perlu melihat SEMUA termasuk yang sudah
  dipegang driver lain. `PilihMobil::ambil()` juga perlu jalur pintas
  serupa: admin yang mengklik kartu kendaraan siapa pun langsung diarahkan
  ke layar kunjungannya, tidak pernah ditolak dengan "sudah diambil driver
  lain" (itu penolakan yang cuma berlaku untuk driver sungguhan) dan tidak
  pernah ikut mengklaim kendaraannya.

Begitu masuk ke layar kunjungan kendaraan, semua tindakan driver (unggah
nota, batalkan, kampas) dikunci di sisi SERVER lewat
`DaftarKunjungan::pastikanBisaBertindak()`, bukan cuma disembunyikan di
tampilan — tombol yang tersembunyi tetap bisa dipicu langsung lewat
panggilan komponen kalau cuma disembunyikan di Blade saja. Sebaliknya,
`selesaikanKendaraan()` mengunci arah yang berlawanan: driver ditolak 403
kalau mencoba memicunya, cuma admin/superadmin yang boleh.

Satu-satunya tindakan yang boleh dilakukan admin/superadmin di layar ini
adalah **Selesaikan Mobil** (`PengirimanService::selesaikanKendaraan()`):
mengembalikan seluruh sisa kampas yang belum diampaskan ke stok gudang,
dipakai kalau driver sudah tidak akan menghabiskan sisa muatannya lagi hari
itu. Aman dijalankan berulang kali — mutasinya ditandai `kendaraan_id`
(bukan `pesanan_id`, karena sisa satu kendaraan biasanya berasal dari
beberapa toko yang batal/dicoret sekaligus) dan `PengirimanService::jatahKampas()`
mengurangkan jumlah yang sudah dikembalikan lewat mutasi ini, jadi sisa yang
sudah "ditutup buku"-nya tidak akan ditawarkan lagi ke driver maupun
dikembalikan dua kali kalau admin menjalankannya lagi nanti.

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
  Total qty (jumlah dus keseluruhan) tercetak sejajar kolom Qty tepat di
  bawah tabel item, di kedua jalur (HTML/PDF via `nota-pesanan.blade.php`
  maupun ESC/P via `EscpNotaBuilder`) — sebelumnya versi ESC/P tidak
  pernah menghitungnya sama sekali, cuma versi PDF yang punya baris ini.
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

Dua aturan yang sengaja beda dari pesanan biasa:

- **Tidak ada minimal pembelian** — mulai dari 1, bukan `min_dus_per_toko`.
- **Toko yang masih punya pesanan pengantaran aktif tetap boleh dilayani** —
  POS tidak bersinggungan dengan routing sama sekali, jadi aturan "satu
  pesanan aktif per toko" (yang ada untuk mencegah konflik rute) tidak
  berlaku.

**Stoknya TETAP dibandingkan dengan `stok_tersedia`** (`stok - stok_reserved`),
SAMA seperti pesanan biasa — bukan `produks.stok` fisik mentah. Awalnya POS
sengaja dibuat memeriksa `stok` mentah dengan alasan "menjual langsung dari
rak, reservasi pesanan lain tidak relevan", tapi alasan itu keliru untuk dus
yang sedang di dalam mobil: `stok` fisik baru berkurang saat barang
benar-benar sampai ke toko (lihat `PesananService::keluarkanStok()`), jadi
dus yang sudah dimuat ke mobil (baik yang sedang dikirim, maupun sisa
kampas dari toko yang batal/dicoret dan belum diampaskan atau ditutup admin
— lihat [Tiga keputusan driver di lapangan](#tiga-keputusan-driver-di-lapangan))
masih terhitung "ada" di `stok` padahal fisiknya sedang tidak di rak. Kalau
POS dibiarkan menjual dari angka itu, dus yang sama bisa terjanjikan dua
kali: sekali ke pesanan/kampas yang menguncinya, sekali lagi ke pembeli POS.
Diperbaiki dengan menyamakan pemeriksaannya ke `stok_tersedia` di
`PesananService::buatPos()` dan pre-check `Kasir::halangan()`.

Konsekuensinya, stok yang benar-benar keluar lewat POS TIDAK memakai
`keluarkanStok()` yang sama dengan pesanan biasa — method itu ikut memotong
`stok_reserved` dengan asumsi jumlahnya persis sama dengan kuncian yang
sebelumnya dipasang `kunciStok()` untuk pesanan yang sama. POS tidak pernah
lewat `kunciStok()` sama sekali (tidak ada fase reservasi, barangnya
langsung keluar dalam satu langkah), jadi memakai `keluarkanStok()` apa
adanya akan salah sasaran: memotong kuncian `stok_reserved` milik pesanan
lain yang sama sekali tidak berkaitan dengan transaksi POS ini. Karena itu
POS memakai method terpisah, `keluarkanStokLangsung()` — hanya memotong
`stok` fisik, sama sekali tidak menyentuh `stok_reserved`.

Pembayarannya langsung diminta saat itu juga dan disimpan dalam transaksi
yang sama dengan pesanannya, bukan lewat `PelunasanService` terpisah
belakangan. Untuk sekarang **hanya cash** (`Kasir::$nominalCash`) — opsi
transfer sengaja belum ada karena belum dibutuhkan operasional; kolom
`nominal_transfer` di basis data tetap ada dan selalu dikirim `0` dari
kasir, jadi menambahkannya kembali nanti tidak perlu migrasi baru, cukup
menambah opsi di layar. Nominalnya diketik manual (tombol "Isi Total
Belanja" cuma bantuan awal, tetap bisa diubah) dan harus persis sama
dengan total belanja sebelum tombol Simpan aktif — pola yang sama dengan
Pelunasan (lihat di atas), lengkap dengan pesan pas/kurang/lebih dan
format titik ribuan sambil mengetik.

Sempat dicoba versi dua kolom nominal terpisah (cash & transfer, sama
seperti Pelunasan) untuk kasir, tapi itu memungkinkan kedua kolom terisi
bersamaan dan JUMLAHNYA kebetulan pas dengan tagihan padahal maksudnya
bukan pembayaran campuran — membingungkan untuk transaksi yang seharusnya
satu metode saja. Dibatalkan sebelum opsi transfer sempat dipakai
siapa pun, jadi tidak ada riwayat/migrasi yang perlu disesuaikan.

### Mencetak nota untuk transaksi POS

Layar Kasir menampilkan link **"Cetak Nota"** di banner sukses begitu
transaksi tersimpan (di sebelah "Lihat riwayat pendapatan"), membuka nota
di tab baru — memakai templat dan keempat jalur cetak yang PERSIS SAMA
dengan pesanan biasa (lihat
[Mencetak nota dan packing list](#mencetak-nota-dan-packing-list)): Cetak,
Unduh PDF, Unduh ESC/P, maupun jalur `ondprint://` untuk OND Print Helper.
Baris "Cetak Nota" yang sama juga muncul di Daftar Pesanan untuk pesanan
berjenis POS, karena layar itu tidak pernah menyaring berdasarkan `jenis`.

Sebelum ini, transaksi POS **sama sekali tidak punya nota** — bukan karena
sengaja disembunyikan, tapi karena gerbang cetak (`StatusPesanan::bisaDicetak()`)
hanya mengizinkan status PROCESS/DELIVERY, sementara POS langsung tercatat
**SELESAI** seketika dibuat (lihat `PesananService::buatPos()` di atas) dan
tidak pernah melewati fase-fase itu sama sekali — jadi apa pun keadaannya,
notanya mustahil tercapai, bukan cuma belum sempat. Diperbaiki lewat
accessor baru `Pesanan::bisa_dicetak` (dipakai di seluruh gerbang cetak,
menggantikan pemanggilan `$pesanan->status->bisaDicetak()` langsung): tetap
memakai aturan status PROCESS/DELIVERY untuk pesanan biasa, tapi
mengecualikan SELURUH pesanan berjenis **POS** dari syarat itu, apa pun
statusnya (praktiknya selalu Selesai). Templat notanya sendiri tidak perlu
disunting sama sekali — satu-satunya elemen yang berkaitan dengan rute
pengantaran cuma label kotak tanda tangan "Driver," yang murni kosmetik
(sekadar kotak kosong untuk ditandatangani, tidak bergantung data kendaraan
apa pun), jadi tetap tampil apa adanya untuk nota POS.

### Bonus produk (admin/superadmin)

Sama seperti langkah bonus di Input Pesanan: khusus admin/superadmin, ada
langkah tambahan **"Pilih Bonus Produk & Jumlah Dus"** (di antara Pilih
Produk dan Pembayaran, yang untuk admin/superadmin ikut bergeser nomornya —
Catatan pun ikut bergeser). Sales tidak melihat langkah ini sama sekali —
tidak ada perubahan apa pun di layarnya.

Fungsi dan perlakuannya identik dengan bonus di Input Pesanan — harga
SELALU Rp 0 apa pun produk/jumlahnya, produk yang sama boleh dipilih di
kedua daftar dan tetap tersimpan sebagai dua baris terpisah, stok diperiksa
gabungan (biasa+bonus) per produk, dan nominal cash cuma perlu mengikuti
total item biasa (bonus tidak pernah ikut ditagihkan). Satu-satunya beda:
**tidak ada "Pilih Sales"** — notanya tetap bisa dicetak (lihat
[Mencetak nota untuk transaksi POS](#mencetak-nota-untuk-transaksi-pos) di
bawah), tapi atribusi penjualnya otomatis memakai akun yang menginput
(`dibuat_oleh`), bukan dipilih manual seperti langkah "atas nama sales" di
Input Pesanan.

Karena stok POS memang sudah keluar fisik seketika (bukan direservasi lalu
dilepas saat pengiriman seperti pesanan biasa), item bonus di sini langsung
memakai `keluarkanStok()` yang sama dengan item biasa — tidak ada langkah
tambahan apa pun untuk "melepas" nanti.

### Tanpa Toko — transaksi yang tidak diikat ke toko tertentu (admin/superadmin)

Pilihan **"Tanpa Toko"** di langkah 1 (tab di sebelah "Toko", khusus
admin/superadmin) dipakai untuk transaksi yang tidak perlu diikat ke
toko/perusahaan pelanggan tertentu — pembeli perorangan, atau pemberian ke
karyawan, misalnya. **Bukan jenis transaksi khusus**: produk, langkah bonus,
maupun pembayarannya tetap PERSIS SAMA seperti transaksi POS yang memilih
toko sungguhan — nominal cash tetap wajib pas dengan total belanja seperti
biasa. Satu-satunya beda adalah toko tidak wajib diisi. Kalau transaksinya
memang perlu cuma-cuma, pakai langkah bonus yang sudah ada (harga 0,
independen dari Tanpa Toko) — Tanpa Toko dan bonus adalah dua sumbu yang
lepas satu sama lain, boleh dipakai salah satu, keduanya, atau tidak
keduanya sama sekali.

**Diimplementasikan lewat `Toko::internal()`**, satu baris `Toko` semu
(kode `INTERNAL-POS`, dibuat otomatis saat pertama dipakai lewat
`firstOrCreate`) — bukan `toko_id` yang benar-benar `NULL`. Alasannya:
puluhan tempat di aplikasi (faktur, Daftar Pesanan, Pendapatan, peta
dashboard, dsb.) mengandalkan `$pesanan->toko` selalu ada; mengaudit
semuanya satu per satu untuk null-safety jauh lebih berisiko daripada
menyediakan satu baris toko sungguhan yang aman didereferensi di mana pun.
Baris ini sengaja `aktif = false` supaya otomatis tersembunyi dari SEMUA
pencarian/listing toko biasa (`Toko::aktif()` dipakai di pencarian toko POS
maupun Input Pesanan, kandidat routing, peta) — satu-satunya tempat ia bisa
sengaja terlihat adalah Master Toko, yang memang menampilkan toko nonaktif
juga. Karena statusnya sengaja nonaktif, `PesananService::buatPos()`
mengecualikannya secara eksplisit (`Toko::isInternal()`) dari pemeriksaan
"toko harus aktif" yang berlaku untuk toko sungguhan.

Mengaktifkan tab-nya cuma mengisi `tokoId` ke toko semu ini — sales tidak
diberi UI untuk mengaktifkannya sama sekali, dan `tokoId` mereka tidak
pernah otomatis terisi ke toko semu ini, jadi memaksa properti komponen
`tanpaToko=true` lewat `$wire.set()` tidak membuat toko jadi opsional bagi
mereka (tokoId kosong tetap ditolak seperti biasa).

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

### Tanggal pendapatan & insentif kategori driver mengikuti tanggal keberangkatan, bukan tanggal lunas/selesai

Toko yang kendaraannya berangkat tanggal 20 tapi baru dilunasi tanggal 22
(atau baru benar-benar SELESAI diterima tanggal 22) tetap terhitung
sebagai pendapatan **maupun insentif sales** tanggal **20** — bukan 22.
Dus-nya memang sudah keluar gudang tanggal 20; kapan tagihannya kebetulan
dilunasi atau kapan toko sungguh menerimanya belakangan tidak seharusnya
menggeser hari mana yang "menjual" dus itu. Berlaku di KEDUA layar
(**Pendapatan** dan **Insentif Sales**) sekaligus, supaya keduanya selalu
sepakat soal "pesanan ini masuk hitungan tanggal berapa" — sebelum
diseragamkan, Insentif Sales masih memakai `selesai_at` sendiri,
membuatnya bisa melenceng dari Pendapatan yang sudah lebih dulu dipindah
ke tanggal keberangkatan.

Ini cuma berlaku untuk kategori **driver** (rute biasa & kampas, keduanya
lewat kendaraan) — kategori **pos** tidak pernah lewat kendaraan sama
sekali (langsung lunas seketika saat dibuat, `tanggal_lunas`-nya memang
satu-satunya tanggal yang bermakna), jadi tetap memakai `tanggal_lunas`
seperti sebelumnya.

Dipusatkan lewat DUA anggota `Pesanan` yang saling melengkapi, dipakai
BERSAMA oleh `Pendapatan::pesanans()` maupun `InsentifSales::pesanans()`
supaya logikanya tidak pernah dobel-tulis dan diam-diam melenceng:

- **Accessor `tanggal_pendapatan`** — untuk kategori driver mengambil
  `stop->kendaraan->batch->tanggal` (`RoutingBatch::tanggal`, tanggal
  keberangkatan yang sama dengan bagian
  [Tanggal keberangkatan berbeda dari tanggal dibuat](#tanggal-keberangkatan-berbeda-dari-tanggal-dibuat)),
  jatuh kembali ke `tanggal_lunas` kalau rantai relasinya ternyata putus
  (mis. data lama yang tidak lengkap), supaya layar-layar ini tidak
  pernah pecah gara-gara satu baris yang datanya tidak biasa. Dipakai
  untuk pengelompokan `ringkasanHarian()`/grafik Pendapatan, pengurutan
  tabel riwayatnya, dan kolom Tanggal di tabel itu sendiri.
- **Scope `tanggalPendapatanAntara($dari, $sampai)`** — versi kueri dari
  aturan yang sama, dipakai kedua layar untuk penyaring "hari/bulan/rentang"
  langsung di lapisan basis data (bukan disaring belakangan di memori).
  Dua batasnya dibandingkan lewat `whereDate()`, BUKAN `whereBetween()`
  dengan string tanggal polos. Ini bukan sekadar gaya penulisan: kolom
  `date` di Eloquent bisa saja tersimpan dengan sisa waktu `00:00:00` di
  baliknya tergantung driver basis data (SQLite yang dipakai pengujian
  menyimpannya apa adanya sebagai `'2026-08-20 00:00:00'`, sedangkan
  MySQL asli memotongnya bersih jadi `'2026-08-20'` karena tipe kolom
  `DATE` fisik tidak bisa menyimpan komponen waktu sama sekali) —
  `whereBetween(['2026-08-20', '2026-08-20'])` gagal mencocokkan nilai
  yang punya sisa waktu itu (secara leksikografis dianggap LEBIH BESAR
  dari batas atasnya), sedangkan `whereDate()` mengekstrak bagian
  tanggalnya lewat SQL sebelum dibandingkan sehingga kebal dari
  perbedaan format penyimpanan antar driver basis data.

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

## Insentif

Menu **Insentif** (header baru di navigasi admin) — dasar hitung insentif
per akun, dipecah per peran karena cara menghitungnya beda:

- **Insentif Sales** (`/insentif/sales`, sudah ada) — berapa dus yang
  berhasil terantar per akun sales, dihitung dari siapa yang MENGINPUT
  pesanannya (`Pesanan::dibuat_oleh`).
- **Insentif Driver** (menyusul) — akan dihitung dari kunjungan yang
  diselesaikan driver di lapangan, bukan dari siapa yang menginput
  pesanan. Header menu dan strukturnya sudah disiapkan supaya
  menambahkannya nanti tinggal menambah satu route + satu item menu, tidak
  perlu mengubah yang sudah ada.

### Insentif Sales

Tiga aturan yang menentukan dus mana yang dihitung:

- **Hanya pesanan berstatus SELESAI** — pesanan yang masih ORDER/PROCESS/
  DELIVERY belum jadi apa-apa buat sales, dan yang CANCEL memang tidak
  jadi terkirim.
- **Dus yang dihitung adalah yang BENAR-BENAR terkirim**
  (`PesananItem::terkirim`, sudah memperhitungkan koreksi nota dicoret),
  bukan `jumlah_dus` mentah — toko yang cuma mengambil sebagian tidak
  boleh dihitung penuh sebagai insentif.
- **Hanya pesanan yang penginputnya berperan Sales** — dicek lewat
  `whereHas('pembuat', fn ($q) => $q->where('role', PeranPengguna::Sales))`,
  bukan lewat `jenis` pesanannya. Ini otomatis menyingkirkan dua kasus
  tanpa perlu pengecualian eksplisit: pesanan **kampas** dibuat DRIVER
  (`dibuat_oleh` = id driver, bukan sales) jadi otomatis tidak ikut — itu
  akan jadi bagian Insentif Driver nanti; dan penjualan **POS** yang
  diinput ADMIN sengaja tidak dihitung sebagai insentif sales, sementara
  POS yang diinput sales sendiri tetap ikut — insentif ini soal siapa yang
  menjual, bukan soal jalur penjualannya (driver vs POS).

Tanggalnya mengikuti `Pesanan::selesai_at` (kapan pesanan SUNGGUH tuntas),
bukan `tanggal` (target awal) atau `created_at` (waktu diinput) —
konsisten dengan Pelunasan/Pendapatan yang juga memakai patokan yang sama.
Empat mode penyaring seperti Pendapatan (Harian/Bulanan/Rentang/Semua),
tapi **default-nya Bulanan** (Pendapatan default-nya Harian) — insentif
memang lazimnya direkap bulanan.

Tabelnya terurut dus terbanyak dulu (papan peringkat), dilengkapi grafik
batang HORIZONTAL (`resources/js/insentif-chart.js`, bukan tegak seperti
grafik Pendapatan) — batang mendatar membiarkan nama sales terbaca penuh
di sumbu tanpa terpotong atau diputar miring, sengaja dipisah jadi modul
sendiri dari `pendapatan-chart.js` karena datasetnya beda konsep (dus per
orang, bukan uang per hari).

---

## Pengujian

```bash
php artisan test
```

567 tes, mencakup:

- **[`tests/Feature/TokoTidakAktifTest.php`](tests/Feature/TokoTidakAktifTest.php)** —
  toko yang ditugaskan bulan ini tapi belum pernah pesan, atau pesanan
  terakhirnya SELESAI lebih dari 1 bulan lalu, keduanya masuk daftar;
  toko yang selesai KURANG dari 1 bulan lalu tidak masuk; pesanan yang
  belum SELESAI tidak membuat toko dianggap aktif; toko yang tidak
  ditugaskan ke sales mana pun bulan ini tidak pernah muncul meski tidak
  aktif; pengelompokan per sales, penyaring sales dan pencarian toko;
  badge jumlah pada tombol TIDAK terpengaruh penyaring yang sedang aktif
  di modal; pesan kosong yang beda antara "belum ada penugasan sama
  sekali" dan "semua toko tanggungan sudah aktif"; dan halaman tampil
  dengan BEBERAPA sales dan toko sekaligus tanpa lazy load — pelajaran
  yang sama seperti `InsentifSalesTest` dan `DaftarPesananFilterTest`.
- **[`tests/Feature/PengirimanLapanganTest.php`](tests/Feature/PengirimanLapanganTest.php)**
  (ditambah, bukan baru) — bug nyata: `stok_reserved` TIDAK dilepas begitu
  toko dibatalkan atau dicoret notanya (dus-nya masih di mobil, bukan
  kembali ke gudang); coret nota hanya melepas kuncian sebesar yang
  benar-benar diterima, sisanya tetap terkunci; kampas-lah yang akhirnya
  melepaskan kuncian yang tertunda itu; dan `selesaikanKendaraan()`
  (admin) mengembalikan sisa yang belum diampaskan ke gudang, aman
  dijalankan berulang (hanya mengembalikan sisa yang BELUM pernah
  dikembalikan), mencatat mutasi bertanda `kendaraan_id` bukan
  `pesanan_id`, dan setelahnya `jatahKampas()`/`kampas()` menolak sisa
  yang sudah ditutup buku. Ditambah: admin/superadmin bisa membuka
  kendaraan siapa pun di layar kunjungan driver tapi ditolak (403) kalau
  mencoba memicu tindakan driver LANGSUNG lewat panggilan komponen
  (bukan cuma tombolnya yang disembunyikan di tampilan), sales sama
  sekali tidak bisa membuka layar ini, dan driver (bukan admin) ditolak
  memicu `selesaikanKendaraan()`.

- **[`tests/Feature/InsentifSalesTest.php`](tests/Feature/InsentifSalesTest.php)** —
  hanya admin yang bisa akses, mode default Bulanan; dus dihitung dari yang
  BENAR-BENAR terkirim (bukan jumlah pesanan mentah, dites lewat nota yang
  dicoret); pesanan yang belum/tidak SELESAI tidak dihitung; pesanan kampas
  tidak ikut karena penginputnya driver bukan sales (tanpa pengecualian
  eksplisit lewat `jenis`); POS yang diinput sales terhitung, POS yang
  diinput admin tidak; jumlah toko dihitung UNIK per sales, bukan jumlah
  pesanan; keempat mode penyaring (hari/bulan/rentang/semua) menyaring
  lewat `Pesanan::tanggalPendapatanAntara()` — tanggal KEBERANGKATAN
  kendaraan untuk pesanan yang lewat rute, bukan `selesai_at` (scope
  bersama dengan Pendapatan, lihat
  [Tanggal pendapatan & insentif kategori driver](#tanggal-pendapatan--insentif-kategori-driver-mengikuti-tanggal-keberangkatan-bukan-tanggal-lunasselesai));
  dan halaman tampil dengan BEBERAPA sales
  sekaligus tanpa lazy load — pelajaran yang sama seperti
  `rute:perbaiki-geometry` dan `DaftarPesananFilterTest`; dan dus dari item
  bonus (`is_bonus = true`) tetap dikecualikan dari jumlahnya sebagai
  pertahanan lapis kedua, sekalipun secara hipotetis muncul pada pesanan
  yang penginputnya berperan sales.
- **[`tests/Feature/PendapatanRiwayatTest.php`](tests/Feature/PendapatanRiwayatTest.php)** —
  penyaring tabel riwayat lewat kode pesanan, nama toko, kategori (`pos`
  vs `driver` — termasuk memastikan rute biasa DAN kampas sama-sama masuk
  `driver`), dan metode bayar (`cash`/`transfer`, termasuk pembayaran
  campuran yang muncul di kedua penyaring metode); pencarian yang tidak
  cocok menampilkan pesan kosong yang berbeda dari benar-benar tidak ada
  transaksi; tombol Bersihkan mengembalikan seluruh transaksi; dan
  penyaring riwayat sengaja tidak mengubah kartu ringkasan kategori di
  atasnya.
- **[`tests/Feature/PendapatanTanggalKeberangkatanTest.php`](tests/Feature/PendapatanTanggalKeberangkatanTest.php)** —
  pesanan kategori driver yang kendaraannya berangkat tanggal 20 tapi baru
  lunas tanggal 22 tetap terhitung sebagai pendapatan tanggal 20:
  `Pesanan::tanggal_pendapatan` mengambil tanggal keberangkatan
  (`RoutingBatch::tanggal`) bukan `tanggal_lunas`; mode "hari" pada tanggal
  keberangkatan menemukannya, mode "hari" pada tanggal pelunasan TIDAK
  (tidak terhitung dua kali); `ringkasanHarian()`/grafik mengelompokkannya
  ke tanggal keberangkatan; mode "rentang" yang mencakup tanggal
  keberangkatan menemukannya walau tanggal lunasnya di luar rentang; dan
  pesanan POS (tidak pernah lewat kendaraan) tidak terpengaruh sama
  sekali, tetap memakai `tanggal_lunas` seperti sebelumnya. Regresi nyata
  yang ditemukan sambil membangun ini: `whereBetween()` dengan string
  tanggal polos gagal mencocokkan kolom `date` yang tersimpan dengan sisa
  waktu `00:00:00` (kebiasaan SQLite yang dipakai pengujian, beda dari
  MySQL asli yang memotongnya bersih karena tipe kolom `DATE` fisik) —
  diperbaiki jadi `whereDate()`, yang kebal dari perbedaan format
  penyimpanan antar driver basis data.
- **[`tests/Feature/PosTest.php`](tests/Feature/PosTest.php)** —
  `PesananService::buatPos()` langsung SELESAI+LUNAS tanpa membuat
  `KendaraanStop`, stok fisik berkurang seketika (bukan lewat reservasi),
  tidak menuntut minimal dus, tetap melayani toko yang masih punya pesanan
  pengantaran aktif, menolak stok fisik kurang dan nominal cash+transfer
  yang tidak pas; **menolak POS kalau stok sedang terkunci pesanan
  pengantaran lain walau stok fisik masih terlihat cukup** (memeriksa
  `stok_tersedia`, bukan `stok` mentah — lihat
  [Point of Sale (POS)](#point-of-sale-pos)), dan memastikan kuncian
  `stok_reserved` milik pesanan lain itu TIDAK ikut terpotong oleh
  penjualan POS (`keluarkanStokLangsung()`); layar Kasir menyelesaikan
  penjualan dari awal sampai
  akhir, menambah baris dari barcode (termasuk memindai kode yang sama dua
  kali menambah jumlah, bukan baris baru) dan menolak barcode yang tidak
  dikenali; tombol Simpan terkunci sampai nominal cash persis sama dengan
  total belanja, dan nominal yang diubah manual SETELAH tombol "Isi Total
  Belanja" ditekan tetap yang tersimpan (bukan otomatis kembali ke total);
  `kodeTerakhir`/`idTerakhir` (dasar banner sukses dan link "Cetak Nota")
  tetap terisi dengan kode dan ID pesanan yang baru dibuat, sengaja tidak
  ikut ter-reset bersama field form lainnya; penjualan POS dan pesanan
  pengantaran biasa yang lunas
  terkategori benar di Pendapatan (`pos` vs `driver`), termasuk lewat blade
  yang sungguhan dirender dengan BEBERAPA pesanan sekaligus — pelajaran
  yang sama seperti pengujian `rute:perbaiki-geometry` dan
  `DaftarPesananFilterTest`: pelanggaran mode ketat pada relasi `toko` baru
  kelihatan begitu koleksinya lebih dari satu model; dan Master Produk
  menolak barcode yang sudah dipakai produk lain tapi membiarkan dua produk
  sama-sama belum punya barcode; dan `<x-pilih-cari>` bisa memilih produk
  lewat `$wire.set()` end-to-end sampai tersimpan di kedua layar (Kasir
  maupun Input Pesanan), termasuk menampilkan label yang sudah terpilih.
- **[`tests/Feature/RiwayatMutasiStokTest.php`](tests/Feature/RiwayatMutasiStokTest.php)** —
  `JenisMutasiStok`: tiap kasus punya `label()`/`badge()`, dan
  `StokMutasi::tipe` otomatis ter-cast jadi instance enum (bukan string
  polos) sementara baris mentahnya di DB tetap tersimpan sebagai string
  biasa; tombol "Riwayat Mutasi" pada Master Produk membuka modal untuk
  produk yang tepat; riwayatnya hanya berisi mutasi milik produk itu
  (bukan produk lain), terurut paling baru dulu; bisa disaring per jenis
  mutasi maupun rentang tanggal; membuka riwayat produk LAIN membersihkan
  penyaring yang tersisa dari produk sebelumnya; tombol Bersihkan
  mengembalikan seluruh riwayat; kolom Terkait menampilkan kode pesanan
  sungguhan untuk mutasi `reserve` yang berasal dari `PesananService::buat()`;
  penyesuaian manual lewat modal "± Stok" yang sudah ada langsung muncul
  di riwayat; dan `tutupRiwayat()` membersihkan seluruh state modal
  (produk yang dipilih maupun penyaringnya).
- **[`tests/Feature/PosBonusTest.php`](tests/Feature/PosBonusTest.php)** —
  langkah bonus admin/superadmin di POS: item bonus tersimpan dengan harga
  0 tapi stok fisik tetap berkurang SEKETIKA (`keluarkanStokLangsung()`,
  sama seperti item biasa, bukan lewat reservasi); nominal cash cukup
  mengikuti total item biasa saja, bonus tidak pernah ikut ditagihkan;
  produk yang sama di kedua daftar tersimpan sebagai DUA baris terpisah;
  pemeriksaan stok GABUNGAN (biasa+bonus per produk) terhadap
  `stok_tersedia`; bonus
  saja tanpa item biasa tetap sah; visibilitas langkah bonus di komponen
  `Kasir` (tersembunyi total untuk sales, termasuk penomoran ulang langkah
  Pembayaran/Catatan untuk admin/superadmin — tanpa "Pilih Sales" sama
  sekali, beda dari Input Pesanan, karena atribusi penjual POS otomatis
  memakai `dibuat_oleh`, tidak dipilih manual); dan tes keamanan yang sama
  seperti `PesananBonusTest`: sales
  yang memaksa mengisi `barisBonus` lewat `$wire.set()` langsung tetap
  TIDAK tersimpan sebagai bonus.
- **[`tests/Feature/PosTanpaTokoTest.php`](tests/Feature/PosTanpaTokoTest.php)** —
  opsi "Tanpa Toko" admin/superadmin: `Toko::internal()` membuat satu baris
  toko semu yang idempoten (dipanggil dua kali menghasilkan id yang sama)
  dan tidak pernah muncul di pencarian toko biasa karena `aktif=false`;
  mengaktifkannya otomatis mengisi `tokoId` ke toko semu itu TANPA mengubah
  apa pun yang lain — langkah Pilih Bonus dan Pembayaran tetap tampil dan
  berfungsi persis seperti biasa; admin bisa menyimpan transaksi Tanpa Toko
  yang BERBAYAR (harga normal, nominal cash tetap wajib pas) sama seperti
  transaksi bertoko; tetap ditolak kalau nominal cash tidak pas atau stok
  fisik kurang; admin bisa mengombinasikan Tanpa Toko dengan langkah bonus
  sekaligus (baris biasa berbayar + baris bonus gratis, toko_id ke toko
  semu); kembali ke tab "Toko" atau memilih toko sungguhan membersihkan
  status `tanpaToko`; `PesananService::buatPos()` sengaja mengecualikan
  toko semu ini dari pemeriksaan "toko harus aktif" (`Toko::isInternal()`)
  yang berlaku untuk toko sungguhan; dan tes keamanan: sales yang memaksa
  `tanpaToko=true` lewat komponen TETAP wajib memilih toko sungguhan
  (`tokoId` mereka tidak pernah otomatis terisi ke toko semu, jadi
  `tokoId` kosong tetap ditolak), dan `aktifkanTanpaToko()` yang dipanggil
  langsung oleh non-admin tidak berefek sama sekali.
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
- **[`tests/Feature/KmlRuteTest.php`](tests/Feature/KmlRuteTest.php)** —
  `KmlRuteBuilder::build()` menghasilkan XML/KML yang benar-benar valid
  (diverifikasi lewat `DOMDocument::loadXML()`) dengan urutan koordinat
  longitude-lalu-latitude yang benar; melewati toko tanpa koordinat, tidak
  ikut jadi Placemark; `styleUrl` tiap Placemark mengikuti warna status
  kunjungannya (pending/selesai/dibatalkan); nama toko yang mengandung
  karakter XML khusus (`&`, `<`, `"`) tetap menghasilkan KML valid dan
  ternormalisasi balik dengan benar lewat `DOMXPath`; dan
  `DaftarKunjungan::unduhKml()` — baik driver maupun admin yang memantau
  bisa mengunduhnya (`assertFileDownloaded()`), isinya persis sama dengan
  yang dihasilkan builder secara langsung, dan nama berkas/content-type-nya
  benar.
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
  tidak pernah tercapai lewat rute cetak); dan faktur pesanan dengan bonus:
  produk yang sama di baris biasa dan baris bonus tercetak sebagai DUA
  baris terpisah (HTML maupun ESC/P), baris bonus menampilkan Disc% 100
  dan harga 0, dan kolom Sales menampilkan akun atas nama yang dipilih
  admin, bukan admin yang mengetik; dan total qty (jumlah dus keseluruhan)
  tercetak sejajar kolom Qty tepat di bawah tabel item ESC/P, terpisah
  dari "Total Harga" (header) dan "Total Invoice" (ringkasan nominal) yang
  kebetulan sama-sama mengandung kata "Total"; dan nota untuk transaksi
  **POS**: `Pesanan::bisa_dicetak` (bukan `StatusPesanan::bisaDicetak()`
  langsung) meloloskan pesanan berjenis POS lewat Cetak/PDF/ESC-P meski
  statusnya SELESAI — sesuatu yang MUSTAHIL tercapai lewat aturan status
  PROCESS/DELIVERY biasa karena POS langsung SELESAI seketika dibuat (lihat
  `PesananService::buatPos()`) — sementara pesanan rute biasa yang sudah
  SELESAI tetap ditolak seperti sebelumnya, membuktikan pengecualiannya
  benar-benar khusus jenis POS, bukan longgar untuk status SELESAI secara
  umum.
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
- **[`tests/Feature/OrderUlangTest.php`](tests/Feature/OrderUlangTest.php)** —
  "Order Ulang"/"Batalkan" untuk pesanan yang dibatalkan dengan alasan
  selain "toko membatalkan pesanan": `Pesanan::bisa_order_ulang` benar
  untuk SEMUA keenam pilihan alasan (kecuali yang sudah final), diuji
  lewat dataset gabungan pembatalan DRIVER di lapangan MAUPUN admin
  langsung dari Daftar Pesanan — keduanya sama-sama harus eligible
  (regresi nyata: awalnya `bisa_order_ulang` keliru mensyaratkan baris
  `KendaraanStop` masih ada, syarat yang cuma benar untuk jalur driver,
  membuat pesanan yang dibatalkan admin langsung dengan alasan yang sudah
  memenuhi syarat tetap tidak menampilkan Order Ulang — dilaporkan
  pengguna, diperbaiki dengan melepas syarat `stop` sama sekali); salah
  untuk pesanan yang belum dibatalkan sama sekali;
  `PesananService::tandaiBatalKarenaToko()` mengubah alasan_cancel TANPA
  menyentuh stok maupun siapa yang sungguh membatalkan (`dibatalkan_oleh`
  tetap apa adanya) untuk KEDUA jalur pembatalan, dan menolak dipanggil
  dua kali; tombol Order Ulang/Batalkan di Daftar Pesanan tampil untuk
  baris yang eligible dari kedua jalur sekaligus; membuka modal Order
  Ulang mengisi baris produk dan sales sesuai pesanan lama; menyimpannya
  membuat pesanan baru dengan item yang sama, berhasil baik dari
  pembatalan driver maupun admin; ditolak dengan galat DI DALAM modal
  (bukan cuma notifikasi) kalau stok tidak mencukupi, modal tetap
  terbuka untuk disesuaikan, dan percobaan susulan yang berhasil TIDAK
  lagi membawa galat lama yang sudah tidak relevan (regresi nyata lain:
  `simpanOrderUlang()` awalnya lupa `resetValidation()`); dan sales tidak
  bisa memicu `bukaOrderUlang()`/`tandaiBatalKarenaToko()` sama sekali (403).
- **[`tests/Feature/PesananBonusTest.php`](tests/Feature/PesananBonusTest.php)** —
  langkah bonus produk admin/superadmin di Input Pesanan: item bonus
  tersimpan dengan harga 0 tapi stok tetap dikunci; admin wajib memilih
  akun sales atas nama (ditolak kalau kosong atau bukan akun berperan
  sales); sales yang menginput sendiri tidak perlu mengisinya; dus bonus
  ikut dihitung ke batas minimal dan ke pemeriksaan stok GABUNGAN
  (biasa+bonus per produk, bukan dua kali terpisah); produk yang sama di
  kedua daftar tersimpan sebagai DUA baris terpisah, bukan digabung;
  visibilitas langkah bonus di komponen Livewire (tersembunyi total untuk
  sales, termasuk penomoran ulang Catatan jadi langkah 4 untuk
  admin/superadmin); dan tes keamanan yang memastikan sales yang memaksa
  mengisi `barisBonus`/`salesId` lewat `$wire.set()` langsung tetap TIDAK
  tersimpan sebagai bonus — pertahanannya di server (`isAdmin()` dicek
  ulang saat `simpan()`), bukan sekadar disembunyikan di tampilan. Ditambah:
  SELURUH pesanan yang diinput admin (baris biasa maupun bonus di
  dalamnya, sekalipun sudah terkirim tuntas sampai SELESAI) sama sekali
  tidak masuk Insentif Sales — penyaringnya memakai peran PENGINPUT
  (`dibuat_oleh`/`pembuat`), bukan menyaring per baris item.
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
  sudah ditetapkan admin lebih dulu lewat Generate Routing. Ditambah: bug
  nyata di mana admin (bukan superadmin) ditolak 403 di rute `/driver`
  sama sekali, dan admin/superadmin tidak bisa melihat maupun membuka
  kendaraan yang sudah dibawa driver lain — dites lewat rute HTTP
  sungguhan (`->get(route(...))`), bukan `Livewire::test()` yang memanggil
  komponen langsung, karena jalur itu tidak pernah melewati middleware
  rute sama sekali sehingga galat 403 pada rute tidak pernah tertangkap.
- **[`tests/Feature/PengirimanLapanganTest.php`](tests/Feature/PengirimanLapanganTest.php)** —
  ketiga tindakan driver dan pembukuan stoknya, termasuk garis rute
  (geometry) dan ETA yang dihitung ulang setelah kampas menambah toko baru
  ke urutan kunjungan — dan total jarak batch routing yang ikut diperbarui:
  pembatalan yang menuntaskan
  toko tanpa menambah dus terkirim, coret nota beserta penolakan di bawah
  batas minimal, jatah kampas per produk yang menolak permintaan melebihi sisa
  produk itu meski total jatahnya cukup, dan pemeriksaan bahwa dus yang tidak
  sampai ke mana pun tidak memotong stok gudang — termasuk POS: dus yang
  masih terkunci di mobil setelah toko dibatalkan di lapangan TIDAK boleh
  dijual lagi lewat POS walau `stok` fisik mentahnya masih terlihat cukup
  (lihat [Point of Sale (POS)](#point-of-sale-pos)), dan begitu kuncian itu
  lepas (kendaraan ditutup admin) baru boleh terjual. Termasuk cegatan di layar:
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
