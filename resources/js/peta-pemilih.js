import L from 'leaflet';

/**
 * Peta kecil untuk menaruh titik koordinat sebuah toko.
 *
 * Admin cukup mengklik lokasinya di peta, atau menggeser penandanya. Ini
 * jalan keluar ketika hasil pencarian alamat otomatis meleset — yang sering
 * terjadi pada alamat toko di gang atau perumahan yang belum terpetakan.
 */
export function pasangPemilihTitik(idWadah, pengaturan = {}) {
    const wadah = document.getElementById(idWadah);

    if (!wadah) {
        return null;
    }

    if (wadah._pemilihTitik) {
        return wadah._pemilihTitik;
    }

    const awal = [
        pengaturan.lat ?? pengaturan.depot?.lat ?? -6.2,
        pengaturan.lng ?? pengaturan.depot?.lng ?? 106.816666,
    ];

    const peta = L.map(idWadah).setView(awal, pengaturan.lat ? 16 : 12);

    L.tileLayer(pengaturan.tileUrl, {
        attribution: pengaturan.attribution,
        maxZoom: 19,
    }).addTo(peta);

    const ikon = L.divIcon({
        className: 'penanda-toko',
        html: '<span style="background:#dc2626">📍</span>',
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    });

    let penanda = null;

    function taruh(lat, lng, beriTahu = true) {
        if (penanda) {
            penanda.setLatLng([lat, lng]);
        } else {
            penanda = L.marker([lat, lng], { icon: ikon, draggable: true }).addTo(peta);

            penanda.on('dragend', () => {
                const p = penanda.getLatLng();
                kirim(p.lat, p.lng);
            });
        }

        if (beriTahu) {
            kirim(lat, lng);
        }
    }

    function kirim(lat, lng) {
        window.dispatchEvent(
            new CustomEvent('titik-dipilih', {
                detail: { lat: Number(lat.toFixed(7)), lng: Number(lng.toFixed(7)) },
            }),
        );
    }

    if (pengaturan.lat && pengaturan.lng) {
        taruh(pengaturan.lat, pengaturan.lng, false);
    }

    peta.on('click', (e) => taruh(e.latlng.lat, e.latlng.lng));

    const api = {
        peta,

        /** Memindahkan penanda dari luar, misalnya setelah pencarian alamat. */
        pindah(lat, lng, zoom = 17) {
            taruh(lat, lng, false);
            peta.setView([lat, lng], zoom);
        },
    };

    wadah._pemilihTitik = api;

    setTimeout(() => peta.invalidateSize(), 200);

    return api;
}
