import {
    ambilPilihan,
    aturSenter,
    daftarKameraBelakang,
    fokusKeTitik,
    mainkanVideo,
    nyalakanAutofokus,
    punyaSenter,
    simpanPilihan,
    syaratVideo,
    tebakLensaUtama,
} from './kamera-util';

/**
 * Pengambilan foto langsung dari kamera.
 *
 * Sengaja tidak memakai <input type="file"> sama sekali. Atribut `capture`
 * pada input berkas hanya berupa saran bagi peramban — di banyak ponsel
 * pemakainya tetap bisa memilih gambar lama dari galeri. Dengan membaca aliran
 * kamera langsung, satu-satunya sumber gambar adalah apa yang sedang dilihat
 * lensa saat itu.
 *
 * Pemilihan lensa dan autofokus ditangani sama seperti pada pemindai QR:
 * ponsel dengan beberapa kamera belakang kerap menjawab permintaan dengan
 * lensa ultra-lebar yang fokusnya tetap, dan hasilnya foto suhu freezer atau
 * papan nama toko menjadi buram.
 */
export function pasangKamera(idWadah, pengaturan = {}) {
    const wadah = document.getElementById(idWadah);

    if (!wadah) {
        return null;
    }

    if (wadah._kamera) {
        return wadah._kamera;
    }

    const video = wadah.querySelector('video');
    const kanvas = document.createElement('canvas');

    let aliran = null;
    let track = null;
    let lokasiTerakhir = null;
    let pengawasLokasi = null;
    let kameraTersedia = [];
    let indeksKamera = 0;
    let senterNyala = false;

    async function nyalakan(deviceId = null) {
        if (aliran && deviceId === null) {
            return true;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            kabarkan('galat', pengaturan.pesan?.tidakDidukung);

            return false;
        }

        matikanAliran();

        const pilihan = deviceId ?? ambilPilihan();

        try {
            aliran = await navigator.mediaDevices.getUserMedia({
                video: syaratVideo(pilihan),
                audio: false,
            });
        } catch (e) {
            if (pilihan) {
                simpanPilihan('');

                return nyalakan(null);
            }

            kabarkan('galat', e.name === 'NotAllowedError' ? pengaturan.pesan?.izinDitolak : pengaturan.pesan?.gagal);

            return false;
        }

        video.srcObject = aliran;

        // Penolakan play() di Safari iOS dilaporkan, bukan dibiarkan menggagalkan
        // seluruh proses tanpa jejak.
        if (! await mainkanVideo(video)) {
            kabarkan('galat', pengaturan.pesan?.sentuhUntukMulai);
        }

        track = aliran.getVideoTracks()[0] ?? null;
        await nyalakanAutofokus(track);

        kameraTersedia = await daftarKameraBelakang();

        const aktif = pilihan ?? track?.getSettings?.().deviceId ?? null;
        const posisi = kameraTersedia.findIndex((d) => d.deviceId === aktif);
        indeksKamera = posisi >= 0 ? posisi : 0;

        // Lensa utama dipilih sekali di awal, sama seperti pada pemindai QR.
        if (!ambilPilihan() && kameraTersedia.length > 1) {
            const utama = tebakLensaUtama(kameraTersedia);

            if (utama && utama !== aktif) {
                simpanPilihan(utama);

                return nyalakan(utama);
            }
        }

        senterNyala = false;

        kabarkan('siap', {
            jumlahKamera: kameraTersedia.length,
            adaSenter: punyaSenter(track),
        });

        return true;
    }

    function matikanAliran() {
        aliran?.getTracks().forEach((t) => t.stop());
        aliran = null;
        track = null;

        if (video) {
            video.srcObject = null;
        }
    }

    function matikan() {
        matikanAliran();
    }

    async function gantiKamera() {
        if (kameraTersedia.length < 2) {
            return;
        }

        indeksKamera = (indeksKamera + 1) % kameraTersedia.length;

        const deviceId = kameraTersedia[indeksKamera].deviceId;
        simpanPilihan(deviceId);

        await nyalakan(deviceId);
    }

    async function alihkanSenter() {
        senterNyala = !senterNyala;

        if (!await aturSenter(track, senterNyala)) {
            senterNyala = false;
        }

        kabarkan('senter', senterNyala);
    }

    async function fokusDi(rasioX, rasioY) {
        await fokusKeTitik(track, rasioX, rasioY);
        kabarkan('fokus');
    }

    /** Mengambil satu bidikan dan mengirimnya ke komponen Livewire. */
    async function jepret(jenis) {
        if (!aliran) {
            const berhasil = await nyalakan();

            if (!berhasil) {
                return;
            }
        }

        kanvas.width = video.videoWidth;
        kanvas.height = video.videoHeight;

        if (!kanvas.width || !kanvas.height) {
            kabarkan('galat', pengaturan.pesan?.belumSiap);

            return;
        }

        kanvas.getContext('2d').drawImage(video, 0, 0, kanvas.width, kanvas.height);

        // Mutu di sini dibuat tinggi; pengecilan dan watermark dikerjakan di
        // server supaya jam yang tercetak tidak bisa disetel dari ponsel.
        const dataUrl = kanvas.toDataURL('image/jpeg', 0.92);

        kabarkan('jepretan', { jenis, gambar: dataUrl, lokasi: lokasiTerakhir });
    }

    /**
     * Membaca titik GPS terus-menerus selama layar kunjungan terbuka, supaya
     * saat tombol jepret ditekan titiknya sudah tersedia dan tidak perlu
     * menunggu.
     */
    function pantauLokasi() {
        if (!navigator.geolocation || pengawasLokasi !== null) {
            return;
        }

        pengawasLokasi = navigator.geolocation.watchPosition(
            (posisi) => {
                lokasiTerakhir = {
                    lat: posisi.coords.latitude,
                    lng: posisi.coords.longitude,
                    akurasi: Math.round(posisi.coords.accuracy),
                };

                kabarkan('lokasi', lokasiTerakhir);
            },
            () => kabarkan('lokasi', null),
            { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 },
        );
    }

    function lepasLokasi() {
        if (pengawasLokasi !== null) {
            navigator.geolocation.clearWatch(pengawasLokasi);
            pengawasLokasi = null;
        }
    }

    function kabarkan(nama, muatan = null) {
        wadah.dispatchEvent(new CustomEvent('kamera:' + nama, { detail: muatan, bubbles: true }));
    }

    const api = {
        nyalakan,
        matikan,
        jepret,
        pantauLokasi,
        lepasLokasi,
        gantiKamera,
        alihkanSenter,
        fokusDi,
        get lokasi() {
            return lokasiTerakhir;
        },
    };

    wadah._kamera = api;

    // Aliran kamera dilepas saat berpindah halaman, kalau tidak lampu kamera
    // tetap menyala dan baterai terkuras.
    document.addEventListener('livewire:navigating', () => {
        matikan();
        lepasLokasi();
    });

    return api;
}
