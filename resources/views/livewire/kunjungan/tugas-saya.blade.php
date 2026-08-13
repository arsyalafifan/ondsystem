<div>
    @php $p = $this->progres; $periode = $this->periode; @endphp

    <x-judul-halaman :judul="__('kunjungan.judul_tugas')" :keterangan="__('kunjungan.ket_tugas')">
        <x-slot:aksi>
            <a href="{{ route('kunjungan.kunjungi') }}" wire:navigate
               class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <x-heroicon-o-camera class="size-4 inline" /> {{ __('nav.mulai_kunjungan') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    {{-- Ringkasan minggu berjalan --}}
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm text-gray-600">
                {{ __('kunjungan.periode') }} <strong class="text-gray-900">{{ $periode->kode }}</strong>
                · {{ $periode->rentang }}
            </p>
            <span class="text-lg font-semibold tabular-nums text-gray-900">{{ $p['persen'] }}%</span>
        </div>

        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $p['persen'] }}%"></div>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3 text-center sm:grid-cols-4">
            @foreach ([
                [__('kunjungan.target_efektif'), $p['target_efektif']],
                [__('kunjungan.dikunjungi'), $p['selesai']],
                [__('kunjungan.belum_dikunjungi'), $p['belum']],
                [__('kunjungan.toko_tutup'), $p['tutup']],
            ] as [$label, $nilai])
                <div class="rounded-lg bg-gray-50 px-2 py-2">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $nilai }}</p>
                </div>
            @endforeach
        </div>

        @if ($p['tutup'] > 0)
            <p class="mt-2 text-xs text-gray-500">{{ __('kunjungan.ket_target_efektif') }}</p>
        @endif
    </div>

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="min-w-56 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.300ms="cari"
                       placeholder="{{ __('master.cari_toko') }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.status') }}</label>
                <select wire:model.live="saringStatus"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('kunjungan.semua_status') }}</option>
                    <option value="belum">{{ __('kunjungan.saring_belum') }}</option>
                    @foreach (\App\Enums\StatusKunjungan::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <ul class="divide-y divide-gray-100">
            @forelse ($this->tokos as $toko)
                @php $kunjungan = $toko->kunjungans->first(); @endphp
                <li class="flex items-start gap-3 p-3">
                    <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg text-lg"
                          style="background: {{ $kunjungan ? $kunjungan->status->warna().'22' : '#f3f4f6' }}">
                        @if($kunjungan?->status->sudahTuntas())
                            <x-heroicon-s-check-circle class="size-5 text-emerald-500 inline" />
                        @elseif($kunjungan)
                            <x-heroicon-o-clock class="size-5 text-amber-500 inline" />
                        @else
                            <x-heroicon-o-building-storefront class="size-5 text-gray-500 inline" />
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-900">{{ $toko->nama }}</p>
                        <p class="truncate text-sm text-gray-600">{{ $toko->alamat }}</p>
                        <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-gray-500">
                            <span>{{ $toko->kode }}</span>
                            @if ($toko->asset_id)
                                <span>{{ __('kunjungan.asset_id') }}: {{ $toko->asset_id }}</span>
                            @else
                                <span class="text-amber-700">{{ __('master.belum_ada_koordinat') }}</span>
                            @endif
                            @if ($toko->wilayah)<span>{{ $toko->wilayah->nama }}</span>@endif
                        </p>

                        @if ($kunjungan)
                            <span class="mt-1.5 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $kunjungan->status->badge() }}">
                                {{ $kunjungan->status->label() }}
                                @if ($kunjungan->selesai_at)
                                    · {{ $kunjungan->selesai_at->format('H:i') }}
                                @endif
                            </span>
                        @endif
                    </div>

                    @if ($toko->punya_koordinat)
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $toko->latitude }},{{ $toko->longitude }}"
                           target="_blank" rel="noopener" title="{{ __('driver.buka_navigasi') }}"
                           class="shrink-0 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm hover:bg-gray-50"><x-heroicon-o-paper-airplane class="size-4 inline" /></a>
                    @endif
                </li>
            @empty
                <li>
                    <x-kosong ikon="building-storefront" :judul="__('kunjungan.tugas_kosong')" :keterangan="__('kunjungan.tugas_kosong_ket')" />
                </li>
            @endforelse
        </ul>
    </x-kartu>

    @if ($this->riwayat->isNotEmpty())
        <h2 class="mb-3 mt-8 text-sm font-semibold text-gray-900">{{ __('kunjungan.riwayat_kunjungan') }}</h2>
        <x-kartu>
            <ul class="divide-y divide-gray-100">
                @foreach ($this->riwayat as $r)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-gray-900">{{ $r->toko->nama }}</span>
                            <span class="text-xs text-gray-500">{{ $r->periode->kode }} · {{ $r->periode->rentang }}</span>
                        </span>
                        <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $r->status->badge() }}">
                            {{ $r->status->label() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-kartu>
    @endif
</div>
