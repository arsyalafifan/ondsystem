<div>
    <x-judul-halaman :judul="__('hak_akses.judul')" :keterangan="__('hak_akses.keterangan')">
        <x-slot:aksi>
            <button type="button" wire:click="kembalikanBawaan" wire:confirm="{{ __('hak_akses.konfirmasi_kembalikan') }}"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('hak_akses.kembalikan_bawaan') }}
            </button>
            <button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                {{ __('hak_akses.simpan') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <div class="mb-4 flex w-fit flex-wrap rounded-lg border border-gray-300 bg-gray-50 p-1">
        @foreach (\App\Livewire\HakAkses\KelolaHakAkses::peranBisaDiatur() as $nilai)
            <button type="button" wire:click="pilihPeran('{{ $nilai }}')"
                    @class([
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                        'bg-white text-blue-700 shadow-sm' => $peran === $nilai,
                        'text-gray-500 hover:text-gray-700' => $peran !== $nilai,
                    ])>
                {{ \App\Enums\PeranPengguna::from($nilai)->label() }}
            </button>
        @endforeach
    </div>

    <div class="space-y-4">
        @foreach ($this->struktur as $aplikasi)
            <div wire:key="aplikasi-{{ $aplikasi['kunci'] }}">
                <x-kartu>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            @svg('heroicon-o-'.$aplikasi['ikon'], ['class' => 'size-5 text-slate-500'])
                            {{ $aplikasi['label'] }}
                        </div>
                        @if (count($aplikasi['baris']) > 0)
                            <div class="flex gap-2">
                                <button type="button" wire:click="aturSemua('{{ $aplikasi['kunci'] }}', true)"
                                        class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('hak_akses.pilih_semua') }}
                                </button>
                                <button type="button" wire:click="aturSemua('{{ $aplikasi['kunci'] }}', false)"
                                        class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('hak_akses.kosongkan_semua') }}
                                </button>
                            </div>
                        @endif
                    </div>

                    @if (count($aplikasi['baris']) === 0)
                        <p class="px-4 py-3 text-sm text-gray-500">{{ __('hak_akses.segera_hadir_ket') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-2 font-medium">{{ __('hak_akses.menu') }}</th>
                                        <th class="w-24 px-4 py-2 text-center font-medium">{{ __('hak_akses.akses') }}</th>
                                        <th class="w-56 px-4 py-2 font-medium">{{ __('hak_akses.data') }}</th>
                                        <th class="w-24 px-4 py-2 font-medium"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($aplikasi['baris'] as $baris)
                                        @php $boleh = (bool) ($isian[$baris['isian']]['boleh'] ?? false); @endphp
                                        <tr wire:key="menu-{{ $baris['isian'] }}" class="hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                <div class="flex items-center gap-2 text-gray-900">
                                                    @svg('heroicon-o-'.$baris['ikon'], ['class' => 'size-4 shrink-0 text-slate-400'])
                                                    @if ($baris['grup'])
                                                        <span class="text-gray-400">{{ $baris['grup'] }} /</span>
                                                    @endif
                                                    {{ $baris['label'] }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <input type="checkbox" wire:model.live="isian.{{ $baris['isian'] }}.boleh"
                                                       class="size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                                            </td>
                                            <td class="px-4 py-2">
                                                @if ($baris['cakupan_data'])
                                                    <select wire:model.live="isian.{{ $baris['isian'] }}.cakupan" @disabled(! $boleh)
                                                            class="block w-full rounded-lg border-gray-400 bg-gray-50 px-3 py-1.5 text-sm text-gray-900 disabled:opacity-50">
                                                        @foreach (\App\Enums\CakupanData::cases() as $cakupan)
                                                            <option value="{{ $cakupan->value }}">{{ $cakupan->label() }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2">
                                                @if ($this->diubah($baris['menu']))
                                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ __('hak_akses.diubah') }}</span>
                                                @else
                                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500">{{ __('hak_akses.bawaan') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-kartu>
            </div>
        @endforeach
    </div>
</div>
