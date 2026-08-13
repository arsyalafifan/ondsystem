<div>
    <x-judul-halaman :judul="__('pembayaran.judul_belum_lunas')" :keterangan="__('pembayaran.ket_belum_lunas')" />

    <x-kartu>
        <div class="border-b border-gray-200 p-4">
            <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
            <input type="search" wire:model.live.debounce.300ms="cari" placeholder="{{ __('umum.toko') }}…"
                   class="mt-1 block w-full max-w-sm rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.toko') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('pembayaran.tagihan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pembayaran.tanggal_kirim') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pembayaran.mobil_asal') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->pesanans as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <span class="block font-medium text-gray-900">{{ $p->toko->nama }}</span>
                                <span class="block text-xs text-gray-500">{{ $p->kode }}</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">@rupiah($p->tagihan)</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $p->tanggal->isoFormat('ll') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $p->stop?->kendaraan?->nama ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" wire:click="konfirmasi({{ $p->id }})"
                                        class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700">
                                    {{ __('pembayaran.tombol_lunas') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-kosong ikon="check-circle" :judul="__('pembayaran.kosong_belum_lunas')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->pesanans->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->pesanans->links() }}</div>
        @endif
    </x-kartu>

    @if ($konfirmasiPesananId)
        @php $p = \App\Models\Pesanan::find($konfirmasiPesananId); @endphp
        <x-modal :judul="__('pembayaran.konfirmasi_lunas_judul')" tutup="batalkanKonfirmasi">
            <div class="p-5 text-sm text-gray-600">
                <p>{{ __('pembayaran.konfirmasi_lunas_teks', ['toko' => $p?->toko?->nama, 'nilai' => \App\Support\Bahasa::rupiah($p?->tagihan)]) }}</p>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="batalkanKonfirmasi"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.kembali') }}
                </button>
                <button type="button" wire:click="tandaiLunas" wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    {{ __('umum.proses') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
