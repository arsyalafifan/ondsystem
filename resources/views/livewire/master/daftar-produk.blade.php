<div>
    <x-judul-halaman :judul="__('master.judul_produk')" :keterangan="__('master.ket_produk')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('master.produk_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="border-b border-gray-200 p-4">
            <input type="search" wire:model.live.debounce.300ms="cari" placeholder="{{ __('master.cari_produk') }}"
                   class="block w-full max-w-sm rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('master.barcode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.nama') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('master.stok_fisik') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('master.dikunci') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('pesanan.tersedia') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.harga') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->produks as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $p->kode }}</td>
                            <td class="whitespace-nowrap px-4 py-2 font-mono text-xs text-gray-600">
                                {{ $p->barcode ?? __('master.barcode_belum_ada') }}
                            </td>
                            <td class="px-4 py-2">
                                {{ $p->nama }}
                                <span class="text-xs text-gray-500">/ {{ $p->satuan }}</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">@angka($p->stok)</td>
                            <td class="px-4 py-2 text-right tabular-nums text-amber-700">@angka($p->stok_reserved)</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums {{ $p->stok_tersedia === 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                @angka($p->stok_tersedia)
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">@rupiah((float) $p->harga)</td>
                            <td class="px-4 py-2">
                                @if ($p->aktif)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('umum.aktif') }}</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="bukaPenyesuaian({{ $p->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('master.sesuaikan_stok') }}
                                    </button>
                                    <button type="button" wire:click="bukaRiwayat({{ $p->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('master.riwayat_mutasi') }}
                                    </button>
                                    <button type="button" wire:click="sunting({{ $p->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-kosong ikon="cube" :judul="__('master.produk_kosong')" :keterangan="__('master.produk_kosong_ket')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->produks->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->produks->links() }}</div>
        @endif
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$produkId ? __('master.judul_produk_sunting') : __('master.judul_produk_baru')" tutup="tutupForm">
            <div class="space-y-3 p-5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('umum.kode') }}</label>
                        <input type="text" wire:model="kode"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('kode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.satuan') }}</label>
                        <input type="text" wire:model="satuan"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('master.nama_produk') }}</label>
                    <input type="text" wire:model="nama"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('master.barcode') }}</label>
                    <input type="text" wire:model="barcode" placeholder="{{ __('master.barcode_belum_ada') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('barcode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.stok_fisik') }}</label>
                        <input type="number" min="0" wire:model="stok"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('stok') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.harga_satuan') }}</label>
                        <input type="number" min="0" step="0.01" wire:model="harga"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('harga') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="aktif" class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    {{ __('master.produk_aktif') }}
                </label>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($produkDisesuaikan)
        <x-modal :judul="__('master.judul_penyesuaian')" tutup="$set('produkDisesuaikan', null)">
            <div class="space-y-3 p-5">
                <p class="text-sm text-gray-600">{{ __('master.ket_penyesuaian') }}</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('master.jumlah') }}</label>
                    <input type="number" wire:model="jumlahPenyesuaian" placeholder="{{ __('master.jumlah_contoh') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('jumlahPenyesuaian') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.keterangan') }}</label>
                    <input type="text" wire:model="keteranganPenyesuaian" placeholder="{{ __('master.keterangan_contoh') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('keteranganPenyesuaian') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('produkDisesuaikan', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpanPenyesuaian"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- Riwayat mutasi stok --}}
    @if ($this->produkRiwayatModel)
        <x-modal :judul="__('master.judul_riwayat_mutasi', ['nama' => $this->produkRiwayatModel->nama])" lebar="max-w-4xl" tutup="tutupRiwayat">
            <div class="border-b border-gray-200 p-4">
                <p class="text-sm text-gray-600">{{ __('master.ket_riwayat_mutasi') }}</p>
            </div>

            <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('master.filter_tipe_mutasi') }}</label>
                    <select wire:model.live="riwayatTipe"
                            class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('master.semua_tipe_mutasi') }}</option>
                        @foreach ($this->tipeMutasiCases as $tipe)
                            <option value="{{ $tipe->value }}">{{ $tipe->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('pembayaran.dari_tanggal') }}</label>
                    <input type="date" wire:model.live="riwayatDari"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('pembayaran.sampai_tanggal') }}</label>
                    <input type="date" wire:model.live="riwayatSampai"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <button type="button" wire:click="bersihkanFilterRiwayat"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.bersihkan') }}
                </button>
            </div>

            <div class="max-h-[60vh] overflow-y-auto">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('umum.tanggal') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('master.filter_tipe_mutasi') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ __('master.jumlah') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ __('master.stok_fisik') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ __('master.dikunci') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('umum.keterangan') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('master.terkait') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('master.oleh') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($this->riwayatMutasi as $m)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                        {{ $m->created_at->isoFormat('ll') }}
                                        <span class="block text-xs text-gray-400">{{ $m->created_at->format('H:i') }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <span class="rounded px-2 py-0.5 text-xs font-medium {{ $m->tipe->badge() }}">{{ $m->tipe->label() }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums {{ $m->jumlah >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                        {{ $m->jumlah >= 0 ? '+' : '' }}@angka($m->jumlah)
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums">@angka($m->stok_sesudah)</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-amber-700">@angka($m->reserved_sesudah)</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $m->keterangan ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                        {{ $m->pesanan?->kode ?? $m->kendaraan?->nama ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $m->user?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        @if ($riwayatTipe !== '' || $riwayatDari !== '' || $riwayatSampai !== '')
                                            <x-kosong ikon="magnifying-glass" :judul="__('master.kosong_riwayat_mutasi_filter')" />
                                        @else
                                            <x-kosong ikon="archive-box" :judul="__('master.kosong_riwayat_mutasi')" />
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->riwayatMutasi->hasPages())
                    <div class="border-t border-gray-200 px-4 py-3">{{ $this->riwayatMutasi->links() }}</div>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupRiwayat"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.tutup') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
