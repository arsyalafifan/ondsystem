{{--
    Layar kunjungan saat tidak ada jaringan.

    Seluruhnya `wire:ignore`: Livewire tidak boleh menyentuh bagian ini sama
    sekali, karena justru di sinilah sales bekerja ketika server tidak
    terjangkau. Isinya digerakkan resources/js/kunjungan-offline.js dan
    disimpan ke IndexedDB, bukan dikirim ke server.

    Markup-nya tetap dirender server (sekadar disembunyikan saat daring)
    supaya ikut terbawa ke dalam cache service worker — panel yang baru
    dibuat JS saat offline tidak akan pernah ada di halaman hasil cache.
--}}
<div wire:ignore id="offline-panel" class="hidden">
    <div class="mb-4 rounded-xl border border-violet-200 bg-violet-50 p-4">
        <p class="flex items-center gap-2 font-medium text-violet-900">
            <x-heroicon-o-signal-slash class="size-5 inline" /> {{ __('kunjungan.offline_judul') }}
        </p>
        <p class="mt-1 text-sm text-violet-800">{{ __('kunjungan.offline_ket') }}</p>
        <p id="offline-stempel" class="mt-1 text-xs text-violet-700"></p>
    </div>

    <p id="offline-pesan" class="mb-3 hidden rounded-lg bg-gray-900 px-3 py-2 text-sm text-white"></p>

    {{-- ---------- Langkah 1: memilih toko ---------- --}}
    <div id="offline-pilih-toko">
        <div id="pemindai-qr-offline" class="mb-3 overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="relative aspect-[4/3] w-full overflow-hidden bg-slate-900">
                <video playsinline muted class="size-full object-cover"></video>
                <div class="pointer-events-none absolute inset-0 grid place-items-center">
                    <div class="size-48 rounded-lg border-2 border-white/70"></div>
                </div>
                <div class="absolute right-3 top-3 flex flex-col gap-2">
                    <button type="button" id="tombol-ganti-lensa-offline" title="{{ __('kunjungan.ganti_lensa') }}"
                            class="hidden rounded-full bg-white/20 px-3 py-2 text-white backdrop-blur"><x-heroicon-o-arrow-path class="size-4 inline" /></button>
                    <button type="button" id="tombol-senter-offline" title="{{ __('kunjungan.senter') }}"
                            class="hidden rounded-full bg-white/20 px-3 py-2 text-white backdrop-blur"><x-heroicon-o-light-bulb class="size-4 inline" /></button>
                </div>
            </div>
            <div class="space-y-2 p-3">
                <button type="button" id="tombol-mulai-pindai-offline"
                        class="w-full rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('kunjungan.nyalakan_kamera') }}
                </button>
                <p id="pesan-pindai-offline" class="hidden rounded-lg bg-red-50 p-2 text-xs text-red-800"></p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 p-3">
                <label class="block text-xs font-medium text-gray-600">{{ __('kunjungan.cari_toko_judul') }}</label>
                <input type="search" id="offline-cari" placeholder="{{ __('kunjungan.cari_toko_penugasan') }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div id="offline-hasil"></div>
        </div>
    </div>

    {{-- ---------- Langkah 2: memotret ---------- --}}
    <div id="offline-kerja" class="hidden">
        <div class="mb-3 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p id="offline-nama-toko" class="font-medium text-gray-900"></p>
                    <p id="offline-ket-toko" class="text-xs text-gray-500"></p>
                </div>
                <button type="button" id="tombol-offline-batal"
                        class="shrink-0 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
            </div>
        </div>

        <div class="mb-3 flex items-baseline justify-between">
            <p class="text-sm font-medium text-gray-900">{{ __('kunjungan.foto_wajib_judul') }}</p>
            <span id="offline-hitung-foto" class="text-xs tabular-nums text-gray-500"></span>
        </div>

        <div id="offline-foto-daftar" class="space-y-2"></div>

        <div class="mt-4 space-y-2">
            <button type="button" id="tombol-offline-selesai"
                    class="w-full rounded-lg bg-emerald-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('kunjungan.selesaikan_kunjungan') }}
            </button>
            <button type="button" id="tombol-offline-tutup"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('kunjungan.lapor_toko_tutup') }}
            </button>
        </div>

        <div id="offline-form-tutup" class="mt-3 hidden rounded-xl border border-gray-200 bg-white p-4">
            <label class="block text-sm font-medium text-gray-700">{{ __('kunjungan.alasan_tutup') }}</label>
            <input type="text" id="offline-catatan-tutup" placeholder="{{ __('kunjungan.alasan_tutup_contoh') }}"
                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            <button type="button" id="tombol-offline-kirim-tutup"
                    class="mt-2 w-full rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                {{ __('kunjungan.kirim_laporan') }}
            </button>
        </div>
    </div>

    {{-- ---------- Jendela kamera khusus offline ---------- --}}
    {{-- Sengaja terpisah dari jendela kamera alur daring, yang tampil-sembunyinya
         dikendalikan Alpine bersama state Livewire. Wadah sendiri berarti mode
         offline tidak bisa mengganggu alur daring, dan sebaliknya. --}}
    <div id="kamera-offline" class="fixed inset-0 z-50 hidden flex-col bg-black">
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <span class="text-sm font-medium">{{ __('kunjungan.ambil_foto') }}</span>
            <button type="button" id="tombol-offline-batal-kamera"
                    class="rounded p-2 text-white/70 hover:bg-white/10"><x-heroicon-o-x-mark class="size-4 inline" /></button>
        </div>

        <div class="relative flex-1 overflow-hidden">
            <video playsinline muted class="size-full object-contain"></video>
        </div>

        <div class="p-4">
            <button type="button" id="tombol-offline-jepret"
                    class="w-full rounded-lg bg-white px-3 py-3 text-sm font-semibold text-gray-900">
                {{ __('kunjungan.ambil_foto') }}
            </button>
        </div>
    </div>
</div>

{{-- ---------- Lencana antrean ---------- --}}
{{-- Di luar #offline-panel supaya tetap terlihat setelah sinyal kembali:
     justru saat itulah sales perlu tahu masih ada kunjungan yang menunggu
     terkirim. --}}
<div wire:ignore id="offline-antrean" class="mb-4 hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-amber-900">
            <x-heroicon-o-inbox-arrow-down class="size-4 inline" />
            <span id="offline-antrean-jumlah" class="font-semibold tabular-nums">0</span>
            {{ __('kunjungan.antrean_menunggu') }}
            <span id="offline-antrean-bermasalah" class="ml-1 hidden rounded bg-red-100 px-1.5 text-xs font-medium text-red-800"></span>
        </p>
        <button type="button" id="tombol-kirim-antrean"
                class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
            {{ __('kunjungan.kirim_sekarang') }}
        </button>
    </div>
    <ul id="offline-antrean-daftar" class="mt-2 divide-y divide-amber-200/70"></ul>
</div>
