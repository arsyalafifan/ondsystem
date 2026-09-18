@php
    $inputKelas = 'mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
    $dipilih = $this->semuaPengguna->whereIn('id', $pengguna);
@endphp

<div>
    <x-judul-halaman :judul="__('izin.judul_setting')" :keterangan="__('izin.ket_setting')" />

    <div class="grid gap-4 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-3">
            {{-- Mode --}}
            <x-kartu>
                <div class="space-y-3 p-4">
                    <p class="text-sm font-semibold text-gray-900">{{ __('izin.atr_mode') }}</p>
                    @foreach (\App\Enums\ModePersetujuanIzin::cases() as $m)
                        <label @class([
                            'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition',
                            'border-blue-600 bg-blue-50' => $mode === $m->value,
                            'border-gray-200 hover:bg-gray-50' => $mode !== $m->value,
                        ])>
                            <input type="radio" wire:model.live="mode" value="{{ $m->value }}" class="mt-0.5 size-4 text-blue-600 focus:ring-blue-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-900">{{ $m->label() }}</span>
                                <span class="block text-xs text-gray-600">{{ $m->keterangan() }}</span>
                            </span>
                        </label>
                    @endforeach

                    @if ($mode === 'atasan' && $this->jumlahTanpaAtasan > 0)
                        <p class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                            {{ __('izin.ket_tanpa_atasan', ['jumlah' => $this->jumlahTanpaAtasan]) }}
                        </p>
                    @endif
                </div>
            </x-kartu>

            {{-- Approver umum --}}
            <x-kartu>
                <div class="space-y-4 p-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ __('izin.approver_umum') }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $mode === 'atasan' ? __('izin.ket_approver_umum_cadangan') : __('izin.ket_approver_umum') }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('izin.atr_approver_peran') }}</label>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach ($this->peranCases as $p)
                                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                    <input type="checkbox" value="{{ $p->value }}" wire:model.live="peran"
                                           class="size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                                    {{ $p->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('izin.atr_approver_pengguna') }}</label>
                        <div class="mt-1 flex gap-2">
                            <select wire:model="penggunaDipilih" class="{{ $inputKelas }} mt-0">
                                <option value="">{{ __('izin.pilih_pengguna') }}</option>
                                @foreach ($this->semuaPengguna->whereNotIn('id', $pengguna) as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->role->label() }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="tambahPengguna"
                                    class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                                {{ __('izin.tombol_tambah') }}
                            </button>
                        </div>
                        @if ($dipilih->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($dipilih as $u)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-800 ring-1 ring-inset ring-blue-600/20">
                                        {{ $u->name }}
                                        <button type="button" wire:click="hapusPengguna({{ $u->id }})" class="text-blue-600 hover:text-blue-900">
                                            <x-heroicon-o-x-mark class="size-3.5" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </x-kartu>

            {{-- Lembur --}}
            <x-kartu>
                <div class="space-y-2 p-4">
                    <p class="text-sm font-semibold text-gray-900">{{ __('lembur.judul_setting_lembur') }}</p>
                    <label class="block text-sm font-medium text-gray-700">{{ __('lembur.atr_maks_lembur') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="number" min="0.25" max="12" step="0.25" wire:model="maksLemburJam"
                               class="block w-28 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <span class="text-sm text-gray-600">{{ __('lembur.satuan_jam') }}</span>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('lembur.ket_maks_lembur') }}</p>
                    @error('maksLemburJam') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </x-kartu>

            <div class="flex justify-end">
                <button type="button" wire:click="simpan" wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    {{ __('umum.simpan') }}
                </button>
            </div>
        </div>

        {{-- Ringkasan aturan --}}
        <div class="space-y-4 lg:col-span-2">
            <x-kartu>
                <div class="p-4">
                    <p class="text-sm font-semibold text-gray-900">{{ __('izin.pratinjau_approver') }}</p>
                    @if ($this->pratinjau->isEmpty())
                        <p class="mt-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">{{ __('izin.ket_approver_kosong') }}</p>
                    @else
                        <ul class="mt-2 divide-y divide-gray-100 text-sm">
                            @foreach ($this->pratinjau as $u)
                                <li class="flex justify-between py-1.5">
                                    <span class="text-gray-900">{{ $u->name }}</span>
                                    <span class="text-xs text-gray-500">{{ $u->role->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-kartu>

            <x-kartu>
                <div class="p-4 text-sm text-gray-700">
                    <p class="font-semibold text-gray-900">{{ __('izin.aturan_tetap') }}</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-gray-600">
                        <li>{{ __('izin.aturan_tidak_sendiri') }}</li>
                        <li>{{ __('izin.aturan_cadangan') }}</li>
                        <li>{{ __('izin.aturan_superadmin') }}</li>
                    </ul>
                </div>
            </x-kartu>
        </div>
    </div>
</div>
