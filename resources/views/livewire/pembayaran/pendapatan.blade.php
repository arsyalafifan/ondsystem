<div>
    <x-judul-halaman :judul="__('pembayaran.judul_pendapatan')" :keterangan="__('pembayaran.ket_pendapatan')" />

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
                        {{ __('pembayaran.'.$label) }}
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
                    <label class="block text-xs font-medium text-gray-600">{{ __('pembayaran.dari_tanggal') }}</label>
                    <input type="date" wire:model.live="dariTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('pembayaran.sampai_tanggal') }}</label>
                    <input type="date" wire:model.live="sampaiTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('pembayaran.total_pendapatan') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@rupiah($this->totalKeseluruhan)</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('pembayaran.total_transaksi') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($this->pesanans->count())</p>
            </div>
        </div>

        <div wire:ignore class="border-t border-gray-200 p-4 h-96">
            <canvas id="chart-pendapatan"></canvas>
        </div>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.tanggal') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('pembayaran.total_pendapatan') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->ringkasanHarian as $tanggal => $nilai)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-900">{{ \Illuminate\Support\Carbon::parse($tanggal)->isoFormat('ll') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-900">@rupiah($nilai)</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">
                                <x-kosong ikon="chart-bar" :judul="__('pembayaran.kosong_pendapatan')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @script
    <script>
        const chart = window.pasangChartPendapatan('chart-pendapatan', @js($this->dataChart));

        if (chart) {
            $wire.on('pendapatan-diperbarui', (payload) => {
                chart.gambar(payload.data ?? payload[0]?.data ?? payload);
            });
        }
    </script>
    @endscript
</div>
