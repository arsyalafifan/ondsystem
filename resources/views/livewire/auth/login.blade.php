<div>
    {{-- Header Logo & Identitas Aplikasi --}}
    <div class="mb-6 text-center">
        <div class="inline-flex size-13 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-md shadow-blue-600/20">
            <x-heroicon-s-truck class="size-7 text-white" />
        </div>
        <h1 class="mt-3 text-xl font-bold tracking-tight text-white">{{ config('app.name') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('auth.subjudul') }}</p>
    </div>

    {{-- Kartu Formulir Masuk --}}
    <form wire:submit="masuk" class="space-y-4 rounded-2xl bg-white p-6 shadow-xl border border-gray-100">
        {{-- Input Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('auth.email') }}
            </label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                    <x-heroicon-o-envelope class="size-5" />
                </div>
                <input id="email" type="email" wire:model="email" autocomplete="username" autofocus
                       @class([
                           'block w-full rounded-lg border bg-gray-50/50 pl-10 pr-3.5 py-2.5 text-sm text-gray-900 transition-colors focus:bg-white focus:outline-none focus:ring-2',
                           'border-gray-300 focus:border-blue-600 focus:ring-blue-500/20' => !$errors->has('email'),
                           'border-red-400 text-red-900 focus:border-red-500 focus:ring-red-500/20' => $errors->has('email'),
                       ])>
            </div>
            @error('email')
                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Input Kata Sandi --}}
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('auth.kata_sandi') }}
            </label>
            <div class="relative" x-data="{ lihat: false }">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                    <x-heroicon-o-lock-closed class="size-5" />
                </div>
                <input id="password" :type="lihat ? 'text' : 'password'" wire:model="password" autocomplete="current-password"
                       @class([
                           'block w-full rounded-lg border bg-gray-50/50 pl-10 pr-10 py-2.5 text-sm text-gray-900 transition-colors focus:bg-white focus:outline-none focus:ring-2',
                           'border-gray-300 focus:border-blue-600 focus:ring-blue-500/20' => !$errors->has('password'),
                           'border-red-400 text-red-900 focus:border-red-500 focus:ring-red-500/20' => $errors->has('password'),
                       ])>
                <button type="button" @click="lihat = !lihat"
                        :aria-label="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        tabindex="-1"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <x-heroicon-o-eye x-show="!lihat" class="size-5" />
                    <x-heroicon-o-eye-slash x-show="lihat" x-cloak class="size-5" />
                </button>
            </div>
            @error('password')
                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Checkbox Ingat Saya --}}
        <div class="flex items-center justify-between pt-0.5">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                <input type="checkbox" wire:model="ingatSaya"
                       class="size-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <span>{{ __('auth.ingat_saya') }}</span>
            </label>
        </div>

        {{-- Tombol Submit --}}
        <button type="submit" wire:loading.attr="disabled" wire:target="masuk"
                class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 active:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:opacity-60 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="masuk">{{ __('auth.tombol_masuk') }}</span>
            <span wire:loading wire:target="masuk" class="inline-flex items-center justify-center gap-2">
                <svg class="size-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ __('auth.memeriksa') }}</span>
            </span>
        </button>
    </form>
</div>
