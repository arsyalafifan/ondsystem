<div>
    <div class="mb-6 text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-blue-600 text-2xl">🚚</span>
        <h1 class="mt-3 text-lg font-semibold text-white">{{ config('app.name') }}</h1>
        <p class="text-sm text-slate-400">{{ __('auth.subjudul') }}</p>
    </div>

    <form wire:submit="masuk" class="space-y-4 rounded-xl bg-white p-6 shadow-xl">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('auth.email') }}</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" autofocus
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">{{ __('auth.kata_sandi') }}</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" wire:model="ingatSaya" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            {{ __('auth.ingat_saya') }}
        </label>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="masuk">{{ __('auth.tombol_masuk') }}</span>
            <span wire:loading wire:target="masuk">{{ __('auth.memeriksa') }}</span>
        </button>
    </form>
</div>
