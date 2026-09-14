<div>
    <x-judul-halaman :judul="__('hr.judul_jabatan')" :keterangan="__('hr.ket_jabatan')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('hr.jabatan_baru') }}
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
                        <th class="px-4 py-2 text-right font-medium">{{ __('hr.jumlah_karyawan') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->jabatans as $j)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $j->kode }}</td>
                            <td class="px-4 py-2">{{ $j->nama }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $j->karyawans_count }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $j->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiHapus', {{ $j->id }})"
                                            @disabled($j->karyawans_count > 0)
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400">
                                        {{ __('umum.hapus') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-kosong ikon="briefcase" :judul="__('hr.jabatan_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$jabatanId ? __('hr.judul_jabatan_sunting') : __('hr.judul_jabatan_baru')" tutup="tutupForm">
            <div class="space-y-3 p-5">
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

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($konfirmasiHapus)
        <x-modal :judul="__('hr.judul_hapus_jabatan')" tutup="$set('konfirmasiHapus', null)">
            <p class="p-5 text-sm text-gray-600">{{ __('hr.ket_hapus_jabatan') }}</p>
            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="hapus({{ $konfirmasiHapus }})"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('umum.hapus') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
