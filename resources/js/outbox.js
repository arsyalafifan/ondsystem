/**
 * Antrean kunjungan offline di perangkat sales.
 *
 * Bentuknya SATU ARAH: perangkat menyimpan apa yang sudah terjadi, lalu
 * mengirimkannya begitu ada sinyal. Server tidak pernah mengirim balik
 * perubahan untuk digabungkan, jadi tidak ada pertanyaan "siapa yang menang"
 * yang perlu dijawab di sini.
 *
 * Dipakai IndexedDB, bukan localStorage: localStorage hanya sekitar 5 MB dan
 * menyimpan teks secara sinkron (memblokir layar), sementara satu kunjungan
 * saja membawa lima foto. IndexedDB sanggup menampung puluhan kunjungan dan
 * bekerja tanpa membekukan antarmuka.
 */

const NAMA_DB = 'ond-kunjungan';
const VERSI_DB = 1;

const TOKO = 'toko';
const ANTREAN = 'antrean';
const META = 'meta';

let dbPromise = null;

function buka() {
    if (dbPromise) {
        return dbPromise;
    }

    dbPromise = new Promise((selesai, gagal) => {
        const permintaan = indexedDB.open(NAMA_DB, VERSI_DB);

        permintaan.onupgradeneeded = () => {
            const db = permintaan.result;

            if (!db.objectStoreNames.contains(TOKO)) {
                db.createObjectStore(TOKO, { keyPath: 'id' });
            }

            if (!db.objectStoreNames.contains(ANTREAN)) {
                db.createObjectStore(ANTREAN, { keyPath: 'uuid_klien' });
            }

            if (!db.objectStoreNames.contains(META)) {
                db.createObjectStore(META, { keyPath: 'kunci' });
            }
        };

        permintaan.onsuccess = () => selesai(permintaan.result);
        permintaan.onerror = () => gagal(permintaan.error);
    });

    return dbPromise;
}

async function jalankan(nama, mode, kerja) {
    const db = await buka();

    return new Promise((selesai, gagal) => {
        const transaksi = db.transaction(nama, mode);
        const store = transaksi.objectStore(nama);
        const hasil = kerja(store);

        transaksi.oncomplete = () => selesai(hasil?.result ?? hasil);
        transaksi.onerror = () => gagal(transaksi.error);
        transaksi.onabort = () => gagal(transaksi.error);
    });
}

function semua(nama) {
    return jalankan(nama, 'readonly', (store) => store.getAll());
}

// --- Daftar toko tanggungan -------------------------------------------

/** Mengambil daftar toko terbaru dari server dan menyimpannya di perangkat. */
export async function segarkanToko() {
    const jawaban = await fetch(window.ondOffline.ruteTanggungan, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!jawaban.ok) {
        throw new Error('gagal-ambil-tanggungan');
    }

    const data = await jawaban.json();
    const db = await buka();

    await new Promise((selesai, gagal) => {
        const transaksi = db.transaction([TOKO, META], 'readwrite');
        const store = transaksi.objectStore(TOKO);

        store.clear();
        data.toko.forEach((t) => store.put(t));

        transaksi.objectStore(META).put({
            kunci: 'tanggungan',
            diambil_at: data.diambil_at,
            periode: data.periode,
            foto_wajib: data.foto_wajib,
        });

        transaksi.oncomplete = selesai;
        transaksi.onerror = () => gagal(transaksi.error);
    });

    return data;
}

export function daftarToko() {
    return semua(TOKO);
}

export async function metaTanggungan() {
    const baris = await jalankan(META, 'readonly', (store) => store.get('tanggungan'));

    return baris ?? null;
}

/** Mencocokkan nomor aset hasil pindaian QR dengan toko yang tersimpan. */
export async function tokoDariAset(assetId) {
    const bersih = String(assetId || '').toUpperCase().replace(/\s+/g, '');

    if (!bersih) {
        return null;
    }

    const daftar = await daftarToko();

    return daftar.find((t) => String(t.asset_id || '').toUpperCase() === bersih) ?? null;
}

// --- Antrean kunjungan -------------------------------------------------

export function antrean() {
    return semua(ANTREAN);
}

export async function jumlahAntrean() {
    const daftar = await antrean();

    return daftar.length;
}

export async function simpanKunjungan(kunjungan) {
    await jalankan(ANTREAN, 'readwrite', (store) => store.put(kunjungan));
    await kabarkanPerubahan();

    return kunjungan;
}

export async function hapusKunjungan(uuid) {
    await jalankan(ANTREAN, 'readwrite', (store) => store.delete(uuid));
    await kabarkanPerubahan();
}

async function tandaiGagal(kunjungan, status, pesan) {
    await jalankan(ANTREAN, 'readwrite', (store) => store.put({
        ...kunjungan,
        galat: { status, pesan, pada: new Date().toISOString() },
    }));
}

