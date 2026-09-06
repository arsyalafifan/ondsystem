<div>
    <x-judul-halaman :judul="__('master.judul_promo')" :keterangan="__('master.ket_promo')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('master.promo_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('master.nama_promo') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('master.periode') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('master.minimal_dus') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('master.bonus_dus') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('master.produk_berhak') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('nav.pesanan') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->promos as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $p->nama }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                {{ $p->tanggal_mulai->isoFormat('ll') }} – {{ $p->tanggal_selesai->isoFormat('ll') }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">@angka($p->minimal_dus) {{ __('umum.satuan_dus') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-emerald-700">@angka($p->bonus_dus) {{ __('umum.satuan_dus') }}</td>
                            <td class="max-w-xs truncate px-4 py-2 text-gray-600">{{ $p->produks->pluck('nama')->join(', ') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $p->pesanans_count }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $p->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiHapus', {{ $p->id }})"
                                            @disabled($p->pesanans_count > 0)
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400">
                                        {{ __('umum.hapus') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="gift" :judul="__('master.promo_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$promoId ? __('master.judul_promo_sunting') : __('master.judul_promo_baru')" tutup="tutupForm" lebar="max-w-2xl">
            <div class="space-y-3 p-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('master.nama_promo') }}</label>
                    <input type="text" wire:model="nama"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.atr_tanggal_mulai') }}</label>
                        <input type="date" wire:model="tanggalMulai"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('tanggalMulai') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.atr_tanggal_selesai') }}</label>
                        <input type="date" wire:model="tanggalSelesai"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('tanggalSelesai') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.minimal_dus') }}</label>
                        <input type="number" min="1" wire:model="minimalDus"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('minimalDus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.bonus_dus') }}</label>
                        <input type="number" min="1" wire:model="bonusDus"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('bonusDus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.produk_berhak') }}</label>
                        <div class="flex gap-2">
                            <button type="button" wire:click="pilihSemuaProduk" class="text-xs font-medium text-blue-600 hover:text-blue-800">
                                {{ __('master.pilih_semua') }}
                            </button>
                            <button type="button" wire:click="kosongkanProduk" class="text-xs font-medium text-gray-500 hover:text-gray-700">
                                {{ __('master.kosongkan') }}
                            </button>
                        </div>
                    </div>
                    <div class="mt-1 grid max-h-56 grid-cols-2 gap-1 overflow-y-auto rounded-lg border border-gray-300 bg-gray-50 p-3">
                        @foreach ($this->produkAktif as $produk)
                            <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-white">
                                <input type="checkbox" wire:model="produkTerpilih" value="{{ $produk->id }}"
                                       class="rounded text-blue-600 border-gray-400 focus:ring-blue-500/20">
                                {{ $produk->nama }}
                            </label>
                        @endforeach
                    </div>
                    @error('produkTerpilih') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($konfirmasiHapus)
        <x-modal :judul="__('master.judul_hapus_promo')" tutup="$set('konfirmasiHapus', null)">
            <p class="p-5 text-sm text-gray-600">{{ __('master.ket_hapus_promo') }}</p>
            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="hapus({{ $konfirmasiHapus }})"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('umum.hapus') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
