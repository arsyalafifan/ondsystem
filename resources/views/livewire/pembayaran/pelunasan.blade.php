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
                                <span class="text-xs text-gray-500">
                                    {{ __('pembayaran.menunggu_keputusan') }} ({{ $r['pending'] }})
                                </span>
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

    {{-- Konfirmasi lunas: rincian sumber pembayaran --}}
    @if ($konfirmasi && $konfirmasi['jenis'] === 'lunas')
        @php $p = $this->pesananKonfirmasi; @endphp
        <x-modal :judul="__('pembayaran.konfirmasi_lunas_judul')" tutup="batalkanKonfirmasi">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">
                    {{ __('pembayaran.konfirmasi_lunas_teks', ['toko' => $p?->toko?->nama, 'nilai' => \App\Support\Bahasa::rupiah($p?->tagihan ?? 0)]) }}
                </p>

                {{-- Titik ribuan cuma tampilan (mis. 1.500.000) — nilai yang
                     dikirim ke Livewire tetap angka bersih tanpa titik. --}}
                <div class="grid grid-cols-2 gap-3"
                     x-data="{
                        cash: formatRibuan(@js($nominalCash)),
                        transfer: formatRibuan(@js($nominalTransfer)),
                        perbaruiCash(e) {
                            const angka = e.target.value.replace(/\D/g, '');
                            this.cash = formatRibuan(angka);
                            $wire.set('nominalCash', angka);
                        },
                        perbaruiTransfer(e) {
                            const angka = e.target.value.replace(/\D/g, '');
                            this.transfer = formatRibuan(angka);
                            $wire.set('nominalTransfer', angka);
                        },
                     }">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('pembayaran.nominal_cash') }}</label>
                        <input type="text" inputmode="numeric" x-model="cash" @input="perbaruiCash($event)"
                               placeholder="0"
                               class="mt-1 block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('pembayaran.nominal_transfer') }}</label>
                        <input type="text" inputmode="numeric" x-model="transfer" @input="perbaruiTransfer($event)"
                               placeholder="0"
                               class="mt-1 block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>

                @if ($this->selisihNominal === 0.0)
                    <p class="rounded-lg bg-emerald-50 p-2.5 text-xs text-emerald-800">
                        {{ __('pembayaran.nominal_pas') }}
                    </p>
                @elseif ($this->selisihNominal > 0)
                    <p class="rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800">
                        {{ __('pembayaran.nominal_kurang', ['sisa' => \App\Support\Bahasa::rupiah($this->selisihNominal)]) }}
                    </p>
                @else
                    <p class="rounded-lg bg-red-50 p-2.5 text-xs text-red-800">
                        {{ __('pembayaran.nominal_lebih', ['lebih' => \App\Support\Bahasa::rupiah(abs($this->selisihNominal))]) }}
                    </p>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="batalkanKonfirmasi"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.kembali') }}
                </button>
                <button type="button" wire:click="proses" wire:loading.attr="disabled"
                        @disabled($this->selisihNominal !== 0.0)
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                    {{ __('umum.proses') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- Konfirmasi belum lunas: yes/no biasa --}}
    @if ($konfirmasi && $konfirmasi['jenis'] === 'belum_lunas')
        @php $p = \App\Models\Pesanan::find($konfirmasi['pesanan_id']); @endphp
        <x-modal :judul="__('pembayaran.konfirmasi_belum_lunas_judul')" tutup="batalkanKonfirmasi">
            <div class="p-5 text-sm text-gray-600">
                <p>{{ __('pembayaran.konfirmasi_belum_lunas_teks', ['toko' => $p?->toko?->nama]) }}</p>
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
