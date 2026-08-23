/**
 * Menyisipkan titik pemisah ribuan pada angka rupiah (mis. "1500000" jadi
 * "1.500.000") — murni tampilan, angka yang benar-benar dikirim ke Livewire
 * tetap bersih tanpa titik. Karakter non-digit (termasuk minus) dibuang,
 * karena nominal cash/transfer tidak pernah berupa desimal atau negatif.
 */
export function formatRibuan(nilai) {
    if (nilai === null || nilai === undefined || nilai === '') {
        return '';
    }

    const bersih = String(nilai).replace(/\D/g, '');

    return bersih === '' ? '' : bersih.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
