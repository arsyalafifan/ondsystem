<div>
    <x-judul-halaman :judul="__('statistik.judul_dus_bonus')" :keterangan="__('statistik.ket_dus_bonus')">
        <x-slot:aksi>
            <button type="button" wire:click="unduhExcel" wire:loading.attr="disabled" wire:target="unduhExcel"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60">
                <x-heroicon-o-arrow-down-tray class="size-4" />
                <span wire:loading.remove wire:target="unduhExcel">{{ __('statistik.unduh_excel') }}</span>
                <span wire:loading wire:target="unduhExcel">{{ __('umum.memproses') }}</span>
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        {{-- Toolbar Filter Periode Tanggal --}}
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="flex rounded-lg border border-gray-300 bg-gray-50 p-1">
                @foreach (['hari' => 'mode_hari', 'bulan' => 'mode_bulan', 'rentang' => 'mode_rentang', 'semua' => 'mode_semua'] as $nilai => $label)
                    <button type="button" wire:click="$set('mode', '{{ $nilai }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                                'bg-white text-purple-700 shadow-sm' => $mode === $nilai,
                                'text-gray-500 hover:text-gray-700' => $mode !== $nilai,
                            ])>
                        {{ __('statistik.'.$label) }}
                    </button>
                @endforeach
            </div>

            @if ($mode === 'hari')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                    <input type="date" wire:model.live="tanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
                </div>
            @elseif ($mode === 'bulan')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                    <input type="month" wire:model.live="bulan"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
                </div>
            @elseif ($mode === 'rentang')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.dari_tanggal') }}</label>
                    <input type="date" wire:model.live="dariTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.sampai_tanggal') }}</label>
                    <input type="date" wire:model.live="sampaiTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
                </div>
            @endif
        </div>

        {{-- Kartu Ringkasan KPI --}}
        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_dus_bonus') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-purple-700">@angka($this->totalDusBonus) {{ __('umum.satuan_dus') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_varian_bonus') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->totalVarian)</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_transaksi_bonus') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->totalPesanan)</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_toko_bonus') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->totalToko)</p>
            </div>
        </div>

        {{-- Bagian Grafik Dus Bonus per Varian --}}
        <div wire:ignore class="border-t border-gray-200 p-4" style="height: {{ max(240, $this->ringkasanProduk->take(10)->count() * 44 + 60) }}px">
            <canvas id="chart-dus-bonus"></canvas>
        </div>

        {{-- Tabel Ringkasan per Varian Produk --}}
        <div class="overflow-x-auto border-t border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-16 px-4 py-2.5 font-medium">{{ __('statistik.peringkat') }}</th>
                        <th class="w-28 px-4 py-2.5 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('umum.produk') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium">{{ __('statistik.total_dus_bonus') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium">{{ __('statistik.jumlah_pesanan') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium">{{ __('statistik.jumlah_toko') }}</th>
                        <th class="w-24 px-4 py-2.5 text-right font-medium">{{ __('statistik.persentase_bonus') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->ringkasanProduk as $i => $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 tabular-nums text-gray-500">#{{ $i + 1 }}</td>
                            <td class="whitespace-nowrap px-4 py-2 font-mono text-xs text-gray-600">{{ $r['kode'] }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $r['nama'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-purple-700">@angka($r['total_dus']) {{ __('umum.satuan_dus') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-600">@angka($r['transaksi'])</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-600">@angka($r['toko'])</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $r['porsi'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="gift" :judul="__('statistik.kosong_dus_bonus')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    {{-- Riwayat Pengiriman Dus Bonus --}}
    <x-kartu :judul="__('statistik.riwayat_dus_bonus')" class="mt-5">
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="min-w-56 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.300ms="riwayatCari" placeholder="{{ __('statistik.cari_riwayat_bonus') }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.produk') }}</label>
                <select wire:model.live="riwayatProduk"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
                    <option value="">{{ __('penjualan.semua_produk') }}</option>
                    @foreach ($this->produkPilihan as $p)
                        <option value="{{ $p['id'] }}">{{ $p['nama'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('statistik.tipe_bonus') }}</label>
                <select wire:model.live="riwayatTipeBonus"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
                    <option value="">{{ __('statistik.semua_tipe_bonus') }}</option>
                    <option value="promo">{{ __('statistik.bonus_promo') }}</option>
                    <option value="manual">{{ __('statistik.bonus_manual') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('penjualan.kategori') }}</label>
                <select wire:model.live="riwayatKategori"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-purple-500 focus:bg-white focus:ring-2 focus:ring-purple-500/20">
                    <option value="">{{ __('penjualan.semua_kategori') }}</option>
                    <option value="driver">{{ __('penjualan.kategori_driver') }}</option>
                    <option value="pos">{{ __('penjualan.kategori_pos') }}</option>
                </select>
            </div>
            <button type="button" wire:click="bersihkanFilterRiwayat"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                {{ __('umum.bersihkan') }}
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">{{ __('umum.tanggal') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('umum.toko') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('umum.produk') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('penjualan.kategori') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('statistik.tipe_bonus') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium">{{ __('penjualan.dus_bonus') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->riwayat as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                {{ $item->pesanan->tanggal_pendapatan?->isoFormat('ll') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                {{ $item->pesanan->kode }}
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                <div class="font-medium text-gray-900">{{ $item->pesanan->toko?->nama }}</div>
                                @if ($item->pesanan->toko?->wilayah)
                                    <div class="text-xs text-gray-500">{{ $item->pesanan->toko->wilayah->nama }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-900">
                                <span class="font-medium">{{ $item->produk?->nama }}</span>
                                <span class="ml-1 text-xs text-gray-500">({{ $item->produk?->kode }})</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($item->pesanan->jenis->kategoriPendapatan() === 'pos')
                                    <span class="rounded bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800">
                                        {{ __('penjualan.kategori_pos') }}
                                    </span>
                                @else
                                    <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                        {{ __('penjualan.kategori_driver') }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($item->pesanan->promo_id !== null)
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                                        {{ $item->pesanan->promo?->nama ?? __('statistik.bonus_promo') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800">
                                        {{ __('statistik.bonus_manual') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-purple-700">
                                @angka($item->terkirim) {{ __('umum.satuan_dus') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @if ($this->items->isEmpty())
                                    <x-kosong ikon="gift" :judul="__('statistik.kosong_dus_bonus')" />
                                @else
                                    <x-kosong ikon="gift" :judul="__('statistik.kosong_dus_bonus_filter')" />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->riwayat->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">
                {{ $this->riwayat->links() }}
            </div>
        @endif
    </x-kartu>

    @script
    <script>
        const chart = window.pasangChartDusBonus('chart-dus-bonus', @js($this->dataChart));

        if (chart) {
            $wire.on('dus-bonus-diperbarui', (payload) => {
                chart.gambar(payload.data ?? payload[0]?.data ?? payload);
            });
        }
    </script>
    @endscript
</div>
