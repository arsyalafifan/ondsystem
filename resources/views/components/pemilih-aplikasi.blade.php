@props(['peran'])

@php
    $daftarAplikasi = [
        [
            'kunci' => 'ond',
            'label' => __('nav.aplikasi_ond'),
            'ikon' => 'truck',
            'rute' => $peran->beranda(),
            'aktif' => ! \App\Support\AplikasiSaatIni::hr() && ! \App\Support\AplikasiSaatIni::userAdmin(),
        ],
        [
            'kunci' => 'hr',
            'label' => __('nav.aplikasi_hr'),
            'ikon' => 'identification',
            'rute' => 'hr.dashboard',
            'aktif' => \App\Support\AplikasiSaatIni::hr(),
        ],
        [
            'kunci' => 'accounting',
            'label' => __('nav.aplikasi_accounting'),
            'ikon' => 'calculator',
            'rute' => null,
            'aktif' => false,
        ],
    ];

    if (auth()->user()->isSuperadmin()) {
        $daftarAplikasi[] = [
            'kunci' => 'user_admin',
            'label' => __('nav.aplikasi_user_admin'),
            'ikon' => 'shield-check',
            'rute' => 'pengguna.daftar',
            'aktif' => \App\Support\AplikasiSaatIni::userAdmin(),
        ];
    }

    $terpilih = collect($daftarAplikasi)->firstWhere('aktif', true);
@endphp

{{-- Pemilih aplikasi (O&D System / HR System / Accounting / User Admin).
     Bukan form POST seperti <x-pemilih-depot /> — tidak ada state sesi yang
     ditulis, cuma tautan biasa ke rute beranda aplikasi tujuan (lihat
     App\Support\AplikasiSaatIni). --}}
<div x-data="{ buka: false }" @click.outside="buka = false" class="relative mb-2">
    <button type="button" @click="buka = !buka"
            :aria-expanded="buka.toString()"
            aria-haspopup="listbox"
            class="flex w-full items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200 hover:bg-gray-50">
        <x-heroicon-o-squares-2x2 class="size-5 text-slate-500" />
        <span class="flex-1 truncate text-left">{{ $terpilih['label'] ?? __('nav.aplikasi_ond') }}</span>
        <x-heroicon-o-chevron-down class="size-4 text-slate-400" />
    </button>

    <ul x-show="buka" x-cloak x-transition.opacity role="listbox"
        class="absolute z-50 mt-1 w-full min-w-56 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
        @foreach ($daftarAplikasi as $item)
            <li role="option" aria-selected="{{ $item['aktif'] ? 'true' : 'false' }}">
                @if ($item['rute'] !== null)
                    <a href="{{ route($item['rute']) }}" wire:navigate
                       @class([
                           'flex items-center gap-3 px-4 py-2 text-left text-sm transition-colors',
                           'bg-blue-50 font-medium text-blue-700' => $item['aktif'],
                           'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $item['aktif'],
                       ])>
                        @svg('heroicon-o-'.$item['ikon'], ['class' => 'size-4 shrink-0'])
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        @if ($item['aktif'])
                            <x-heroicon-o-check class="size-4 shrink-0 text-blue-600" />
                        @endif
                    </a>
                @else
                    <span class="flex cursor-not-allowed items-center gap-3 px-4 py-2 text-left text-sm text-gray-400">
                        @svg('heroicon-o-'.$item['ikon'], ['class' => 'size-4 shrink-0'])
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        <span class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500">
                            {{ __('nav.segera_hadir') }}
                        </span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
