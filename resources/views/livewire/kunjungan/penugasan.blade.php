<div>
    <x-judul-halaman :judul="__('kunjungan.judul_penugasan')" :keterangan="__('kunjungan.ket_penugasan')">
        <x-slot:aksi>
            <input type="month" wire:model.live="bulan"
                   class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <button type="button" wire:click="bukaSalin"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('kunjungan.salin_bulan_lalu') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($this->tanpaAssetId > 0)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            ⚠ {{ __('kunjungan.tanpa_asset_id', ['jumlah' => $this->tanpaAssetId]) }} —
            <a href="{{ route('master.toko') }}" wire:navigate class="font-semibold underline">{{ __('nav.master_toko') }}</a>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Daftar sales --}}
        <div class="lg:col-span-1">
            <x-kartu :judul="__('status.peran_sales')">
                <x-slot:aksi>
                    <span class="text-xs text-gray-500">{{ __('kunjungan.belum_ditugaskan', ['jumlah' => $this->jumlahBelumDitugaskan]) }}</span>
                </x-slot:aksi>

                <ul class="divide-y divide-gray-100">
                    @forelse ($this->salesList as $s)
                        @php $penuh = $s->jumlah_toko >= $this->maksToko; @endphp
                        <li>
                            <button type="button" wire:click="pilihSales({{ $s->id }})"
                                    @class([
                                        'flex w-full items-center gap-3 px-4 py-3 text-left',
                                        'bg-blue-50' => $salesDipilih === $s->id,
                                        'hover:bg-gray-50' => $salesDipilih !== $s->id,
                                    ])>
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-gray-100 text-base">👤</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-gray-900">{{ $s->name }}</span>
                                    <span class="text-xs {{ $penuh ? 'font-medium text-amber-700' : 'text-gray-500' }}">
                                        {{ __('kunjungan.toko_dipegang', ['jumlah' => $s->jumlah_toko, 'batas' => $this->maksToko]) }}
                                        @if ($penuh) · {{ __('kunjungan.kuota_penuh') }} @endif
                                    </span>
                                </span>
                                @if ($salesDipilih === $s->id)
                                    <span class="shrink-0 text-blue-600">●</span>
                                @endif
                            </button>
                        </li>
                    @empty
                        <li><x-kosong ikon="👤" :judul="__('kunjungan.tugas_kosong')" /></li>
                    @endforelse
                </ul>
            </x-kartu>
        </div>

        {{-- Pemilihan toko --}}
        <div class="lg:col-span-2">
            <x-kartu :judul="__('kunjungan.sales_terpilih').': '.($this->salesList->firstWhere('id', $salesDipilih)?->name ?? '—')">
                <x-slot:aksi>
                    @php $sisa = $this->maksToko - count($terpilih); @endphp
                    <span @class([
                        'text-xs',
                        'font-medium text-amber-700' => $sisa <= 0,
                        'text-gray-500' => $sisa > 0,
                    ])>
                        {{ count($terpilih) }} / {{ $this->maksToko }}
                        @if ($sisa > 0) · {{ __('kunjungan.sisa_kuota', ['jumlah' => $sisa]) }} @endif
                    </span>
                </x-slot:aksi>

                <div class="flex flex-wrap items-end gap-2 border-b border-gray-200 p-4">
                    <div class="min-w-56 flex-1">
                        <input type="search" wire:model.live.debounce.300ms="cari"
                               placeholder="{{ __('kunjungan.cari_toko_penugasan') }}"
                               class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <button type="button" wire:click="pilihSemuaTampil"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        {{ __('kunjungan.pilih_semua_tampil') }}
                    </button>
                    <button type="button" wire:click="kosongkan"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        {{ __('kunjungan.kosongkan_pilihan') }}
                    </button>
                </div>

                <div class="max-h-[28rem] divide-y divide-gray-100 overflow-y-auto">
                    @forelse ($this->tokoTersedia as $toko)
                        @php
                            $dipilih = in_array($toko->id, $terpilih, true);
                            $kuotaHabis = ! $dipilih && count($terpilih) >= $this->maksToko;
                        @endphp
                        <label @class([
                            'flex items-start gap-3 px-4 py-2.5 text-sm',
                            'bg-blue-50/60' => $dipilih,
                            'opacity-50' => $kuotaHabis,
                            'hover:bg-gray-50' => ! $dipilih && ! $kuotaHabis,
                        ])>
                            <input type="checkbox" wire:model.live="terpilih" value="{{ $toko->id }}"
                                   @disabled($kuotaHabis)
                                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">

                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-gray-900">{{ $toko->nama }}</span>
                                <span class="block truncate text-xs text-gray-500">
                                    {{ $toko->kode }}
                                    @if ($toko->asset_id) · {{ $toko->asset_id }} @endif
                                    @if ($toko->wilayah) · {{ $toko->wilayah->nama }} @endif
                                    · {{ $toko->alamat }}
                                </span>
                            </span>

                            @unless ($toko->asset_id)
                                <span class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">
                                    {{ __('kunjungan.asset_id') }} —
                                </span>
                            @endunless
                        </label>
                    @empty
                        <x-kosong ikon="🏪" :judul="__('master.toko_kosong')" :keterangan="__('master.toko_kosong_ket')" />
                    @endforelse
                </div>

                <div class="flex justify-end border-t border-gray-200 p-4">
                    <button type="button" wire:click="simpan" wire:loading.attr="disabled"
                            @disabled($salesDipilih === null)
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-300">
                        <span wire:loading.remove wire:target="simpan">{{ __('umum.simpan') }}</span>
                        <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                    </button>
                </div>
            </x-kartu>
        </div>
    </div>

    @if ($konfirmasiSalin)
        <x-modal :judul="__('kunjungan.judul_salin')" tutup="$set('konfirmasiSalin', false)">
            <div class="space-y-3 p-5">
                <p class="text-sm text-gray-600">{{ __('kunjungan.ket_salin') }}</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('kunjungan.bulan_sumber') }}</label>
                    <input type="month" wire:model="bulanSumber"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiSalin', false)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="salinDariBulanLalu"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('umum.simpan') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
