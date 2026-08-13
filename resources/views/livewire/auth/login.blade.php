<div>
    <div class="mb-6 text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-blue-600 text-2xl"><x-heroicon-o-truck class="size-4 inline" /></span>
        <h1 class="mt-3 text-lg font-semibold text-white">{{ config('app.name') }}</h1>
        <p class="text-sm text-slate-400">{{ __('auth.subjudul') }}</p>
    </div>

    <form wire:submit="masuk" class="space-y-4 rounded-xl bg-white p-6 shadow-xl">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('auth.email') }}</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" autofocus
                   class="mt-1 block w-full sm:text-sm rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">{{ __('auth.kata_sandi') }}</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password"
                   class="mt-1 block w-full sm:text-sm rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" wire:model="ingatSaya" class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            {{ __('auth.ingat_saya') }}
        </label>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="masuk">{{ __('auth.tombol_masuk') }}</span>
            <span wire:loading wire:target="masuk">{{ __('auth.memeriksa') }}</span>
        </button>
    </form>
</div>
