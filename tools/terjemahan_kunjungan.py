# -*- coding: utf-8 -*-
"""
Membuat lang/<kode>/kunjungan.php untuk keempat bahasa dari satu tabel sumber,
sekaligus menambahkan kunci baru ke nav.php dan master.php.

Ditulis sekali per bahasa dari sumber yang sama, supaya tidak mungkin ada
kunci yang terisi di satu bahasa tapi terlewat di bahasa lain.
"""
import pathlib, re

BAHASA = ['id', 'en', 'zh_CN', 'zh_TW']
AKAR = pathlib.Path(__file__).resolve().parent.parent

T = {
 # --- Judul halaman ---
 'judul_periode': ['Visit Sales', 'Sales Visits', '销售拜访', '銷售拜訪'],
 'ket_periode': ['Kunjungan rutin sales ke toko, dikelompokkan per minggu kerja Senin sampai Sabtu.',
   'Routine sales visits to stores, grouped by the Monday-to-Saturday work week.',
   '销售对门店的例行拜访，按周一至周六的工作周分组。','銷售對門店的例行拜訪，按週一至週六的工作週分組。'],
 'judul_periode_detail': ['Periode :kode', 'Period :kode', '周期 :kode', '週期 :kode'],
 'judul_penugasan': ['Penugasan Toko', 'Store Assignment', '门店分配', '門店分配'],
 'ket_penugasan': ['Tentukan toko mana saja yang menjadi tanggungan tiap sales bulan ini. Satu toko hanya boleh dipegang satu sales.',
   'Decide which stores each sales rep is responsible for this month. A store may belong to only one rep.',
   '设定本月每位销售负责的门店。一家门店只能由一位销售负责。','設定本月每位銷售負責的門店。一家門店只能由一位銷售負責。'],
 'judul_tugas': ['Tugas Saya', 'My Stores', '我的门店', '我的門店'],
 'ket_tugas': ['Toko yang harus Anda kunjungi minggu ini. Kunjungan dimulai dengan memindai QR code pada freezer.',
   'The stores you must visit this week. A visit starts by scanning the QR code on the freezer.',
   '本周您需要拜访的门店。拜访须先扫描冰柜上的二维码。','本週您需要拜訪的門店。拜訪須先掃描冰櫃上的 QR code。'],
 'judul_kunjungi': ['Kunjungan Toko', 'Store Visit', '门店拜访', '門店拜訪'],
 # --- Periode ---
 'periode': ['Periode', 'Period', '周期', '週期'],
 'minggu_ke': ['Minggu ke-:minggu', 'Week :minggu', '第 :minggu 周', '第 :minggu 週'],
 'berjalan': ['Berjalan', 'Running', '进行中', '進行中'],
 'periode_selesai': ['Selesai', 'Closed', '已结束', '已結束'],
 'periode_kosong': ['Belum ada periode kunjungan', 'No visit periods yet', '尚无拜访周期', '尚無拜訪週期'],
 'periode_kosong_ket': ['Periode dibuat otomatis setiap Senin. Tetapkan dulu penugasan toko per sales.',
   'A period opens automatically every Monday. Set up the store assignments first.',
   '周期于每周一自动开启。请先设定门店分配。','週期於每週一自動開啟。請先設定門店分配。'],
 # --- Angka progres ---
 'target': ['Target', 'Target', '目标', '目標'],
 'target_efektif': ['Target efektif', 'Effective target', '有效目标', '有效目標'],
 'dikunjungi': ['Dikunjungi', 'Visited', '已拜访', '已拜訪'],
 'belum_dikunjungi': ['Belum dikunjungi', 'Not visited', '未拜访', '未拜訪'],
 'toko_tutup': ['Toko tutup', 'Closed stores', '门店歇业', '門店歇業'],
 'menunggu_tinjauan': ['Menunggu tinjauan', 'Awaiting review', '待审核', '待審核'],
 'ada_menunggu_tinjauan': [':jumlah laporan toko tutup menunggu keputusan Anda',
   ':jumlah closed-store reports await your decision',':jumlah 份歇业报告等待您处理',':jumlah 份歇業報告等待您處理'],
 'ket_target_efektif': ['Toko yang laporan tutupnya sudah dibenarkan admin dikeluarkan dari target, jadi tidak menjadi beban sales.',
   'Stores whose closure was confirmed by an admin are removed from the target, so they do not count against the rep.',
   '经管理员确认歇业的门店会从目标中剔除，不计入销售的负担。','經管理員確認歇業的門店會從目標中剔除，不計入銷售的負擔。'],
 # --- Status ---
 'status_berjalan': ['Sedang dikunjungi', 'In progress', '拜访中', '拜訪中'],
 'status_selesai': ['Selesai', 'Completed', '已完成', '已完成'],
 'status_tutup_diajukan': ['Tutup — menunggu admin', 'Closed — pending admin', '歇业 — 待管理员确认', '歇業 — 待管理員確認'],
 'status_tutup_disetujui': ['Tutup — dibenarkan', 'Closed — confirmed', '歇业 — 已确认', '歇業 — 已確認'],
 'status_tutup_ditolak': ['Tutup — ditolak', 'Closure rejected', '歇业 — 被驳回', '歇業 — 被駁回'],
 # --- Jenis foto ---
 'foto_sales_depan_toko': ['Sales di depan toko', 'Rep in front of the store', '销售在店门前', '銷售在店門前'],
 'foto_freezer_sebelum': ['Freezer sebelum dibersihkan', 'Freezer before cleaning', '清洁前的冰柜', '清潔前的冰櫃'],
 'foto_freezer_sesudah': ['Freezer sesudah dibersihkan', 'Freezer after cleaning', '清洁后的冰柜', '清潔後的冰櫃'],
 'foto_spanduk': ['Spanduk toko', 'Store banner', '门店横幅', '門店橫幅'],
 'foto_flag_hanger': ['Flag hanger toko', 'Store flag hanger', '门店吊旗', '門店吊旗'],
 'foto_suhu_freezer': ['Suhu freezer', 'Freezer temperature', '冰柜温度', '冰櫃溫度'],
 'petunjuk_sales_depan_toko': ['Berdiri menghadap kamera dengan papan nama toko terlihat jelas.',
   'Stand facing the camera with the store sign clearly visible.','面向镜头站立，确保店招清晰可见。','面向鏡頭站立，確保店招清晰可見。'],
 'petunjuk_freezer_sebelum': ['Ambil sebelum menyentuh apa pun, termasuk bagian yang kotor.',
   'Take this before touching anything, including the dirty parts.','在动手清洁前拍摄，包括脏污部分。','在動手清潔前拍攝，包括髒污部分。'],
 'petunjuk_freezer_sesudah': ['Ambil dari sudut yang sama dengan foto sebelumnya agar bisa dibandingkan.',
   'Shoot from the same angle as the before photo so the two can be compared.','与清洁前从同一角度拍摄，便于对比。','與清潔前從同一角度拍攝，便於對比。'],
 'petunjuk_spanduk': ['Pastikan seluruh spanduk masuk dalam bingkai.','Make sure the whole banner fits in the frame.',
   '确保整幅横幅都在画面内。','確保整幅橫幅都在畫面內。'],
 'petunjuk_flag_hanger': ['Ambil dari jarak yang cukup agar posisi pemasangannya terlihat.',
   'Shoot from far enough back that the mounting position is visible.','拉开距离拍摄，让安装位置可见。','拉開距離拍攝，讓安裝位置可見。'],
 'petunjuk_suhu_freezer': ['Dekatkan kamera ke penunjuk suhu sampai angkanya terbaca.',
   'Move close to the temperature display until the reading is legible.','靠近温度显示屏，直到数字清晰可读。','靠近溫度顯示螢幕，直到數字清晰可讀。'],
 # --- Pemindaian ---
 'pindai_qr': ['Pindai QR Freezer', 'Scan Freezer QR', '扫描冰柜二维码', '掃描冰櫃 QR code'],
 'ket_pindai': ['Arahkan kamera ke QR code yang tertempel pada freezer. Toko akan dikenali otomatis.',
   'Point the camera at the QR code on the freezer. The store will be identified automatically.',
   '将镜头对准冰柜上的二维码，系统会自动识别门店。','將鏡頭對準冰櫃上的 QR code，系統會自動辨識門店。'],
 'nyalakan_kamera': ['Nyalakan kamera', 'Turn on camera', '开启摄像头', '開啟鏡頭'],
 'pindai_lagi': ['Pindai lagi', 'Scan again', '重新扫描', '重新掃描'],
 'asset_id': ['Nomor aset', 'Asset ID', '资产编号', '資產編號'],
 'kamera_izin_ditolak': ['Izin kamera ditolak. Aktifkan izin kamera di pengaturan peramban, lalu muat ulang halaman.',
   'Camera permission was denied. Enable it in your browser settings, then reload the page.',
   '摄像头权限被拒绝。请在浏览器设置中开启后重新载入页面。','鏡頭權限被拒絕。請在瀏覽器設定中開啟後重新載入頁面。'],
 'kamera_gagal': ['Kamera tidak bisa dibuka. Pastikan tidak sedang dipakai aplikasi lain.',
   'The camera could not be opened. Make sure no other app is using it.',
   '无法打开摄像头，请确认没有其他应用正在使用。','無法開啟鏡頭，請確認沒有其他應用程式正在使用。'],
 'kamera_tidak_didukung': ['Peramban ini tidak mendukung kamera. Buka lewat Chrome atau Safari versi terbaru, dan pastikan alamatnya diawali https.',
   'This browser does not support camera access. Use an up-to-date Chrome or Safari, and make sure the address starts with https.',
   '此浏览器不支持摄像头。请使用最新版 Chrome 或 Safari，并确保网址以 https 开头。','此瀏覽器不支援鏡頭。請使用最新版 Chrome 或 Safari，並確保網址以 https 開頭。'],
 'kamera_belum_siap': ['Kamera belum siap, tunggu sebentar.','The camera is not ready yet, please wait.','摄像头尚未就绪，请稍候。','鏡頭尚未就緒，請稍候。'],
 # --- Foto ---
 'foto_wajib_judul': ['Foto Bukti Kunjungan', 'Visit Evidence Photos', '拜访证明照片', '拜訪證明照片'],
 'ket_foto_wajib': ['Keenam foto ini wajib diambil langsung dari kamera. Waktu dan lokasi dicetak otomatis oleh sistem.',
   'All six photos must be taken live from the camera. The system stamps the time and location automatically.',
   '这六张照片必须现场拍摄。系统会自动加盖时间与位置水印。','這六張照片必須現場拍攝。系統會自動加蓋時間與位置浮水印。'],
 'ambil_foto': ['Ambil foto', 'Take photo', '拍照', '拍照'],
 'ulangi_foto': ['Ambil ulang', 'Retake', '重拍', '重拍'],
 'foto_terkumpul': [':sudah dari :total foto terkumpul', ':sudah of :total photos taken', '已拍摄 :sudah / :total 张', '已拍攝 :sudah / :total 張'],
 'sedang_menyimpan_foto': ['Menyimpan foto…', 'Saving photo…', '正在保存照片…', '正在儲存照片…'],
 'selesaikan_kunjungan': ['Selesaikan Kunjungan', 'Complete Visit', '完成拜访', '完成拜訪'],
 'lapor_toko_tutup': ['Toko Tutup', 'Store Closed', '门店歇业', '門店歇業'],
 'judul_lapor_tutup': ['Laporkan Toko Tutup', 'Report Store as Closed', '报告门店歇业', '報告門店歇業'],
 'ket_lapor_tutup': ['Laporan ini dikirim ke admin untuk dibenarkan. Setelah dibenarkan, toko dikeluarkan dari target minggu ini dan tidak lagi menjadi tanggungan Anda.',
   'This report goes to an admin for confirmation. Once confirmed, the store is removed from this week’s target and is no longer your responsibility.',
   '该报告将提交管理员确认。确认后，该门店将从本周目标中剔除，不再由您负责。','該報告將提交管理員確認。確認後，該門店將從本週目標中剔除，不再由您負責。'],
 'alasan_tutup': ['Keterangan keadaan toko', 'Describe what you found', '门店情况说明', '門店情況說明'],
 'alasan_tutup_contoh': ['Misalnya: rolling door terkunci, tetangga bilang tutup sejak minggu lalu',
   'For example: shutter locked, neighbour says it has been closed since last week',
   '例如：卷帘门上锁，邻居说上周起就没开门','例如：捲門上鎖，鄰居說上週起就沒開門'],
 'kirim_laporan': ['Kirim Laporan', 'Send Report', '提交报告', '提交報告'],
 'catatan_kunjungan': ['Catatan kunjungan (opsional)', 'Visit note (optional)', '拜访备注（可选）', '拜訪備註（選填）'],
 'catatan_kunjungan_contoh': ['Misalnya: pemilik minta tambah stok minggu depan',
   'For example: owner asked for more stock next week','例如：店主希望下周补货','例如：店主希望下週補貨'],
 # --- Lokasi ---
 'lokasi_terbaca': ['Lokasi terbaca (±:akurasi m)', 'Location acquired (±:akurasi m)', '已获取位置（±:akurasi 米）', '已取得位置（±:akurasi 公尺）'],
 'lokasi_belum': ['Menunggu sinyal GPS…', 'Waiting for GPS…', '正在等待 GPS…', '正在等待 GPS…'],
 'lokasi_tidak_ada': ['Tanpa lokasi', 'No location', '无位置信息', '無位置資訊'],
 'jarak_dari_toko': ['Jarak dari titik toko', 'Distance from store point', '距门店坐标', '距門店座標'],
 'lokasi_jauh': ['Titik pengambilan foto :jarak m dari koordinat toko. Perlu diperiksa.',
   'Photos were taken :jarak m from the store’s coordinates. Worth checking.',
   '拍摄位置距门店坐标 :jarak 米，建议核实。','拍攝位置距門店座標 :jarak 公尺，建議查核。'],
 # --- Watermark (dicetak ke gambar, sengaja Latin agar terbaca GD) ---
 'wm_sales': ['Sales', 'Rep', 'Sales', 'Sales'],
 'wm_lokasi': ['Lokasi', 'Location', 'Lokasi', 'Lokasi'],
 'wm_tanpa_lokasi': ['Lokasi tidak tersedia', 'Location unavailable', 'Lokasi tidak tersedia', 'Lokasi tidak tersedia'],
 # --- Penugasan ---
 'bulan_penugasan': ['Bulan penugasan', 'Assignment month', '分配月份', '分配月份'],
 'sales_terpilih': ['Sales terpilih', 'Selected rep', '所选销售', '所選銷售'],
 'toko_dipegang': [':jumlah dari :batas toko', ':jumlah of :batas stores', ':jumlah / :batas 家门店', ':jumlah / :batas 家門店'],
 'sisa_kuota': ['Sisa kuota :jumlah toko', ':jumlah stores left in quota', '剩余配额 :jumlah 家', '剩餘配額 :jumlah 家'],
 'kuota_penuh': ['Kuota penuh', 'Quota full', '配额已满', '配額已滿'],
 'cari_toko_penugasan': ['Cari nama, kode, alamat, atau nomor aset…', 'Search name, code, address, or asset ID…',
   '搜索名称、编号、地址或资产编号…','搜尋名稱、編號、地址或資產編號…'],
 'pilih_semua_tampil': ['Pilih semua yang tampil', 'Select all shown', '选择全部显示项', '選擇全部顯示項'],
 'kosongkan_pilihan': ['Kosongkan', 'Clear all', '清空', '清空'],
 'belum_ditugaskan': [':jumlah toko belum dipegang sales mana pun', ':jumlah stores are not assigned to anyone',
   '有 :jumlah 家门店尚未分配','有 :jumlah 家門店尚未分配'],
 'tanpa_asset_id': [':jumlah toko belum punya nomor aset freezer, jadi belum bisa dipindai sales',
   ':jumlah stores have no freezer asset ID yet, so reps cannot scan them',
   '有 :jumlah 家门店尚无冰柜资产编号，销售无法扫描','有 :jumlah 家門店尚無冰櫃資產編號，銷售無法掃描'],
 'salin_bulan_lalu': ['Salin dari bulan lain', 'Copy from another month', '从其他月份复制', '從其他月份複製'],
 'judul_salin': ['Salin Penugasan', 'Copy Assignments', '复制分配', '複製分配'],
 'ket_salin': ['Seluruh penugasan bulan sumber disalin ke bulan ini. Toko yang sudah punya penugasan di bulan ini dilewati.',
   'Every assignment from the source month is copied into this month. Stores already assigned this month are skipped.',
   '源月份的全部分配将复制到本月。本月已有分配的门店会被跳过。','源月份的全部分配將複製到本月。本月已有分配的門店會被跳過。'],
 'bulan_sumber': ['Bulan sumber', 'Source month', '源月份', '源月份'],
 # --- Peninjauan ---
 'judul_tinjauan': ['Tinjau Laporan Toko Tutup', 'Review Closed-Store Report', '审核歇业报告', '審核歇業報告'],
 'ket_tinjauan': ['Membenarkan laporan berarti toko keluar dari target sales minggu ini. Menolak berarti toko tetap wajib dikunjungi.',
   'Confirming removes the store from the rep’s target this week. Rejecting keeps it on the list.',
   '确认后该门店将从本周目标中剔除；驳回则仍需拜访。','確認後該門店將從本週目標中剔除；駁回則仍需拜訪。'],
 'laporan_sales': ['Keterangan sales', 'Rep’s description', '销售说明', '銷售說明'],
 'catatan_admin': ['Catatan admin (opsional)', 'Admin note (optional)', '管理员备注（可选）', '管理員備註（選填）'],
 'benarkan_tutup': ['Benarkan Tutup', 'Confirm Closed', '确认歇业', '確認歇業'],
 'tolak_tutup': ['Tolak Laporan', 'Reject Report', '驳回报告', '駁回報告'],
 'tinjau': ['Tinjau', 'Review', '审核', '審核'],
 'ditinjau_oleh': ['Ditinjau :nama · :waktu', 'Reviewed by :nama · :waktu', '由 :nama 审核 · :waktu', '由 :nama 審核 · :waktu'],
 # --- Daftar ---
 'lihat_foto': ['Lihat foto', 'View photos', '查看照片', '查看照片'],
 'judul_detail': ['Kunjungan :toko', 'Visit to :toko', '拜访 :toko', '拜訪 :toko'],
 'waktu_kunjungan': ['Waktu kunjungan', 'Visit time', '拜访时间', '拜訪時間'],
 'semua_status': ['Semua status', 'All statuses', '所有状态', '所有狀態'],
 'belum_ada_kunjungan': ['Belum ada kunjungan', 'No visits yet', '尚无拜访记录', '尚無拜訪記錄'],
 'sisa_toko': ['Sisa toko belum dikunjungi', 'Stores still to visit', '尚未拜访的门店', '尚未拜訪的門店'],
 'riwayat_kunjungan': ['Riwayat minggu sebelumnya', 'Previous weeks', '往周记录', '往週記錄'],
 'tugas_kosong': ['Belum ada toko yang ditugaskan', 'No stores assigned yet', '尚未分配门店', '尚未分配門店'],
 'tugas_kosong_ket': ['Hubungi admin untuk menetapkan daftar toko yang menjadi tanggungan Anda bulan ini.',
   'Ask an admin to set up your store list for this month.','请联系管理员设定您本月负责的门店。','請聯絡管理員設定您本月負責的門店。'],
 'saring_belum': ['Belum dikunjungi', 'Not visited', '未拜访', '未拜訪'],
 # --- Notifikasi ---
 'notif_toko_dikenali': ['Toko dikenali: :nama. Silakan mulai ambil foto.', 'Store identified: :nama. You can start taking photos.',
   '已识别门店：:nama，请开始拍照。','已辨識門店：:nama，請開始拍照。'],
 'notif_foto_tersimpan': ['Foto ":jenis" tersimpan.', 'Photo “:jenis” saved.', '照片「:jenis」已保存。', '照片「:jenis」已儲存。'],
 'notif_selesai': ['Kunjungan ke :nama selesai dan tercatat.', 'Visit to :nama completed and recorded.',
   '对 :nama 的拜访已完成并记录。','對 :nama 的拜訪已完成並記錄。'],
 'notif_tutup_diajukan': ['Laporan toko tutup terkirim. Menunggu keputusan admin.', 'Closed-store report sent. Awaiting an admin decision.',
   '歇业报告已提交，等待管理员处理。','歇業報告已提交，等待管理員處理。'],
 'notif_tinjauan_tersimpan': ['Keputusan tersimpan.', 'Decision saved.', '处理结果已保存。', '處理結果已儲存。'],
 'notif_penugasan_tersimpan': ['Penugasan tersimpan: :ditambah toko ditambah, :dihapus dilepas.',
   'Assignment saved: :ditambah stores added, :dihapus removed.',
   '分配已保存：新增 :ditambah 家，移除 :dihapus 家。','分配已儲存：新增 :ditambah 家，移除 :dihapus 家。'],
 'notif_penugasan_disalin': [':jumlah penugasan disalin.', ':jumlah assignments copied.', '已复制 :jumlah 条分配。', '已複製 :jumlah 筆分配。'],
 # --- Galat ---
 'galat_qr_tidak_terbaca': ['QR code tidak dikenali. Pastikan yang dipindai adalah QR pada freezer halocoko.',
   'That QR code was not recognised. Make sure you are scanning the QR on the halocoko freezer.',
   '无法识别该二维码，请确认扫描的是 halocoko 冰柜上的二维码。','無法辨識該 QR code，請確認掃描的是 halocoko 冰櫃上的 QR code。'],
 'galat_aset_tidak_dikenal': ['Nomor aset :aset belum terdaftar pada toko mana pun. Laporkan ke admin.',
   'Asset ID :aset is not registered to any store. Report this to an admin.',
   '资产编号 :aset 尚未登记到任何门店，请报告管理员。','資產編號 :aset 尚未登記到任何門店，請報告管理員。'],
 'galat_bukan_tanggungan': [':nama bukan toko tanggungan Anda bulan ini, jadi kunjungannya tidak bisa dicatat.',
   ':nama is not one of your assigned stores this month, so the visit cannot be recorded.',
   ':nama 不在您本月负责的门店范围内，无法记录拜访。',':nama 不在您本月負責的門店範圍內，無法記錄拜訪。'],
 'galat_sudah_dikunjungi': [':nama sudah dikunjungi :sales minggu ini.', ':nama has already been visited by :sales this week.',
   ':nama 本周已由 :sales 拜访。',':nama 本週已由 :sales 拜訪。'],
 'galat_toko_nonaktif': ['Toko :nama berstatus nonaktif.', 'Store :nama is inactive.', '门店 :nama 已停用。', '門店 :nama 已停用。'],
 'galat_tidak_berjalan': ['Kunjungan ini sudah tidak dalam keadaan berjalan.', 'This visit is no longer in progress.',
   '该拜访已不在进行中。','該拜訪已不在進行中。'],
 'galat_foto_kurang': ['Masih ada foto wajib yang belum diambil: :daftar', 'Some required photos are still missing: :daftar',
   '仍有必拍照片未完成：:daftar','仍有必拍照片未完成：:daftar'],
 'galat_catatan_tutup_wajib': ['Isi keterangan keadaan toko sebelum mengirim laporan.', 'Describe what you found before sending the report.',
   '提交报告前请填写门店情况说明。','提交報告前請填寫門店情況說明。'],
 'galat_bukan_pengajuan_tutup': ['Kunjungan ini bukan laporan toko tutup yang menunggu tinjauan.',
   'This visit is not a closed-store report awaiting review.','该拜访不是待审核的歇业报告。','該拜訪不是待審核的歇業報告。'],
 'galat_gambar_rusak': ['Gambar tidak terbaca. Coba ambil ulang fotonya.', 'The image could not be read. Please retake the photo.',
   '图片无法读取，请重新拍摄。','圖片無法讀取，請重新拍攝。'],
 'galat_jenis_foto': ['Jenis foto tidak dikenali.', 'Unknown photo type.', '未知的照片类型。', '未知的照片類型。'],
 'galat_melebihi_batas': ['Jumlah toko :jumlah melebihi batas :batas per sales.', ':jumlah stores exceeds the limit of :batas per rep.',
   '门店数 :jumlah 超过每位销售 :batas 家的上限。','門店數 :jumlah 超過每位銷售 :batas 家的上限。'],
 'galat_toko_sudah_dipegang': [':toko dilewati karena sudah dipegang :sales.', ':toko was skipped because :sales already has it.',
   ':toko 已由 :sales 负责，已跳过。',':toko 已由 :sales 負責，已跳過。'],
 'galat_bulan_sama': ['Bulan sumber dan tujuan tidak boleh sama.', 'Source and target month must differ.',
   '源月份与目标月份不能相同。','源月份與目標月份不能相同。'],
 'galat_bulan_sumber_kosong': ['Bulan sumber tidak punya penugasan.', 'The source month has no assignments.',
   '源月份没有任何分配。','源月份沒有任何分配。'],
 'oleh_anda': ['Anda sendiri', 'you', '您本人', '您本人'],
 'sales_lain': ['sales lain', 'another rep', '其他销售', '其他銷售'],
 # --- CSV ---
 'csv_sales': ['Sales', 'Sales Rep', '销售', '銷售'],
 'csv_waktu': ['Waktu', 'Time', '时间', '時間'],
 'csv_jumlah_foto': ['Jumlah Foto', 'Photo Count', '照片数', '照片數'],
 'csv_jarak': ['Jarak dari Toko (m)', 'Distance from Store (m)', '距门店（米）', '距門店（公尺）'],
}

