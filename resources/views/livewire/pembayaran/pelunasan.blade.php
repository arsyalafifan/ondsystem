<div>
    <x-judul-halaman :judul="__('pembayaran.judul_pelunasan')" :keterangan="__('pembayaran.ket_pelunasan')">
        <x-slot:aksi>
            <input type="date" wire:model.live="tanggal"
                   class="rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($this->kendaraans->isEmpty())
        <x-kartu>
            <x-kosong ikon="banknotes" :judul="__('pembayaran.tidak_ada_selesai')" />
        </x-kartu>
    @else
        <div class="space-y-4">
            @foreach ($this->kendaraans as $k)
                @php $r = $this->ringkasan[$k->id]; @endphp
                <x-kartu>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $k->nama }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $k->wilayah?->nama ?? '—' }} · {{ $k->driver?->name ?? '—' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="text-right">
                                <p class="text-xs text-gray-500">{{ __('pembayaran.terkumpul') }}</p>
                                <p class="font-semibold tabular-nums text-gray-900">@rupiah($r['lunas']) <span class="text-gray-400">/ @rupiah($r['tagihan'])</span></p>
                            </div>
                            @if ($r['tuntas'])
                                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                                    {{ __('pembayaran.sudah_tuntas') }}
                                </span>
                            @else
                                <button type="button" wire:click="konfirmasiLunasSemua({{ $k->id }})"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                    {{ __('pembayaran.tombol_lunas_semua') }} ({{ $r['pending'] }})
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2 font-medium">{{ __('umum.toko') }}</th>
                                    <th class="px-4 py-2 text-right font-medium">{{ __('pembayaran.tagihan') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                                    <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($k->stops as $stop)
                                    @php $p = $stop->pesanan; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <span class="block font-medium text-gray-900">{{ $stop->toko->nama }}</span>
                                            <span class="block text-xs text-gray-500">{{ $p->kode }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums">@rupiah($p->tagihan)</td>
                                        <td class="px-4 py-2"><x-badge-bayar :status="$p->status_bayar" /></td>
                                        <td class="px-4 py-2 text-right">
                                            @if ($p->status_bayar === \App\Enums\StatusBayar::Pending)
                                                <div class="flex justify-end gap-1">
                                                    <button type="button" wire:click="konfirmasiLunas({{ $p->id }})"
                                                            class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700">
                                                        {{ __('pembayaran.tombol_lunas') }}
                                                    </button>
                                                    <button type="button" wire:click="konfirmasiBelumLunas({{ $p->id }})"
                                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                                        {{ __('pembayaran.tombol_belum_lunas') }}
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-kartu>
            @endforeach
        </div>
    @endif

    {{-- Konfirmasi ganda --}}
    @if ($konfirmasi)
        <x-modal
            :judul="match ($konfirmasi['jenis']) {
                'lunas' => __('pembayaran.konfirmasi_lunas_judul'),
                'belum_lunas' => __('pembayaran.konfirmasi_belum_lunas_judul'),
                'lunas_semua' => __('pembayaran.konfirmasi_lunas_semua_judul'),
            }"
            tutup="batalkanKonfirmasi">
            <div class="p-5 text-sm text-gray-600">
                @if ($konfirmasi['jenis'] === 'lunas')
                    @php $p = \App\Models\Pesanan::find($konfirmasi['pesanan_id']); @endphp
                    <p>{{ __('pembayaran.konfirmasi_lunas_teks', ['toko' => $p?->toko?->nama, 'nilai' => \App\Support\Bahasa::rupiah($p?->tagihan)]) }}</p>
                @elseif ($konfirmasi['jenis'] === 'belum_lunas')
                    @php $p = \App\Models\Pesanan::find($konfirmasi['pesanan_id']); @endphp
                    <p>{{ __('pembayaran.konfirmasi_belum_lunas_teks', ['toko' => $p?->toko?->nama]) }}</p>
                @elseif ($konfirmasi['jenis'] === 'lunas_semua')
                    @php $k = \App\Models\Kendaraan::find($konfirmasi['kendaraan_id']); @endphp
                    <p>{{ __('pembayaran.konfirmasi_lunas_semua_teks', ['mobil' => $k?->nama]) }}</p>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="batalkanKonfirmasi"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.kembali') }}
                </button>
                <button type="button" wire:click="proses" wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    {{ __('umum.proses') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
