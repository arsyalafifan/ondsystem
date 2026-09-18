<div x-data="{ kameraTerbuka: false, kameraSlot: null }">
    <x-judul-halaman :judul="__('kendaraan.judul_cek')" :keterangan="$kendaraan->nama">
        @unless ($this->melihatSebagaiAdmin)
            <x-slot:aksi>
                @if ($kendaraan->catatanBerangkat)
                    <a href="{{ route('driver.kunjungan', $kendaraan) }}" wire:navigate
                       class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        {{ __('kendaraan.ke_pengiriman') }}
                    </a>
                @endif
            </x-slot:aksi>
        @endunless
    </x-judul-halaman>

    @if ($this->melihatSebagaiAdmin)
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
            <x-heroicon-o-eye class="size-5 shrink-0" />
            <p>{{ __('pengiriman.mode_lihat_admin') }}</p>
        </div>
    @endif

    @if (! $this->melihatSebagaiAdmin && $kendaraan->catatanBerangkat === null && ! $kendaraan->bebas_cek_bbm)
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
            <x-heroicon-o-exclamation-circle class="size-5 shrink-0" />
            <p>{{ __('kendaraan.wajib_berangkat_dulu') }}</p>
        </div>
    @elseif (! $this->melihatSebagaiAdmin && $kendaraan->catatanBerangkat === null && $kendaraan->bebas_cek_bbm)
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
            <x-heroicon-o-information-circle class="size-5 shrink-0" />
            <p>{{ __('kendaraan.ket_bebas_cek_bbm') }}</p>
        </div>
    @endif

    {{-- ============ Berangkat & Kembali ============ --}}
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([['jenis' => 'berangkat', 'catatan' => $kendaraan->catatanBerangkat, 'ikon' => 'arrow-up-circle'], ['jenis' => 'kembali', 'catatan' => $kendaraan->catatanKembali, 'ikon' => 'arrow-down-circle']] as $kartu)
            @php $c = $kartu['catatan']; @endphp
            <div @class([
                'rounded-xl border bg-white p-4',
                'border-amber-300 ring-1 ring-amber-200' => $kartu['jenis'] === 'kembali' && $c === null && $kendaraan->status === 'selesai',
                'border-gray-200' => ! ($kartu['jenis'] === 'kembali' && $c === null && $kendaraan->status === 'selesai'),
            ])>
                <div class="flex items-center justify-between gap-2">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        @svg('heroicon-o-'.$kartu['ikon'], 'size-5 text-gray-500')
                        {{ __('kendaraan.jenis_'.$kartu['jenis']) }}
                    </p>
                    @if ($c)
                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('kendaraan.tercatat') }}</span>
                    @elseif ($kartu['jenis'] === 'kembali' && $kendaraan->status === 'selesai')
                        <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('kendaraan.belum_tercatat') }}</span>
                    @endif
                </div>

                @if ($c)
                    <div class="mt-3 flex items-center gap-3">
                        <img src="{{ $c->url_foto }}" alt="{{ __('kendaraan.jenis_'.$kartu['jenis']) }}"
                             class="size-16 shrink-0 rounded-lg border border-gray-200 object-cover">
                        <div class="min-w-0 text-sm">
                            <p class="font-medium tabular-nums text-gray-900">{{ __('kendaraan.ket_km', ['km' => \App\Support\Bahasa::angka($c->km)]) }}</p>
                            <p class="text-gray-600">{{ __('kendaraan.ket_bbm', ['level' => $c->level_bbm->label()]) }}</p>
                            <p class="text-xs text-gray-500">{{ $c->created_at->format('d/m/Y H:i') }} · {{ $c->dicatatOleh?->name }}</p>
                        </div>
                    </div>
                @elseif (! $this->melihatSebagaiAdmin && ($kartu['jenis'] !== 'kembali' || $kendaraan->status === 'selesai'))
                    <button type="button" wire:click="bukaModal('{{ $kartu['jenis'] }}')"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        <x-heroicon-o-camera class="size-4" /> {{ __('kendaraan.tombol_ambil_foto') }}
                    </button>
                @elseif (! $this->melihatSebagaiAdmin)
                    {{-- Kembali, tapi kunjungan belum seluruhnya tuntas — lihat CatatanBbmService::kembali(). --}}
                    <button type="button" disabled
                            class="mt-3 flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg bg-gray-100 px-3 py-2.5 text-sm font-semibold text-gray-400">
                        <x-heroicon-o-camera class="size-4" /> {{ __('kendaraan.tombol_ambil_foto') }}
                    </button>
                    <p class="mt-1.5 text-center text-xs text-gray-500">{{ __('kendaraan.ket_kembali_belum_bisa') }}</p>
                @else
                    <p class="mt-3 text-sm text-gray-500">{{ __('kendaraan.belum_tercatat') }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ============ Pengisian bahan bakar (opsional) ============ --}}
    <div class="mt-4 rounded-xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-4">
            <div>
                <p class="text-sm font-semibold text-gray-900">{{ __('kendaraan.jenis_pengisian') }}</p>
                <p class="text-xs text-gray-500">{{ __('kendaraan.ket_pengisian') }}</p>
            </div>
            @unless ($this->melihatSebagaiAdmin)
                <button type="button" wire:click="bukaModal('pengisian')"
                        class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    <x-heroicon-o-plus class="size-4 inline" /> {{ __('kendaraan.tombol_catat_pengisian') }}
                </button>
            @endunless
        </div>

        @if ($this->pengisians->isEmpty())
            <x-kosong ikon="fire" :judul="__('kendaraan.pengisian_kosong')" />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($this->pengisians as $p)
                    <li class="flex flex-wrap items-center gap-3 p-4">
                        <img src="{{ $p->url_foto }}" alt="{{ __('kendaraan.label_struk') }}"
                             class="size-14 shrink-0 rounded-lg border border-gray-200 object-cover">
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-medium text-gray-900">
                                @if ($p->liter !== null) @angka($p->liter, 2) L @endif
                                @if ($p->biaya !== null) · Rp @angka($p->biaya, 0) @endif
                                @if ($p->liter === null && $p->biaya === null) {{ __('kendaraan.label_struk') }} @endif
                            </p>
                            @if ($p->catatan)
                                <p class="text-gray-600">{{ $p->catatan }}</p>
                            @endif
                            <p class="text-xs text-gray-500">{{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->dicatatOleh?->name }}</p>
                        </div>
                        @if ($p->foto_sebelum || $p->foto_sesudah)
                            <div class="flex shrink-0 gap-1.5">
                                @if ($p->foto_sebelum)
                                    <a href="{{ $p->url_foto_sebelum }}" target="_blank" rel="noopener" title="{{ __('kendaraan.label_sebelum_isi') }}">
                                        <img src="{{ $p->url_foto_sebelum }}" class="size-10 rounded border border-gray-200 object-cover">
                                    </a>
                                @endif
                                @if ($p->foto_sesudah)
                                    <a href="{{ $p->url_foto_sesudah }}" target="_blank" rel="noopener" title="{{ __('kendaraan.label_sesudah_isi') }}">
                                        <img src="{{ $p->url_foto_sesudah }}" class="size-10 rounded border border-gray-200 object-cover">
                                    </a>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- ============ Modal: berangkat / kembali ============ --}}
    @if ($modal === 'berangkat' || $modal === 'kembali')
        <x-modal :judul="__('kendaraan.jenis_'.$modal)" tutup="tutupModal">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('kendaraan.ket_wajib_foto') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('kendaraan.atr_km') }}</label>
                    <input type="number" min="0" max="9999999" inputmode="numeric" wire:model="km"
                           placeholder="{{ __('kendaraan.km_contoh') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('km') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('kendaraan.atr_level_bbm') }}</label>
                    <div class="mt-1 grid grid-cols-5 gap-1.5">
                        @foreach ($this->levelBbmOptions as $opt)
                            <button type="button" wire:click="$set('levelBbm', '{{ $opt->value }}')"
                                    @class([
                                        'rounded-lg border px-2 py-2 text-sm font-semibold transition',
                                        'border-blue-600 bg-blue-600 text-white' => $levelBbm === $opt->value,
                                        'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $levelBbm !== $opt->value,
                                    ])>
                                {{ $opt->label() }}
                            </button>
                        @endforeach
                    </div>
                    @error('levelBbm') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('kendaraan.atr_foto_speedometer') }}</label>

                    @if ($fotoBbm)
                        <div class="relative mt-1 inline-block">
                            <img src="{{ $fotoBbm }}" alt="{{ __('kendaraan.atr_foto_speedometer') }}" class="max-h-56 rounded-lg border border-gray-200">
                            <button type="button" wire:click="hapusFoto('bbm')"
                                    class="absolute right-1.5 top-1.5 rounded-full bg-black/60 p-1.5 text-white hover:bg-black/80">
                                <x-heroicon-o-arrow-path class="size-4" />
                            </button>
                        </div>
                    @else
                        <button type="button"
                                @click="kameraSlot = 'bbm'; kameraTerbuka = true; $nextTick(() => window._kameraKendaraan?.nyalakan())"
                                class="mt-1 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-6 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            <x-heroicon-o-camera class="size-5" /> {{ __('kendaraan.tombol_ambil_foto') }}
                        </button>
                    @endif
                    @error('fotoBbm') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupModal"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan{{ ucfirst($modal) }}" wire:loading.attr="disabled" wire:target="simpan{{ ucfirst($modal) }}"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="simpan{{ ucfirst($modal) }}">{{ __('umum.simpan') }}</span>
                    <span wire:loading wire:target="simpan{{ ucfirst($modal) }}">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Modal: pengisian bahan bakar ============ --}}
    @if ($modal === 'pengisian')
        <x-modal :judul="__('kendaraan.jenis_pengisian')" lebar="max-w-xl" tutup="tutupModal">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('kendaraan.ket_pengisian_modal') }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('kendaraan.atr_liter') }}</label>
                        <input type="number" min="0" step="0.01" inputmode="decimal" wire:model="liter"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('liter') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('kendaraan.atr_biaya') }}</label>
                        <input type="number" min="0" step="1" inputmode="numeric" wire:model="biaya"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('biaya') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.catatan_opsional') }}</label>
                    <textarea wire:model="catatanPengisian" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>

                @foreach ([['slot' => 'struk', 'prop' => 'fotoStruk', 'label' => __('kendaraan.label_struk'), 'wajib' => true], ['slot' => 'sebelum', 'prop' => 'fotoSebelum', 'label' => __('kendaraan.label_sebelum_isi'), 'wajib' => false], ['slot' => 'sesudah', 'prop' => 'fotoSesudah', 'label' => __('kendaraan.label_sesudah_isi'), 'wajib' => false]] as $s)
                    @php $nilai = $s['prop'] === 'fotoStruk' ? $fotoStruk : ($s['prop'] === 'fotoSebelum' ? $fotoSebelum : $fotoSesudah); @endphp
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $s['label'] }} @if (! $s['wajib']) <span class="font-normal text-gray-400">({{ __('umum.opsional') }})</span> @endif
                        </label>

                        @if ($nilai)
                            <div class="relative mt-1 inline-block">
                                <img src="{{ $nilai }}" alt="{{ $s['label'] }}" class="max-h-40 rounded-lg border border-gray-200">
                                <button type="button" wire:click="hapusFoto('{{ $s['slot'] }}')"
                                        class="absolute right-1.5 top-1.5 rounded-full bg-black/60 p-1.5 text-white hover:bg-black/80">
                                    <x-heroicon-o-arrow-path class="size-4" />
                                </button>
                            </div>
                        @else
                            <button type="button"
                                    @click="kameraSlot = '{{ $s['slot'] }}'; kameraTerbuka = true; $nextTick(() => window._kameraKendaraan?.nyalakan())"
                                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                <x-heroicon-o-camera class="size-4" /> {{ __('kendaraan.tombol_ambil_foto') }}
                            </button>
                        @endif
                    </div>
                @endforeach
                @error('fotoStruk') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupModal"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpanPengisian" wire:loading.attr="disabled" wire:target="simpanPengisian"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="simpanPengisian">{{ __('umum.simpan') }}</span>
                    <span wire:loading wire:target="simpanPengisian">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Jendela kamera (satu, dipakai bergantian oleh semua slot) ============ --}}
    <div wire:ignore id="kamera-kendaraan" x-show="kameraTerbuka" x-cloak
         class="fixed inset-0 z-50 flex flex-col bg-black">
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <span class="text-sm font-medium" x-text="window._labelSlotKendaraan?.[kameraSlot] ?? ''"></span>
            <button type="button" @click="kameraTerbuka = false; window._kameraKendaraan?.matikan()"
                    class="rounded p-2 text-white/70 hover:bg-white/10">
                <x-heroicon-o-x-mark class="size-4 inline" />
            </button>
        </div>

        <div class="relative flex-1 overflow-hidden">
            <video playsinline muted class="size-full object-contain"></video>

            <button type="button" id="tombol-ganti-lensa-kendaraan" title="{{ __('hr.ganti_kamera') }}"
                    class="absolute right-3 top-3 rounded-full bg-white/20 px-3 py-2 text-white backdrop-blur">
                <x-heroicon-o-arrow-path class="size-4 inline" />
            </button>
        </div>

        <p id="pesan-kamera-kendaraan" class="mx-4 mb-2 hidden rounded-lg bg-red-500/90 p-2 text-center text-xs text-white"></p>

        <div class="flex items-center justify-center gap-6 pb-8 pt-2">
            <button type="button"
                    @click="window._kameraKendaraan?.jepret(kameraSlot); kameraTerbuka = false"
                    class="grid size-20 place-items-center rounded-full border-4 border-white bg-white/20 text-white active:scale-95">
                <x-heroicon-o-camera class="size-6" />
            </button>
        </div>
    </div>

    @script
    <script>
        window._labelSlotKendaraan = @js([
            'bbm' => __('kendaraan.atr_foto_speedometer'),
            'struk' => __('kendaraan.label_struk'),
            'sebelum' => __('kendaraan.label_sebelum_isi'),
            'sesudah' => __('kendaraan.label_sesudah_isi'),
        ]);

        const kamera = window.pasangKamera('kamera-kendaraan', {
            pesan: @js([
                'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
                'gagal' => __('kunjungan.kamera_gagal'),
                'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
                'belumSiap' => __('kunjungan.kamera_belum_siap'),
                'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
            ]),
        });

        window._kameraKendaraan = kamera;

        document.getElementById('tombol-ganti-lensa-kendaraan')
            ?.addEventListener('click', () => kamera?.gantiLensa());

        const wadah = document.getElementById('kamera-kendaraan');

        wadah?.addEventListener('kamera:jepretan', (e) => {
            $wire.terimaFoto(e.detail.jenis, e.detail.gambar);
        });

        wadah?.addEventListener('kamera:galat', (e) => {
            const pesan = document.getElementById('pesan-kamera-kendaraan');

            if (pesan) {
                pesan.textContent = e.detail ?? '';
                pesan.classList.toggle('hidden', !e.detail);
            }
        });
    </script>
    @endscript
</div>
