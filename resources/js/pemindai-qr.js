import jsQR from 'jsqr';
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
    tandaBerhasil,
    tebakLensaUtama,
    tungguBingkaiSiap,
} from './kamera-util';

/**
 * Pemindai QR code pada freezer.
 *
 * Membaca aliran kamera bingkai demi bingkai lalu mencari pola QR di dalamnya.
 * Pembacaan dilakukan di perangkat, jadi tidak ada gambar yang dikirim ke mana
 * pun sampai kodenya benar-benar terbaca.
 *
 * Tiga penyesuaian yang membuatnya bisa dipakai di lapangan:
 *
 *  - Bila peramban menyediakan BarcodeDetector, itu yang dipakai. Pembaca
 *    bawaan peramban jauh lebih cepat dan lebih tahan gambar buram daripada
 *    pembacaan lewat JavaScript. Chrome di Android punya; Safari di iOS tidak.
 *  - Tanpa pembaca bawaan, tiap bingkai hanya menjalankan satu sapuan dan
 *    bergantian antara potongan tengah dan seluruh bingkai. Potongan tengah
 *    menang ketika QR memenuhi kotak bidik, seluruh bingkai menolong ketika
 *    kodenya kecil dan agak jauh dari tengah.
 *  - Penolakan play() dari Safari ditangkap dan dilaporkan, bukan dibiarkan
 *    menghentikan segalanya tanpa jejak.
 */
