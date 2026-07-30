<div>
    <x-judul-halaman :judul="__('driver.judul_pilih')" :keterangan="__('driver.ket_pilih')" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->kendaraans as $k)
            @php $persen = $k->persen_selesai; @endphp
            <button type="button" wire:click="ambil({{ $k->id }})"
                    class="rounded-xl border border-l-4 border-gray-200 bg-white p-4 text-left shadow-sm transition hover:border-gray-300 hover:shadow"
                    style="border-left-color: {{ $k->warna }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-lg font-semibold text-gray-900">🚚 {{ $k->nama }}</p>
                        @if ($k->wilayah)
                            <p class="text-sm text-gray-500">{{ $k->wilayah->nama }}</p>
                        @endif
                    </div>
                    @if ($k->driver_id === auth()->id())
                        <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">{{ __('driver.mobil_anda') }}</span>
                    @endif
                </div>

                <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('umum.toko') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ $k->total_toko }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('umum.dus') }}</dt>
                        <dd class="font-semibold tabular-nums">@angka($k->total_dus)</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('umum.jarak') }}</dt>
                        <dd class="font-semibold tabular-nums">@angka($k->jarak_km, 1) km</dd>
                    </div>
                </dl>

                @if ($persen > 0)
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>{{ __('driver.selesai_dari', ['selesai' => $k->total_selesai, 'total' => $k->total_toko]) }}</span>
                            <span>{{ $persen }}%</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-200">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $persen }}%"></div>
                        </div>
                    </div>
                @endif

                <p class="mt-3 text-sm font-medium text-blue-600">
                    {{ $k->driver_id === auth()->id() ? __('driver.lanjutkan') : __('driver.ambil_mobil') }}
                </p>
            </button>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-kartu>
                    <x-kosong ikon="🚚" :judul="__('driver.belum_ada_mobil')" :keterangan="__('driver.belum_ada_mobil_ket')" />
                </x-kartu>
            </div>
        @endforelse
    </div>

    @if ($this->riwayat->isNotEmpty())
        <h2 class="mb-3 mt-8 text-sm font-semibold text-gray-900">{{ __('driver.riwayat_anda') }}</h2>
        <x-kartu>
            <ul class="divide-y divide-gray-100">
                @foreach ($this->riwayat as $k)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span>
                            <span class="font-medium text-gray-900">{{ $k->nama }}</span>
                            <span class="text-gray-500">· {{ $k->wilayah?->nama }} · {{ $k->total_toko }} {{ __('umum.toko') }}</span>
                        </span>
                        <span class="flex items-center gap-3">
                            <span class="text-xs text-gray-400">{{ $k->updated_at->isoFormat('ll') }}</span>
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('dashboard.selesai') }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-kartu>
    @endif
</div>
