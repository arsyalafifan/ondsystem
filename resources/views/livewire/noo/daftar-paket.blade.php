<div>
    <x-judul-halaman :judul="__('noo.judul_paket')" :keterangan="__('noo.ket_paket')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('noo.paket_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('noo.atr_nama_paket') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('noo.atr_dus_reguler') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('noo.atr_dus_bonus') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('noo.isi_paket') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->pakets as $paket)
                        <tr class="hover:bg-gray-50" wire:key="paket-{{ $paket->id }}">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $paket->nama }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">@angka($paket->dus_reguler) {{ __('umum.satuan_dus') }}</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums text-emerald-700">
                                @angka($paket->dus_bonus) {{ __('umum.satuan_dus') }}
                            </td>
                            <td class="max-w-xs px-4 py-2 text-gray-600">
                                @if ($paket->items->isEmpty())
                                    <span class="text-gray-400">{{ __('noo.isi_paket_kosong') }}</span>
                                @else
                                    <span class="line-clamp-2">
                                        {{ $paket->items->map(fn ($i) => $i->produk->nama.' '.$i->jumlah_dus.($i->is_bonus ? ' ('.__('noo.bonus').')' : ''))->join(', ') }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <div class="flex flex-wrap items-center gap-1">
                                    @if ($paket->aktif)
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800">{{ __('umum.aktif') }}</span>
                                    @else
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                                    @endif

                                    @unless ($paket->lengkap)
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                                              title="{{ __('noo.ket_belum_lengkap') }}">
                                            {{ __('noo.belum_lengkap') }}
                                        </span>
                                    @endunless
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $paket->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiHapus', {{ $paket->id }})"
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                        {{ __('umum.hapus') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-kosong ikon="squares-2x2" :judul="__('noo.paket_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$paketId ? __('noo.judul_paket_sunting') : __('noo.judul_paket_baru')" tutup="tutupForm" lebar="max-w-2xl">
            <div class="space-y-4 p-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('noo.atr_nama_paket') }}</label>
                    <input type="text" wire:model="nama" placeholder="{{ __('noo.nama_paket_contoh') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('noo.atr_dus_reguler') }}</label>
                        <input type="number" min="1" wire:model.live="dusReguler"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('dusReguler') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('noo.atr_dus_bonus') }}</label>
                        <input type="number" min="0" wire:model.live="dusBonus"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('dusBonus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="aktif"
                           class="size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500/20">
                    {{ __('noo.atr_paket_aktif') }}
                </label>

                {{-- ============ Isi paket: dus reguler ============ --}}
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-700">{{ __('noo.judul_item_reguler') }}</p>
                        <span @class([
                            'rounded px-2 py-0.5 text-xs font-semibold tabular-nums',
                            'bg-emerald-100 text-emerald-800' => $this->terpilihReguler === (int) $dusReguler,
                            'bg-amber-100 text-amber-800' => $this->terpilihReguler !== (int) $dusReguler,
                        ])>
                            {{ __('noo.hitungan_dus', ['terpilih' => $this->terpilihReguler, 'target' => (int) $dusReguler]) }}
                        </span>
                    </div>

                    <div class="mt-2 space-y-2">
                        @foreach ($baris as $i => $b)
                            <div class="flex gap-2" wire:key="baris-{{ $i }}">
                                <select wire:model="baris.{{ $i }}.produk_id"
                                        class="block w-full rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    <option value="">{{ __('noo.pilih_produk') }}</option>
                                    @foreach ($this->produkAktif as $produk)
                                        <option value="{{ $produk->id }}">{{ $produk->nama }}</option>
                                    @endforeach
                                </select>
                                <input type="number" min="1" wire:model.live="baris.{{ $i }}.jumlah_dus"
                                       placeholder="{{ __('umum.satuan_dus') }}"
                                       class="block w-24 shrink-0 rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <button type="button" wire:click="hapusBaris({{ $i }})"
                                        class="shrink-0 rounded-lg border border-gray-300 bg-white px-2 text-gray-500 hover:bg-gray-50">
                                    <x-heroicon-o-trash class="size-4" />
                                </button>
                            </div>
                            @error('baris.'.$i.'.produk_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('baris.'.$i.'.jumlah_dus') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @endforeach
                    </div>

                    <button type="button" wire:click="tambahBaris" class="mt-2 text-xs font-medium text-blue-600 hover:text-blue-800">
                        + {{ __('noo.tambah_produk') }}
                    </button>

                    @error('baris') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- ============ Isi paket: dus bonus ============ --}}
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-700">{{ __('noo.judul_item_bonus') }}</p>
                        <span @class([
                            'rounded px-2 py-0.5 text-xs font-semibold tabular-nums',
                            'bg-emerald-100 text-emerald-800' => $this->terpilihBonus === (int) $dusBonus,
                            'bg-amber-100 text-amber-800' => $this->terpilihBonus !== (int) $dusBonus,
                        ])>
                            {{ __('noo.hitungan_dus', ['terpilih' => $this->terpilihBonus, 'target' => (int) $dusBonus]) }}
                        </span>
                    </div>

                    <div class="mt-2 space-y-2">
                        @foreach ($barisBonus as $i => $b)
                            <div class="flex gap-2" wire:key="baris-bonus-{{ $i }}">
                                <select wire:model="barisBonus.{{ $i }}.produk_id"
                                        class="block w-full rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    <option value="">{{ __('noo.pilih_produk') }}</option>
                                    @foreach ($this->produkAktif as $produk)
                                        <option value="{{ $produk->id }}">{{ $produk->nama }}</option>
                                    @endforeach
                                </select>
                                <input type="number" min="1" wire:model.live="barisBonus.{{ $i }}.jumlah_dus"
                                       placeholder="{{ __('umum.satuan_dus') }}"
                                       class="block w-24 shrink-0 rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <button type="button" wire:click="hapusBarisBonus({{ $i }})"
                                        class="shrink-0 rounded-lg border border-gray-300 bg-white px-2 text-gray-500 hover:bg-gray-50">
                                    <x-heroicon-o-trash class="size-4" />
                                </button>
                            </div>
                            @error('barisBonus.'.$i.'.produk_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('barisBonus.'.$i.'.jumlah_dus') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @endforeach
                    </div>

                    <button type="button" wire:click="tambahBarisBonus" class="mt-2 text-xs font-medium text-blue-600 hover:text-blue-800">
                        + {{ __('noo.tambah_produk') }}
                    </button>

                    @error('barisBonus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan" wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="simpan">{{ __('umum.simpan') }}</span>
                    <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($konfirmasiHapus)
        <x-modal :judul="__('noo.judul_hapus_paket')" tutup="$set('konfirmasiHapus', null)">
            <p class="p-5 text-sm text-gray-600">{{ __('noo.ket_hapus_paket') }}</p>
            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="hapus({{ $konfirmasiHapus }})"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('umum.hapus') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
