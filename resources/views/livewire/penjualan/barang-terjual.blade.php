<div>
    <x-judul-halaman :judul="__('penjualan.judul_barang_terjual')" :keterangan="__('penjualan.ket_barang_terjual')" />

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="flex rounded-lg border border-gray-300 bg-gray-50 p-1">
                @foreach (['hari' => 'mode_hari', 'bulan' => 'mode_bulan', 'rentang' => 'mode_rentang', 'semua' => 'mode_semua'] as $nilai => $label)
                    <button type="button" wire:click="$set('mode', '{{ $nilai }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                                'bg-white text-blue-700 shadow-sm' => $mode === $nilai,
                                'text-gray-500 hover:text-gray-700' => $mode !== $nilai,
                            ])>
                        {{ __('penjualan.'.$label) }}
                    </button>
                @endforeach
            </div>

            @if ($mode === 'hari')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                    <input type="date" wire:model.live="tanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
            @elseif ($mode === 'bulan')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                    <input type="month" wire:model.live="bulan"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
            @elseif ($mode === 'rentang')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('penjualan.dari_tanggal') }}</label>
                    <input type="date" wire:model.live="dariTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('penjualan.sampai_tanggal') }}</label>
                    <input type="date" wire:model.live="sampaiTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('penjualan.total_dus_terjual') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-700">@angka($this->totalDusTerjual) {{ __('umum.satuan_dus') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('penjualan.total_varian') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->totalVarian)</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('penjualan.total_transaksi') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->totalTransaksi)</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('penjualan.total_bonus') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-violet-700">@angka($this->totalBonus) {{ __('umum.satuan_dus') }}</p>
            </div>
        </div>

        {{-- Kategori sumber dus terjual: pengantaran driver (rute + kampas) vs POS — padanan kartu kategori di Pendapatan, tapi satuan dus. --}}
        <div class="grid grid-cols-2 gap-3 border-t border-gray-200 p-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('penjualan.kategori_driver') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-violet-700">@angka($this->totalPerKategori['driver']) {{ __('umum.satuan_dus') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('penjualan.kategori_pos') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-orange-700">@angka($this->totalPerKategori['pos']) {{ __('umum.satuan_dus') }}</p>
            </div>
        </div>

        <div wire:ignore class="border-t border-gray-200 p-4" style="height: {{ max(240, $this->ringkasanProduk->take(10)->count() * 44 + 60) }}px">
            <canvas id="chart-barang-terjual"></canvas>
        </div>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-16 px-4 py-2 font-medium">{{ __('penjualan.peringkat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.produk') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('penjualan.dus_terjual') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('penjualan.dus_bonus') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('penjualan.jumlah_transaksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->ringkasanProduk as $i => $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 tabular-nums text-gray-500">#{{ $i + 1 }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $r['nama'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-amber-700">@angka($r['qty']) {{ __('umum.satuan_dus') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-500">
                                @if ($r['qty_bonus'] > 0)
                                    @angka($r['qty_bonus'])
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-600">@angka($r['transaksi'])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-kosong ikon="archive-box" :judul="__('penjualan.kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    {{-- Riwayat per item: satu baris satu produk pada satu pesanan, bisa disaring dan dipaging supaya tabelnya tidak perlu discroll panjang-panjang. --}}
    <x-kartu :judul="__('penjualan.riwayat_judul')" class="mt-5">
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="min-w-56 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.300ms="riwayatCari" placeholder="{{ __('penjualan.cari_riwayat') }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('penjualan.kategori') }}</label>
                <select wire:model.live="riwayatKategori"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('penjualan.semua_kategori') }}</option>
                    <option value="driver">{{ __('penjualan.kategori_driver') }}</option>
                    <option value="pos">{{ __('penjualan.kategori_pos') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.produk') }}</label>
                <select wire:model.live="riwayatProduk"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('penjualan.semua_produk') }}</option>
                    @foreach ($this->produkPilihan as $p)
                        <option value="{{ $p['id'] }}">{{ $p['nama'] }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="bersihkanFilterRiwayat"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('umum.bersihkan') }}
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.tanggal') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.toko') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.produk') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('penjualan.kategori') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('penjualan.dus_terjual') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->riwayat as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $item->pesanan->tanggal_pendapatan?->isoFormat('ll') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $item->pesanan->kode }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $item->pesanan->toko->nama }}</td>
                            <td class="px-4 py-2 text-gray-900">
                                {{ $item->produk->nama }}
                                @if ($item->is_bonus)
                                    <span class="ml-1 rounded bg-violet-100 px-1.5 py-0.5 text-xs font-medium text-violet-800">{{ __('penjualan.bonus') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($item->pesanan->jenis->kategoriPendapatan() === 'pos')
                                    <span class="rounded bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800">{{ __('penjualan.kategori_pos') }}</span>
                                @else
                                    <span class="rounded bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">{{ __('penjualan.kategori_driver') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-900">@angka($item->terkirim) {{ __('umum.satuan_dus') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                @if ($this->items->isEmpty())
                                    <x-kosong ikon="archive-box" :judul="__('penjualan.riwayat_kosong')" />
                                @else
                                    <x-kosong ikon="archive-box" :judul="__('penjualan.riwayat_tidak_cocok')" />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->riwayat->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->riwayat->links() }}</div>
        @endif
    </x-kartu>

    @script
    <script>
        const chart = window.pasangChartBarangTerjual('chart-barang-terjual', @js($this->dataChart));

        if (chart) {
            $wire.on('barang-terjual-diperbarui', (payload) => {
                chart.gambar(payload.data ?? payload[0]?.data ?? payload);
            });
        }
    </script>
    @endscript
</div>
