@props(['callback' => 'pusatkanLokasiSaya'])

{{-- Tombol GPS generik: memusatkan peta Leaflet manapun ke lokasi pengguna
     saat ini, lewat fungsi window[$callback](lat, lng) yang didaftarkan
     komponen pemanggil. --}}
<button type="button"
        x-data="{ mencari: false }"
        @click="
            if (!navigator.geolocation) {
                window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: @js(__('umum.gps_tidak_didukung')), jenis: 'error' } }));
                return;
            }
            mencari = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    mencari = false;
                    window.{{ $callback }}?.(pos.coords.latitude, pos.coords.longitude);
                },
                () => {
                    mencari = false;
                    window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: @js(__('umum.gps_gagal')), jenis: 'error' } }));
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        "
        :disabled="mencari"
        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50 disabled:opacity-60">
    <span x-show="!mencari">{{ __('umum.lokasi_saya') }}</span>
    <span x-show="mencari" x-cloak>{{ __('umum.mencari') }}</span>
</button>
