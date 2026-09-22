<?php

namespace App\Akses;

use App\Services\Izin\ApproverIzin;

/**
 * SATU-SATUNYA daftar aplikasi, grup, dan menu aplikasi ini.
 *
 * Disimpan di kode (bukan database) supaya struktur menu selalu sama di
 * localhost maupun live — ikut deploy, tanpa input manual. Yang disimpan di
 * database hanya PENGECUALIAN hak akses per peran dari layar Hak Akses
 * Management (tabel `hak_akses_perans`), lihat App\Akses\HakAkses.
 *
 * Cara pakai:
 * - Tambah APLIKASI: tambah entri di APLIKASI (urutan = urutan di pemilih
 *   aplikasi). `segera_hadir` = tampil tapi belum bisa diklik.
 * - Tambah GRUP (menu yang punya anak): tambah entri di GRUP.
 * - Tambah MENU: tambah entri di MENU pada posisi yang diinginkan (urutan
 *   array = urutan tampil; anak satu grup ditulis berurutan), lalu pasang
 *   `->middleware('akses:<kunci menu>')` di rutenya.
 *     - `peran`        peran yang BAWAAN-nya boleh (tanpa superadmin — ia
 *                      selalu boleh). Setting di layar menimpa bawaan ini.
 *     - `cakupan_data` true bila menu ini mendukung "hanya data milik
 *                      sendiri" (komponennya wajib membaca HakAkses::cakupan()).
 *     - `label_peran`  label khusus untuk peran tertentu (opsional).
 *     - `akses_tambahan` kelas App\Akses\AksesTambahan yang MENAMBAH akses
 *                      berdasarkan data (mis. approver izin yang ditunjuk).
 * - Tambah PERAN: tambah `case` di App\Enums\PeranPengguna (+ label di
 *   lang/.../status.php), lalu sebut perannya di `peran` menu yang relevan.
 * - Urutan menu khusus satu peran: URUTAN_PERAN.
 *
 * PENTING: kunci menu jangan diganti nama setelah dipakai — setting yang
 * sudah tersimpan mengacu ke kunci ini.
 */
final class DaftarAkses
{
    public const APLIKASI = [
        'ond' => ['label' => 'nav.aplikasi_ond', 'ikon' => 'truck'],
        'hr' => ['label' => 'nav.aplikasi_hr', 'ikon' => 'identification'],
        'accounting' => ['label' => 'nav.aplikasi_accounting', 'ikon' => 'calculator', 'segera_hadir' => true],
        'user_admin' => ['label' => 'nav.aplikasi_user_admin', 'ikon' => 'shield-check'],
    ];

    public const GRUP = [
        'ond.pembayaran' => ['label' => 'nav.pembayaran', 'ikon' => 'banknotes'],
        'ond.statistik' => ['label' => 'nav.statistik', 'ikon' => 'chart-bar-square'],
        'ond.master' => ['label' => 'nav.master', 'ikon' => 'archive-box'],
        'ond.pengiriman' => ['label' => 'nav.pengiriman', 'ikon' => 'truck'],
        'ond.monitoring' => ['label' => 'nav.monitoring', 'ikon' => 'chart-bar-square'],
        'ond.noo' => ['label' => 'nav.noo', 'ikon' => 'sparkles'],
        'hr.master' => ['label' => 'nav.master', 'ikon' => 'archive-box'],
    ];

