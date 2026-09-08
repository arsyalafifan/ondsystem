@props(['gaya' => 'gelap'])

@php
    $daftar = \App\Models\Depot::query()->aktif()->orderBy('nama')->get();
    $modeAktif = \App\Support\DepotContext::mode();
    $depotAktif = \App\Support\DepotContext::current();
    $namaAktif = $modeAktif === \App\Support\ModeDepot::SemuaDepot ? __('umum.semua_depot') : ($depotAktif->nama ?? '—');
@endphp

{{-- Sama seperti alasan <x-pemilih-bahasa /> pakai form POST biasa (bukan
     Livewire): ganti depot harus memuat ulang seluruh halaman, supaya
     tidak ada data depot lama yang tertinggal tergambar di komponen yang
     sudah ter-mount sebelumnya. --}}
<div x-data="{ buka: false }" @click.outside="buka = false" class="relative">
    <button type="button" @click="buka = !buka"
            :aria-expanded="buka.toString()"
            aria-haspopup="listbox"
            :title="'{{ __('auth.depot') }}'"
            @class([
                'flex w-full items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-all duration-200',
                'bg-slate-50 text-slate-700 hover:bg-slate-100' => $gaya === 'gelap',
                'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 shadow-sm' => $gaya === 'terang',
            ])>
        <x-heroicon-o-building-storefront class="size-5 text-slate-500" />
        <span class="flex-1 truncate text-left">{{ $namaAktif }}</span>
        <x-heroicon-o-chevron-down class="size-4 text-slate-400" />
    </button>

    <ul x-show="buka" x-cloak x-transition.opacity role="listbox"
        @class([
            'absolute z-50 w-full min-w-56 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg',
            'bottom-full mb-1' => $gaya === 'gelap',
            'top-full mt-1 right-0' => $gaya === 'terang',
        ])>
        @foreach ($daftar as $depot)
            @php $terpilih = $modeAktif === \App\Support\ModeDepot::Terkunci && $depotAktif?->id === $depot->id; @endphp
            <li role="option" aria-selected="{{ $terpilih ? 'true' : 'false' }}">
                <form method="POST" action="{{ route('depot.ganti') }}">
                    @csrf
                    <input type="hidden" name="depot_id" value="{{ $depot->id }}">
                    <button type="submit"
                            @class([
                                'flex w-full items-center gap-3 px-4 py-2 text-left text-sm transition-colors',
                                'bg-blue-50 font-medium text-blue-700' => $terpilih,
                                'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $terpilih,
                            ])>
                        <span class="flex-1 truncate">{{ $depot->nama }}</span>
                        @if ($terpilih)
                            <x-heroicon-o-check class="size-4 ml-auto text-blue-600" />
                        @endif
                    </button>
                </form>
            </li>
        @endforeach

        <li class="my-1 border-t border-gray-100"></li>

        @php $semuaTerpilih = $modeAktif === \App\Support\ModeDepot::SemuaDepot; @endphp
        <li role="option" aria-selected="{{ $semuaTerpilih ? 'true' : 'false' }}">
            <form method="POST" action="{{ route('depot.ganti') }}">
                @csrf
                <input type="hidden" name="depot_id" value="semua">
                <button type="submit"
                        @class([
                            'flex w-full items-center gap-3 px-4 py-2 text-left text-sm transition-colors',
                            'bg-blue-50 font-medium text-blue-700' => $semuaTerpilih,
                            'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $semuaTerpilih,
                        ])>
                    <span class="flex-1">{{ __('umum.semua_depot') }}</span>
                    @if ($semuaTerpilih)
                        <x-heroicon-o-check class="size-4 ml-auto text-blue-600" />
                    @endif
                </button>
            </form>
        </li>
    </ul>
</div>
