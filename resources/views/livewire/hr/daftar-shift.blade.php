<div>
    <x-judul-halaman :judul="__('hr.judul_shift')" :keterangan="__('hr.ket_shift')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('hr.shift_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.nama') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_jam_masuk') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_jam_pulang') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('hr.jumlah_karyawan') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->shifts as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $s->kode }}</td>
                            <td class="px-4 py-2">{{ $s->nama }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ substr($s->jam_masuk, 0, 5) }}</td>
                            <td class="px-4 py-2 tabular-nums">
                                {{ substr($s->jam_pulang, 0, 5) }}
                                @if ($s->lintas_hari)
                                    <span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-800">{{ __('hr.lintas_hari_singkat') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-800' => $s->aktif,
                                    'bg-gray-100 text-gray-600' => ! $s->aktif,
                                ])>{{ $s->aktif ? __('umum.aktif') : __('umum.nonaktif') }}</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $s->karyawans_count }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $s->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiHapus', {{ $s->id }})"
                                            @disabled($s->karyawans_count > 0)
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400">
                                        {{ __('umum.hapus') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="clock" :judul="__('hr.shift_kosong')" :keterangan="__('hr.ket_shift_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$shiftId ? __('hr.judul_shift_sunting') : __('hr.judul_shift_baru')" tutup="tutupForm">
            <div class="space-y-3 p-5">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('umum.kode') }}</label>
                        <input type="text" wire:model="kode"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('kode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('umum.nama') }}</label>
                        <input type="text" wire:model="nama"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_jam_masuk') }}</label>
                        <input type="time" wire:model="jamMasuk"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('jamMasuk') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_jam_pulang') }}</label>
                        <input type="time" wire:model="jamPulang"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('jamPulang') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_durasi_istirahat') }}</label>
                    <input type="number" min="0" max="480" wire:model="durasiIstirahatMenit" placeholder="{{ __('hr.ikut_posisi') }}" class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 sm:w-40">
                    <p class="mt-1 text-xs text-gray-500">{{ __('hr.ket_durasi_istirahat_shift') }}</p>
                    @error('durasiIstirahatMenit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="lintasHari" class="mt-0.5 size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                    <span>
                        {{ __('hr.atr_lintas_hari') }}
                        <span class="block text-xs text-gray-500">{{ __('hr.ket_lintas_hari') }}</span>
                    </span>
                </label>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="aktif" class="size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                    {{ __('umum.aktif') }}
                </label>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('umum.simpan') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($konfirmasiHapus)
        <x-modal :judul="__('hr.judul_hapus_shift')" tutup="$set('konfirmasiHapus', null)">
            <div class="p-5 text-sm text-gray-600">{{ __('hr.ket_hapus_shift') }}</div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="hapus({{ $konfirmasiHapus }})"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    {{ __('umum.hapus') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
