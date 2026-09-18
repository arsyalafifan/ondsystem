{{-- Filter status/tanggal/cari + pemberitahuan menunggu di luar tanggal —
     dipakai Persetujuan Izin & Persetujuan Lembur (Concerns\DaftarPersetujuan). --}}
@php
    $inputKelas = 'mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
@endphp
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="flex flex-wrap rounded-lg border border-gray-300 bg-gray-50 p-1">
                @foreach (['menunggu', 'disetujui', 'ditolak', 'dibatalkan', ''] as $s)
                    <button type="button" wire:click="$set('filterStatus', '{{ $s }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                                'bg-white text-blue-700 shadow-sm' => $filterStatus === $s,
                                'text-gray-500 hover:text-gray-700' => $filterStatus !== $s,
                            ])>
                        {{ $s === '' ? __('umum.semua') : \App\Enums\StatusPengajuan::from($s)->label() }}
                        @if ($s === 'menunggu' && $this->jumlahMenunggu > 0)
                            <span class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white">{{ $this->jumlahMenunggu }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ $labelDari ?? __('izin.filter_dari') }}</label>
                <input type="date" wire:model.live="dariTanggal" class="{{ $inputKelas }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('izin.filter_sampai') }}</label>
                <input type="date" wire:model.live="sampaiTanggal" min="{{ $dariTanggal }}" class="{{ $inputKelas }}">
            </div>
            <div class="flex gap-1">
                <button type="button" wire:click="hariIni"
                        @class([
                            'rounded-lg border px-3 py-2.5 text-sm font-medium',
                            'border-blue-600 bg-blue-50 text-blue-700' => $dariTanggal === today()->toDateString() && $sampaiTanggal === today()->toDateString(),
                            'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => ! ($dariTanggal === today()->toDateString() && $sampaiTanggal === today()->toDateString()),
                        ])>{{ __('izin.filter_hari_ini') }}</button>
                <button type="button" wire:click="semuaTanggal"
                        @class([
                            'rounded-lg border px-3 py-2.5 text-sm font-medium',
                            'border-blue-600 bg-blue-50 text-blue-700' => $dariTanggal === '',
                            'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $dariTanggal !== '',
                        ])>{{ __('izin.filter_semua_tanggal') }}</button>
            </div>
            <div class="min-w-48 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('hr.cari_karyawan') }}" class="{{ $inputKelas }} w-full">
            </div>
        </div>

        @if ($this->menungguDiLuarTanggal > 0)
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="size-4 shrink-0" />
                    {{ __('izin.menunggu_di_luar_tanggal', ['jumlah' => $this->menungguDiLuarTanggal]) }}
                </span>
                <button type="button" wire:click="semuaTanggal" class="rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-700">
                    {{ __('izin.lihat_semua_tanggal') }}
                </button>
            </div>
        @endif

