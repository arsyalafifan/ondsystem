import L from 'leaflet';
import { decodePolyline } from './polyline';

/**
 * Peta yang menampilkan rute beberapa kendaraan sekaligus.
 *
 * Dipakai di dua tempat: halaman Generate Routing (admin menyunting hasil)
 * dan halaman Monitoring (memantau progres pengiriman).
 *
 * Peta ini menggantikan pekerjaan menempel penanda satu per satu di
 * mapmarker.app. Setiap toko langsung muncul dengan nomor urut kunjungan dan
 * warna mobilnya, dan garis rutenya mengikuti jalan sebenarnya.
 */
export function pasangPetaRute(idWadah, pengaturan = {}) {
    const wadah = document.getElementById(idWadah);

    if (!wadah) {
        return null;
    }

    // Peta yang sudah terpasang dipakai ulang, supaya Livewire yang
    // menggambar ulang halaman tidak meninggalkan peta bertumpuk.
    if (wadah._petaRute) {
        return wadah._petaRute;
    }

    const peta = L.map(idWadah, {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView(
        [pengaturan.depot?.lat ?? -6.2, pengaturan.depot?.lng ?? 106.816666],
        pengaturan.zoom ?? 12,
    );

    L.tileLayer(pengaturan.tileUrl, {
        attribution: pengaturan.attribution,
        maxZoom: 19,
    }).addTo(peta);

    const lapisanRute = L.layerGroup().addTo(peta);
    const lapisanPenanda = L.layerGroup().addTo(peta);

    let penandaPerStop = {};
    let tersembunyi = new Set();
    let dataTerakhir = { kendaraan: [] };

    if (pengaturan.depot) {
        L.marker([pengaturan.depot.lat, pengaturan.depot.lng], {
            icon: L.divIcon({
                className: 'penanda-toko penanda-depot',
                html: `<span style="background:#111827">🏭</span>`,
                iconSize: [32, 32],
                iconAnchor: [16, 16],
            }),
            zIndexOffset: 1000,
        })
            .bindTooltip(pengaturan.depot.nama ?? 'Gudang', { direction: 'top' })
            .addTo(peta);
    }

    function gambar(data) {
        dataTerakhir = data ?? { kendaraan: [] };

        lapisanRute.clearLayers();
        lapisanPenanda.clearLayers();
        penandaPerStop = {};

        const semuaTitik = [];

        if (pengaturan.depot) {
            semuaTitik.push([pengaturan.depot.lat, pengaturan.depot.lng]);
        }

        (dataTerakhir.kendaraan || []).forEach((kendaraan) => {
            if (tersembunyi.has(kendaraan.id)) {
                return;
            }

            const garis = decodePolyline(kendaraan.geometry || '');

            if (garis.length > 1) {
                L.polyline(garis, {
                    color: kendaraan.warna,
                    weight: 4,
                    opacity: 0.75,
                }).addTo(lapisanRute);
            }

            (kendaraan.stops || []).forEach((stop) => {
                if (stop.lat === null || stop.lng === null) {
                    return;
                }

                const posisi = [stop.lat, stop.lng];
                semuaTitik.push(posisi);

                // Kunjungan yang sudah selesai ditandai centang, sisanya
                // menampilkan nomor urutnya. Halaman monitoring mengirim
                // warnaStatus agar penanda mengikuti status pesanan, bukan
                // warna mobil.
                const isi = stop.selesai ? '✓' : stop.urutan;
                const warna = stop.warnaStatus ?? (stop.selesai ? '#059669' : kendaraan.warna);

                const penanda = L.marker(posisi, {
                    icon: L.divIcon({
                        className: 'penanda-toko',
                        html: `<span style="background:${warna}">${isi}</span>`,
                        iconSize: [26, 26],
                        iconAnchor: [13, 13],
                    }),
                });

                penanda.bindTooltip(
                    `<strong>${escapeHtml(stop.nama)}</strong><br>` +
                        `${kendaraan.nama} · urutan ${stop.urutan} · ${stop.dus} dus` +
                        (stop.eta ? `<br>Perkiraan tiba ${stop.eta}` : ''),
                    { direction: 'top' },
                );

                if (pengaturan.bisaDiklik) {
                    penanda.on('click', () => {
                        window.Livewire.dispatch('stop-dipilih', { stopId: stop.id });
                    });
                }

                penanda.addTo(lapisanPenanda);
                penandaPerStop[stop.id] = penanda;
            });
        });

        // Toko yang pesanannya belum masuk rute mana pun.
        (dataTerakhir.belumDirutekan || []).forEach((toko) => {
            if (toko.lat === null || toko.lng === null) {
                return;
            }

            semuaTitik.push([toko.lat, toko.lng]);

            L.marker([toko.lat, toko.lng], {
                icon: L.divIcon({
                    className: 'penanda-toko',
                    html: `<span style="background:#9ca3af">?</span>`,
                    iconSize: [26, 26],
                    iconAnchor: [13, 13],
                }),
            })
                .bindTooltip(`<strong>${escapeHtml(toko.nama)}</strong><br>Belum masuk rute`, {
                    direction: 'top',
                })
                .addTo(lapisanPenanda);
        });

        if (semuaTitik.length > 1) {
            peta.fitBounds(L.latLngBounds(semuaTitik), { padding: [40, 40], maxZoom: 15 });
        }
    }

    const api = {
        peta,
        gambar,

        /** Menyembunyikan atau menampilkan rute satu kendaraan. */
        alihkanKendaraan(id) {
            if (tersembunyi.has(id)) {
                tersembunyi.delete(id);
            } else {
                tersembunyi.add(id);
            }

            gambar(dataTerakhir);
        },

        tampilkanSemua() {
            tersembunyi = new Set();
            gambar(dataTerakhir);
        },

        /** Memusatkan peta ke satu toko, dipakai saat baris daftar diklik. */
        sorot(stopId) {
            const penanda = penandaPerStop[stopId];

            if (!penanda) {
                return;
            }

            peta.setView(penanda.getLatLng(), Math.max(peta.getZoom(), 15), { animate: true });
            penanda.openTooltip();
        },

        /** Memusatkan peta ke seluruh rute satu kendaraan. */
        sorotKendaraan(kendaraanId) {
            const kendaraan = (dataTerakhir.kendaraan || []).find((k) => k.id === kendaraanId);

            if (!kendaraan) {
                return;
            }

            const titik = (kendaraan.stops || [])
                .filter((s) => s.lat !== null)
                .map((s) => [s.lat, s.lng]);

            if (titik.length === 0) {
                return;
            }

            peta.fitBounds(L.latLngBounds(titik), { padding: [40, 40] });
        },
    };

    wadah._petaRute = api;

    // Leaflet salah menghitung ukuran bila wadahnya semula tersembunyi
    // (misalnya di dalam tab). Sekali diperbarui setelah render, ukurannya benar.
    setTimeout(() => peta.invalidateSize(), 200);

    return api;
}

function escapeHtml(teks) {
    const el = document.createElement('div');
    el.textContent = teks ?? '';

    return el.innerHTML;
}
