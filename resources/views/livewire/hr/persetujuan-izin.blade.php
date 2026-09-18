@php
    $inputKelas = 'mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
@endphp

<div>
    <x-judul-halaman :judul="__('izin.judul_persetujuan')" :keterangan="__('izin.ket_persetujuan')" />

    <x-kartu>
        @include('livewire.hr.partials.filter-persetujuan')

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_nama_lengkap') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('izin.atr_jenis') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.tanggal') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('izin.atr_alasan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->pengajuans as $p)
                        <tr class="align-top hover:bg-gray-50" wire:key="p-{{ $p->id }}">
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $p->karyawan->nama_lengkap }}</span>
                                <span class="block text-xs text-gray-500">{{ $p->karyawan->kode_karyawan }} · {{ $p->karyawan->posisi?->nama ?? '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                {{ $p->jenis->label() }}
                                <span class="block text-xs text-gray-500">{{ $p->porsi->label() }} · {{ $p->dibayar ? __('izin.dibayar') : __('izin.tidak_dibayar') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                {{ $p->tanggalTeks() }}
                                <span class="block text-xs text-gray-500">{{ __('izin.jumlah_hari', ['hari' => \App\Support\Bahasa::angka($p->jumlah_hari, fmod($p->jumlah_hari, 1) > 0 ? 1 : 0)]) }}</span>
                            </td>
                            <td class="max-w-sm px-4 py-2 text-gray-700">
                                {{ $p->alasan }}
                                @if ($p->lampiran)
                                    <a href="{{ route('hr.izin.lampiran', $p) }}" target="_blank" rel="noopener"
                                       class="mt-1 flex items-center gap-1 text-xs text-blue-700 underline">
                                        <x-heroicon-o-paper-clip class="size-3.5" /> {{ $p->lampiran_nama ?? __('izin.atr_lampiran') }}
                                    </a>
                                @endif
                                <span class="mt-1 block text-xs text-gray-400">{{ __('izin.diajukan_pada', ['waktu' => $p->created_at->format('d/m/Y H:i')]) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $p->status->badge() }}">{{ $p->status->label() }}</span>
                                @if ($p->diputuskanOleh)
                                    <span class="mt-1 block text-xs text-gray-500">{{ $p->diputuskanOleh->name }} · {{ $p->diputuskan_at?->format('d/m H:i') }}</span>
                                @endif
                                @if ($p->catatan_keputusan)
                                    <span class="mt-1 block max-w-48 whitespace-normal text-xs text-gray-500">{{ $p->catatan_keputusan }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @if ($p->status === \App\Enums\StatusPengajuan::Menunggu && ! ($this->hakKeputusan[$p->id]['boleh'] ?? false))
                                    <span class="block max-w-44 whitespace-normal text-right text-xs text-gray-500">
                                        {{ __('izin.menunggu_persetujuan', ['nama' => $this->hakKeputusan[$p->id]['menunggu'] ?? '—']) }}
                                    </span>
                                @elseif ($p->status === \App\Enums\StatusPengajuan::Menunggu)
                                    <div class="flex justify-end gap-1">
                                        <button type="button" wire:click="buka({{ $p->id }}, 'setujui')"
                                                class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700">{{ __('izin.tombol_setujui') }}</button>
                                        <button type="button" wire:click="buka({{ $p->id }}, 'tolak')"
                                                class="rounded-md border border-red-300 bg-white px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50">{{ __('izin.tombol_tolak') }}</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-kosong ikon="inbox" :judul="__('izin.persetujuan_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->pengajuans->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->pengajuans->links() }}</div>
        @endif
    </x-kartu>

    @if ($this->dipilih)
        @php $d = $this->dipilih; @endphp
        <x-modal :judul="($keputusan === 'tolak' ? __('izin.judul_tolak') : __('izin.judul_setujui')).' · '.$d->karyawan->nama_lengkap" tutup="tutup">
            <div class="space-y-3 p-5">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('izin.atr_jenis') }}</dt>
                        <dd class="text-gray-900">{{ $d->jenis->label() }} · {{ $d->porsi->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('umum.tanggal') }}</dt>
                        <dd class="text-gray-900">{{ $d->tanggalTeks() }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500">{{ __('izin.atr_alasan') }}</dt>
                        <dd class="text-gray-900">{{ $d->alasan }}</dd>
                    </div>
                </dl>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ __('izin.atr_catatan_keputusan') }}
                        @if ($keputusan !== 'tolak') <span class="font-normal text-gray-400">({{ __('umum.opsional') }})</span> @endif
                    </label>
                    <textarea wire:model="catatan" rows="3" class="{{ $inputKelas }} w-full"></textarea>
                    @error('catatan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <x-slot:aksi>
                <button type="button" wire:click="tutup"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="putuskan" wire:loading.attr="disabled" wire:target="putuskan"
                        @class([
                            'rounded-lg px-3 py-2 text-sm font-semibold text-white disabled:opacity-60',
                            'bg-red-600 hover:bg-red-700' => $keputusan === 'tolak',
                            'bg-emerald-600 hover:bg-emerald-700' => $keputusan !== 'tolak',
                        ])>
                    {{ $keputusan === 'tolak' ? __('izin.tombol_tolak') : __('izin.tombol_setujui') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
