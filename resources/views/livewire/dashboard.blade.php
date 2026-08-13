<div>
    @php $t = $this->totalHariIni; $tindakan = $this->perluTindakan; @endphp

    <x-judul-halaman :judul="__('dashboard.judul')" :keterangan="__('dashboard.ket')">
        <x-slot:aksi>
            <input type="date" wire:model.live="tanggal"
                   class="rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
        </x-slot:aksi>
    </x-judul-halaman>

    {{-- Hal yang perlu ditangani --}}
    @if (array_sum($tindakan) > 0)
        <div class="mb-4 flex flex-wrap gap-2">
            @if ($tindakan['menunggu_persetujuan'] > 0)
                <a href="{{ route('pesanan.daftar') }}?status=order" wire:navigate
                   class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 hover:bg-blue-100">
                    {!! __('dashboard.menunggu_persetujuan', ['jumlah' => '<strong>'.$tindakan['menunggu_persetujuan'].'</strong>']) !!}
                </a>
            @endif
            @if ($tindakan['menunggu_routing'] > 0)
                <a href="{{ route('routing.generate') }}" wire:navigate
                   class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm text-violet-900 hover:bg-violet-100">
                    {!! __('dashboard.menunggu_routing', ['jumlah' => '<strong>'.$tindakan['menunggu_routing'].'</strong>']) !!}
                </a>
            @endif
            @if ($tindakan['tanpa_koordinat'] > 0)
                <a href="{{ route('master.toko') }}?tanpaKoordinat=1" wire:navigate
                   class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 hover:bg-amber-100">
                    {!! __('dashboard.toko_tanpa_koordinat', ['jumlah' => '<strong>'.$tindakan['tanpa_koordinat'].'</strong>']) !!}
                </a>
            @endif
        </div>
    @endif

    {{-- Ringkasan status --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($this->ringkasan as $r)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <x-badge-status :status="$r['status']" />
                <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-900">{{ $r['jumlah'] }}</p>
                <p class="text-xs text-gray-500">{{ $r['status']->keterangan() }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
        {{-- Progres kendaraan --}}
        <div class="lg:col-span-2">
            <x-kartu :judul="__('dashboard.progres_kendaraan')">
                <x-slot:aksi>
                    @if ($t['kendaraan'] > 0)
                        <span class="text-xs text-gray-500">
                            {{ __('dashboard.dus_terkirim', ['terkirim' => \App\Support\Bahasa::angka($t['dus_terkirim']), 'target' => \App\Support\Bahasa::angka($t['dus_target'])]) }} · {{ $t['persen'] }}%
                        </span>
                    @endif
                </x-slot:aksi>

                @forelse ($this->kendaraans as $k)
                    @php $persen = $k->persen_selesai; @endphp
                    <button type="button" wire:click="$set('kendaraanDilihat', {{ $k->id }})"
                            class="flex w-full items-center gap-3 border-b border-gray-100 px-4 py-3 text-left last:border-0 hover:bg-gray-50">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg text-white"
                              style="background: {{ $k->warna }}"><x-heroicon-o-truck class="size-4 inline" /></span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ $k->nama }}</span>
                                <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ $persen }}%</span>
                            </span>
                            <span class="block text-xs text-gray-500">
                                {{ __('dashboard.dus_terkirim', ['terkirim' => \App\Support\Bahasa::angka($k->dus_terkirim), 'target' => \App\Support\Bahasa::angka($k->target_dus)]) }}
                                @if ($k->total_dibatalkan > 0)
                                    · <span class="text-rose-700">{{ __('dashboard.dibatalkan_jumlah', ['jumlah' => $k->total_dibatalkan]) }}</span>
                                @endif
                                @if ($k->driver)
                                    · {{ $k->driver->name }}
                                @else
                                    · <span class="text-amber-700">{{ __('dashboard.belum_diambil') }}</span>
                                @endif
                            </span>
                            <span class="mt-1.5 block h-2 overflow-hidden rounded-full bg-gray-200">
                                <span class="block h-full rounded-full transition-all"
                                      style="width: {{ $persen }}%; background: {{ $persen === 100 ? '#059669' : $k->warna }}"></span>
                            </span>
                        </span>
                    </button>
                @empty
                    <x-kosong ikon="truck" :judul="__('dashboard.belum_ada_kendaraan')"
                              :keterangan="__('dashboard.belum_ada_kendaraan_ket')" />
                @endforelse

                @if ($t['kendaraan'] > 0)
                    <div class="grid grid-cols-3 gap-px border-t border-gray-200 bg-gray-200 text-center">
                        @foreach ([
                            [__('umum.mobil'), \App\Support\Bahasa::angka($t['kendaraan'])],
                            [__('umum.dus'), \App\Support\Bahasa::angka($t['dus'])],
                            [__('umum.jarak'), \App\Support\Bahasa::angka($t['jarak_km'], 1).' km'],
                        ] as [$label, $nilai])
                            <div class="bg-white px-2 py-3">
                                <p class="text-xs text-gray-500">{{ $label }}</p>
                                <p class="font-semibold tabular-nums text-gray-900">{{ $nilai }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-kartu>
        </div>

        {{-- Peta --}}
        <div class="lg:col-span-3">
            <x-kartu :judul="__('dashboard.peta_monitoring')">
                <div wire:ignore id="peta-monitoring" class="peta h-[520px]"></div>

                <div class="flex flex-wrap gap-3 border-t border-gray-200 p-3 text-xs">
                    @foreach (\App\Enums\StatusPesanan::cases() as $s)
                        <span class="flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full" style="background: {{ $s->warna() }}"></span>
                            {{ $s->label() }}
                        </span>
                    @endforeach
                </div>
            </x-kartu>
        </div>
    </div>

    {{-- Rincian kendaraan --}}
    @if ($this->detailKendaraan)
        @php $k = $this->detailKendaraan; @endphp
        <x-modal :judul="__('dashboard.judul_rincian', ['mobil' => $k->nama])" lebar="max-w-2xl" tutup="$set('kendaraanDilihat', null)">
            <div class="p-5">
                <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    @foreach ([
                        [__('dashboard.total_toko'), $k->total_toko],
                        [__('dashboard.selesai'), $k->total_selesai],
                        [__('dashboard.dibatalkan'), $k->total_dibatalkan],
                        [__('dashboard.belum'), $k->total_belum],
                        [__('dashboard.total_dus'), \App\Support\Bahasa::angka($k->target_dus)],
                        [__('dashboard.dus_sudah_kirim'), \App\Support\Bahasa::angka($k->dus_terkirim)],
                        [__('dashboard.dus_sisa_mobil'), \App\Support\Bahasa::angka($k->dus_tersisa)],
                        [__('dashboard.persen_dus'), $k->persen_selesai.'%'],
                    ] as [$label, $nilai])
                        <div>
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                            <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $nilai }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                    <span>{{ __('umum.wilayah') }}: <strong class="text-gray-900">{{ $k->wilayah?->nama ?? '—' }}</strong></span>
                    <span>{{ __('umum.driver') }}: <strong class="text-gray-900">{{ $k->driver?->name ?? __('dashboard.belum_diambil_singkat') }}</strong></span>
                    <span>{{ __('umum.jarak') }}: <strong class="text-gray-900">@angka($k->jarak_km, 1) km</strong></span>
                    @if ($k->estimasi_selesai)
                        <span>{{ __('dashboard.estimasi_selesai') }}: <strong class="text-gray-900">{{ substr((string) $k->estimasi_selesai, 0, 5) }}</strong></span>
                    @endif
                </div>

                <div class="mt-4 max-h-80 overflow-y-auto rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">#</th>
                                <th class="px-3 py-2 font-medium">{{ __('umum.toko') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('umum.dus') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('umum.eta') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('umum.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($k->stops as $stop)
                                <tr>
                                    <td class="px-3 py-2 tabular-nums text-gray-500">{{ $stop->urutan }}</td>
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-gray-900">{{ $stop->toko->nama }}</span>
                                        <span class="block text-xs text-gray-500">{{ $stop->pesanan->kode }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">@angka($stop->total_dus)</td>
                                    <td class="px-3 py-2 tabular-nums text-gray-600">{{ $stop->eta ? substr((string) $stop->eta, 0, 5) : '—' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($stop->status === \App\Enums\StatusStop::Selesai)
                                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                                {{ __('driver.selesai_pukul', ['waktu' => $stop->selesai_at?->format('H:i')]) }}
                                            </span>
                                        @else
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('dashboard.belum') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </x-modal>
    @endif

    @script
    <script>
        const peta = window.pasangPetaRute('peta-monitoring', @js($this->konfigPeta));

        if (peta) {
            peta.gambar(@js($this->dataPeta));

            $wire.on('peta-diperbarui', (payload) => {
                peta.gambar(payload.data ?? payload[0]?.data ?? payload);
            });
        }
    </script>
    @endscript
</div>
