<div>
    <x-judul-halaman :judul="__('transfer_stok.judul')" :keterangan="__('transfer_stok.ket')">
        <x-slot:aksi>
            @if ($this->depotSekarangGudangPenyimpanan)
                <button type="button" wire:click="buatBaruKirim"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('transfer_stok.tombol_kirim_baru') }}
                </button>
            @endif
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($depotBelumDipilih)
        <x-butuh-depot-terkunci />
    @else
        @unless ($this->depotSekarangGudangPenyimpanan)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                {{ __('transfer_stok.ket_bukan_gudang_penyimpanan') }}
            </div>
        @endunless

        {{-- Ringkasan status, sekaligus tombol saring cepat --}}
        <div class="mb-4 grid grid-cols-3 gap-3">
            @foreach ($this->statusCases as $s)
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
            <div class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-4">
                <div class="min-w-56 flex-1">
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                    <input type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('transfer_stok.cari_placeholder') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('transfer_stok.atr_arah') }}</label>
                    <select wire:model.live="filterArah"
                            class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('transfer_stok.arah_semua') }}</option>
                        <option value="keluar">{{ __('transfer_stok.arah_keluar') }}</option>
                        <option value="masuk">{{ __('transfer_stok.arah_masuk') }}</option>
                    </select>
                </div>
                <button type="button" wire:click="bersihkanFilter"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.bersihkan') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('transfer_stok.kolom_kode') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('transfer_stok.atr_arah') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('transfer_stok.atr_gudang_asal') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('transfer_stok.atr_gudang_tujuan') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ __('transfer_stok.kolom_jumlah_item') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->daftar as $t)
                            @php $keluar = $t->depot_asal_id === \App\Support\DepotContext::currentOrFail()->id; @endphp
                            <tr class="hover:bg-gray-50" wire:key="transfer-{{ $t->id }}">
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                    {{ $t->kode }}
                                    <span class="block text-xs font-normal text-gray-500">{{ $t->dikirim_at?->isoFormat('ll') }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($keluar)
                                        <span class="rounded bg-orange-100 px-1.5 py-0.5 text-xs font-medium text-orange-800">{{ __('transfer_stok.arah_keluar') }}</span>
                                    @else
                                        <span class="rounded bg-teal-100 px-1.5 py-0.5 text-xs font-medium text-teal-800">{{ __('transfer_stok.arah_masuk') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $t->depotAsal?->nama }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $t->depotTujuan?->nama }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ $t->items->count() }}</td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $t->status->badge() }}">
                                        {{ $t->status->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        @if ($t->status === \App\Enums\StatusTransferStok::Dikirim && ! $keluar)
                                            <button type="button" wire:click="bukaTerima({{ $t->id }})"
                                                    class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700">
                                                {{ __('transfer_stok.tombol_terima') }}
                                            </button>
                                        @endif
                                        @if ($t->status === \App\Enums\StatusTransferStok::Dikirim && $keluar)
                                            <button type="button" wire:click="bukaBatalkan({{ $t->id }})"
                                                    class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                                {{ __('transfer_stok.tombol_batalkan') }}
                                            </button>
                                        @endif
                                        <button type="button" wire:click="$set('dilihat', {{ $t->id }})"
                                                class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                            {{ __('umum.rincian') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-kosong ikon="arrows-right-left" :judul="__('transfer_stok.kosong')" :keterangan="__('transfer_stok.ket_kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->daftar->hasPages())
                <div class="border-t border-gray-100 p-4">{{ $this->daftar->links() }}</div>
            @endif
        </x-kartu>
    @endif

    {{-- ============ Formulir kirim ============ --}}
    @if ($formKirimTerbuka)
        <x-modal :judul="__('transfer_stok.tombol_kirim_baru')" tutup="tutupFormKirim" lebar="max-w-2xl">
            <div class="space-y-4 p-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('transfer_stok.atr_gudang_tujuan') }}</label>
                    <select wire:model="depotTujuanId"
                            class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('transfer_stok.pilih_gudang_tujuan') }}</option>
                        @foreach ($this->opsiGudangTujuan as $g)
                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                        @endforeach
                    </select>
                    @error('depotTujuanId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @if ($this->opsiGudangTujuan->isEmpty())
                        <p class="mt-1 text-xs text-amber-600">{{ __('transfer_stok.tidak_ada_gudang_tujuan') }}</p>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('transfer_stok.atr_item') }}</label>
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('umum.produk') }}</th>
                                    <th class="w-28 px-3 py-2 text-right font-medium">{{ __('transfer_stok.kolom_tersedia') }}</th>
                                    <th class="w-28 px-3 py-2 font-medium">{{ __('transfer_stok.kolom_jumlah_kirim') }}</th>
                                    <th class="w-10 px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php $opsiProduk = $this->produkTersedia->map(fn ($p) => ['value' => $p->id, 'label' => $p->nama.' ('.$p->kode.')'])->all(); @endphp
                                @foreach ($baris as $i => $b)
                                    @php $produk = $this->produkTersedia->firstWhere('id', (int) $b['produk_id']); @endphp
                                    <tr wire:key="baris-{{ $i }}">
                                        <td class="px-3 py-2">
                                            <x-pilih-cari :opsi="$opsiProduk" :nilai="$b['produk_id']"
                                                          set="baris.{{ $i }}.produk_id"
                                                          placeholder="{{ __('pesanan.pilih_produk') }}" />
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums {{ $produk && (int) ($b['jumlah'] ?: 0) > $produk->stok_tersedia ? 'font-semibold text-red-600' : 'text-gray-500' }}">
                                            {{ $produk ? \App\Support\Bahasa::angka($produk->stok_tersedia) : '—' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" min="1" wire:model.live.debounce.400ms="baris.{{ $i }}.jumlah"
                                                   class="block w-full rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" wire:click="hapusBaris({{ $i }})"
                                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                <x-heroicon-o-trash class="size-5" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('baris') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    <button type="button" wire:click="tambahBaris"
                            class="mt-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-gray-50">
                        + {{ __('transfer_stok.tombol_tambah_item') }}
                    </button>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('transfer_stok.atr_catatan_kirim') }}</label>
                    <textarea wire:model="catatanKirim" rows="2" placeholder="{{ __('umum.catatan_opsional') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupFormKirim"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="kirim" wire:loading.attr="disabled" wire:target="kirim"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="kirim">{{ __('transfer_stok.tombol_kirim') }}</span>
                    <span wire:loading wire:target="kirim">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Terima ============ --}}
    @if ($this->transferDiterima)
        @php $td = $this->transferDiterima; @endphp
        <x-modal :judul="__('transfer_stok.judul_terima', ['kode' => $td->kode])" tutup="tutupTerima" lebar="max-w-2xl">
            <div class="space-y-4 p-5">
                <p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                    {{ __('transfer_stok.ket_terima', ['gudang' => $td->depotAsal->nama]) }}
                </p>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="w-10 px-3 py-2"><span class="sr-only">{{ __('driver.kolom_cek') }}</span></th>
                                <th class="px-3 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="w-24 px-3 py-2 text-right font-medium">{{ __('transfer_stok.kolom_jumlah_kirim') }}</th>
                                <th class="w-28 px-3 py-2 font-medium">{{ __('transfer_stok.kolom_jumlah_terima') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($td->items as $item)
                                @php $kurang = (int) ($jumlahTerima[$item->id] ?? $item->jumlah_kirim) < $item->jumlah_kirim; @endphp
                                <tr @class(['bg-amber-50' => $kurang])>
                                    <td class="px-3 py-2">
                                        <input type="checkbox" wire:model.live="dicekTerima.{{ $item->id }}"
                                               aria-label="{{ __('driver.kolom_cek') }}"
                                               class="size-5 rounded text-emerald-600 focus:ring-emerald-500">
                                    </td>
                                    <td class="px-3 py-2">{{ $item->produkAsal->nama }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">@angka($item->jumlah_kirim)</td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" max="{{ $item->jumlah_kirim }}"
                                               wire:model.live="jumlahTerima.{{ $item->id }}"
                                               class="block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @unless ($this->semuaTercekTerima)
                    <p class="text-xs text-amber-700">{{ __('transfer_stok.ket_belum_tercek') }}</p>
                @endunless

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('transfer_stok.atr_catatan_terima') }}</label>
                    <textarea wire:model="catatanTerima" rows="2" placeholder="{{ __('umum.catatan_opsional') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('transfer_stok.atr_foto_terima') }}</label>
                    <input type="file" wire:model="fotoTerima" multiple accept="image/*"
                           class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                    @error('fotoTerima.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="fotoTerima" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>

                    @if ($fotoTerima)
                        <div class="mt-2 grid grid-cols-4 gap-2">
                            @foreach ($fotoTerima as $i => $foto)
                                <div class="relative" wire:key="foto-terima-{{ $i }}">
                                    <img src="{{ $foto->temporaryUrl() }}" class="h-20 w-full rounded-lg object-cover">
                                    <button type="button" wire:click="hapusFotoTerima({{ $i }})"
                                            class="absolute -right-1.5 -top-1.5 rounded-full bg-red-600 p-1 text-white hover:bg-red-700">
                                        <x-heroicon-o-x-mark class="size-3" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupTerima"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="terima" wire:loading.attr="disabled" wire:target="terima"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="terima">{{ __('transfer_stok.tombol_terima') }}</span>
                    <span wire:loading wire:target="terima">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Batalkan ============ --}}
    @if ($batalkanId)
        <x-modal :judul="__('transfer_stok.judul_batalkan')" tutup="tutupBatalkan">
            <div class="space-y-3 p-5">
                <p class="text-sm text-gray-600">{{ __('transfer_stok.ket_batalkan') }}</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('transfer_stok.atr_alasan_batal') }}</label>
                    <textarea wire:model="alasanBatal" rows="2" placeholder="{{ __('umum.catatan_opsional') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>
            <x-slot:aksi>
                <button type="button" wire:click="tutupBatalkan"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="batalkan"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('transfer_stok.tombol_batalkan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Rincian ============ --}}
    @if ($this->detail)
        @php $d = $this->detail; @endphp
        <x-modal :judul="$d->kode" tutup="$set('dilihat', null)" lebar="max-w-2xl">
            <div class="space-y-4 p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $d->status->badge() }}">{{ $d->status->label() }}</span>
                    <span class="text-xs text-gray-500">{{ $d->status->keterangan() }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-xs text-gray-500">{{ __('transfer_stok.atr_gudang_asal') }}</p>
                        <p class="font-medium text-gray-900">{{ $d->depotAsal->nama }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('transfer_stok.dikirim_oleh', ['nama' => $d->pengirim?->name, 'waktu' => $d->dikirim_at?->isoFormat('lll')]) }}</p>
                        @if ($d->catatan_kirim)
                            <p class="mt-1 text-xs text-gray-600">{{ $d->catatan_kirim }}</p>
                        @endif
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-xs text-gray-500">{{ __('transfer_stok.atr_gudang_tujuan') }}</p>
                        <p class="font-medium text-gray-900">{{ $d->depotTujuan->nama }}</p>
                        @if ($d->status === \App\Enums\StatusTransferStok::Diterima)
                            <p class="mt-1 text-xs text-gray-500">{{ __('transfer_stok.diterima_oleh', ['nama' => $d->penerima?->name, 'waktu' => $d->diterima_at?->isoFormat('lll')]) }}</p>
                            @if ($d->catatan_terima)
                                <p class="mt-1 text-xs text-gray-600">{{ $d->catatan_terima }}</p>
                            @endif
                        @elseif ($d->status === \App\Enums\StatusTransferStok::Dibatalkan)
                            <p class="mt-1 text-xs text-red-600">{{ __('transfer_stok.dibatalkan_oleh', ['nama' => $d->pembatal?->name, 'waktu' => $d->dibatalkan_at?->isoFormat('lll')]) }}</p>
                            @if ($d->alasan_batal)
                                <p class="mt-1 text-xs text-gray-600">{{ $d->alasan_batal }}</p>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="w-24 px-3 py-2 text-right font-medium">{{ __('transfer_stok.kolom_jumlah_kirim') }}</th>
                                <th class="w-24 px-3 py-2 text-right font-medium">{{ __('transfer_stok.kolom_jumlah_terima') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($d->items as $item)
                                <tr @class(['bg-amber-50' => $item->kekurangan > 0])>
                                    <td class="px-3 py-2">{{ $item->produkAsal->nama }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">@angka($item->jumlah_kirim)</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $item->jumlah_terima === null ? '—' : \App\Support\Bahasa::angka($item->jumlah_terima) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($d->fotos->isNotEmpty())
                    <div>
                        <p class="mb-2 text-sm font-semibold text-gray-900">{{ __('transfer_stok.atr_foto_terima') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($d->fotos as $foto)
                                <a href="{{ Illuminate\Support\Facades\Storage::disk('public')->url($foto->path) }}" target="_blank" rel="noopener">
                                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($foto->path) }}"
                                         class="h-24 w-full rounded-lg border border-gray-200 object-cover transition hover:opacity-90">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-modal>
    @endif
</div>
