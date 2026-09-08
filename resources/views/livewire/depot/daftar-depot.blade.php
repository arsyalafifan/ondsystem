<div>
    <x-judul-halaman :judul="__('depot.judul')" :keterangan="__('depot.ket')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('depot.depot_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.nama') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.koordinat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('depot.atr_jam_berangkat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('depot.kapasitas') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->depots as $d)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-mono text-xs text-gray-600">{{ $d->kode }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $d->nama }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                @if ($d->lat !== null && $d->lng !== null)
                                    {{ number_format($d->lat, 5) }}, {{ number_format($d->lng, 5) }}
                                @else
                                    <span class="text-amber-700">{{ __('depot.belum_ada_koordinat') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ mb_substr($d->jam_berangkat, 0, 5) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                {{ __('depot.ringkasan_kapasitas', ['toko' => $d->max_toko, 'dus' => $d->max_dus]) }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($d->aktif)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('umum.aktif') }}</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <button type="button" wire:click="sunting({{ $d->id }})"
                                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('umum.sunting') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="building-storefront" :judul="__('depot.depot_kosong')" :keterangan="__('depot.depot_kosong_ket')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$depotId ? __('depot.judul_depot_sunting') : __('depot.judul_depot_baru')" tutup="tutupForm">
            <div class="space-y-3 p-5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('depot.atr_kode') }}</label>
                        <input type="text" wire:model="kode"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('kode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('depot.atr_nama') }}</label>
                        <input type="text" wire:model="nama"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('umum.latitude') }}</label>
                        <input type="text" wire:model="lat" placeholder="-2.500000"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('lat') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('umum.longitude') }}</label>
                        <input type="text" wire:model="lng" placeholder="118.000000"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('lng') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-xs text-gray-500">{{ __('depot.ket_koordinat') }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('depot.atr_jam_berangkat') }}</label>
                        <input type="time" wire:model="jamBerangkat"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('jamBerangkat') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('depot.atr_service_minutes') }}</label>
                        <input type="number" min="1" wire:model="serviceMinutes"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('serviceMinutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('depot.atr_max_toko') }}</label>
                        <input type="number" min="1" wire:model="maxToko"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('maxToko') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('depot.atr_max_dus') }}</label>
                        <input type="number" min="1" wire:model="maxDus"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('maxDus') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('depot.atr_min_dus_per_toko') }}</label>
                        <input type="number" min="1" wire:model="minDusPerToko"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('minDusPerToko') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="aktif" class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    {{ __('depot.depot_aktif') }}
                </label>

                @if (! $depotId)
                    <p class="text-xs text-gray-500">{{ __('depot.ket_depot_baru') }}</p>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
