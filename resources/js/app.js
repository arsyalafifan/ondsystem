import L from 'leaflet';
import { pasangPetaRute } from './peta-rute';
import { pasangPemilihTitik } from './peta-pemilih';

// Leaflet mencari berkas gambar penanda bawaan lewat jalur relatif yang tidak
// cocok dengan keluaran Vite. Aplikasi ini memakai penanda buatan sendiri,
// jadi cukup dimatikan agar tidak ada permintaan gambar yang gagal.
delete L.Icon.Default.prototype._getIconUrl;

window.L = L;
window.pasangPetaRute = pasangPetaRute;
window.pasangPemilihTitik = pasangPemilihTitik;
