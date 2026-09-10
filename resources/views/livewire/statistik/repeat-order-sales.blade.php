<div>
    <x-judul-halaman :judul="__('statistik.judul_repeat_order')" :keterangan="__('statistik.ket_repeat_order')" />

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
                        {{ __('statistik.'.$label) }}
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
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.dari_tanggal') }}</label>
                    <input type="date" wire:model.live="dariTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.sampai_tanggal') }}</label>
                    <input type="date" wire:model.live="sampaiTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_dus_keseluruhan') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">@angka($this->totalDusKeseluruhan) {{ __('umum.satuan_dus') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_sales') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->perSales->count())</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_pesanan') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->pesanans->count())</p>
            </div>
        </div>

        {{-- Tinggi mengikuti jumlah sales, sama seperti grafik Insentif Sales. --}}
        <div wire:ignore class="border-t border-gray-200 p-4" style="height: {{ max(240, $this->perSales->count() * 44 + 60) }}px">
            <canvas id="chart-repeat-order"></canvas>
        </div>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-16 px-4 py-2 font-medium">{{ __('statistik.peringkat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('statistik.nama_sales') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('statistik.dus_diinput') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('statistik.jumlah_pesanan') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('statistik.jumlah_toko') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->perSales as $i => $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 tabular-nums text-gray-500">#{{ $i + 1 }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $s['nama'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-semibold text-emerald-700">@angka($s['total_dus']) {{ __('umum.satuan_dus') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-600">@angka($s['total_pesanan'])</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-600">@angka($s['total_toko'])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-kosong ikon="arrow-path" :judul="__('statistik.kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @script
    <script>
        const chart = window.pasangChartInsentif('chart-repeat-order', @js($this->dataChart));

        if (chart) {
            $wire.on('repeat-order-diperbarui', (payload) => {
                chart.gambar(payload.data ?? payload[0]?.data ?? payload);
            });
        }
    </script>
    @endscript
</div>
