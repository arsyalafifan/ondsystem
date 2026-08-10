<div>
    <x-judul-halaman :judul="__('kunjungan.judul_periode')" :keterangan="__('kunjungan.ket_periode')">
        <x-slot:aksi>
            <a href="{{ route('kunjungan.penugasan') }}" wire:navigate
               class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                🗂️ {{ __('nav.penugasan') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($this->menungguTinjauan > 0)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            ⚠ {{ __('kunjungan.ada_menunggu_tinjauan', ['jumlah' => $this->menungguTinjauan]) }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->periodes as $periode)
            @php $r = $periode->ringkasan; @endphp

            <a href="{{ route('kunjungan.periode.lihat', $periode) }}" wire:navigate
               @class([
                   'rounded-xl border bg-white p-4 shadow-sm transition hover:shadow',
                   'border-blue-500 ring-1 ring-blue-500' => $periode->berjalan(),
                   'border-gray-200 hover:border-gray-300' => ! $periode->berjalan(),
               ])>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $periode->kode }}</p>
                        <p class="text-xs text-gray-500">{{ $periode->rentang }}</p>
                    </div>
                    @if ($periode->berjalan())
                        <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                            {{ __('kunjungan.berjalan') }}
                        </span>
                    @else
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                            {{ __('kunjungan.periode_selesai') }}
                        </span>
                    @endif
                </div>

                <div class="mt-3 flex items-baseline justify-between text-sm">
                    <span class="text-gray-600">
                        <strong class="text-gray-900">{{ $r['selesai'] }}</strong> / {{ $r['target_efektif'] }}
                        {{ __('umum.toko') }}
                    </span>
                    <span class="font-semibold tabular-nums text-gray-900">{{ $r['persen'] }}%</span>
                </div>

                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full {{ $r['persen'] === 100 ? 'bg-emerald-500' : 'bg-blue-500' }}"
                         style="width: {{ $r['persen'] }}%"></div>
                </div>

                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                    <span>👤 {{ $periode->periodeSales->count() }}</span>
                    @if ($r['tutup'] > 0)<span>🚪 {{ $r['tutup'] }}</span>@endif
                    @if ($r['menunggu'] > 0)
                        <span class="font-medium text-amber-700">⏳ {{ $r['menunggu'] }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-kartu>
                    <x-kosong ikon="🧭" :judul="__('kunjungan.periode_kosong')" :keterangan="__('kunjungan.periode_kosong_ket')" />
                </x-kartu>
            </div>
        @endforelse
    </div>

    @if ($this->periodes->hasPages())
        <div class="mt-4">{{ $this->periodes->links() }}</div>
    @endif
</div>
