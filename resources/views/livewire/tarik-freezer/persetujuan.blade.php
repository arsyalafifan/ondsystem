<div>
    <x-judul-halaman :judul="__('tarik_freezer.judul_persetujuan')" :keterangan="__('tarik_freezer.ket_persetujuan')" />

    @if ($depotBelumDipilih)
        <x-butuh-depot-terkunci />
    @else
        <x-kartu>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('tarik_freezer.kolom_kode') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('umum.toko') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('master.atr_wilayah') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('master.atr_asset_id') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('tarik_freezer.kolom_pengaju') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->antrean as $t)
                            <tr class="hover:bg-gray-50" wire:key="antre-{{ $t->id }}">
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                    {{ $t->kode }}
                                    <span class="block text-xs font-normal text-gray-500">{{ $t->diajukan_at?->isoFormat('ll') }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-gray-900">{{ $t->toko->nama }}</span>
                                    <span class="block text-xs text-gray-500">{{ $t->toko->kode }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $t->toko->wilayah?->nama }}</td>
                                <td class="px-4 py-2 font-mono text-gray-600">{{ $t->toko->asset_id }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $t->pengaju?->name }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button type="button" wire:click="pilih({{ $t->id }})"
                                            class="rounded-md bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                        {{ __('tarik_freezer.periksa') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-kosong ikon="check-badge" :judul="__('tarik_freezer.antrean_kosong')" :keterangan="__('tarik_freezer.ket_antrean_kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->antrean->hasPages())
                <div class="border-t border-gray-100 p-4">{{ $this->antrean->links() }}</div>
            @endif
        </x-kartu>
    @endif

    {{-- ============ Periksa & putuskan ============ --}}
    @if ($this->tarikFreezer)
        @php $t = $this->tarikFreezer; @endphp
        <x-modal :judul="__('tarik_freezer.judul_periksa', ['kode' => $t->kode])" tutup="tutup" lebar="max-w-xl">
            <div class="space-y-5 p-5">
                <div class="flex flex-wrap items-center gap-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                    <span>{{ __('tarik_freezer.kolom_pengaju') }}: <span class="font-medium text-gray-900">{{ $t->pengaju?->name }}</span></span>
                    <span>·</span>
                    <span>{{ $t->diajukan_at?->isoFormat('lll') }}</span>
                </div>

                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="font-semibold text-gray-900">{{ $t->toko->nama }} <span class="font-normal text-gray-500">({{ $t->toko->kode }})</span></p>
                    <p class="text-sm text-gray-600">{{ $t->toko->alamat }}</p>
                    <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                        <span>{{ __('master.atr_wilayah') }}: {{ $t->toko->wilayah?->nama }}</span>
                        <span>{{ __('master.atr_asset_id') }}: <span class="font-mono">{{ $t->toko->asset_id }}</span></span>
                        @if ($t->toko->freezer_tipe)
                            <span>{{ __('noo.atr_freezer_tipe') }}: {{ $t->toko->freezer_tipe }}</span>
                        @endif
                    </div>
                </div>

                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ __('tarik_freezer.atr_alasan') }}</p>
                    <p class="mt-1 whitespace-pre-line rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ $t->alasan }}</p>
                </div>

                @if ($formTolakTerbuka)
                    <div class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-3">
                        <label class="block text-sm font-medium text-red-900">{{ __('tarik_freezer.alasan_tolak') }}</label>
                        <textarea wire:model="alasanTolak" rows="2" placeholder="{{ __('tarik_freezer.alasan_tolak_contoh') }}"
                                  class="block w-full rounded-lg border-red-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20"></textarea>
                        @error('alasanTolak') <p class="text-xs text-red-700">{{ $message }}</p> @enderror

                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('formTolakTerbuka', false)"
                                    class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50">
                                {{ __('umum.batal') }}
                            </button>
                            <button type="button" wire:click="tolak" wire:loading.attr="disabled" wire:target="tolak"
                                    class="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                                {{ __('tarik_freezer.tombol_tolak') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('formTolakTerbuka', true)"
                        class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                    {{ __('tarik_freezer.tombol_tolak') }}
                </button>
                <button type="button" wire:click="setujui" wire:loading.attr="disabled" wire:target="setujui"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="setujui">{{ __('tarik_freezer.tombol_setujui') }}</span>
                    <span wire:loading wire:target="setujui">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