NAV = {
 'visit_sales': ['Visit Sales', 'Sales Visits', '销售拜访', '銷售拜訪'],
 'penugasan': ['Penugasan Toko', 'Store Assignment', '门店分配', '門店分配'],
 'tugas_saya': ['Tugas Saya', 'My Stores', '我的门店', '我的門店'],
 'mulai_kunjungan': ['Mulai Kunjungan', 'Start Visit', '开始拜访', '開始拜訪'],
}

MASTER = {
 'asset_id': ['Nomor aset freezer', 'Freezer asset ID', '冰柜资产编号', '冰櫃資產編號'],
 'asset_id_ket': ['Tercetak pada QR code di badan freezer. Inilah yang dipindai sales saat berkunjung.',
   'Printed on the QR code on the freezer body. This is what reps scan during a visit.',
   '印在冰柜机身的二维码上，销售拜访时扫描的就是它。','印在冰櫃機身的 QR code 上，銷售拜訪時掃描的就是它。'],
 'freezer_tipe': ['Tipe freezer', 'Freezer model', '冰柜型号', '冰櫃型號'],
 'atr_asset_id': ['nomor aset', 'asset ID', '资产编号', '資產編號'],
}

def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"

def tulis(kode, berkas, isi):
    jalur = AKAR / 'lang' / kode / (berkas + '.php')
    baris = ["<?php", "", "return ["]
    for k, v in isi.items():
        baris.append("    " + php_str(k) + " => " + php_str(v) + ",")
    baris.append("];")
    jalur.write_text("\n".join(baris) + "\n", encoding='utf-8')

def sisipkan(kode, berkas, tambahan):
    """Menambahkan kunci baru ke berkas yang sudah ada, tanpa menyentuh isinya."""
    jalur = AKAR / 'lang' / kode / (berkas + '.php')
    teks = jalur.read_text(encoding='utf-8')
    idx = BAHASA.index(kode)
    sisip = ""
    for k, v in tambahan.items():
        if ("'" + k + "'") in teks:
            continue
        sisip += "    " + php_str(k) + " => " + php_str(v[idx]) + ",\n"
    if sisip:
        teks = re.sub(r"\n\];\s*$", "\n" + sisip + "];\n", teks)
        jalur.write_text(teks, encoding='utf-8')

for i, kode in enumerate(BAHASA):
    tulis(kode, 'kunjungan', {k: v[i] for k, v in T.items()})
    sisipkan(kode, 'nav', NAV)
    sisipkan(kode, 'master', MASTER)
    print("%-6s kunjungan.php (%d kunci) + nav + master" % (kode, len(T)))
