import L from 'leaflet';
import { pasangPetaRute } from './peta-rute';
import { pasangPemilihTitik } from './peta-pemilih';
import { pasangKamera } from './kamera';
import { pasangPemindaiQr } from './pemindai-qr';
import { pasangKunjunganOffline } from './kunjungan-offline';
import { bersihkanCacheHalaman, siapkanServiceWorker } from './outbox';
import { pasangChartPendapatan } from './pendapatan-chart';
import { pasangChartInsentif } from './insentif-chart';
import { pasangChartBarangTerjual } from './barang-terjual-chart';
import { formatRibuan } from './format-rupiah';

// Leaflet mencari berkas gambar penanda bawaan lewat jalur relatif yang tidak
// cocok dengan keluaran Vite. Aplikasi ini memakai penanda buatan sendiri,
// jadi cukup dimatikan agar tidak ada permintaan gambar yang gagal.
delete L.Icon.Default.prototype._getIconUrl;

window.L = L;
window.pasangPetaRute = pasangPetaRute;
window.pasangPemilihTitik = pasangPemilihTitik;
window.pasangKamera = pasangKamera;
window.pasangPemindaiQr = pasangPemindaiQr;
window.pasangKunjunganOffline = pasangKunjunganOffline;
window.bersihkanCacheHalaman = bersihkanCacheHalaman;

// Service worker didaftarkan dari satu tempat saja, berdasarkan tanda yang
// dipasang tata letak (lihat window.ondOffline di app.blade.php). Saat
// tandanya mati, pemanggilan yang sama justru MENCABUT service worker yang
// mungkin masih tertinggal di perangkat — itulah kill-switch-nya.
if (window.ondOffline) {
    siapkanServiceWorker({
        aktif: window.ondOffline.pwaAktif,
        versi: window.ondOffline.versi,
    });
}
window.pasangChartPendapatan = pasangChartPendapatan;
window.pasangChartInsentif = pasangChartInsentif;
window.pasangChartBarangTerjual = pasangChartBarangTerjual;
window.formatRibuan = formatRibuan;
