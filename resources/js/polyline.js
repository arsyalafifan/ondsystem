/**
 * Membaca polyline terenkode dari OSRM menjadi daftar koordinat.
 * Presisi 5 sesuai keluaran bawaan OSRM.
 */
export function decodePolyline(terenkode, presisi = 5) {
    if (!terenkode) {
        return [];
    }

    const faktor = Math.pow(10, presisi);
    const titik = [];

    let indeks = 0;
    let lat = 0;
    let lng = 0;

    while (indeks < terenkode.length) {
        let hasil = 1;
        let geser = 0;
        let b;

        do {
            b = terenkode.charCodeAt(indeks++) - 63 - 1;
            hasil += b << geser;
            geser += 5;
        } while (b >= 0x1f);

        lat += hasil & 1 ? ~(hasil >> 1) : hasil >> 1;

        hasil = 1;
        geser = 0;

        do {
            b = terenkode.charCodeAt(indeks++) - 63 - 1;
            hasil += b << geser;
            geser += 5;
        } while (b >= 0x1f);

        lng += hasil & 1 ? ~(hasil >> 1) : hasil >> 1;

        titik.push([lat / faktor, lng / faktor]);
    }

    return titik;
}