export function pasangPemindaiQr(idWadah, pengaturan = {}) {
    const wadah = document.getElementById(idWadah);

    if (!wadah) {
        return null;
    }

    if (wadah._pemindaiQr) {
        return wadah._pemindaiQr;
    }

    const video = wadah.querySelector('video');
    const kanvas = document.createElement('canvas');
    const konteks = kanvas.getContext('2d', { willReadFrequently: true });

    let aliran = null;
    let track = null;
    let berjalan = false;
    let terakhirTerbaca = null;
    let kameraTersedia = [];
    let indeksKamera = 0;
    let pembacaBawaan = null;
    let senterNyala = false;
    let butuhSentuhan = false;
    let bingkaiKe = 0;

    if ('BarcodeDetector' in window) {
        try {
            pembacaBawaan = new window.BarcodeDetector({ formats: ['qr_code'] });
        } catch {
            pembacaBawaan = null;
        }
    }

    async function mulai(deviceId = null) {
        if (!navigator.mediaDevices?.getUserMedia) {
            kabarkan('galat', pengaturan.pesan?.tidakDidukung);

            return false;
        }

        hentikanAliran();

        const pilihan = deviceId ?? ambilPilihan();

        try {
            aliran = await navigator.mediaDevices.getUserMedia({
                video: syaratVideo(pilihan),
                audio: false,
            });
        } catch (e) {
            // Kamera yang tersimpan bisa saja sudah tidak ada, misalnya karena
            // berganti perangkat. Dicoba sekali lagi tanpa menyebut lensa.
            if (pilihan) {
                simpanPilihan('');

                return mulai(null);
            }

            kabarkan('galat', e.name === 'NotAllowedError' ? pengaturan.pesan?.izinDitolak : pengaturan.pesan?.gagal);

            return false;
        }

        video.srcObject = aliran;

        if (! await mainkanVideo(video)) {
            // Aliran kameranya sudah tersambung, hanya pemutarannya yang
            // ditolak. Pengguna bisa memulainya lagi dengan menyentuh layar,
            // yang menjadi konteks sentuhan baru bagi peramban.
            butuhSentuhan = true;
            kabarkan('butuh-sentuhan', pengaturan.pesan?.sentuhUntukMulai);
        }

        track = aliran.getVideoTracks()[0] ?? null;
        await nyalakanAutofokus(track);

        await muatDaftarKamera(pilihan);

        berjalan = true;
        senterNyala = false;

        kabarkan('siap', {
            jumlahKamera: kameraTersedia.length,
            adaSenter: punyaSenter(track),
        });

        await tungguBingkaiSiap(video);

        requestAnimationFrame(pindaiBingkai);

        return true;
    }

    /** Mencoba memutar ulang video, dipanggil dari sentuhan pengguna. */
    async function cobaMainkanLagi() {
        if (! aliran || ! butuhSentuhan) {
            return;
        }

        if (await mainkanVideo(video)) {
            butuhSentuhan = false;
            kabarkan('butuh-sentuhan', null);
        }
    }

    /** Melengkapi daftar lensa, yang labelnya baru terbaca setelah izin diberikan. */
    async function muatDaftarKamera(deviceIdTerpakai) {
        kameraTersedia = await daftarKameraBelakang();

        const aktif = deviceIdTerpakai ?? track?.getSettings?.().deviceId ?? null;
        const posisi = kameraTersedia.findIndex((d) => d.deviceId === aktif);

        indeksKamera = posisi >= 0 ? posisi : 0;

        // Saat pertama kali dibuka dan lensanya belum pernah dipilih, sistem
        // berpindah ke lensa utama. Inilah yang mencegah pemindai terjebak di
        // lensa ultra-lebar yang tidak bisa fokus dekat.
        if (!ambilPilihan() && kameraTersedia.length > 1) {
            const utama = tebakLensaUtama(kameraTersedia);

            if (utama && utama !== aktif) {
                simpanPilihan(utama);
                await mulai(utama);
            }
        }
    }

    /** Berpindah ke lensa belakang berikutnya. */
    async function gantiKamera() {
        if (kameraTersedia.length < 2) {
            return;
        }

        indeksKamera = (indeksKamera + 1) % kameraTersedia.length;

        const deviceId = kameraTersedia[indeksKamera].deviceId;
        simpanPilihan(deviceId);

        await mulai(deviceId);
    }

    async function alihkanSenter() {
        senterNyala = !senterNyala;

        const berhasil = await aturSenter(track, senterNyala);

        if (!berhasil) {
            senterNyala = false;
        }

        kabarkan('senter', senterNyala);
    }

    /** Memfokuskan ke titik yang disentuh pengguna pada tampilan video. */
    async function fokusDi(rasioX, rasioY) {
        await fokusKeTitik(track, rasioX, rasioY);
        kabarkan('fokus');
    }

    function hentikanAliran() {
        aliran?.getTracks().forEach((t) => t.stop());
        aliran = null;
        track = null;

        if (video) {
            video.srcObject = null;
        }
    }

    function berhenti() {
        berjalan = false;
        hentikanAliran();
    }

    async function pindaiBingkai() {
        if (!berjalan) {
            return;
        }

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            const isi = await bacaKode();

            // Kode yang sama tidak dilaporkan berulang kali; tanpa penjagaan
            // ini satu QR bisa terkirim puluhan kali per detik.
            if (isi && isi !== terakhirTerbaca) {
                terakhirTerbaca = isi;
                tandaBerhasil();
                kabarkan('terbaca', isi);
            }
        }

        requestAnimationFrame(pindaiBingkai);
    }

    async function bacaKode() {
        const lebar = video.videoWidth;
        const tinggi = video.videoHeight;

        if (!lebar || !tinggi) {
            return null;
        }

        // Pembaca bawaan peramban membaca elemen video langsung, tanpa perlu
        // menyalin bingkainya ke kanvas.
        if (pembacaBawaan) {
            try {
                const hasil = await pembacaBawaan.detect(video);

                if (hasil.length > 0) {
                    return hasil[0].rawValue;
                }
            } catch {
                // Sebagian perangkat melaporkan dukungan tapi gagal saat
                // dipakai. Kalau begitu, pembacaan lewat JavaScript diteruskan.
                pembacaBawaan = null;
            }
        }

        // Tanpa pembaca bawaan, seluruh pekerjaan jatuh ke JavaScript — inilah
        // keadaan di Safari iOS. Karena itu tiap bingkai hanya menjalankan satu
        // sapuan, bergantian antara potongan tengah dan seluruh bingkai.
        // Menjalankan keduanya sekaligus pada resolusi penuh membuat lajunya
        // turun sampai beberapa bingkai per detik, dan pemindai terasa seperti
        // tidak bekerja sama sekali.
        bingkaiKe++;

        const sisi = Math.floor(Math.min(lebar, tinggi) * 0.7);

        if (bingkaiKe % 2 === 1) {
            // Potongan tengah, diperkecil secukupnya. Kotak bidik di layar
            // menuntun pengguna menaruh QR persis di daerah ini.
            const ukuran = Math.min(sisi, 640);

            return bacaDariKanvas(
                ukuran, ukuran,
                Math.floor((lebar - sisi) / 2), Math.floor((tinggi - sisi) / 2), sisi, sisi,
            );
        }

        // Seluruh bingkai, untuk kode yang kecil dan agak jauh dari tengah.
        const skala = Math.min(1, 720 / lebar);

        return bacaDariKanvas(
            Math.floor(lebar * skala),
            Math.floor(tinggi * skala),
            0, 0, lebar, tinggi,
        );
    }

    function bacaDariKanvas(lebarKanvas, tinggiKanvas, sX, sY, sLebar, sTinggi) {
        kanvas.width = lebarKanvas;
        kanvas.height = tinggiKanvas;

        konteks.drawImage(video, sX, sY, sLebar, sTinggi, 0, 0, lebarKanvas, tinggiKanvas);

        const data = konteks.getImageData(0, 0, lebarKanvas, tinggiKanvas);

        // Pembalikan warna hanya dicoba sesekali; jarang diperlukan dan
        // menggandakan waktu pemindaian.
        const kode = jsQR(data.data, data.width, data.height, {
            inversionAttempts: bingkaiKe % 8 === 0 ? 'attemptBoth' : 'dontInvert',
        });

        return kode?.data ?? null;
    }

    function ulangi() {
        terakhirTerbaca = null;
    }

    function kabarkan(nama, muatan = null) {
        wadah.dispatchEvent(new CustomEvent('qr:' + nama, { detail: muatan, bubbles: true }));
    }

    const api = { mulai, berhenti, ulangi, gantiKamera, alihkanSenter, fokusDi, cobaMainkanLagi };

    wadah._pemindaiQr = api;

    document.addEventListener('livewire:navigating', berhenti);

    return api;
}
