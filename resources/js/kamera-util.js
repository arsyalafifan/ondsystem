/**
 * Perkakas bersama untuk pemindai QR dan pengambil foto.
 *
 * Dua hal yang paling sering membuat kamera di peramban terasa jauh lebih
 * buruk daripada aplikasi kamera bawaan:
 *
 *  1. Ponsel masa kini punya beberapa kamera belakang — utama, ultra-lebar,
 *     kadang makro. Permintaan `facingMode: environment` boleh dijawab dengan
 *     lensa mana pun, dan banyak perangkat menjawabnya dengan ultra-lebar yang
 *     fokusnya tetap. Lensa itu tidak akan pernah bisa menajamkan objek dekat
 *     seperti stiker QR, sekeras apa pun penggunanya mencoba.
 *
 *  2. Autofokus tidak otomatis menyala. Aliran video bisa terkunci pada satu
 *     jarak fokus sampai diminta secara tegas.
 *
 * Keduanya ditangani di sini, ditambah jalan bagi pengguna untuk berpindah
 * lensa sendiri ketika tebakan otomatisnya meleset.
 */

const KUNCI_SIMPANAN = 'ond:kamera-pilihan';

/**
 * Memutar aliran kamera pada elemen video.
 *
 * Safari di iOS kerap menolak play() karena konteks sentuhan pengguna dianggap
 * hilang setelah menunggu getUserMedia. Penolakan itu berupa Promise yang
 * gagal; kalau dibiarkan tanpa penangkap, seluruh proses penyalaan kamera
 * berhenti diam-diam — tanpa gambar, tanpa pesan galat, tanpa apa pun yang
 * bisa dilihat pengguna.
 *
 * Karena itu kegagalannya ditangkap dan dilaporkan sebagai nilai, bukan
 * dilempar. Aliran videonya sendiri sudah tersambung, jadi pemutaran masih
 * bisa dicoba ulang saat pengguna menyentuh layar.
 */
export async function mainkanVideo(video) {
    // Ditegaskan lewat properti, bukan hanya atribut HTML: Safari menuntut
    // keduanya sebelum bersedia memutar tanpa sentuhan.
    video.muted = true;
    video.defaultMuted = true;
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');

    try {
        await video.play();

        return true;
    } catch {
        return false;
    }
}

/**
 * Menunggu sampai bingkai video benar-benar punya ukuran.
 *
 * Di iOS, videoWidth bisa tetap nol beberapa saat setelah play() berhasil.
 * Memindai sebelum itu hanya membuang tenaga.
 */
export function tungguBingkaiSiap(video, batasMs = 3000) {
    return new Promise((selesai) => {
        if (video.videoWidth > 0) {
            selesai(true);

            return;
        }

        const mulai = Date.now();

        const periksa = () => {
            if (video.videoWidth > 0) {
                selesai(true);
            } else if (Date.now() - mulai > batasMs) {
                selesai(false);
            } else {
                requestAnimationFrame(periksa);
            }
        };

        requestAnimationFrame(periksa);
    });
}

/**
 * Tanda bahwa kode berhasil terbaca.
 *
 * Getaran tidak tersedia di iOS — Safari tidak mengenal Vibration API sama
 * sekali. Karena itu tandanya berlapis: getaran bila ada, ditambah bunyi
 * pendek yang berjalan di mana saja setelah pengguna menyentuh layar sekali.
 */
export function tandaBerhasil() {
    navigator.vibrate?.(120);

    try {
        const Audio = window.AudioContext ?? window.webkitAudioContext;

        if (!Audio) {
            return;
        }

        const konteks = new Audio();
        const nada = konteks.createOscillator();
        const keras = konteks.createGain();

        nada.frequency.value = 880;
        keras.gain.value = 0.08;

        nada.connect(keras).connect(konteks.destination);
        nada.start();
        nada.stop(konteks.currentTime + 0.12);

        setTimeout(() => konteks.close(), 400);
    } catch {
        // Bunyi hanya pelengkap; kegagalannya tidak boleh mengganggu apa pun.
    }
}

