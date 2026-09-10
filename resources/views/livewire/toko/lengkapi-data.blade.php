<div>
    <x-judul-halaman :judul="__('toko.judul')" :keterangan="__('toko.ket')" />

    {{-- Dua cara pakai layar ini: melengkapi data satu toko, atau memantau
         progres seluruh sales — sengaja satu menu/rute, bukan dua, supaya
         admin tidak perlu berpindah layar. --}}
    <div class="mb-4 inline-flex rounded-lg border border-gray-300 bg-gray-50 p-0.5">
        @foreach ([['lengkapi', 'pencil-square', __('toko.tab_lengkapi')], ['progres', 'chart-bar', __('toko.tab_progres')]] as [$namaTab, $ikon, $label])
            <button type="button" wire:click="gantiTab('{{ $namaTab }}')"
                    @class([
                        'rounded-md px-3 py-2 text-sm font-medium transition-all flex items-center gap-2',
                        'bg-white text-blue-600 shadow-sm ring-1 ring-gray-200' => $tab === $namaTab,
                        'text-gray-500 hover:text-gray-900' => $tab !== $namaTab,
                    ])>
                @svg('heroicon-o-'.$ikon, ['class' => 'size-4'])
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'progres')
        <x-kartu :judul="__('toko.judul_progres')">
            {{-- <p class="border-b border-gray-100 px-4 py-3 text-sm text-gray-600">{{ __('toko.ket_progres') }}</p> --}}
            <div class="divide-y divide-gray-100">
                @forelse ($this->progres as $baris)
                    <div class="flex items-center gap-4 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900">{{ $baris['sales']->name }}</p>
                            <p class="text-xs text-gray-500">
                                @if ($baris['total'] === 0)
                                    {{ __('toko.progres_belum_ada_tanggungan') }}
                                @else
                                    {{ __('toko.progres_ringkasan', ['lengkap' => $baris['lengkap'], 'total' => $baris['total']]) }}
                                @endif
                            </p>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-200">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $baris['persen'] }}%"></div>
                            </div>
                        </div>
                        <span class="shrink-0 text-lg font-semibold tabular-nums text-gray-900">{{ $baris['persen'] }}%</span>
                    </div>
                @empty
                    <x-kosong ikon="user-group" :judul="__('toko.progres_kosong')" />
                @endforelse
            </div>
        </x-kartu>

        @if (auth()->user()->isSales() && $this->tokoSaya->isNotEmpty())
            <h2 class="mb-3 mt-8 text-sm font-semibold text-gray-900">{{ __('toko.daftar_toko_saya') }}</h2>
            <x-kartu>
                <ul class="divide-y divide-gray-100">
                    @foreach ($this->tokoSaya as $toko)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-gray-900">{{ $toko->nama }}</span>
                                <span class="text-xs text-gray-500">{{ $toko->kode }}</span>
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
                        </li>
                    @endforeach
                </ul>
            </x-kartu>
        @endif
    @else
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
    @endif
</div>
