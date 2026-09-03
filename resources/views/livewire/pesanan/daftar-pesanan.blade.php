<div>
    <x-judul-halaman :judul="__('pesanan.judul_daftar')" :keterangan="__('pesanan.ket_daftar')">
        <x-slot:aksi>
            <button type="button" wire:click="bukaTokoTidakAktif"
                    class="relative inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                <x-heroicon-o-bell-alert class="size-4 text-amber-500" />
                {{ __('pesanan.tombol_toko_tidak_aktif') }}
                @if ($this->totalTokoTidakAktif > 0)
                    <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-amber-500 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-white">
                        {{ $this->totalTokoTidakAktif }}
                    </span>
                @endif
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

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
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('pesanan.penginput') }}</label>
                <select wire:model.live="filterPenginput"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('pesanan.semua_penginput') }}</option>
                    @foreach ($this->penginputs as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
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
                        <th class="px-4 py-2 font-medium">{{ __('pesanan.tanggal_diinput') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pesanan.update_by_date') }}</th>
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
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                {{ $p->created_at->isoFormat('ll') }}
                                <span class="text-xs text-gray-400">{{ $p->created_at->format('H:i') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                @php $pembaru = $p->pembaru_terakhir @endphp
                                <span class="block font-medium text-gray-900">{{ $pembaru['user']?->name ?? '—' }}</span>
                                <span class="block text-xs text-gray-500">
                                    {{ $pembaru['at']?->isoFormat('ll') }}
                                    @if ($pembaru['at']?->format('H:i:s') !== '00:00:00')
                                        {{ $pembaru['at']?->format('H:i') }}
                                    @endif
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="$set('pesananDilihat', {{ $p->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.rincian') }}
                                    </button>

                                    @if ($p->bisa_dicetak)
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

                                    {{-- Dibatalkan dengan alasan selain "toko membatalkan
                                         pesanan" (baik oleh driver di lapangan maupun admin
                                         langsung dari sini) — masih perlu ditindaklanjuti:
                                         dicoba lagi, atau ditandai final. --}}
                                    @if (auth()->user()->isAdmin() && $p->bisa_order_ulang)
                                        <button type="button" wire:click="bukaOrderUlang({{ $p->id }})"
                                                class="rounded-md border border-blue-300 bg-white px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50">
                                            {{ __('pesanan.tombol_order_ulang') }}
                                        </button>
                                        <button type="button" wire:click="tandaiBatalKarenaToko({{ $p->id }})"
                                                wire:confirm="{{ __('pesanan.konfirmasi_batal_final', ['kode' => $p->kode]) }}"
                                                class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                            {{ __('pesanan.tombol_batalkan_final') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
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
                        @if ($d->kurang_kirim)
                            <p class="font-medium">@angka($d->items->sum(fn ($i) => $i->terkirim)) {{ __('umum.satuan_dus') }} · @rupiah((float) $d->tagihan)</p>
                            <p class="text-xs text-gray-400 line-through">@angka($d->total_dus) {{ __('umum.satuan_dus') }} · @rupiah((float) $d->total_nilai)</p>
                        @else
                            <p class="font-medium">@angka($d->total_dus) {{ __('umum.satuan_dus') }} · @rupiah((float) $d->total_nilai)</p>
                        @endif
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

                @if ($d->kurang_kirim)
                    <div class="rounded-lg bg-orange-50 p-3 text-sm text-orange-800">
                        {{ __('pesanan.ket_kurang_kirim') }}
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
                            {{-- Baris yang toko sama sekali tidak ambil (terkirim = 0) sengaja
                                 tidak ditampilkan — item-nya tetap tersimpan apa adanya di
                                 basis data, hanya disembunyikan dari tampilan supaya tidak
                                 terlihat seperti ikut ditagihkan. --}}
                            @foreach ($d->items->filter(fn ($i) => $i->terkirim > 0) as $item)
                                <tr>
                                    <td class="px-3 py-2">{{ $item->produk->nama }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        @angka($item->terkirim)
                                        @if ($item->terkirim < $item->jumlah_dus)
                                            <span class="text-xs text-gray-400">/ @angka($item->jumlah_dus)</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">@rupiah((float) $item->harga_satuan)</td>
                                    <td class="px-3 py-2 text-right tabular-nums">@rupiah($item->terkirim * (float) $item->harga_satuan)</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($d->kurang_kirim)
                            <tfoot>
                                <tr class="border-t border-gray-200 bg-gray-50 font-medium">
                                    <td class="px-3 py-2" colspan="3">{{ __('umum.total') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">@rupiah((float) $d->tagihan)</td>
                                </tr>
                            </tfoot>
                        @endif
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

            @if ($d->bisa_dicetak)
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

    {{-- Order ulang: pesanan yang dibatalkan dengan alasan selain "toko
         membatalkan pesanan" (bukan penolakan final) — item-nya sudah
         diisi apa adanya dari pesanan lama, tinggal disesuaikan kalau
         perlu. --}}
    @if ($this->pesananOrderUlangModel)
        @php $po = $this->pesananOrderUlangModel; @endphp
        <x-modal :judul="__('pesanan.judul_order_ulang', ['kode' => $po->kode])" lebar="max-w-2xl" tutup="tutupOrderUlang">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('pesanan.ket_order_ulang', ['toko' => $po->toko->nama, 'alasan' => $po->alasan_cancel]) }}</p>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="w-32 px-3 py-2 font-medium">{{ __('pesanan.jumlah_dus') }}</th>
                                <th class="w-10 px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $opsiProdukOrderUlang = $this->produkOrderUlang->map(fn ($pr) => ['value' => $pr->id, 'label' => $pr->nama.' ('.$pr->kode.')'])->all();
                            @endphp
                            @foreach ($barisOrderUlang as $i => $b)
                                <tr wire:key="baris-order-ulang-{{ $i }}">
                                    <td class="px-3 py-2">
                                        <x-pilih-cari :opsi="$opsiProdukOrderUlang" :nilai="$b['produk_id']"
                                                       set="barisOrderUlang.{{ $i }}.produk_id"
                                                       placeholder="{{ __('pesanan.pilih_produk') }}" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="1" wire:model.live.debounce.400ms="barisOrderUlang.{{ $i }}.jumlah_dus"
                                               class="block w-full rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" wire:click="hapusBarisOrderUlang({{ $i }})"
                                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 transition">
                                            <x-heroicon-o-trash class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" wire:click="tambahBarisOrderUlang"
                        class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                    {{ __('pesanan.tambah_baris') }}
                </button>
                @error('barisOrderUlang') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pesanan.label_sales') }}</label>
                    <select wire:model="salesOrderUlang"
                            class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('pesanan.pilih_sales_bonus') }}</option>
                        @foreach ($this->salesListOrderUlang as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @error('salesOrderUlang') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.catatan') }}</label>
                    <textarea wire:model="catatanOrderUlang" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>

                @error('items') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('toko_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupOrderUlang"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.kembali') }}
                </button>
                <button type="button" wire:click="simpanOrderUlang" wire:loading.attr="disabled"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    {{ __('pesanan.tombol_order_ulang') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- Toko yang belum pesan 1 bulan --}}
    @if ($tokoTidakAktifTerbuka)
        <x-modal :judul="__('pesanan.judul_toko_tidak_aktif')" lebar="max-w-2xl" tutup="tutupTokoTidakAktif">
            <div class="border-b border-gray-200 p-4">
                <p class="text-sm text-gray-600">{{ __('pesanan.ket_toko_tidak_aktif') }}</p>
            </div>

            <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
                <div class="min-w-56 flex-1">
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                    <input type="search" wire:model.live.debounce.300ms="cariTokoTidakAktif" placeholder="{{ __('pesanan.cari_toko_tidak_aktif') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('pesanan.label_sales') }}</label>
                    <select wire:model.live="filterSalesTidakAktif"
                            class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('pesanan.semua_sales_tidak_aktif') }}</option>
                        @foreach ($this->tokoTidakAktifSemua as $g)
                            <option value="{{ $g['sales']->id }}">{{ $g['sales']->name }} ({{ $g['tokos']->count() }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="max-h-[60vh] space-y-5 overflow-y-auto p-4">
                @forelse ($this->tokoTidakAktif as $g)
                    <div>
                        <p class="mb-2 flex items-center justify-between text-sm font-semibold text-gray-900">
                            <span>{{ $g['sales']->name }}</span>
                            <span class="text-xs font-normal text-gray-500">@angka($g['tokos']->count()) {{ __('umum.toko') }}</span>
                        </p>
                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                            @foreach ($g['tokos'] as $toko)
                                <li class="px-3 py-2 text-sm">
                                    <span class="block truncate font-medium text-gray-900">{{ $toko->nama }}</span>
                                    <span class="block text-xs text-gray-500">{{ $toko->kode }} · {{ $toko->wilayah?->nama ?? __('pesanan.toko_tanpa_wilayah') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    @if (! $this->adaPenugasanBulanIni)
                        <x-kosong ikon="user-group" :judul="__('pesanan.toko_tanpa_penugasan')" />
                    @elseif ($this->tokoTidakAktifSemua->isEmpty())
                        <x-kosong ikon="check-circle" :judul="__('pesanan.kosong_toko_tidak_aktif')" />
                    @else
                        <x-kosong ikon="magnifying-glass" :judul="__('pesanan.kosong_toko_tidak_aktif_filter')" />
                    @endif
                @endforelse
            </div>
        </x-modal>
    @endif
</div>