/** Menyalakan autofokus menerus bila perangkatnya mendukung. */
export async function nyalakanAutofokus(track) {
    const kemampuan = track?.getCapabilities?.() ?? {};

    if (!kemampuan.focusMode) {
        return false;
    }

    const mode = kemampuan.focusMode.includes('continuous')
        ? 'continuous'
        : (kemampuan.focusMode.includes('auto') ? 'auto' : null);

    if (!mode) {
        return false;
    }

    try {
        await track.applyConstraints({ advanced: [{ focusMode: mode }] });

        return true;
    } catch {
        return false;
    }
}

/**
 * Memfokuskan ke satu titik pada bingkai, dipakai saat pengguna menyentuh
 * layar. Nilainya 0..1 dihitung dari sudut kiri atas gambar.
 */
export async function fokusKeTitik(track, x, y) {
    const kemampuan = track?.getCapabilities?.() ?? {};

    if (!kemampuan.pointsOfInterest) {
        // Perangkat tanpa dukungan titik fokus tetap dibantu dengan memicu
        // ulang autofokus, yang biasanya cukup untuk menajamkan gambar.
        return nyalakanAutofokus(track);
    }

    try {
        await track.applyConstraints({
            advanced: [{ pointsOfInterest: [{ x, y }], focusMode: 'single-shot' }],
        });

        return true;
    } catch {
        return false;
    }
}

export function punyaSenter(track) {
    return Boolean(track?.getCapabilities?.().torch);
}

export async function aturSenter(track, nyala) {
    if (!punyaSenter(track)) {
        return false;
    }

    try {
        await track.applyConstraints({ advanced: [{ torch: nyala }] });

        return true;
    } catch {
        return false;
    }
}

/**
 * Daftar kamera belakang yang tersedia.
 *
 * Label perangkat baru terisi setelah izin kamera diberikan, jadi fungsi ini
 * dipanggil setelah aliran pertama berhasil dibuka.
 */
export async function daftarKameraBelakang() {
    if (!navigator.mediaDevices?.enumerateDevices) {
        return [];
    }

    const semua = await navigator.mediaDevices.enumerateDevices();
    const video = semua.filter((d) => d.kind === 'videoinput');

    const belakang = video.filter((d) => /back|rear|belakang|environment/i.test(d.label));

    // Sebagian peramban tidak memberi label sama sekali. Dalam keadaan itu
    // seluruh kamera ditawarkan, biar pengguna yang memilih.
    return belakang.length > 0 ? belakang : video;
}

/**
 * Menebak lensa utama di antara beberapa kamera belakang.
 *
 * Lensa ultra-lebar dan makro biasanya menyebut dirinya sendiri pada label,
 * jadi yang tidak menyebut apa-apa hampir selalu lensa utama. Kalau tebakan
 * ini meleset, pengguna masih bisa berpindah lensa lewat tombol.
 */
export function tebakLensaUtama(kameraBelakang) {
    if (kameraBelakang.length === 0) {
        return null;
    }

    const bukanKhusus = kameraBelakang.find(
        (d) => !/wide|ultra|tele|macro|depth|zoom/i.test(d.label),
    );

    return (bukanKhusus ?? kameraBelakang[0]).deviceId;
}

export function simpanPilihan(deviceId) {
    try {
        localStorage.setItem(KUNCI_SIMPANAN, deviceId);
    } catch {
        // Penyimpanan bisa ditolak pada mode penjelajahan tersamar; tidak apa.
    }
}

export function ambilPilihan() {
    try {
        return localStorage.getItem(KUNCI_SIMPANAN);
    } catch {
        return null;
    }
}

/**
 * Syarat aliran video. Resolusi diminta tinggi karena QR pada stiker freezer
 * berukuran kecil di dalam bingkai; makin banyak piksel, makin besar peluang
 * kodenya terbaca.
 */
export function syaratVideo(deviceId) {
    const dasar = {
        width: { ideal: 1920 },
        height: { ideal: 1080 },
        frameRate: { ideal: 30 },
        // Meminta fokus menerus sejak awal. Peramban yang tidak mengenalnya
        // akan mengabaikan bagian ini tanpa menggagalkan permintaan.
        focusMode: { ideal: 'continuous' },
    };

    return deviceId
        ? { ...dasar, deviceId: { exact: deviceId } }
        : { ...dasar, facingMode: { ideal: 'environment' } };
}
