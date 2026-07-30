@php
    $bahasa = \App\Support\Bahasa::info();
    $peran = auth()->user()->role;

    $menu = match ($peran) {
        \App\Enums\PeranPengguna::Admin => [
            ['rute' => 'dashboard', 'label' => __('nav.dashboard'), 'ikon' => '📊'],
            ['rute' => 'pesanan.daftar', 'label' => __('nav.pesanan'), 'ikon' => '📋'],
            ['rute' => 'pesanan.buat', 'label' => __('nav.input_pesanan'), 'ikon' => '➕'],
            ['rute' => 'routing.generate', 'label' => __('nav.generate_routing'), 'ikon' => '🗺️'],
            ['rute' => 'routing.riwayat', 'label' => __('nav.riwayat_routing'), 'ikon' => '🕘'],
            ['rute' => 'master.toko', 'label' => __('nav.master_toko'), 'ikon' => '🏪'],
            ['rute' => 'master.produk', 'label' => __('nav.master_produk'), 'ikon' => '📦'],
            ['rute' => 'master.wilayah', 'label' => __('nav.master_wilayah'), 'ikon' => '📍'],
        ],
        \App\Enums\PeranPengguna::Sales => [
            ['rute' => 'pesanan.buat', 'label' => __('nav.input_pesanan'), 'ikon' => '➕'],
            ['rute' => 'pesanan.daftar', 'label' => __('nav.riwayat_pesanan'), 'ikon' => '📋'],
        ],
        \App\Enums\PeranPengguna::Driver => [
            ['rute' => 'driver.pilih-mobil', 'label' => __('nav.pilih_mobil'), 'ikon' => '🚚'],
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $bahasa['html'] }}" dir="{{ $bahasa['arah'] }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('auth.subjudul') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-100 font-sans text-gray-900 antialiased">

<div class="flex min-h-full flex-col lg:flex-row">
    <aside x-data="{ buka: false }" class="bg-slate-900 text-slate-300 lg:flex lg:w-64 lg:shrink-0 lg:flex-col">
        <div class="flex items-center justify-between px-4 py-3 lg:py-5">
            <a href="{{ route($peran->beranda()) }}" wire:navigate class="flex items-center gap-2">
                <span class="grid size-9 place-items-center rounded-lg bg-blue-600 text-lg">🚚</span>
                <span class="text-sm font-semibold text-white">{{ config('app.name') }}</span>
            </a>
            <button type="button" @click="buka = !buka" class="rounded p-2 text-slate-400 hover:bg-slate-800 lg:hidden">
                <span x-show="!buka">☰</span>
                <span x-show="buka" x-cloak>✕</span>
            </button>
        </div>

        <nav :class="buka ? 'block' : 'hidden'" class="px-2 pb-4 lg:!flex lg:flex-1 lg:flex-col">
            <div class="lg:flex-1">
                @foreach ($menu as $item)
                    <a href="{{ route($item['rute']) }}" wire:navigate
                       @class([
                           'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                           'bg-blue-600 font-medium text-white' => request()->routeIs($item['rute']),
                           'hover:bg-slate-800 hover:text-white' => ! request()->routeIs($item['rute']),
                       ])>
                        <span class="text-base">{{ $item['ikon'] }}</span>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="mt-4 space-y-1 border-t border-slate-800 pt-3">
                <x-pemilih-bahasa />

                <div class="px-3 pb-1 pt-2">
                    <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500">{{ $peran->label() }}</p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-slate-800 hover:text-white">
                        <span class="text-base">🚪</span> {{ __('auth.keluar') }}
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <main class="min-w-0 flex-1">
        <div class="mx-auto max-w-[1600px] p-4 lg:p-6">
            <x-notifikasi />
            {{ $slot }}
        </div>
    </main>
</div>

@livewireScripts
</body>
</html>
