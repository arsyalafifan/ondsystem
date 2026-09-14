@props(['judul', 'lebar' => 'max-w-lg', 'tutup' => null])

{{--
    Latar gelap menutup seluruh layar; klik di luar kotak ikut menutup.

    Pengecualian: elemen bertanda [data-popover-teleport] (mis. daftar
    pilihan <x-pilih-cari> yang di-x-teleport ke <body>, lihat catatan di
    komponen itu) secara DOM memang berada DI LUAR kotak modal ini — tanpa
    penjagaan `closest()` di sini, mengeklik satu barisnya akan ikut
    terhitung "klik di luar" dan menutup seluruh modal sebelum pilih()
    sempat menyimpan pilihannya.
--}}
<div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 p-4"
     x-data
     x-on:keydown.escape.window="{{ $tutup ? '$wire.'.$tutup : '' }}">
    <div class="flex min-h-full items-center justify-center">
        <div class="w-full {{ $lebar }} rounded-xl bg-white shadow-xl"
             x-on:click.outside="if (! $event.target.closest('[data-popover-teleport]')) { {{ $tutup ? '$wire.'.$tutup : '' }} }">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-900">{{ $judul }}</h2>
                @if ($tutup)
                    <button type="button" wire:click="{{ $tutup }}"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <x-heroicon-o-x-mark class="size-5" />
                    </button>
                @endif
            </div>

            {{ $slot }}

            @if (isset($aksi))
                <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-3">{{ $aksi }}</div>
            @endif
        </div>
    </div>
</div>
