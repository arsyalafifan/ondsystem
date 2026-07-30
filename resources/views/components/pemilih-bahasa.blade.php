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
                'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm transition',
                'hover:bg-slate-800 hover:text-white' => $gaya === 'gelap',
                'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $gaya === 'terang',
            ])>
        <span class="text-base">🌐</span>
        <span class="flex-1 text-left">{{ $aktif['nama'] }}</span>
        <span class="text-xs opacity-60">▾</span>
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
                                'flex w-full items-center gap-2 px-3 py-2 text-left text-sm',
                                'bg-blue-50 font-medium text-blue-700' => $kode === $aktif['kode'],
                                'text-gray-700 hover:bg-gray-50' => $kode !== $aktif['kode'],
                            ])>
                        <span class="w-6 shrink-0 text-xs opacity-60">{{ $bahasa['singkat'] }}</span>
                        {{ $bahasa['nama'] }}
                        @if ($kode === $aktif['kode'])
                            <span class="ml-auto">✓</span>
                        @endif
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
