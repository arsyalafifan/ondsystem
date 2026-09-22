<?php

/**
 * NOO (New Open Outlet) — new customer development, from a sales rep
 * registering a prospective store through to the freezer being installed and
 * the opening order being created.
 */
return [

    // --- NOO default order settings ---
    'judul_paket' => 'NOO Default Order Settings',
    'ket_paket' => 'Opening sales packages for new partner stores. A sales rep picks one when registering a store, and its contents become that store\'s first order once the freezer has been delivered.',
    'paket_baru' => 'New Package',
    'paket_kosong' => 'No packages yet',
    'judul_paket_baru' => 'New NOO Package',
    'judul_paket_sunting' => 'Edit NOO Package',
    'judul_hapus_paket' => 'Delete Package',
    'ket_hapus_paket' => 'Sales reps will no longer be able to pick this package.',

    'atr_nama_paket' => 'Package name',
    'nama_paket_contoh' => 'e.g. 15+2',
    'atr_dus_reguler' => 'Regular boxes',
    'atr_dus_bonus' => 'Bonus boxes',
    'atr_paket_aktif' => 'Active — selectable by sales',
    'atr_produk' => 'product',
    'atr_jumlah_dus' => 'number of boxes',

    'isi_paket' => 'Package contents',
    'isi_paket_kosong' => 'No products chosen yet',
    'bonus' => 'bonus',
    'belum_lengkap' => 'Incomplete',
    'ket_belum_lengkap' => 'The chosen products do not add up to the package size yet, so sales reps cannot pick it.',
    'judul_item_reguler' => 'Regular box products',
    'judul_item_bonus' => 'Bonus box products',
    'hitungan_dus' => ':terpilih / :target boxes',
    'pilih_produk' => 'Choose a product',
    'tambah_produk' => 'Add product',

    'paket_tersimpan' => 'Package saved.',
    'paket_dihapus' => 'Package deleted.',

    'galat_nama_paket_dipakai' => 'That package name is already taken.',
    'galat_produk_dobel' => 'The same product cannot be chosen twice.',
    'galat_item_kosong' => 'Choose the products that make up this package first.',
    'galat_dus_reguler_tidak_cocok' => 'The regular boxes chosen (:terpilih) must add up to exactly :target.',
    'galat_dus_bonus_tidak_cocok' => 'The bonus boxes chosen (:terpilih) must add up to exactly :target.',

    // --- Registering a prospective partner ---
    'judul' => 'NOO — New Customer Development',
    'ket' => 'Registering prospective new partner stores. Once an admin approves, the freezer is routed and delivered by a driver; the opening order is created automatically as soon as the delivery is completed.',
    'noo_baru' => 'New NOO',
    'cari_placeholder' => 'Search code, store name, or owner name...',
    'semua_status' => 'All statuses',
    'kolom_kode' => 'Code',
    'kolom_pengaju' => 'Submitted by',
    'kosong' => 'No NOO submissions yet',
    'ket_kosong' => 'Submissions will appear here.',

    'judul_noo_baru' => 'New NOO Submission',
    'judul_data_toko' => 'Store details',
    'judul_data_pemilik' => 'Owner details',
    'judul_paket_dipilih' => 'Package & freezer',
    'judul_titik_lokasi' => 'Store location',
    'judul_foto_wajib' => 'Required photos',
    'judul_detail' => 'NOO :kode details',
    'judul_bukti' => 'Photo evidence',

    'atr_nama_toko' => 'Store name',
    'atr_paket' => 'Package',
    'atr_freezer_tipe' => 'Freezer type',
    'freezer_tipe_contoh' => 'optional, e.g. 6 ft',
    'ringkas_paket' => ':reguler boxes + :bonus bonus',
    'belum_ada_paket_tersedia' => 'No package is ready to use yet. Ask an admin to finish choosing its products in NOO Default Order Settings.',
    'ket_titik_lokasi' => 'Tap "My Location" while standing in front of the store, or drag the marker on the map.',
    'titik_belum_dipilih' => 'No location picked yet',
    'ket_foto_wajib' => 'Take them directly with the camera, or upload from the gallery if the camera has issues.',
    'ambil_foto' => 'Take Photo',
    'ambil_ulang' => 'Retake',
    'tombol_ajukan' => 'Submit',
    'toko_terbentuk' => 'Store created',
    'alasan_tolak' => 'Rejection reason',
    'notif_diajukan' => 'Submission :kode saved and is awaiting admin approval.',

    // --- Statuses ---
    'status_order' => 'Order',
    'ket_status_order' => 'Awaiting admin approval.',
    'status_process' => 'Process',
    'ket_status_process' => 'Approved — waiting for the freezer to be routed.',
    'status_delivery' => 'Delivery',
    'ket_status_delivery' => 'The freezer is on its way to the store.',
    'status_selesai' => 'Completed',
    'ket_status_selesai' => 'Freezer installed and the store is now active.',
    'status_ditolak' => 'Rejected',
    'ket_status_ditolak' => 'The prospect was not approved by an admin.',

    // --- Photo evidence ---
    'bukti_ktp_pemilik' => 'Owner ID Card',
    'bukti_kartu_keluarga' => 'Family Card',
    'bukti_tampak_depan_sales' => 'Store Front',
    'bukti_tampak_depan_driver' => 'Store Front',
    'bukti_surat_perjanjian' => 'Agreement Handover',
    'bukti_qr_code' => 'Freezer QR Code',
    'bukti_posisi_freezer' => 'Freezer Placement',
    'bukti_spanduk' => 'Banner',
    'bukti_flag_hanger' => 'Flag Hanger',

    'petunjuk_bukti_ktp_pemilik' => 'Photo of the owner\'s ID card — make sure the ID number and name are legible.',
    'petunjuk_bukti_kartu_keluarga' => 'Photo of the owner\'s family card.',
    'petunjuk_bukti_tampak_depan_sales' => 'Photo of the storefront from outside, with the signage visible.',
    'petunjuk_bukti_tampak_depan_driver' => 'Photo of the storefront from outside once the freezer is installed.',
    'petunjuk_bukti_surat_perjanjian' => 'Photo of the driver together with the owner holding the agreement letter.',
    'petunjuk_bukti_qr_code' => 'Photo of the QR code stuck on the freezer.',
    'petunjuk_bukti_posisi_freezer' => 'Photo of the freezer in its final position inside the store.',
    'petunjuk_bukti_spanduk' => 'Photo of the banner once it is put up.',
    'petunjuk_bukti_flag_hanger' => 'Photo of the flag hanger once it is put up.',

    // --- Errors ---
    'galat_koordinat_wajib' => 'The store location must be picked.',
    'galat_paket_tidak_tersedia' => 'The chosen package is not available.',
    'galat_bukti_belum_lengkap' => 'Complete all the required photos first.',
    'galat_bukan_order' => 'NOO :kode is no longer in Order status.',
    'galat_alasan_tolak_wajib' => 'A rejection reason is required.',

    // --- Admin approval ---
    'judul_persetujuan' => 'NOO Approval',
    'ket_persetujuan' => 'Prospective partner submissions awaiting a decision. Approving creates the store in Master Toko — still inactive until its freezer is installed.',
    'periksa' => 'Review',
    'antrean_kosong' => 'Nothing awaiting approval',
    'ket_antrean_kosong' => 'New submissions from sales reps will appear here.',
    'judul_periksa' => 'Review NOO :kode',
    'ket_koreksi_admin' => 'The details below may be corrected before approving. Every change is recorded along with who made it and when.',
    'alasan_tolak_contoh' => 'For example: too close to an existing partner, or the owner details do not match.',

    'peringatan_jarak_judul' => 'Too close to an active store',
    'peringatan_jarak' => 'This point is only :jarak m from :toko (:kode), which is already active.',
    'ket_peringatan_jarak' => 'This is a warning, not a block. If they really are two separate stores that happen to sit next to each other, go ahead.',

    'tombol_tolak' => 'Reject',
    'tombol_simpan_perubahan' => 'Save Changes',
    'tombol_setujui' => 'Approve',
    'tombol_tetap_setujui' => 'Approve Anyway',

    'notif_perubahan_disimpan' => 'Changes saved.',
    'notif_disetujui' => 'NOO :kode approved. Store :toko created and awaiting freezer installation.',
    'notif_ditolak' => 'NOO :kode rejected.',

    // --- Freezer routing ---
    'judul_routing' => 'NOO Freezer Routing',
    'ket_routing' => 'Plans freezer delivery routes for approved prospects. Kept separate from order routing because the load is freezer units, not boxes.',
    'menunggu_rute' => ':jumlah prospects awaiting routing',
    'ket_menunggu_rute' => 'NOOs already approved by an admin and not yet on any route.',
    'tombol_susun_rute' => 'Plan Route',
    'tidak_ada_siap_rute' => 'Nothing awaiting routing',
    'ket_tidak_ada_siap_rute' => 'Newly approved prospects will appear here.',
    'ringkas_rute' => ':mobil vehicles · :freezer freezers',
    'ringkas_mobil' => ':freezer freezers · :km km',
    'tombol_setujui_rute' => 'Approve Route',
    'rute_sudah_disetujui' => 'Approved',
    'ket_hapus_draft_rute' => 'The draft route is discarded and every prospect on it goes back to awaiting routing.',
    'notif_rute_dibuat' => 'Route :kode planned. Assign drivers, then approve it.',
    'notif_rute_disetujui' => 'Route :kode approved. The freezers are ready to go.',
    'notif_rute_dihapus' => 'Draft route discarded.',
    'galat_tidak_ada_siap_routing' => 'No approved prospect is ready to be routed yet.',

    // --- Freezer installation by the driver ---
    'label_noo' => 'NOO',
    'satu_freezer' => '1 freezer',
    'tombol_pasang_freezer' => 'Install Freezer',
    'judul_pasang' => 'Freezer Installation — :toko',
    'ket_pasang' => 'Enter the freezer number (IDN) on the sticker, fill in any store details still missing, then take all six installation photos.',
    'atr_idn' => 'IDN / freezer number',
    'idn_contoh' => 'e.g. IDNAH2025280001',
    'judul_lengkapi_alamat' => 'Complete the store details',
    'ket_lengkapi_alamat' => 'Only fill in what is still empty — leave it blank if it is genuinely unknown.',
    'judul_bukti_pemasangan' => 'Installation evidence',
    'tombol_selesaikan_pasang' => 'Complete Installation',
    'notif_terpasang' => 'Freezer for :kode installed. The store is now active and its opening order has been created.',
    'catatan_pesanan_perdana' => 'Opening order from :kode',
    'galat_bukan_delivery' => 'NOO :kode is not in Delivery status yet.',

    'ket_badge_pesanan' => 'Opening order from a NOO — must be approved individually; never included in bulk approval.',
];