async function kabarkanPerubahan() {
    const daftar = await antrean();

    document.dispatchEvent(new CustomEvent('outbox:berubah', {
        detail: {
            jumlah: daftar.length,
            // Kunjungan yang ditolak server tidak dihitung sebagai
            // "menunggu" — mengirimnya lagi tidak akan mengubah apa pun,
            // jadi ia butuh perhatian sales, bukan percobaan ulang.
            menunggu: daftar.filter((k) => !k.galat).length,
            bermasalah: daftar.filter((k) => k.galat).length,
        },
    }));
}

/** Memberi tahu layar tanpa mengubah apa pun — dipakai saat halaman dibuka. */
export function kabarkanSekarang() {
    return kabarkanPerubahan();
}

let sedangKirim = false;

/**
 * Mengirim antrean satu per satu, bukan sekaligus.
 *
 * Satu kunjungan membawa lima foto; menggabungkan beberapa kunjungan dalam
 * satu permintaan gampang menabrak batas post_max_size PHP, dan kalau itu
 * terjadi seluruh kiriman gagal sekaligus. Satu per satu juga berarti sinyal
 * yang putus di tengah hanya membatalkan satu kunjungan, sisanya tetap aman
 * di perangkat.
 */
export async function kirimAntrean() {
    if (sedangKirim || !navigator.onLine) {
        return { terkirim: 0, gagal: 0 };
    }

    sedangKirim = true;

    let terkirim = 0;
    let gagal = 0;

    try {
        const daftar = (await antrean()).filter((k) => !k.galat);

        for (const kunjungan of daftar) {
            const { galat, ...muatan } = kunjungan;

            let jawaban;

            try {
                jawaban = await fetch(window.ondOffline.ruteSinkron, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': window.ondOffline.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ kunjungans: [muatan] }),
                });
            } catch (e) {
                // Sinyal putus lagi: berhenti, sisanya tetap tersimpan.
                gagal++;
                break;
            }

            if (jawaban.status === 419 || jawaban.status === 401) {
                // Sesi kedaluwarsa. Antrean TIDAK dibuang — sales tinggal
                // login ulang, lalu pengiriman dicoba lagi dari awal.
                document.dispatchEvent(new CustomEvent('outbox:butuh-login'));
                gagal++;
                break;
            }

            if (!jawaban.ok) {
                gagal++;
                break;
            }

            const hasil = (await jawaban.json()).hasil?.[0];

            if (!hasil) {
                gagal++;
                break;
            }

            if (hasil.status === 'diterima' || hasil.status === 'duplikat') {
                await hapusKunjungan(kunjungan.uuid_klien);
                terkirim++;
            } else {
                // konflik / ditolak: mengirim ulang tidak akan menolong.
                await tandaiGagal(kunjungan, hasil.status, hasil.pesan);
                gagal++;
            }
        }
    } finally {
        sedangKirim = false;
        await kabarkanPerubahan();
    }

    return { terkirim, gagal };
}

// --- Service worker ----------------------------------------------------

/**
 * Mendaftarkan service worker, atau MENCABUTNYA bila mode offline dimatikan.
 *
 * Cabang kedua itulah kill-switch-nya: halaman selalu diambil network-first,
 * jadi perangkat yang daring sekali saja akan membaca tanda mati ini lalu
 * membersihkan dirinya sendiri. Tidak perlu menyentuh ponsel sales satu per
 * satu, dan tidak perlu deploy — cukup ubah VISIT_PWA_AKTIF.
 */
export async function siapkanServiceWorker({ aktif, versi }) {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    if (!aktif) {
        const terdaftar = await navigator.serviceWorker.getRegistrations();

        await Promise.all(terdaftar.map((r) => r.unregister()));

        if (window.caches) {
            const nama = await caches.keys();
            await Promise.all(nama.filter((n) => n.startsWith('ond-')).map((n) => caches.delete(n)));
        }

        return;
    }

    try {
        await navigator.serviceWorker.register(`/sw.js?v=${encodeURIComponent(versi)}`, { scope: '/' });
    } catch (e) {
        // Gagal mendaftar bukan alasan untuk merusak halaman: aplikasi tetap
        // jalan seperti biasa, hanya tanpa kemampuan offline.
    }
}

/**
 * Membuang halaman yang tersimpan di cache.
 *
 * Dipanggil dari layar masuk: satu ponsel bisa dipakai bergantian oleh dua
 * sales, dan halaman hasil cache milik orang sebelumnya tidak boleh ikut
 * terbawa ke sesi berikutnya.
 */
export async function bersihkanCacheHalaman() {
    if (!window.caches) {
        return;
    }

    const nama = await caches.keys();

    await Promise.all(nama.filter((n) => n.startsWith('ond-')).map((n) => caches.delete(n)));
}
