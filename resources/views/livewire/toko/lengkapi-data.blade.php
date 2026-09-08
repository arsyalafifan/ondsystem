<div>
    <x-judul-halaman :judul="__('toko.judul')" :keterangan="__('toko.ket')" />

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-kartu :judul="__('toko.langkah_toko')">
                <div class="p-4">
                    @if ($this->toko)
                        @php $t = $this->toko; @endphp
                        <div class="flex items-start justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $t->nama }}</p>
                                <p class="text-sm text-gray-600">{{ $t->kode }} · {{ $t->wilayah?->nama ?? __('pesanan.toko_tanpa_wilayah') }}</p>
                                <p class="mt-1 text-sm text-gray-500">
                                    @if ($t->punya_koordinat)
                                        <x-heroicon-o-map-pin class="size-4 inline mr-1" />{{ __('toko.koordinat_terkunci') }}
                                    @else
                                        <span class="text-amber-700"><x-heroicon-o-exclamation-triangle class="size-4 inline mr-1" />{{ __('pesanan.toko_tanpa_koordinat') }}</span>
                                    @endif
                                </p>
                            </div>
                            <button type="button" wire:click="batalPilihToko"
                                    class="shrink-0 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                {{ __('umum.ganti') }}
                            </button>
                        </div>
                    @else
                        <input type="search" wire:model.live.debounce.300ms="cari"
                               placeholder="{{ __('toko.cari_toko') }}"
                               class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">

                        @if (mb_strlen(trim($cari)) >= 2)
                            <div class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200">
                                @forelse ($this->hasilCari as $toko)
                                    <button type="button" wire:click="pilihToko({{ $toko->id }})"
                                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-gray-50">
                                        <span class="min-w-0">
                                            <span class="block font-medium text-gray-900">{{ $toko->nama }}</span>
                                            <span class="block truncate text-xs text-gray-500">
                                                {{ $toko->kode }} · {{ $toko->wilayah?->nama ?? __('pesanan.toko_tanpa_wilayah') }}
                                            </span>
                                        </span>
                                        @if ($toko->profil_lengkap)
                                            <span class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">
                                                {{ __('toko.data_lengkap') }}
                                            </span>
                                        @else
                                            <span class="shrink-0 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                                                {{ __('toko.data_belum_lengkap') }}
                                            </span>
                                        @endif
                                    </button>
                                @empty
                                    <p class="px-3 py-4 text-center text-sm text-gray-500">{{ __('pesanan.tidak_ada_toko') }}</p>
                                @endforelse
                            </div>
                        @endif
                    @endif

                    @error('tokoId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </x-kartu>

            @if ($this->toko)
                <x-kartu :judul="__('toko.langkah_data')">
                    <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">{{ __('master.atr_nama_pemilik') }}</label>
                            <input type="text" wire:model="namaPemilik"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('namaPemilik') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('master.nik_pemilik') }}</label>
                            <input type="text" inputmode="numeric" maxlength="16" wire:model="nikPemilik"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('nikPemilik') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('master.asset_id') }}</label>
                            <input type="text" wire:model="assetId"
                                   placeholder="{{ __('master.asset_id_ket') }}"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('assetId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">{{ __('umum.alamat') }}</label>
                            <textarea wire:model="alamat" rows="2"
                                      class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                            @error('alamat') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('umum.telepon') }}</label>
                            <input type="text" wire:model="telepon"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('telepon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('master.kecamatan') }}</label>
                            <input type="text" wire:model="kecamatan"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('kecamatan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('master.kota') }}</label>
                            <input type="text" wire:model="kota"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('kota') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('master.provinsi') }}</label>
                            <input type="text" wire:model="provinsi"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('provinsi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-200 p-4">
                        <button type="button" wire:click="simpan" wire:loading.attr="disabled"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="simpan">{{ __('umum.simpan') }}</span>
                            <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                        </button>
                    </div>
                </x-kartu>
            @endif
        </div>

        <div class="lg:col-span-1">
            <x-kartu :judul="__('toko.ket_judul')">
                <div class="space-y-2 p-4 text-sm text-gray-600">
                    {{-- <p>{{ __('toko.ket_panel_1') }}</p> --}}
                    <p>{{ __('toko.ket_panel_2') }}</p>
                </div>
            </x-kartu>
        </div>
    </div>
</div>
