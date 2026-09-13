/**
 * Service worker OND System — hanya untuk layar kunjungan sales.
 *
 * ATURAN YANG TIDAK BOLEH DILANGGAR: halaman HTML SELALU diambil
 * network-first. Cache hanya dipakai ketika jaringan benar-benar gagal.
 *
 * Alasannya bukan soal kesegaran data, tapi soal jalan pulang. Service worker
 * mengendalikan apa yang diambil peramban, jadi kalau HTML disajikan
 * cache-first, satu deploy yang salah akan terkunci di ponsel sales dan
 * MEMPERBAIKI SERVER TIDAK MENYEMBUHKANNYA — perangkat tidak akan pernah
 * meminta versi baru. Dengan network-first, perangkat yang daring selalu
 * melihat versi terbaru, termasuk perintah untuk mencabut service worker ini
 * sendiri (lihat saklar visit.offline.pwa_aktif).
 *
 * Berkas Vite (/build/assets/*) boleh cache-first karena namanya mengandung
 * hash isi: berkas dengan nama sama dijamin isinya sama, jadi tidak ada versi
 * basi yang mungkin tersaji.
 *
 * Versi cache datang dari query string saat pendaftaran (sw.js?v=xxxx), yang
 * berubah tiap kali aset dibangun ulang. Berubahnya URL membuat peramban
 * memperlakukannya sebagai service worker baru, dan cache lama dibuang di
 * langkah activate.
 */

const VERSI = new URL(self.location.href).searchParams.get('v') || 'dev';
const CACHE = `ond-${VERSI}`;

// Halaman yang harus tetap terbuka tanpa jaringan. Hanya layar kerja sales —
// layar admin tidak pernah ikut, mereka tidak berada di luar jangkauan.
const HALAMAN_OFFLINE = '/kunjungan';

self.addEventListener('install', (event) => {
    // Menunggu giliran hanya menunda perbaikan; versi baru langsung dipakai.
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.add(HALAMAN_OFFLINE)).catch(() => null),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((nama) => Promise.all(
                nama.filter((n) => n.startsWith('ond-') && n !== CACHE).map((n) => caches.delete(n)),
            ))
            .then(() => self.clients.claim()),
    );
});

/** Perintah dari halaman: dipakai kill-switch untuk membersihkan jejak. */
self.addEventListener('message', (event) => {
    if (event.data === 'bersihkan-cache') {
        event.waitUntil(
            caches.keys().then((nama) => Promise.all(
                nama.filter((n) => n.startsWith('ond-')).map((n) => caches.delete(n)),
            )),
        );
    }
});

self.addEventListener('fetch', (event) => {
    const permintaan = event.request;

    if (permintaan.method !== 'GET') {
        return;
    }

    const url = new URL(permintaan.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Kiriman antrean dan pengambilan data tidak pernah disentuh cache:
    // jawabannya harus selalu dari server yang sebenarnya.
    if (url.pathname.startsWith('/api/')) {
        return;
    }

    if (permintaan.mode === 'navigate') {
        event.respondWith(halamanNetworkFirst(permintaan));

        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(asetCacheFirst(permintaan));
    }
});

/**
 * Jaringan dulu; cache hanya jaring pengaman saat benar-benar gagal.
 * Jawaban yang berhasil ikut disimpan supaya kunjungan berikutnya tetap
 * bisa dibuka di daerah tanpa sinyal.
 */
async function halamanNetworkFirst(permintaan) {
    try {
        const jawaban = await fetch(permintaan);

        // Pengalihan ke layar masuk tidak ikut disimpan — kalau tersimpan,
        // perangkat yang offline akan terus melihat layar masuk yang tidak
        // mungkin diselesaikannya.
        if (jawaban.ok && jawaban.type === 'basic') {
            const salinan = jawaban.clone();
            caches.open(CACHE).then((cache) => cache.put(permintaan, salinan)).catch(() => null);
        }

        return jawaban;
    } catch (e) {
        const tersimpan = await caches.match(permintaan);

        if (tersimpan) {
            return tersimpan;
        }

        const cadangan = await caches.match(HALAMAN_OFFLINE);

        if (cadangan) {
            return cadangan;
        }

        throw e;
    }
}

/** Nama berkas Vite mengandung hash isi, jadi isinya tidak pernah basi. */
async function asetCacheFirst(permintaan) {
    const tersimpan = await caches.match(permintaan);

    if (tersimpan) {
        return tersimpan;
    }

    const jawaban = await fetch(permintaan);

    if (jawaban.ok) {
        const salinan = jawaban.clone();
        caches.open(CACHE).then((cache) => cache.put(permintaan, salinan)).catch(() => null);
    }

    return jawaban;
}
