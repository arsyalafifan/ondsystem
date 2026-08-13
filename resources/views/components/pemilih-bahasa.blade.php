@props(['gaya' => 'gelap'])

@php
    $aktif = \App\Support\Bahasa::info();
    $daftar = \App\Support\Bahasa::tersedia();
@endphp

{{-- Formulir biasa, bukan komponen Livewire, supaya halaman dimuat ulang
     seluruhnya. Tanpa muat ulang, teks yang sudah tergambar di layar akan
     tertinggal dalam bahasa lama. --}}
<div x-data="{ buka: false }" @click.outside="buka = false" class="relative">
    <button type="button" @click="buka = !buka"
            :aria-expanded="buka.toString()"
            aria-haspopup="listbox"
            :title="'{{ __('umum.pilih_bahasa') }}'"
            @class([
                'flex w-full items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all duration-200',
                'bg-slate-50 text-slate-700 hover:bg-slate-100' => $gaya === 'gelap',
                'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 shadow-sm' => $gaya === 'terang',
            ])>
        <x-heroicon-o-language class="size-5 text-slate-500" />
        <span class="flex-1 text-left">{{ $aktif['nama'] }}</span>
        <x-heroicon-o-chevron-down class="size-4 text-slate-400" />
    </button>

    <ul x-show="buka" x-cloak x-transition.opacity role="listbox"
        @class([
            'absolute z-50 w-full min-w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg',
            'bottom-full mb-1' => $gaya === 'gelap',
            'top-full mt-1 right-0' => $gaya === 'terang',
        ])>
        @foreach ($daftar as $kode => $bahasa)
            <li role="option" aria-selected="{{ $kode === $aktif['kode'] ? 'true' : 'false' }}">
                <form method="POST" action="{{ route('bahasa.ubah') }}">
                    @csrf
                    <input type="hidden" name="kode" value="{{ $kode }}">
                    <button type="submit"
                            @class([
                                'flex w-full items-center gap-3 px-4 py-2 text-left text-sm transition-colors',
                                'bg-blue-50 font-medium text-blue-700' => $kode === $aktif['kode'],
                                'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => $kode !== $aktif['kode'],
                            ])>
                        <span class="w-6 shrink-0 text-xs font-semibold text-slate-400">{{ $bahasa['singkat'] }}</span>
                        <span class="flex-1">{{ $bahasa['nama'] }}</span>
                        @if ($kode === $aktif['kode'])
                            <x-heroicon-o-check class="size-4 ml-auto text-blue-600" />
                        @endif
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
