<div>
    <x-judul-halaman :judul="__('pesanan.judul_daftar')" :keterangan="__('pesanan.ket_daftar')" />

    {{-- Ringkasan status, sekaligus tombol saring cepat --}}
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($statusCases as $s)
            <button type="button"
                    wire:click="$set('filterStatus', '{{ $filterStatus === $s->value ? '' : $s->value }}')"
                    @class([
                        'rounded-xl border bg-white p-3 text-left transition hover:border-gray-300',
                        'border-blue-500 ring-1 ring-blue-500' => $filterStatus === $s->value,
                        'border-gray-200' => $filterStatus !== $s->value,
                    ])>
                <x-badge-status :status="$s" />
                <p class="mt-1.5 text-2xl font-semibold tabular-nums text-gray-900">{{ $this->ringkasan[$s->value] }}</p>
            </button>
        @endforeach
    </div>

    <x-kartu>
        {{-- Penyaring --}}
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="min-w-56 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="{{ __('pesanan.cari_daftar') }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.wilayah') }}</label>
                <select wire:model.live="filterWilayah"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('umum.semua_wilayah') }}</option>
                    @foreach ($this->wilayahs as $w)
                        <option value="{{ $w->id }}">{{ $w->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                <input type="date" wire:model.live="filterTanggal"
                       class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            </div>
            <button type="button" wire:click="bersihkanFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('umum.bersihkan') }}
            </button>
        </div>

        {{-- Tindakan massal --}}
        @if (auth()->user()->isAdmin() && count($terpilih) > 0)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-blue-200 bg-blue-50 px-4 py-2.5">
                <p class="text-sm text-blue-900">{{ __('pesanan.terpilih', ['jumlah' => count($terpilih)]) }}</p>
                <button type="button" wire:click="setujuiTerpilih" wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    {{ __('pesanan.setujui_massal') }}
                </button>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        @if (auth()->user()->isAdmin())
                            <th class="w-10 px-4 py-2">
                                <input type="checkbox" wire:model.live="pilihSemua" @disabled($this->idBisaDisetujui === [])
                                       class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            </th>
                        @endif
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.toko') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.wilayah') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.dus') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kendaraan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pesanan.penginput') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->pesanans as $p)
                        <tr class="hover:bg-gray-50">
                            @if (auth()->user()->isAdmin())
                                <td class="px-4 py-2">
                                    @if ($p->status === \App\Enums\StatusPesanan::Order)
                                        <input type="checkbox" wire:model.live="terpilih" value="{{ $p->id }}"
                                               class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $p->kode }}</td>
                            <td class="px-4 py-2">
                                <span class="block font-medium text-gray-900">{{ $p->toko->nama }}</span>
                                <span class="block text-xs text-gray-500">
                                    {{ $p->toko->kode }}
                                    @unless ($p->toko->latitude)
                                        · <span class="text-amber-700">{{ __('master.belum_ada_koordinat') }}</span>
                                    @endunless
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $p->wilayah->nama }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">@angka($p->total_dus)</td>
                            <td class="px-4 py-2"><x-badge-status :status="$p->status" /></td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $p->stop?->kendaraan?->nama ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $p->pembuat->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="$set('pesananDilihat', {{ $p->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.rincian') }}
                                    </button>

                                    @if ($p->status->bisaDicetak())
                                        <a href="{{ route('pesanan.nota', $p) }}" target="_blank"
                                           class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                            <x-heroicon-o-printer class="size-3.5" />
                                            {{ __('pesanan.cetak_nota') }}
                                        </a>
                                    @endif

                                    @if (auth()->user()->isAdmin() && $p->status === \App\Enums\StatusPesanan::Order)
                                        <button type="button" wire:click="setujui({{ $p->id }})"
                                                class="rounded-md bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                            {{ __('umum.setujui') }}
                                        </button>
                                    @endif

                                    @if (auth()->user()->isAdmin() && $p->status->bisaDibatalkan())
                                        <button type="button" wire:click="bukaPembatalan({{ $p->id }})"
                                                class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                            {{ __('pesanan.batalkan') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-kosong :judul="__('pesanan.kosong')" :keterangan="__('pesanan.kosong_ket')" />
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

    {{-- Rincian pesanan --}}
    @if ($this->detail)
        @php $d = $this->detail; @endphp
        <x-modal :judul="__('pesanan.judul_rincian', ['kode' => $d->kode])" lebar="max-w-2xl" tutup="$set('pesananDilihat', null)">
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-gray-500">{{ __('umum.status') }}</p>
                        <x-badge-status :status="$d->status" class="mt-0.5" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('umum.tanggal') }}</p>
                        <p class="font-medium">{{ $d->tanggal->isoFormat('ll') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('umum.total') }}</p>
                        <p class="font-medium">@angka($d->total_dus) {{ __('umum.satuan_dus') }} · @rupiah((float) $d->total_nilai)</p>
                    </div>
                    <div class="col-span-2 sm:col-span-3">
                        <p class="text-xs text-gray-500">{{ __('umum.toko') }}</p>
                        <p class="font-medium">{{ $d->toko->nama }} ({{ $d->toko->kode }}) · {{ $d->toko->wilayah->nama }}</p>
                        <p class="text-gray-600">{{ $d->toko->alamat }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">{{ __('pesanan.diinput_oleh') }}</p>
                        <p class="font-medium">{{ $d->pembuat->name }}</p>
                    </div>
                    @if ($d->pemroses)
                        <div>
                            <p class="text-xs text-gray-500">{{ __('pesanan.disetujui_oleh') }}</p>
                            <p class="font-medium">{{ $d->pemroses->name }}</p>
                        </div>
                    @endif
                    @if ($d->stop?->kendaraan)
                        <div>
                            <p class="text-xs text-gray-500">{{ __('umum.kendaraan') }}</p>
                            <p class="font-medium">{{ $d->stop->kendaraan->nama }} · {{ __('umum.urutan') }} {{ $d->stop->urutan }}</p>
                        </div>
                    @endif
                </div>

                @if ($d->catatan)
                    <div class="rounded-lg bg-gray-50 p-3 text-sm">
                        <p class="text-xs text-gray-500">{{ __('umum.catatan') }}</p>
                        <p>{{ $d->catatan }}</p>
                    </div>
                @endif

                @if ($d->status === \App\Enums\StatusPesanan::Cancel)
                    <div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">
                        <p class="font-medium">{{ __('pesanan.dibatalkan', ['alasan' => $d->alasan_cancel]) }}</p>
                        @if ($d->catatan_cancel) <p>{{ $d->catatan_cancel }}</p> @endif
                        <p class="mt-1 text-xs">
                            {{ __('pesanan.oleh_pada', [
                                'nama' => $d->pembatal?->name,
                                'waktu' => $d->dibatalkan_at?->isoFormat('ll').' '.$d->dibatalkan_at?->format('H:i'),
                            ]) }}
                        </p>
                    </div>
                @endif

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('umum.dus') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('umum.harga') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('umum.subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($d->items as $item)
                                <tr>
                                    <td class="px-3 py-2">{{ $item->produk->nama }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">@angka($item->jumlah_dus)</td>
                                    <td class="px-3 py-2 text-right tabular-nums">@rupiah((float) $item->harga_satuan)</td>
                                    <td class="px-3 py-2 text-right tabular-nums">@rupiah((float) $item->subtotal)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($d->stop?->foto_nota)
                    <div>
                        <p class="mb-1 text-xs text-gray-500">{{ __('pesanan.foto_nota') }}</p>
                        <img src="{{ Storage::disk('public')->url($d->stop->foto_nota) }}" alt="{{ __('pesanan.foto_nota') }}"
                             class="max-h-72 rounded-lg border border-gray-200">
                    </div>
                @endif
            </div>

            @if ($d->status->bisaDicetak())
                <x-slot:aksi>
                    <a href="{{ route('pesanan.nota', $d) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        <x-heroicon-o-printer class="size-4" />
                        {{ __('pesanan.cetak_nota') }}
                    </a>
                </x-slot:aksi>
            @endif
        </x-modal>
    @endif

    {{-- Pembatalan --}}
    @if ($pesananDibatalkan)
        <x-modal :judul="__('pesanan.judul_batalkan')" tutup="$set('pesananDibatalkan', null)">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('pesanan.ket_batalkan') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pesanan.alasan_batal') }}</label>
                    <select wire:model="alasanCancel"
                            class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('pesanan.pilih_alasan') }}</option>
                        @foreach ($this->daftarAlasan as $alasan)
                            <option value="{{ $alasan }}">{{ $alasan }}</option>
                        @endforeach
                    </select>
                    @error('alasanCancel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pesanan.catatan_tambahan') }}</label>
                    <textarea wire:model="catatanCancel" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('pesananDibatalkan', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.kembali') }}
                </button>
                <button type="button" wire:click="batalkan" wire:loading.attr="disabled"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                    {{ __('pesanan.tombol_batalkan') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
