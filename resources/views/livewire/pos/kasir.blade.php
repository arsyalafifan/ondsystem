<div>
    <x-judul-halaman :judul="__('pos.judul')" :keterangan="__('pos.ket')" />

    @if ($kodeTerakhir)
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {!! __('pos.tersimpan', ['kode' => '<strong>'.e($kodeTerakhir).'</strong>']) !!}
            <a href="{{ route('pembayaran.pendapatan') }}" wire:navigate class="font-semibold underline">{{ __('pos.lihat_riwayat') }}</a>
            @if ($idTerakhir)
                &middot;
                <a href="{{ route('pesanan.nota', $idTerakhir) }}" target="_blank" class="font-semibold underline">{{ __('pesanan.cetak_nota') }}</a>
            @endif
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">

            {{-- Langkah 1: toko --}}
            <x-kartu :judul="__('pos.langkah_toko')">
                <div class="p-4">
                    {{-- Khusus admin/superadmin: pilihan "Tanpa Toko" — sama
                         persis dengan transaksi POS biasa (produk, bonus,
                         pembayaran semuanya tetap sama), cuma toko tidak
                         wajib diisi. --}}
                    @if ($this->bisaTanpaToko())
                        <div class="mb-3 inline-flex rounded-lg border border-gray-300 p-0.5 bg-gray-50">
                            <button type="button" wire:click="nonaktifkanTanpaToko"
                                    @class([
                                        'rounded-md px-3 py-2 text-sm font-medium transition-all',
                                        'bg-white text-blue-600 shadow-sm ring-1 ring-gray-200' => ! $tanpaToko,
                                        'text-gray-500 hover:text-gray-900' => $tanpaToko,
                                    ])>
                                {{ __('pos.tab_toko') }}
                            </button>
                            <button type="button" wire:click="aktifkanTanpaToko"
                                    @class([
                                        'rounded-md px-3 py-2 text-sm font-medium transition-all',
                                        'bg-white text-blue-600 shadow-sm ring-1 ring-gray-200' => $tanpaToko,
                                        'text-gray-500 hover:text-gray-900' => ! $tanpaToko,
                                    ])>
                                {{ __('pos.tab_tanpa_toko') }}
                            </button>
                        </div>
                        {{-- <p class="mb-3 text-xs text-gray-500">{{ __('pos.ket_tanpa_toko') }}</p> --}}
                    @endif

                    @if ($this->toko)
                        <div class="flex items-start justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $this->toko->nama }}</p>
                                <p class="text-sm text-gray-600">{{ $this->toko->kode }} · {{ $this->toko->wilayah?->nama ?? __('pesanan.toko_tanpa_wilayah') }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ $this->toko->alamat }}</p>
                            </div>
                            <button type="button" wire:click="batalPilihToko"
                                    class="shrink-0 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                {{ __('umum.ganti') }}
                            </button>
                        </div>
                    @else
                        <input type="search" wire:model.live.debounce.300ms="cariToko"
                               placeholder="{{ __('pos.cari_toko') }}"
                               class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">

                        @if (mb_strlen(trim($cariToko)) >= 2)
                            <div class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200">
                                @forelse ($this->hasilCariToko as $toko)
                                    <button type="button" wire:click="pilihToko({{ $toko->id }})"
                                            @disabled($toko->wilayah_id === null)
                                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm enabled:hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:opacity-60">
                                        <span class="min-w-0">
                                            <span class="block font-medium text-gray-900">{{ $toko->nama }}</span>
                                            <span class="block truncate text-xs text-gray-500">
                                                {{ $toko->kode }} · {{ $toko->wilayah?->nama ?? __('pesanan.toko_tanpa_wilayah') }} · {{ $toko->alamat }}
                                            </span>
                                        </span>
                                        @if ($toko->wilayah_id === null)
                                            <span class="shrink-0 rounded bg-red-100 px-2 py-0.5 text-xs text-red-800">
                                                {{ __('pesanan.toko_tanpa_wilayah') }}
                                            </span>
                                        @endif
                                    </button>
                                @empty
                                    <p class="px-3 py-4 text-center text-sm text-gray-500">{{ __('pos.tidak_ada_toko') }}</p>
                                @endforelse
                            </div>
                        @endif
                    @endif

                    @error('tokoId') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </x-kartu>

            {{-- Langkah 2: produk --}}
            <x-kartu :judul="__('pos.langkah_produk')">
                <x-slot:aksi>
                    <button type="button" wire:click="tambahBaris"
                            class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                        {{ __('pesanan.tambah_baris') }}
                    </button>
                </x-slot:aksi>

                {{-- Barcode: siap dipakai begitu produk sudah ditempeli kode
                     lewat Master Produk. Pemindai USB/Bluetooth bagi peramban
                     tidak beda dari mengetik cepat lalu Enter, jadi kolom ini
                     sudah berfungsi tanpa kamera sama sekali. --}}
                <div class="border-b border-gray-100 p-4">
                    <label class="block text-sm font-medium text-gray-700">{{ __('pos.cari_barcode_label') }}</label>
                    <form wire:submit.prevent="tambahDariBarcode" class="mt-1 flex gap-2">
                        <input type="text" wire:model="cariBarcode" autofocus
                               placeholder="{{ __('pos.cari_barcode_placeholder') }}"
                               class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <button type="submit"
                                class="shrink-0 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                            {{ __('pos.tombol_tambah_barcode') }}
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="w-32 px-4 py-2 font-medium">{{ __('pesanan.jumlah_dus') }}</th>
                                <th class="w-28 px-4 py-2 text-right font-medium">{{ __('master.stok_fisik') }}</th>
                                <th class="w-32 px-4 py-2 text-right font-medium">{{ __('umum.subtotal') }}</th>
                                <th class="w-10 px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $opsiProduk = $this->produks->map(fn ($p) => ['value' => $p->id, 'label' => $p->nama.' ('.$p->kode.')'])->all();
                            @endphp
                            @foreach ($baris as $i => $b)
                                @php $produk = $this->produks->firstWhere('id', (int) $b['produk_id']); @endphp
                                <tr wire:key="baris-{{ $i }}">
                                    <td class="px-4 py-2">
                                        <x-pilih-cari :opsi="$opsiProduk" :nilai="$b['produk_id']"
                                                       set="baris.{{ $i }}.produk_id"
                                                       placeholder="{{ __('pesanan.pilih_produk') }}" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="1" wire:model.live.debounce.400ms="baris.{{ $i }}.jumlah_dus"
                                               class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    </td>
                                    <td class="px-4 py-2 text-right {{ $produk && (int) ($b['jumlah_dus'] ?: 0) > $produk->stok_tersedia ? 'font-semibold text-red-600' : 'text-gray-500' }}">
                                        {{ $produk ? \App\Support\Bahasa::angka($produk->stok_tersedia) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">
                                        {{ $produk ? \App\Support\Bahasa::rupiah((float) $produk->harga * (int) ($b['jumlah_dus'] ?: 0)) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button type="button" wire:click="hapusBaris({{ $i }})"
                                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 transition">
                                            <x-heroicon-o-trash class="size-5" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-medium">
                            <tr>
                                <td class="px-4 py-2 text-right">{{ __('umum.total') }}</td>
                                <td class="px-4 py-2 tabular-nums">@angka($this->totalDus) {{ __('umum.satuan_dus') }}</td>
                                <td></td>
                                <td class="px-4 py-2 text-right tabular-nums">@rupiah($this->totalNilai)</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @error('baris') <p class="px-4 pb-3 text-sm text-red-600">{{ $message }}</p> @enderror
            </x-kartu>

            {{-- Langkah 3 (khusus admin/superadmin): bonus produk — sama
                 persis seperti langkah bonus di Input Pesanan, cuma tanpa
                 "Pilih Sales" karena tidak ada faktur bercetak untuk POS.
                 Tetap tampil apa adanya baik toko dipilih maupun "Tanpa
                 Toko" — keduanya sama saja. --}}
            @if ($this->bisaInputBonus())
                <x-kartu :judul="__('pesanan.langkah_bonus')">
                    <x-slot:aksi>
                        <button type="button" wire:click="tambahBarisBonus"
                                class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                            {{ __('pesanan.tambah_baris_bonus') }}
                        </button>
                    </x-slot:aksi>

                    <p class="px-4 pt-3 text-xs text-gray-500">{{ __('pesanan.ket_bonus') }}</p>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2 font-medium">{{ __('umum.produk') }}</th>
                                    <th class="w-32 px-4 py-2 font-medium">{{ __('pesanan.jumlah_dus') }}</th>
                                    <th class="w-28 px-4 py-2 text-right font-medium">{{ __('master.stok_fisik') }}</th>
                                    <th class="w-32 px-4 py-2 text-right font-medium">{{ __('umum.subtotal') }}</th>
                                    <th class="w-10 px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($barisBonus as $i => $b)
                                    @php $produkBonus = $this->produks->firstWhere('id', (int) $b['produk_id']); @endphp
                                    <tr wire:key="baris-bonus-{{ $i }}">
                                        <td class="px-4 py-2">
                                            <x-pilih-cari :opsi="$opsiProduk" :nilai="$b['produk_id']"
                                                           set="barisBonus.{{ $i }}.produk_id"
                                                           placeholder="{{ __('pesanan.pilih_produk') }}" />
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" min="1" wire:model.live.debounce.400ms="barisBonus.{{ $i }}.jumlah_dus"
                                                   class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                        </td>
                                        <td class="px-4 py-2 text-right {{ $produkBonus && (int) ($b['jumlah_dus'] ?: 0) > $produkBonus->stok_tersedia ? 'font-semibold text-red-600' : 'text-gray-500' }}">
                                            {{ $produkBonus ? \App\Support\Bahasa::angka($produkBonus->stok_tersedia) : '—' }}
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">
                                            @rupiah(0)
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <button type="button" wire:click="hapusBarisBonus({{ $i }})"
                                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 transition">
                                                <x-heroicon-o-trash class="size-5" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-medium">
                                <tr>
                                    <td class="px-4 py-2 text-right">{{ __('umum.total') }}</td>
                                    <td class="px-4 py-2 tabular-nums">@angka($this->totalDusBonus) {{ __('umum.satuan_dus') }}</td>
                                    <td></td>
                                    <td class="px-4 py-2 text-right tabular-nums">@rupiah(0)</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </x-kartu>
            @endif

            {{-- Langkah pembayaran — cash saja untuk sekarang, opsi
                 transfer sengaja belum ada (belum dibutuhkan operasional).
                 Nominalnya diketik manual, bukan otomatis diisi penuh —
                 tombol "Isi Total Belanja" cuma bantuan awal, tetap bisa
                 diubah sesudahnya. Tetap wajib diisi apa adanya baik untuk
                 toko sungguhan maupun "Tanpa Toko" — keduanya sama saja,
                 kecuali memang semua produknya dipilih lewat langkah bonus
                 di atas (harga 0). --}}
            <x-kartu :judul="$this->bisaInputBonus() ? __('pos.langkah_pembayaran_admin') : __('pos.langkah_pembayaran')">
                <div class="space-y-3 p-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-700">{{ __('pembayaran.nominal_cash') }}</label>
                        <button type="button" wire:click="isiTotalBelanja"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-gray-50">
                            {{ __('pos.isi_total_belanja') }}
                        </button>
                    </div>

                    {{-- Titik ribuan cuma tampilan (mis. 1.500.000) — nilai yang
                         dikirim ke Livewire tetap angka bersih tanpa titik. --}}
                    <div x-data="{
                            cash: formatRibuan(@js($nominalCash)),
                            perbaruiCash(e) {
                                const angka = e.target.value.replace(/\D/g, '');
                                this.cash = formatRibuan(angka);
                                $wire.set('nominalCash', angka);
                            },
                         }"
                         x-effect="cash = formatRibuan(@js($nominalCash))">
                        <input type="text" inputmode="numeric" x-model="cash" @input="perbaruiCash($event)"
                               placeholder="0"
                               class="block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    </div>

                    @if ($this->selisihNominal === 0.0 && $this->totalNilai > 0)
                        <p class="rounded-lg bg-emerald-50 p-2.5 text-xs text-emerald-800">{{ __('pembayaran.nominal_pas') }}</p>
                    @elseif ($this->selisihNominal > 0)
                        <p class="rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800">
                            {{ __('pembayaran.nominal_kurang', ['sisa' => \App\Support\Bahasa::rupiah($this->selisihNominal)]) }}
                        </p>
                    @elseif ($this->selisihNominal < 0)
                        <p class="rounded-lg bg-red-50 p-2.5 text-xs text-red-800">
                            {{ __('pembayaran.nominal_lebih', ['lebih' => \App\Support\Bahasa::rupiah(abs($this->selisihNominal))]) }}
                        </p>
                    @endif

                    @error('nominalCash') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </x-kartu>

            <x-kartu :judul="$this->bisaInputBonus() ? __('pos.langkah_catatan_admin') : __('pos.langkah_catatan')">
                <div class="p-4">
                    <textarea wire:model="catatan" rows="2" placeholder="{{ __('pesanan.catatan_contoh') }}"
                              class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </x-kartu>
        </div>

        {{-- Panel validasi --}}
        <div class="lg:sticky lg:top-6 lg:self-start">
            <x-kartu :judul="__('pesanan.validasi_sistem')">
                <div class="space-y-3 p-4">
                    @php
                        $periksa = [
                            ['lulus' => ! $this->adaHalangan('stok'), 'teks' => __('pesanan.periksa_stok')],
                            ['lulus' => ! $this->adaHalangan('toko') && ! $this->adaHalangan('toko_tanpa_wilayah'), 'teks' => __('pesanan.toko_belum_dipilih')],
                            ['lulus' => ! $this->adaHalangan('nominal'), 'teks' => __('pos.periksa_nominal')],
                        ];
                    @endphp

                    @foreach ($periksa as $p)
                        <div class="flex items-center gap-2.5 text-sm {{ $p['lulus'] ? 'text-emerald-700' : 'text-gray-500' }}">
                            @if ($p['lulus'])
                                <x-heroicon-s-check-circle class="size-5 text-emerald-500" />
                            @else
                                <div class="size-5 rounded-full border-2 border-gray-300"></div>
                            @endif
                            {{ $p['teks'] }}
                        </div>
                    @endforeach

                    @if ($this->halangan !== [])
                        <ul class="mt-3 space-y-1.5 rounded-lg bg-red-50 p-3 text-sm text-red-800">
                            @foreach ($this->halangan as $h)
                                <li class="flex gap-2"><span>•</span><span>{{ $h['pesan'] }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    <button type="button" wire:click="simpan" wire:loading.attr="disabled"
                            @disabled($this->halangan !== [])
                            class="mt-2 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                        <span wire:loading.remove wire:target="simpan">{{ __('pos.tombol_simpan') }}</span>
                        <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                    </button>

                    <p class="text-xs text-gray-500">
                        {{ __('pos.catatan_simpan', ['nama' => auth()->user()->name]) }}
                    </p>
                </div>
            </x-kartu>
        </div>
    </div>
</div>
