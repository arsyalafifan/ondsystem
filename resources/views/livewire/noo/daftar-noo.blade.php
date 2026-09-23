<div x-data="{ kameraTerbuka: false, kameraSlot: null }">
    <x-judul-halaman :judul="__('noo.judul')" :keterangan="__('noo.ket')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('noo.noo_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($depotBelumDipilih)
        <x-butuh-depot-terkunci />
    @else
        <x-kartu>
            <div class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-4">
                <div class="min-w-56 flex-1">
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                    <input type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('noo.cari_placeholder') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.status') }}</label>
                    <select wire:model.live="filterStatus"
                            class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('noo.semua_status') }}</option>
                        @foreach ($this->statusCases as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('noo.kolom_kode') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('noo.atr_nama_toko') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('master.atr_wilayah') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('noo.atr_paket') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('noo.kolom_pengaju') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->noos as $noo)
                            <tr class="hover:bg-gray-50" wire:key="noo-{{ $noo->id }}">
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                    {{ $noo->kode }}
                                    <span class="block text-xs font-normal text-gray-500">{{ $noo->diajukan_at?->isoFormat('ll') }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-gray-900">{{ $noo->nama }}</span>
                                    <span class="block text-xs text-gray-500">{{ $noo->nama_pemilik }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $noo->wilayah?->nama }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $noo->paket?->nama }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $noo->pengaju?->name }}</td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $noo->status->badge() }}">
                                        {{ $noo->status->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button type="button" wire:click="$set('nooDilihat', {{ $noo->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.rincian') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-kosong ikon="sparkles" :judul="__('noo.kosong')" :keterangan="__('noo.ket_kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->noos->hasPages())
                <div class="border-t border-gray-100 p-4">{{ $this->noos->links() }}</div>
            @endif
        </x-kartu>
    @endif

    {{-- ============ Formulir pengajuan ============ --}}
    @if ($formTerbuka)
        <x-modal :judul="__('noo.judul_noo_baru')" tutup="tutupForm" lebar="max-w-3xl">
            <div class="space-y-5 p-5">
                @if ($this->paketTersedia->isEmpty())
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ __('noo.belum_ada_paket_tersedia') }}</p>
                @endif

                @include('livewire.noo.partials.form-data')

                {{-- --- Foto wajib --- --}}
                <div class="space-y-3 border-t border-gray-100 pt-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ __('noo.judul_foto_wajib') }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('noo.ket_foto_wajib') }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach (\App\Enums\JenisBuktiNoo::wajibSales() as $jenisBukti)
                            @php $gambar = $buktiFoto[$jenisBukti->value] ?? null; @endphp
                            <div class="rounded-lg border border-gray-200 p-2 text-center" wire:key="bukti-{{ $jenisBukti->value }}">
                                <p class="truncate text-xs font-medium text-gray-700" title="{{ $jenisBukti->petunjuk() }}">{{ $jenisBukti->label() }}</p>

                                @if ($gambar)
                                    <img src="{{ $gambar }}" alt="{{ $jenisBukti->label() }}" class="mx-auto mt-1.5 h-24 w-full rounded-md object-cover">
                                    <button type="button" wire:click="hapusBuktiFoto('{{ $jenisBukti->value }}')"
                                            class="mt-1.5 w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('noo.ambil_ulang') }}
                                    </button>
                                @else
                                    <button type="button"
                                            @click="kameraSlot = '{{ $jenisBukti->value }}'; kameraTerbuka = true; $nextTick(() => window._kameraNoo?.nyalakan())"
                                            class="mt-1.5 flex h-24 w-full flex-col items-center justify-center gap-1 rounded-md border border-dashed border-gray-300 text-gray-500 hover:bg-gray-50">
                                        <x-heroicon-o-camera class="size-5" />
                                        <span class="text-xs">{{ __('noo.ambil_foto') }}</span>
                                    </button>
                                    <label class="mt-1 block cursor-pointer text-center text-xs font-medium text-blue-600 hover:underline">
                                        <x-heroicon-o-arrow-up-tray class="size-3 inline" /> {{ __('kunjungan.unggah_foto') }}
                                        <input type="file" accept="image/*" class="hidden"
                                               onchange="window.unggahBuktiNoo('{{ $jenisBukti->value }}', this)">
                                    </label>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan"
                        @disabled(! $this->semuaBuktiLengkap || $this->paketTersedia->isEmpty())
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                    <span wire:loading.remove wire:target="simpan">{{ __('noo.tombol_ajukan') }}</span>
                    <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Rincian ============ --}}
    @if ($this->detail)
        @php $d = $this->detail; @endphp
        <x-modal :judul="__('noo.judul_detail', ['kode' => $d->kode])" tutup="$set('nooDilihat', null)" lebar="max-w-2xl">
            <div class="space-y-4 p-5 text-sm">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-gray-500">{{ __('umum.status') }}</p>
                        <span class="mt-0.5 inline-block rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $d->status->badge() }}">
                            {{ $d->status->label() }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('noo.atr_paket') }}</p>
                        <p class="font-medium text-gray-900">{{ $d->paket?->nama }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('noo.kolom_pengaju') }}</p>
                        <p class="font-medium text-gray-900">{{ $d->pengaju?->name }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-xs text-gray-500">{{ __('noo.atr_nama_toko') }}</p>
                    <p class="font-medium text-gray-900">{{ $d->nama }}</p>
                    <p class="text-gray-600">{{ $d->alamat }}</p>
                    <p class="text-xs text-gray-500">
                        {{ collect([$d->kelurahan, $d->kecamatan, $d->kota, $d->provinsi, $d->kode_pos])->filter()->implode(', ') }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-gray-500">{{ __('master.atr_nama_pemilik') }}</p>
                        <p class="text-gray-900">{{ $d->nama_pemilik }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('master.atr_telepon') }}</p>
                        <p class="text-gray-900">{{ $d->telepon }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('noo.atr_freezer_tipe') }}</p>
                        <p class="text-gray-900">{{ $d->freezer_tipe ?: '—' }}</p>
                    </div>
                </div>

                @if ($d->toko)
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-xs text-gray-500">{{ __('noo.toko_terbentuk') }}</p>
                        <p class="font-medium text-gray-900">
                            {{ $d->toko->kode }} · {{ $d->toko->nama }}
                            @if ($d->toko->asset_id)
                                <span class="text-gray-500">· {{ $d->toko->asset_id }}</span>
                            @endif
                        </p>
                    </div>
                @endif

                @if ($d->alasan_tolak)
                    <div class="rounded-lg bg-red-50 p-3">
                        <p class="text-xs text-red-700">{{ __('noo.alasan_tolak') }}</p>
                        <p class="text-red-900">{{ $d->alasan_tolak }}</p>
                    </div>
                @endif

                @if ($d->fotos->isNotEmpty())
                    <div>
                        <p class="mb-2 text-xs text-gray-500">{{ __('noo.judul_bukti') }}</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($d->fotos as $foto)
                                <div>
                                    <a href="{{ route('noo.foto', $foto) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('noo.foto', $foto) }}" alt="{{ $foto->jenis->label() }}"
                                             class="h-28 w-full rounded-lg border border-gray-200 object-cover transition hover:opacity-90">
                                    </a>
                                    <p class="mt-1 text-xs font-medium text-gray-700">{{ $foto->jenis->label() }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-modal>
    @endif

    {{-- ============ Jendela kamera (satu, dipakai bergantian oleh semua slot) ============ --}}
    <div wire:ignore id="kamera-noo" x-show="kameraTerbuka" x-cloak
         class="fixed inset-0 z-50 flex flex-col bg-black">
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <span class="text-sm font-medium" x-text="window._labelBuktiNoo?.[kameraSlot] ?? ''"></span>
            <button type="button" @click="kameraTerbuka = false; window._kameraNoo?.matikan()"
                    class="rounded p-2 text-white/70 hover:bg-white/10">
                <x-heroicon-o-x-mark class="size-4 inline" />
            </button>
        </div>

        <div class="relative flex-1 overflow-hidden">
            <video playsinline muted class="size-full object-contain"></video>

            <button type="button" id="tombol-ganti-lensa-noo" title="{{ __('hr.ganti_kamera') }}"
                    class="absolute right-3 top-3 rounded-full bg-white/20 px-3 py-2 text-white backdrop-blur">
                <x-heroicon-o-arrow-path class="size-4 inline" />
            </button>
        </div>

        <p id="pesan-kamera-noo" class="mx-4 mb-2 hidden rounded-lg bg-red-500/90 p-2 text-center text-xs text-white"></p>

        <div class="flex items-center justify-center gap-6 pb-8 pt-2">
            <button type="button"
                    @click="window._kameraNoo?.jepret(kameraSlot); kameraTerbuka = false"
                    class="grid size-20 place-items-center rounded-full border-4 border-white bg-white/20 text-white active:scale-95">
                <x-heroicon-o-camera class="size-6" />
            </button>
        </div>
    </div>

    {{-- Peta pemilih hanya ada ketika formulir terbuka, jadi pemasangannya
         ditunda sampai elemennya benar-benar muncul. Blok @script ini cuma
         dikirim SEKALI seumur komponen, jadi lat/lng diambil hidup dari
         $wire tiap pasang() — bukan dari nilai yang dibekukan saat mount.
         Komentar JS TIDAK ditaruh di baris pertama <script> dengan sengaja;
         lihat penjelasan di daftar-kunjungan.blade.php. --}}
    @script
    <script>
        let pemilih = null;

        const pasang = () => {
            const wadah = document.getElementById('peta-pemilih-noo');

            if (!wadah || pemilih) {
                return;
            }

            pemilih = window.pasangPemilihTitik('peta-pemilih-noo', {
                ...@js($this->konfigPeta),
                lat: $wire.latitude,
                lng: $wire.longitude,
            });
        };

        const lepas = () => { pemilih = null; };

        pasang();
        Livewire.hook('morphed', () => document.getElementById('peta-pemilih-noo') ? pasang() : lepas());

        window.addEventListener('titik-dipilih', (e) => {
            $wire.call('titikDipilih', e.detail.lat, e.detail.lng);
        });

        $wire.on('pindahkan-penanda', (payload) => {
            const d = payload.lat !== undefined ? payload : payload[0];
            pemilih?.pindah(d.lat, d.lng);
        });
    </script>
    @endscript

    {{-- Satu kamera dipakai bergantian oleh ketiga slot foto wajib sales,
         slotnya ditandai lewat `kameraSlot` (Alpine) dan diteruskan ke
         jepret()/terimaBuktiFoto() — sama seperti bukti pengiriman. --}}
    @script
    <script>
        window._labelBuktiNoo = @js(collect(\App\Enums\JenisBuktiNoo::wajibSales())
            ->mapWithKeys(fn ($j) => [$j->value => $j->label()]));

        const kameraNoo = window.pasangKamera('kamera-noo', {
            pesan: @js([
                'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
                'gagal' => __('kunjungan.kamera_gagal'),
                'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
                'belumSiap' => __('kunjungan.kamera_belum_siap'),
                'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
            ]),
        });

        window._kameraNoo = kameraNoo;

        document.getElementById('tombol-ganti-lensa-noo')
            ?.addEventListener('click', () => kameraNoo?.gantiLensa());

        const wadahNoo = document.getElementById('kamera-noo');

        wadahNoo?.addEventListener('kamera:jepretan', (e) => {
            $wire.terimaBuktiFoto(e.detail.jenis, e.detail.gambar);
        });

        wadahNoo?.addEventListener('kamera:galat', (e) => {
            const pesan = document.getElementById('pesan-kamera-noo');

            if (pesan) {
                pesan.textContent = e.detail ?? '';
                pesan.classList.toggle('hidden', !e.detail);
            }
        });

        const pesanUnggahan = @js([
            'bukanGambar' => __('kunjungan.galat_unggahan_bukan_gambar'),
            'kebesaran' => __('kunjungan.galat_unggahan_kebesaran'),
        ]);
        const ukuranMaksNoo = @js((int) config('visit.foto.ukuran_maks_kb')) * 1024;

        window.unggahBuktiNoo = (jenis, inputEl) => {
            const berkas = inputEl.files?.[0];
            inputEl.value = '';

            if (!berkas) {
                return;
            }

            if (!berkas.type.startsWith('image/')) {
                window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: pesanUnggahan.bukanGambar, jenis: 'error' } }));

                return;
            }

            if (berkas.size > ukuranMaksNoo) {
                window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: pesanUnggahan.kebesaran, jenis: 'error' } }));

                return;
            }

            const pembaca = new FileReader();
            pembaca.onload = () => $wire.terimaBuktiFoto(jenis, pembaca.result);
            pembaca.readAsDataURL(berkas);
        };
    </script>
    @endscript
</div>
