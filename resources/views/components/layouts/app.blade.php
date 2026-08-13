@php
    $bahasa = \App\Support\Bahasa::info();
    $peran = auth()->user()->role;

    $menuAdmin = [
        ['rute' => 'dashboard', 'label' => __('nav.dashboard'), 'ikon' => 'chart-pie'],
        ['rute' => 'pesanan.daftar', 'label' => __('nav.pesanan'), 'ikon' => 'clipboard-document-list'],
        ['rute' => 'pesanan.buat', 'label' => __('nav.input_pesanan'), 'ikon' => 'plus-circle'],
        ['rute' => 'routing.generate', 'label' => __('nav.generate_routing'), 'ikon' => 'map'],
        ['rute' => 'routing.riwayat', 'label' => __('nav.riwayat_routing'), 'ikon' => 'clock'],
        ['rute' => 'kunjungan.periode', 'label' => __('nav.visit_sales'), 'ikon' => 'paper-airplane'],
        ['rute' => 'kunjungan.penugasan', 'label' => __('nav.penugasan'), 'ikon' => 'folder-open'],
        ['label' => __('nav.pembayaran'), 'ikon' => 'banknotes', 'anak' => [
            ['rute' => 'pembayaran.pelunasan', 'label' => __('nav.pelunasan'), 'ikon' => 'check-circle'],
            ['rute' => 'pembayaran.belum-lunas', 'label' => __('nav.belum_lunas'), 'ikon' => 'exclamation-circle'],
            ['rute' => 'pembayaran.pendapatan', 'label' => __('nav.pendapatan'), 'ikon' => 'chart-bar'],
        ]],
        ['label' => __('nav.master'), 'ikon' => 'archive-box', 'anak' => [
            ['rute' => 'master.toko', 'label' => __('nav.master_toko'), 'ikon' => 'building-storefront'],
            ['rute' => 'master.produk', 'label' => __('nav.master_produk'), 'ikon' => 'cube'],
            ['rute' => 'master.wilayah', 'label' => __('nav.master_wilayah'), 'ikon' => 'map-pin'],
        ]],
        ['label' => __('nav.pengiriman'), 'ikon' => 'truck', 'anak' => [
            ['rute' => 'driver.pilih-mobil', 'label' => __('nav.pengiriman_driver'), 'ikon' => 'truck'],
        ]],
    ];

    $menu = match ($peran) {
        \App\Enums\PeranPengguna::Admin => $menuAdmin,
        \App\Enums\PeranPengguna::Superadmin => [
            ...$menuAdmin,
            ['rute' => 'pengguna.daftar', 'label' => __('nav.manage_pengguna'), 'ikon' => 'user-group'],
        ],
        \App\Enums\PeranPengguna::Sales => [
            ['rute' => 'kunjungan.kunjungi', 'label' => __('nav.mulai_kunjungan'), 'ikon' => 'camera'],
            ['rute' => 'kunjungan.tugas', 'label' => __('nav.tugas_saya'), 'ikon' => 'paper-airplane'],
            ['rute' => 'pesanan.buat', 'label' => __('nav.input_pesanan'), 'ikon' => 'plus-circle'],
            ['rute' => 'pesanan.daftar', 'label' => __('nav.riwayat_pesanan'), 'ikon' => 'clipboard-document-list'],
        ],
        \App\Enums\PeranPengguna::Driver => [
            ['label' => __('nav.pengiriman'), 'ikon' => 'truck', 'anak' => [
                ['rute' => 'driver.pilih-mobil', 'label' => __('nav.pengiriman_driver'), 'ikon' => 'truck'],
            ]],
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
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased selection:bg-blue-100 selection:text-blue-900">

<div x-data="{
        sidebarTerbuka: window.innerWidth >= 1024 ? localStorage.getItem('sidebarTerbuka') !== '0' : true,
    }"
    x-init="$watch('sidebarTerbuka', v => localStorage.setItem('sidebarTerbuka', v ? '1' : '0'))"
    class="flex min-h-full flex-col lg:flex-row">
    <aside x-data="{ buka: false }"
           :class="sidebarTerbuka ? 'lg:w-72' : 'lg:w-0 lg:overflow-hidden lg:border-r-0'"
           class="bg-white border-b lg:border-b-0 lg:border-r border-slate-200 text-slate-600 lg:flex lg:shrink-0 lg:flex-col shadow-sm lg:shadow-none z-10 lg:transition-[width] lg:duration-200">
        <div class="flex items-center justify-between px-6 py-4 lg:py-6 lg:w-72">
            <a href="{{ route($peran->beranda()) }}" wire:navigate class="flex items-center gap-3 transition-opacity hover:opacity-80">
                <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-600/20">
                    <x-heroicon-s-truck class="size-6" />
                </div>
                <span class="text-lg font-bold text-slate-800 tracking-tight">{{ config('app.name') }}</span>
            </a>
            <button type="button" @click="buka = !buka" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:hidden transition-colors shrink-0">
                <x-heroicon-o-bars-3 x-show="!buka" class="size-6" />
                <x-heroicon-o-x-mark x-show="buka" x-cloak class="size-6" />
            </button>
            <button type="button" @click="sidebarTerbuka = false" class="hidden rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:block transition-colors shrink-0">
                <x-heroicon-o-chevron-double-left class="size-5" />
            </button>
        </div>

        <nav :class="buka ? 'block' : 'hidden'" class="px-4 pb-6 lg:!flex lg:flex-1 lg:flex-col lg:w-72 overflow-y-auto">
            <div class="lg:flex-1 space-y-1">
                @foreach ($menu as $item)
                    @if (isset($item['anak']))
                        @php $aktifGrup = collect($item['anak'])->contains(fn ($a) => request()->routeIs($a['rute'])); @endphp
                        <div x-data="{ buka: @js($aktifGrup) }">
                            <button type="button" @click="buka = !buka"
                                    @class([
                                        'flex w-full items-center gap-3.5 rounded-xl px-4 py-2.5 text-sm font-medium transition-all duration-200',
                                        'bg-blue-50 text-blue-700 shadow-sm' => $aktifGrup,
                                        'text-slate-500 hover:bg-slate-50 hover:text-slate-900' => ! $aktifGrup,
                                    ])>
                                @svg('heroicon-o-'.$item['ikon'], ['class' => 'size-5 flex-shrink-0' . ($aktifGrup ? ' text-blue-600' : ' text-slate-400 group-hover:text-slate-600')])
                                <span class="flex-1 text-left">{{ $item['label'] }}</span>
                                <span class="size-4 shrink-0 transition-transform" :class="{ 'rotate-180': buka }">
                                    <x-heroicon-o-chevron-down class="size-4" />
                                </span>
                            </button>
                            <div x-show="buka" x-cloak x-transition class="ml-8 mt-1 space-y-1">
                                @foreach ($item['anak'] as $anak)
                                    <a href="{{ route($anak['rute']) }}" wire:navigate
                                       @class([
                                           'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200',
                                           'bg-blue-50 text-blue-700' => request()->routeIs($anak['rute']),
                                           'text-slate-500 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs($anak['rute']),
                                       ])>
                                        @svg('heroicon-o-'.$anak['ikon'], ['class' => 'size-4 flex-shrink-0'])
                                        {{ $anak['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ route($item['rute']) }}" wire:navigate
                           @class([
                               'flex items-center gap-3.5 rounded-xl px-4 py-2.5 text-sm font-medium transition-all duration-200',
                               'bg-blue-50 text-blue-700 shadow-sm' => request()->routeIs($item['rute']),
                               'text-slate-500 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs($item['rute']),
                           ])>
                            @svg('heroicon-o-'.$item['ikon'], ['class' => 'size-5 flex-shrink-0' . (request()->routeIs($item['rute']) ? ' text-blue-600' : ' text-slate-400 group-hover:text-slate-600')])
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>

            <div class="mt-8 space-y-2 border-t border-slate-100 pt-6">
                <x-pemilih-bahasa />

                <a href="{{ route('akun.kata-sandi') }}" wire:navigate
                   class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 border border-slate-100 hover:bg-slate-100 hover:border-slate-200 transition-colors">
                    <div class="h-9 w-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm shrink-0">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs font-medium text-slate-500">{{ $peran->label() }}</p>
                    </div>
                    <x-heroicon-o-key class="size-4 text-slate-400 shrink-0" />
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="group flex w-full items-center gap-3.5 rounded-xl px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 transition-all duration-200">
                        <x-heroicon-o-arrow-right-start-on-rectangle class="size-5 text-red-500 opacity-70 group-hover:opacity-100 transition-opacity" /> 
                        {{ __('auth.keluar') }}
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <main class="min-w-0 flex-1 relative">
        <div class="absolute inset-0 bg-slate-50/50 pointer-events-none"></div>

        <button type="button" x-show="!sidebarTerbuka" x-cloak @click="sidebarTerbuka = true"
                class="fixed top-4 left-4 z-20 hidden rounded-lg border border-slate-200 bg-white p-2 text-slate-400 shadow-sm hover:bg-slate-50 hover:text-slate-600 lg:block transition-colors">
            <x-heroicon-o-chevron-double-right class="size-5" />
        </button>

        <div class="mx-auto max-w-[1600px] p-4 lg:p-8 relative">
            <x-notifikasi />
            {{ $slot }}
        </div>
    </main>
</div>

@livewireScripts
</body>
</html>
