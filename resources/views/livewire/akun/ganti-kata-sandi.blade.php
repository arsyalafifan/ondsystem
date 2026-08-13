<div>
    <x-judul-halaman :judul="__('auth.judul_ganti_sandi')" />

    <div class="max-w-md">
        <x-kartu>
            <form wire:submit="simpan" class="space-y-4 p-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('auth.kata_sandi_lama') }}</label>
                    <input type="password" wire:model="kataSandiLama" autocomplete="current-password"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('kataSandiLama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('auth.kata_sandi_baru') }}</label>
                    <input type="password" wire:model="kataSandiBaru" autocomplete="new-password"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('kataSandiBaru') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('auth.kata_sandi_baru_ulang') }}</label>
                    <input type="password" wire:model="kataSandiBaru_confirmation" autocomplete="new-password"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    {{ __('umum.simpan') }}
                </button>
            </form>
        </x-kartu>
    </div>
</div>