    public const MENU = [
        // --- O&D System ---
        // `tanpa_superadmin`: menu kerja lapangan milik sales sendiri — rutenya
        // tetap bisa dibuka superadmin, tapi tidak ditampilkan di sidebarnya.
        'ond.kunjungi' => ['aplikasi' => 'ond', 'rute' => 'kunjungan.kunjungi', 'label' => 'nav.mulai_kunjungan', 'ikon' => 'camera', 'peran' => ['sales'], 'tanpa_superadmin' => true],
        'ond.tugas' => ['aplikasi' => 'ond', 'rute' => 'kunjungan.tugas', 'label' => 'nav.tugas_saya', 'ikon' => 'paper-airplane', 'peran' => ['sales'], 'tanpa_superadmin' => true],
        'ond.dashboard' => ['aplikasi' => 'ond', 'rute' => 'dashboard', 'label' => 'nav.dashboard', 'ikon' => 'chart-pie', 'peran' => ['admin']],
        'ond.pesanan' => [
            'aplikasi' => 'ond', 'rute' => 'pesanan.daftar', 'label' => 'nav.pesanan', 'ikon' => 'clipboard-document-list',
            'peran' => ['admin', 'sales', 'supervisor'], 'cakupan_data' => true, 'label_peran' => ['sales' => 'nav.riwayat_pesanan'],
        ],
        'ond.input_pesanan' => ['aplikasi' => 'ond', 'rute' => 'pesanan.buat', 'label' => 'nav.input_pesanan', 'ikon' => 'plus-circle', 'peran' => ['admin', 'sales']],
        'ond.pos' => ['aplikasi' => 'ond', 'rute' => 'pos.kasir', 'label' => 'nav.pos', 'ikon' => 'shopping-cart', 'peran' => ['admin']],
        'ond.lengkapi_data_toko' => ['aplikasi' => 'ond', 'rute' => 'toko.lengkapi-data', 'label' => 'nav.lengkapi_data_toko', 'ikon' => 'identification', 'peran' => ['admin', 'sales', 'supervisor']],
        'ond.generate_routing' => ['aplikasi' => 'ond', 'rute' => 'routing.generate', 'label' => 'nav.generate_routing', 'ikon' => 'map', 'peran' => ['admin']],
        'ond.riwayat_routing' => ['aplikasi' => 'ond', 'rute' => 'routing.riwayat', 'label' => 'nav.riwayat_routing', 'ikon' => 'clock', 'peran' => ['admin']],
        'ond.visit_sales' => ['aplikasi' => 'ond', 'rute' => 'kunjungan.periode', 'label' => 'nav.visit_sales', 'ikon' => 'paper-airplane', 'peran' => ['admin']],
        'ond.penugasan' => ['aplikasi' => 'ond', 'rute' => 'kunjungan.penugasan', 'label' => 'nav.penugasan', 'ikon' => 'folder-open', 'peran' => ['admin']],

        'ond.noo_daftar' => ['aplikasi' => 'ond', 'grup' => 'ond.noo', 'rute' => 'noo.daftar', 'label' => 'nav.noo_daftar', 'ikon' => 'sparkles', 'peran' => ['admin', 'sales']],
        'ond.noo_persetujuan' => ['aplikasi' => 'ond', 'grup' => 'ond.noo', 'rute' => 'noo.persetujuan', 'label' => 'nav.noo_persetujuan', 'ikon' => 'check-badge', 'peran' => ['admin']],
        'ond.noo_routing' => ['aplikasi' => 'ond', 'grup' => 'ond.noo', 'rute' => 'noo.routing', 'label' => 'nav.noo_routing', 'ikon' => 'map', 'peran' => ['admin']],
        'ond.setting_paket_noo' => ['aplikasi' => 'ond', 'grup' => 'ond.noo', 'rute' => 'noo.paket', 'label' => 'nav.noo_paket', 'ikon' => 'adjustments-horizontal', 'peran' => ['admin']],

        'ond.pelunasan' => ['aplikasi' => 'ond', 'grup' => 'ond.pembayaran', 'rute' => 'pembayaran.pelunasan', 'label' => 'nav.pelunasan', 'ikon' => 'check-circle', 'peran' => ['admin']],
        'ond.belum_lunas' => ['aplikasi' => 'ond', 'grup' => 'ond.pembayaran', 'rute' => 'pembayaran.belum-lunas', 'label' => 'nav.belum_lunas', 'ikon' => 'exclamation-circle', 'peran' => ['admin']],
        'ond.pendapatan' => ['aplikasi' => 'ond', 'grup' => 'ond.pembayaran', 'rute' => 'pembayaran.pendapatan', 'label' => 'nav.pendapatan', 'ikon' => 'chart-bar', 'peran' => ['admin']],

        'ond.insentif_sales' => ['aplikasi' => 'ond', 'grup' => 'ond.statistik', 'rute' => 'insentif.sales', 'label' => 'nav.insentif_sales', 'ikon' => 'user-group', 'peran' => ['admin']],
        'ond.barang_terjual' => ['aplikasi' => 'ond', 'grup' => 'ond.statistik', 'rute' => 'penjualan.barang-terjual', 'label' => 'nav.barang_terjual', 'ikon' => 'archive-box', 'peran' => ['admin']],
        'ond.repeat_order_sales' => ['aplikasi' => 'ond', 'grup' => 'ond.statistik', 'rute' => 'statistik.repeat-order-sales', 'label' => 'nav.repeat_order_sales', 'ikon' => 'arrow-path', 'peran' => ['admin']],
        'ond.dus_terjual_driver' => ['aplikasi' => 'ond', 'grup' => 'ond.statistik', 'rute' => 'statistik.dus-terjual-driver', 'label' => 'nav.dus_terjual_driver', 'ikon' => 'user-group', 'peran' => ['admin']],
        'ond.dus_pulang_driver' => ['aplikasi' => 'ond', 'grup' => 'ond.statistik', 'rute' => 'statistik.dus-pulang-driver', 'label' => 'nav.dus_pulang_driver', 'ikon' => 'archive-box-arrow-down', 'peran' => ['admin']],
        'ond.dus_bonus_terkirim' => ['aplikasi' => 'ond', 'grup' => 'ond.statistik', 'rute' => 'statistik.dus-bonus-terkirim', 'label' => 'nav.dus_bonus_terkirim', 'ikon' => 'gift', 'peran' => ['admin']],
        'ond.form_pembelian_produk' => ['aplikasi' => 'ond', 'grup' => 'ond.statistik', 'rute' => 'statistik.form-pembelian-produk', 'label' => 'nav.form_pembelian_produk', 'ikon' => 'table-cells', 'peran' => ['admin']],

        'ond.master_toko' => ['aplikasi' => 'ond', 'grup' => 'ond.master', 'rute' => 'master.toko', 'label' => 'nav.master_toko', 'ikon' => 'building-storefront', 'peran' => ['admin']],
        'ond.master_freezer' => ['aplikasi' => 'ond', 'grup' => 'ond.master', 'rute' => 'master.freezer', 'label' => 'nav.master_freezer', 'ikon' => 'cube-transparent', 'peran' => ['admin']],
        'ond.master_produk' => ['aplikasi' => 'ond', 'grup' => 'ond.master', 'rute' => 'master.produk', 'label' => 'nav.master_produk', 'ikon' => 'cube', 'peran' => ['admin']],
        'ond.master_wilayah' => ['aplikasi' => 'ond', 'grup' => 'ond.master', 'rute' => 'master.wilayah', 'label' => 'nav.master_wilayah', 'ikon' => 'map-pin', 'peran' => ['admin']],
        'ond.master_promo' => ['aplikasi' => 'ond', 'grup' => 'ond.master', 'rute' => 'master.promo', 'label' => 'nav.master_promo', 'ikon' => 'gift', 'peran' => ['admin']],

        'ond.pengiriman_driver' => ['aplikasi' => 'ond', 'grup' => 'ond.pengiriman', 'rute' => 'driver.pilih-mobil', 'label' => 'nav.pengiriman_driver', 'ikon' => 'truck', 'peran' => ['driver', 'admin']],

        'ond.monitoring_bbm' => ['aplikasi' => 'ond', 'grup' => 'ond.monitoring', 'rute' => 'monitoring.bahan-bakar', 'label' => 'nav.monitoring_bbm', 'ikon' => 'fire', 'peran' => ['admin']],

        // --- HR System ---
        'hr.dashboard' => ['aplikasi' => 'hr', 'rute' => 'hr.dashboard', 'label' => 'nav.dashboard', 'ikon' => 'chart-pie', 'peran' => ['admin', 'hr']],
        // Absensi dipakai SEMUA peran (merekalah yang absen), jadi peran
        // lapangan pun punya satu menu di HR System.
        'hr.absensi' => ['aplikasi' => 'hr', 'rute' => 'hr.absensi', 'label' => 'nav.hr_absensi', 'ikon' => 'camera', 'peran' => ['admin', 'hr', 'sales', 'driver', 'supervisor']],
        'hr.ajukan_izin' => ['aplikasi' => 'hr', 'rute' => 'hr.izin', 'label' => 'nav.hr_ajukan_izin', 'ikon' => 'document-plus', 'peran' => ['admin', 'hr', 'sales', 'driver', 'supervisor']],
        'hr.ajukan_lembur' => ['aplikasi' => 'hr', 'rute' => 'hr.lembur', 'label' => 'nav.hr_ajukan_lembur', 'ikon' => 'clock', 'peran' => ['admin', 'hr', 'sales', 'driver', 'supervisor']],
        'hr.monitoring_absensi' => ['aplikasi' => 'hr', 'rute' => 'hr.monitoring-absensi', 'label' => 'nav.hr_monitoring_absensi', 'ikon' => 'clipboard-document-check', 'peran' => ['admin', 'hr'], 'cakupan_data' => true],
        // Bawaan menurut peran: HR. Selain itu terbuka untuk siapa pun yang
        // ditunjuk di Setting Approval Izin (orang tertentu / atasan
        // langsung) lewat `akses_tambahan` — siapa yang BOLEH MEMUTUSKAN
        // tetap dijaga App\Services\Izin\ApproverIzin per pengajuan.
        'hr.persetujuan_izin' => [
            'aplikasi' => 'hr', 'rute' => 'hr.persetujuan-izin', 'label' => 'nav.hr_persetujuan_izin', 'ikon' => 'check-badge',
            'peran' => ['hr'], 'akses_tambahan' => ApproverIzin::class,
        ],
        'hr.persetujuan_lembur' => [
            'aplikasi' => 'hr', 'rute' => 'hr.persetujuan-lembur', 'label' => 'nav.hr_persetujuan_lembur', 'ikon' => 'clipboard-document-check',
            'peran' => ['hr'], 'akses_tambahan' => ApproverIzin::class,
        ],
        'hr.setting_approval_izin' => ['aplikasi' => 'hr', 'rute' => 'hr.setting-approval-izin', 'label' => 'nav.hr_setting_approval_izin', 'ikon' => 'adjustments-vertical', 'peran' => ['hr']],
        'hr.karyawan' => ['aplikasi' => 'hr', 'grup' => 'hr.master', 'rute' => 'hr.karyawan', 'label' => 'nav.hr_karyawan', 'ikon' => 'identification', 'peran' => ['admin', 'hr'], 'cakupan_data' => true],
        'hr.department' => ['aplikasi' => 'hr', 'grup' => 'hr.master', 'rute' => 'hr.department', 'label' => 'nav.hr_department', 'ikon' => 'building-office', 'peran' => ['admin', 'hr']],
        'hr.jabatan' => ['aplikasi' => 'hr', 'grup' => 'hr.master', 'rute' => 'hr.jabatan', 'label' => 'nav.hr_jabatan', 'ikon' => 'briefcase', 'peran' => ['admin', 'hr']],
        'hr.posisi' => ['aplikasi' => 'hr', 'grup' => 'hr.master', 'rute' => 'hr.posisi', 'label' => 'nav.hr_posisi', 'ikon' => 'user-group', 'peran' => ['admin', 'hr']],
        'hr.shift' => ['aplikasi' => 'hr', 'grup' => 'hr.master', 'rute' => 'hr.shift', 'label' => 'nav.hr_shift', 'ikon' => 'clock', 'peran' => ['admin', 'hr']],
        'hr.jam_kerja' => ['aplikasi' => 'hr', 'rute' => 'hr.jam-kerja', 'label' => 'nav.hr_jam_kerja', 'ikon' => 'adjustments-horizontal', 'peran' => ['admin', 'hr']],

        // --- User Admin (bawaan: hanya superadmin) ---
        'user_admin.pengguna' => ['aplikasi' => 'user_admin', 'rute' => 'pengguna.daftar', 'label' => 'nav.manage_pengguna', 'ikon' => 'user-group', 'peran' => []],
        'user_admin.depot' => ['aplikasi' => 'user_admin', 'rute' => 'depot.daftar', 'label' => 'nav.manage_depot', 'ikon' => 'building-office-2', 'peran' => []],
        'user_admin.hak_akses' => ['aplikasi' => 'user_admin', 'rute' => 'hak-akses.kelola', 'label' => 'nav.hak_akses', 'ikon' => 'key', 'peran' => []],
    ];

    /** Urutan menu khusus peran; menu yang tidak disebut menyusul dengan urutan bawaan. */
    public const URUTAN_PERAN = [
        'sales' => ['ond.kunjungi', 'ond.tugas', 'ond.lengkapi_data_toko', 'ond.input_pesanan', 'ond.pesanan'],
    ];

    /** @return array<string, mixed>|null */
    public static function menu(string $kunci): ?array
    {
        return self::MENU[$kunci] ?? null;
    }

    /** @return list<string> kunci menu milik aplikasi, urutan bawaan */
    public static function menuAplikasi(string $aplikasi): array
    {
        return array_keys(array_filter(self::MENU, fn (array $m) => $m['aplikasi'] === $aplikasi));
    }
}
