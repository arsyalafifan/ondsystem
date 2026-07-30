<div>
    <x-judul-halaman :judul="__('routing.judul_riwayat')" :keterangan="__('routing.ket_riwayat')">
        <x-slot:aksi>
            <a href="{{ route('routing.generate') }}" wire:navigate
               class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('routing.buat_baru') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.tanggal') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.mobil') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.toko') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.dus') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.jarak') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('routing.dibuat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('routing.disetujui') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->batches as $b)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $b->kode }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $b->tanggal->isoFormat('ll') }}</td>
                            <td class="px-4 py-2">
                                @if ($b->isDraft())
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('routing.draft') }}</span>
                                @else
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('routing.disetujui') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $b->kendaraans_count }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $b->total_toko }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">@angka($b->total_dus)</td>
                            <td class="px-4 py-2 text-right tabular-nums">@angka($b->total_jarak_m / 1000, 1) km</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                {{ $b->pembuat->name }}
                                <span class="block text-xs text-gray-400">{{ $b->created_at->isoFormat('ll').' '.$b->created_at->format('H:i') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $b->penyetuju?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <a href="{{ route('routing.lihat', $b) }}" wire:navigate
                                   class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('umum.lihat') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <x-kosong ikon="🗺️" :judul="__('routing.riwayat_kosong')" :keterangan="__('routing.riwayat_kosong_ket')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->batches->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->batches->links() }}</div>
        @endif
    </x-kartu>
</div>
