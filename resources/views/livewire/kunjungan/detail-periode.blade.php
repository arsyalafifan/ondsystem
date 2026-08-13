<div>
    @php $r = $periode->ringkasan; @endphp

    <x-judul-halaman :judul="$periode->kode" :keterangan="$periode->rentang">
        <x-slot:aksi>
            <button type="button" wire:click="unduhCsv"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                ⬇ {{ __('routing.unduh_csv') }}
            </button>
            <a href="{{ route('kunjungan.periode') }}" wire:navigate
               class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('umum.kembali') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    {{-- Rekap periode --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            [__('kunjungan.target'), $r['target'], 'text-gray-900'],
            [__('kunjungan.target_efektif'), $r['target_efektif'], 'text-gray-900'],
            [__('kunjungan.dikunjungi'), $r['selesai'], 'text-emerald-700'],
            [__('kunjungan.belum_dikunjungi'), $r['belum'], 'text-gray-900'],
            [__('kunjungan.toko_tutup'), $r['tutup'], 'text-gray-600'],
        ] as [$label, $nilai, $warna])
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $warna }}">{{ $nilai }}</p>
            </div>
        @endforeach
    </div>

    @if ($r['menunggu'] > 0)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-heroicon-o-exclamation-triangle class="size-4 inline" /> {{ __('kunjungan.ada_menunggu_tinjauan', ['jumlah' => $r['menunggu']]) }}
        </div>
    @endif

    {{-- Sales beserta progresnya; barisnya bisa dibentangkan ke kunjungan per toko --}}
    <div class="space-y-3">
        @foreach ($this->barisSales as $baris)
            @php $pg = $baris->progres; $dibuka = $salesDibuka === $baris->sales_id; @endphp

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <button type="button" wire:click="bukaSales({{ $baris->sales_id }})"
                        class="flex w-full items-center gap-3 p-4 text-left hover:bg-gray-50">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-lg">👤</span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="font-semibold text-gray-900">{{ $baris->sales->name }}</span>
                            <span class="text-sm tabular-nums text-gray-500">
                                {{ $pg['selesai'] }} / {{ $pg['target_efektif'] }} · {{ $pg['persen'] }}%
                            </span>
                        </span>

                        <span class="mt-1.5 block h-2 overflow-hidden rounded-full bg-gray-200">
                            <span class="block h-full rounded-full transition-all
                                         {{ $pg['persen'] === 100 ? 'bg-emerald-500' : 'bg-blue-500' }}"
                                  style="width: {{ $pg['persen'] }}%"></span>
                        </span>

                        <span class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>{{ __('kunjungan.target') }} {{ $pg['target'] }}</span>
                            <span>{{ __('kunjungan.belum_dikunjungi') }} {{ $pg['belum'] }}</span>
                            @if ($pg['tutup'] > 0)<span>{{ __('kunjungan.toko_tutup') }} {{ $pg['tutup'] }}</span>@endif
                            @if ($pg['menunggu'] > 0)
                                <span class="font-medium text-amber-700">{{ __('kunjungan.menunggu_tinjauan') }} {{ $pg['menunggu'] }}</span>
                            @endif
                        </span>
                    </span>

                    <span class="shrink-0 text-gray-400">
                        @if($dibuka)
                            <x-heroicon-o-chevron-down class="size-3 inline" />
                        @else
                            <x-heroicon-o-chevron-right class="size-3 inline" />
                        @endif
                    </span>
                </button>

                @if ($dibuka)
                    <div class="border-t border-gray-100">
                        <div class="flex flex-wrap items-center gap-2 bg-gray-50 px-4 py-2">
                            <label class="text-xs text-gray-600">{{ __('umum.status') }}</label>
                            <select wire:model.live="saringStatus"
                                    class="py-1 text-xs rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ __('kunjungan.semua_status') }}</option>
                                @foreach ($this->statusTersedia as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Kunjungan per toko --}}
                        <ul class="divide-y divide-gray-100">
                            @forelse ($this->kunjunganSales as $kunjungan)
                                <li class="flex items-start gap-3 px-4 py-3">
                                    <span class="mt-0.5 size-2.5 shrink-0 rounded-full"
                                          style="background: {{ $kunjungan->status->warna() }}"></span>

                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-900">{{ $kunjungan->toko->nama }}</p>
                                        <p class="flex flex-wrap gap-x-3 text-xs text-gray-500">
                                            <span>{{ $kunjungan->toko->kode }}</span>
                                            @if ($kunjungan->toko->asset_id)
                                                <span>{{ $kunjungan->toko->asset_id }}</span>
                                            @endif
                                            @if ($kunjungan->selesai_at)
                                                <span>{{ $kunjungan->selesai_at->isoFormat('ll') }} {{ $kunjungan->selesai_at->format('H:i') }}</span>
                                            @endif
                                            <span><x-heroicon-o-camera class="size-4 inline" /> {{ $kunjungan->fotos->count() }}/{{ $kunjungan->jumlah_foto_wajib }}</span>
                                        </p>

                                        @if ($kunjungan->catatan_sales)
                                            <p class="mt-1 rounded bg-gray-50 px-2 py-1 text-xs text-gray-700">
                                                {{ $kunjungan->catatan_sales }}
                                            </p>
                                        @endif

                                        @if ($kunjungan->lokasi_mencurigakan)
                                            <p class="mt-1 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">
                                                <x-heroicon-o-exclamation-triangle class="size-4 inline" /> {{ __('kunjungan.lokasi_jauh', ['jarak' => $kunjungan->jarak_dari_toko_m]) }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                                        <span class="rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $kunjungan->status->badge() }}">
                                            {{ $kunjungan->status->label() }}
                                        </span>

                                        <div class="flex gap-1">
                                            @if ($kunjungan->fotos->isNotEmpty())
                                                <button type="button" wire:click="$set('kunjunganDilihat', {{ $kunjungan->id }})"
                                                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                                    {{ __('kunjungan.lihat_foto') }}
                                                </button>
                                            @endif

                                            @if ($kunjungan->status->menungguAdmin())
                                                <button type="button" wire:click="bukaTinjauan({{ $kunjungan->id }})"
                                                        class="rounded-md bg-amber-600 px-2 py-1 text-xs font-semibold text-white hover:bg-amber-700">
                                                    {{ __('kunjungan.tinjau') }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-center text-sm text-gray-500">
                                    {{ __('kunjungan.belum_ada_kunjungan') }}
                                </li>
                            @endforelse
                        </ul>

                        {{-- Sisa toko yang belum tersentuh sama sekali --}}
                        @if ($this->tokoBelumDikunjungi->isNotEmpty())
                            <div class="border-t border-gray-100 bg-gray-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ __('kunjungan.sisa_toko') }} ({{ $this->tokoBelumDikunjungi->count() }})
                                </p>
                                <ul class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($this->tokoBelumDikunjungi as $toko)
                                        <li class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-700">
                                            {{ $toko->nama }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Foto bukti satu kunjungan --}}
    @if ($this->detailKunjungan)
        @php $d = $this->detailKunjungan; @endphp
        <x-modal :judul="__('kunjungan.judul_detail', ['toko' => $d->toko->nama])" lebar="max-w-3xl" tutup="$set('kunjunganDilihat', null)">
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <p class="text-xs text-gray-500">{{ __('umum.status') }}</p>
                        <span class="mt-0.5 inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $d->status->badge() }}">
                            {{ $d->status->label() }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('kunjungan.csv_sales') }}</p>
                        <p class="font-medium">{{ $d->sales->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('kunjungan.waktu_kunjungan') }}</p>
                        <p class="font-medium">
                            {{ $d->selesai_at?->isoFormat('ll') }} {{ $d->selesai_at?->format('H:i') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('kunjungan.jarak_dari_toko') }}</p>
                        <p class="font-medium">
                            {{ $d->jarak_dari_toko_m !== null ? \App\Support\Bahasa::angka($d->jarak_dari_toko_m).' m' : __('kunjungan.lokasi_tidak_ada') }}
                        </p>
                    </div>
                </div>

                @if ($d->catatan_sales)
                    <div class="rounded-lg bg-gray-50 p-3 text-sm">
                        <p class="text-xs text-gray-500">{{ __('kunjungan.laporan_sales') }}</p>
                        <p>{{ $d->catatan_sales }}</p>
                    </div>
                @endif

                @if ($d->catatan_admin || $d->peninjau)
                    <div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                        @if ($d->catatan_admin)<p>{{ $d->catatan_admin }}</p>@endif
                        @if ($d->peninjau)
                            <p class="mt-1 text-xs">
                                {{ __('kunjungan.ditinjau_oleh', [
                                    'nama' => $d->peninjau->name,
                                    'waktu' => $d->ditinjau_at?->isoFormat('ll').' '.$d->ditinjau_at?->format('H:i'),
                                ]) }}
                            </p>
                        @endif
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($d->fotos->sortBy(fn ($f) => array_search($f->jenis->value, config('visit.foto_wajib'), true)) as $foto)
                        <figure class="overflow-hidden rounded-lg border border-gray-200">
                            <a href="{{ $foto->url }}" target="_blank" rel="noopener">
                                <img src="{{ $foto->url }}" alt="{{ $foto->jenis->label() }}" class="aspect-[4/3] w-full object-cover">
                            </a>
                            <figcaption class="px-2 py-1.5 text-xs">
                                <span class="block font-medium text-gray-900">{{ $foto->jenis->label() }}</span>
                                <span class="text-gray-500">
                                    {{ $foto->diambil_at->isoFormat('ll') }} {{ $foto->diambil_at->format('H:i:s') }}
                                    @unless ($foto->punya_lokasi) · {{ __('kunjungan.lokasi_tidak_ada') }} @endunless
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </x-modal>
    @endif

    {{-- Tinjauan laporan toko tutup --}}
    @if ($kunjunganDitinjau)
        @php $t = \App\Models\Kunjungan::with('toko:id,nama')->find($kunjunganDitinjau); @endphp
        <x-modal :judul="__('kunjungan.judul_tinjauan')" tutup="$set('kunjunganDitinjau', null)">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('kunjungan.ket_tinjauan') }}</p>

                <div class="rounded-lg bg-gray-50 p-3 text-sm">
                    <p class="font-medium text-gray-900">{{ $t?->toko->nama }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('kunjungan.laporan_sales') }}</p>
                    <p class="text-gray-700">{{ $t?->catatan_sales }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('kunjungan.catatan_admin') }}</label>
                    <textarea wire:model="catatanAdmin" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tolakTutup"
                        class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                    {{ __('kunjungan.tolak_tutup') }}
                </button>
                <button type="button" wire:click="setujuiTutup"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    {{ __('kunjungan.benarkan_tutup') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
