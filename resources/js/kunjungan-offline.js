import { pasangKamera } from './kamera';
import {
    antrean,
    daftarToko,
    hapusKunjungan,
    jumlahAntrean,
    kabarkanSekarang,
    kirimAntrean,
    metaTanggungan,
    segarkanToko,
    simpanKunjungan,
    tokoDariAset,
} from './outbox';

/**
 * Layar kunjungan saat tidak ada jaringan.
 *
 * Seluruh alur daring dikerjakan Livewire, yang mutlak butuh server pada
 * SETIAP interaksi — satu klik pun tidak jalan tanpa sinyal. Karena itu mode
 * offline tidak bisa menumpang alur yang sama dan berjalan sendiri di sini:
 * memilih toko, memotret, lalu menyimpan ke antrean di perangkat.
 *
 * Yang dipakai ulang justru bagian yang memang sudah murni sisi peramban —
 * modul kamera dan pemindai QR. Keduanya berkomunikasi lewat CustomEvent,
 * bukan lewat Livewire, jadi tetap hidup ketika server tidak terjangkau.
 */
export function pasangKunjunganOffline(pengaturan = {}) {
    const panel = document.getElementById('offline-panel');

    if (!panel) {
        return null;
    }

    const teks = pengaturan.teks ?? {};
    const el = (id) => document.getElementById(id);

    let toko = null;
    let fotoWajib = [];
    const diambil = new Map();
    let kamera = null;
    let jenisSedangDiambil = null;

    // --- Status jaringan ------------------------------------------------

    function perbaruiTampilanJaringan() {
        const luring = !navigator.onLine;

        panel.classList.toggle('hidden', !luring);
        el('offline-lencana')?.classList.toggle('hidden', !luring);

        // Layar daring disembunyikan saat luring supaya sales tidak menekan
        // tombol Livewire yang tidak akan pernah menjawab.
        document.getElementById('layar-daring')?.classList.toggle('hidden', luring);
    }

    // --- Antrean --------------------------------------------------------

    function perbaruiAntrean(detail) {
        const wadah = el('offline-antrean');

        if (!wadah) {
            return;
        }

        wadah.classList.toggle('hidden', detail.jumlah === 0);

        const menunggu = el('offline-antrean-jumlah');

        if (menunggu) {
            menunggu.textContent = detail.jumlah;
        }

        const bermasalah = el('offline-antrean-bermasalah');

        if (bermasalah) {
            bermasalah.classList.toggle('hidden', detail.bermasalah === 0);
            bermasalah.textContent = (teks.bermasalah ?? ':jumlah bermasalah').replace(':jumlah', detail.bermasalah);
        }

        gambarDaftarAntrean();
    }

    async function gambarDaftarAntrean() {
        const wadah = el('offline-antrean-daftar');

        if (!wadah) {
            return;
        }

        const daftar = await antrean();

        wadah.innerHTML = '';

        daftar.forEach((k) => {
            const baris = document.createElement('li');
            baris.className = 'flex items-start justify-between gap-2 py-1.5 text-xs';

            const kiri = document.createElement('div');
            kiri.className = 'min-w-0';

            const nama = document.createElement('p');
            nama.className = 'truncate font-medium text-gray-900';
            nama.textContent = k.nama_toko ?? '—';
            kiri.appendChild(nama);

            const ket = document.createElement('p');
            ket.className = k.galat ? 'text-red-700' : 'text-gray-500';
            ket.textContent = k.galat ? k.galat.pesan : new Date(k.mulai_at).toLocaleString();
            kiri.appendChild(ket);

            baris.appendChild(kiri);

            if (k.galat) {
                const buang = document.createElement('button');
                buang.type = 'button';
                buang.className = 'shrink-0 rounded border border-gray-300 bg-white px-2 py-1 font-medium';
                buang.textContent = teks.buang ?? 'Buang';
                buang.addEventListener('click', () => hapusKunjungan(k.uuid_klien));
                baris.appendChild(buang);
            }

            wadah.appendChild(baris);
        });
    }

    // --- Memilih toko ---------------------------------------------------

    async function gambarHasilCari(kata) {
        const wadah = el('offline-hasil');

        if (!wadah) {
            return;
        }

        wadah.innerHTML = '';

        const cari = String(kata || '').trim().toUpperCase();

        if (cari.length < 2) {
            return;
        }

        const daftar = (await daftarToko())
            .filter((t) => t.perlu_dikunjungi)
            .filter((t) => [t.nama, t.kode, t.alamat, t.asset_id]
                .some((n) => String(n || '').toUpperCase().includes(cari)))
            .slice(0, 12);

        if (daftar.length === 0) {
            const kosong = document.createElement('p');
            kosong.className = 'px-3 py-2 text-xs text-gray-500';
            kosong.textContent = teks.tidakAdaToko ?? '—';
            wadah.appendChild(kosong);

            return;
        }

        daftar.forEach((t) => {
            const tombol = document.createElement('button');
            tombol.type = 'button';
            tombol.className = 'block w-full border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-gray-50';
            tombol.innerHTML = '';

            const nama = document.createElement('span');
            nama.className = 'block font-medium text-gray-900';
            nama.textContent = t.nama;

            const ket = document.createElement('span');
            ket.className = 'block text-xs text-gray-500';
            ket.textContent = [t.kode, t.alamat].filter(Boolean).join(' · ');

            tombol.append(nama, ket);
            tombol.addEventListener('click', () => pilihToko(t));

            wadah.appendChild(tombol);
        });
    }

    function pilihToko(pilihan) {
        toko = pilihan;
        diambil.clear();

        el('offline-pilih-toko')?.classList.add('hidden');
        el('offline-kerja')?.classList.remove('hidden');

        const nama = el('offline-nama-toko');

        if (nama) {
            nama.textContent = pilihan.nama;
        }

        const ket = el('offline-ket-toko');

        if (ket) {
            ket.textContent = [pilihan.kode, pilihan.asset_id].filter(Boolean).join(' · ');
        }

        gambarTombolFoto();
        kamera?.pantauLokasi();
    }

    function batalPilihToko() {
        toko = null;
        diambil.clear();
        jenisSedangDiambil = null;

        el('offline-kerja')?.classList.add('hidden');
        el('offline-form-tutup')?.classList.add('hidden');
        el('offline-pilih-toko')?.classList.remove('hidden');
        tutupKamera();
    }

    // --- Foto -----------------------------------------------------------

    function gambarTombolFoto() {
        const wadah = el('offline-foto-daftar');

        if (!wadah) {
            return;
        }

        wadah.innerHTML = '';

        fotoWajib.forEach((jenis) => {
            const sudah = diambil.has(jenis.nilai);

            const tombol = document.createElement('button');
            tombol.type = 'button';
            tombol.className = [
                'flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm',
                sudah ? 'border-emerald-300 bg-emerald-50' : 'border-gray-300 bg-white',
            ].join(' ');

            const kiri = document.createElement('span');
            kiri.className = 'min-w-0';

            const label = document.createElement('span');
            label.className = 'block font-medium text-gray-900';
            label.textContent = jenis.label;

            const petunjuk = document.createElement('span');
            petunjuk.className = 'block text-xs text-gray-500';
            petunjuk.textContent = sudah ? (teks.sudahDiambil ?? '✓') : jenis.petunjuk;

            kiri.append(label, petunjuk);
            tombol.appendChild(kiri);
            tombol.addEventListener('click', () => bukaKamera(jenis.nilai));

            wadah.appendChild(tombol);
        });

        const selesai = el('tombol-offline-selesai');

        if (selesai) {
            selesai.disabled = diambil.size < fotoWajib.length;
            selesai.classList.toggle('opacity-50', selesai.disabled);
        }

        const hitung = el('offline-hitung-foto');

        if (hitung) {
            hitung.textContent = (teks.terkumpul ?? ':sudah/:total')
                .replace(':sudah', diambil.size)
                .replace(':total', fotoWajib.length);
        }
    }

    async function bukaKamera(jenis) {
        jenisSedangDiambil = jenis;

        el('kamera-offline')?.classList.remove('hidden');

        kamera ??= pasangKamera('kamera-offline', { pesan: pengaturan.pesanKamera ?? {} });

        await kamera?.nyalakan();
        kamera?.pantauLokasi();
    }

    function tutupKamera() {
        el('kamera-offline')?.classList.add('hidden');
        kamera?.matikan();
    }

    /**
     * Foto dikecilkan SEBELUM masuk antrean.
     *
     * Bidikan mentah dari kamera ponsel bisa beberapa megabita; lima foto
     * dikali belasan kunjungan yang tertahan seharian akan membengkak sampai
     * ratusan megabita di perangkat. Server toh mengecilkannya lagi ke lebar
     * yang sama, jadi tidak ada mutu bukti yang hilang di sini — yang hilang
     * hanya piksel yang memang akan dibuang juga nanti.
     */
    function kecilkan(dataUrl, lebarMaks, mutu) {
        return new Promise((selesai) => {
            const gambar = new Image();

            gambar.onload = () => {
                const skala = Math.min(1, lebarMaks / gambar.width);

                if (skala === 1) {
                    selesai(dataUrl);

                    return;
                }

                const kanvas = document.createElement('canvas');
                kanvas.width = Math.round(gambar.width * skala);
                kanvas.height = Math.round(gambar.height * skala);
                kanvas.getContext('2d').drawImage(gambar, 0, 0, kanvas.width, kanvas.height);

                selesai(kanvas.toDataURL('image/jpeg', mutu));
            };

            gambar.onerror = () => selesai(dataUrl);
            gambar.src = dataUrl;
        });
    }

    async function terimaJepretan(detail) {
        if (!jenisSedangDiambil) {
            return;
        }

        const gambar = await kecilkan(
            detail.gambar,
            pengaturan.lebarMaks ?? 1280,
            pengaturan.mutu ?? 0.82,
        );

        diambil.set(jenisSedangDiambil, {
            jenis: jenisSedangDiambil,
            gambar,
            diambil_at: new Date().toISOString(),
            latitude: detail.lokasi?.lat ?? null,
            longitude: detail.lokasi?.lng ?? null,
            akurasi_m: detail.lokasi?.akurasi ?? null,
        });

        jenisSedangDiambil = null;
        tutupKamera();
        gambarTombolFoto();
    }

    // --- Menyimpan ke antrean -------------------------------------------

    function uuid() {
        if (crypto.randomUUID) {
            return crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;

            return (c === 'x' ? r : ((r & 0x3) | 0x8)).toString(16);
        });
    }

    async function simpan(status, catatan = null) {
        if (!toko) {
            return;
        }

        const fotos = [...diambil.values()];
        const lokasi = kamera?.lokasi ?? null;
        const sekarang = new Date().toISOString();

        await simpanKunjungan({
            uuid_klien: uuid(),
            toko_id: toko.id,
            nama_toko: toko.nama,
            status,
            mulai_at: fotos[0]?.diambil_at ?? sekarang,
            selesai_at: sekarang,
            asset_id_terpindai: toko.asset_id ?? null,
            catatan_sales: catatan,
            latitude: lokasi?.lat ?? null,
            longitude: lokasi?.lng ?? null,
            akurasi_m: lokasi?.akurasi ?? null,
            fotos,
        });

        batalPilihToko();
        pesan(teks.tersimpan ?? 'Tersimpan di perangkat.');

        // Kalau ternyata sinyal sudah kembali, langsung dikirim saja.
        kirimAntrean();
    }

    function pesan(isi) {
        const wadah = el('offline-pesan');

        if (!wadah) {
            return;
        }

        wadah.textContent = isi;
        wadah.classList.remove('hidden');

        setTimeout(() => wadah.classList.add('hidden'), 4000);
    }

    // --- Pemasangan ------------------------------------------------------

    el('offline-cari')?.addEventListener('input', (e) => gambarHasilCari(e.target.value));
    el('tombol-offline-batal')?.addEventListener('click', batalPilihToko);
    el('tombol-offline-selesai')?.addEventListener('click', () => simpan('selesai'));

    el('tombol-offline-tutup')?.addEventListener('click', () => {
        el('offline-form-tutup')?.classList.toggle('hidden');
    });

    el('tombol-offline-kirim-tutup')?.addEventListener('click', () => {
        const catatan = el('offline-catatan-tutup')?.value ?? '';

        if (catatan.trim() === '') {
            pesan(teks.catatanWajib ?? '');

            return;
        }

        simpan('tutup_diajukan', catatan);
    });

    el('tombol-kirim-antrean')?.addEventListener('click', async () => {
        const hasil = await kirimAntrean();

        pesan((teks.hasilKirim ?? ':terkirim terkirim, :gagal gagal')
            .replace(':terkirim', hasil.terkirim)
            .replace(':gagal', hasil.gagal));
    });

    el('tombol-offline-batal-kamera')?.addEventListener('click', () => {
        jenisSedangDiambil = null;
        tutupKamera();
    });

    el('tombol-offline-jepret')?.addEventListener('click', () => kamera?.jepret(jenisSedangDiambil));

    document.getElementById('kamera-offline')
        ?.addEventListener('kamera:jepretan', (e) => terimaJepretan(e.detail));

    // Hasil pindaian QR dicocokkan ke daftar toko yang tersimpan di
    // perangkat — pekerjaan yang saat daring dilakukan server.
    document.addEventListener('qr:terbaca', async (e) => {
        if (navigator.onLine) {
            return;
        }

        const aset = String(e.detail || '').toUpperCase().match(/[A-Z]{2,6}[A-Z0-9]{8,24}/)?.[0] ?? null;
        const ketemu = aset ? await tokoDariAset(aset) : null;

        if (!ketemu) {
            pesan(teks.asetTidakDikenal ?? '');

            return;
        }

        if (!ketemu.perlu_dikunjungi) {
            pesan(teks.sudahDikunjungi ?? '');

            return;
        }

        pilihToko(ketemu);
    });

    document.addEventListener('outbox:berubah', (e) => perbaruiAntrean(e.detail));
    document.addEventListener('outbox:butuh-login', () => pesan(teks.butuhLogin ?? ''));

    window.addEventListener('online', () => {
        perbaruiTampilanJaringan();
        kirimAntrean();
        segarkanToko().catch(() => null);
    });

    window.addEventListener('offline', perbaruiTampilanJaringan);

    // --- Jalan pertama kali ----------------------------------------------

    (async () => {
        perbaruiTampilanJaringan();

        const meta = await metaTanggungan();
        fotoWajib = meta?.foto_wajib ?? [];

        const stempel = el('offline-stempel');

        if (stempel && meta?.diambil_at) {
            stempel.textContent = (teks.dataPer ?? ':waktu').replace(
                ':waktu',
                new Date(meta.diambil_at).toLocaleString(),
            );
        }

        await kabarkanSekarang();

        if (navigator.onLine) {
            // Selalu disegarkan saat daring: inilah satu-satunya kesempatan
            // perangkat mengambil bekal sebelum masuk daerah tanpa sinyal.
            try {
                const data = await segarkanToko();
                fotoWajib = data.foto_wajib ?? fotoWajib;
            } catch (e) {
                // Gagal menyegarkan bukan alasan menggagalkan layar; bekal
                // lama tetap dipakai.
            }

            if (await jumlahAntrean() > 0) {
                kirimAntrean();
            }
        }
    })();

    return { kirimAntrean, segarkanToko };
}
