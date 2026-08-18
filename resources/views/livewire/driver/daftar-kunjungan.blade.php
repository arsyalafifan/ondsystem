<div>
    @php $p = $this->progres; @endphp

    <x-judul-halaman :judul="$kendaraan->nama"
                     :keterangan="($kendaraan->wilayah?->nama ?? __('driver.semua_wilayah')).' · '.\App\Support\Bahasa::angka($p['target_dus']).' '.__('umum.satuan_dus').' · '.\App\Support\Bahasa::angka($kendaraan->jarak_km, 1).' km'">
        <x-slot:aksi>
            <a href="{{ route('driver.pilih-mobil') }}" wire:navigate
               class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('driver.ganti_mobil') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    {{-- Ringkasan progres, kini berbasis dus --}}
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-sm text-gray-600">
                <strong class="text-lg text-gray-900">@angka($p['dus_terkirim'])</strong>
                / @angka($p['target_dus']) {{ __('umum.satuan_dus') }}
                <span class="block text-xs text-gray-500">
                    {{ __('driver.progres', ['selesai' => $p['selesai'], 'total' => $p['total'], 'dus' => \App\Support\Bahasa::angka($p['dus_terkirim'])]) }}
                </span>
            </p>
            <span class="shrink-0 text-lg font-semibold tabular-nums text-gray-900">{{ $p['persen'] }}%</span>
        </div>
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $p['persen'] }}%"></div>
        </div>

        @if ($p['dibatalkan'] > 0 || $p['dus_sisa'] > 0)
            <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                <div class="rounded-lg bg-red-50 px-2 py-2">
                    <p class="text-xs text-red-700">{{ __('pengiriman.toko_dibatalkan') }}</p>
                    <p class="text-lg font-semibold tabular-nums text-red-900">{{ $p['dibatalkan'] }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 px-2 py-2">
                    <p class="text-xs text-amber-800">{{ __('pengiriman.dus_sisa') }}</p>
                    <p class="text-lg font-semibold tabular-nums text-amber-900">@angka($p['dus_sisa'])</p>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ __('pengiriman.ket_progres_dus') }}</p>
        @endif

        @if ($this->totalJatahKampas > 0)
            <button type="button" wire:click="bukaKampas"
                    class="mt-3 w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">
                <x-heroicon-o-cube class="size-4 inline" /> {{ __('pengiriman.aksi_kampas') }} (@angka($this->totalJatahKampas) {{ __('umum.satuan_dus') }})
            </button>
        @endif
    </div>

    @if ($p['belum'] === 0)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
            <p class="text-2xl"><x-heroicon-o-sparkles class="size-6 inline text-yellow-500" /></p>
            <p class="mt-1 font-semibold text-emerald-900">{{ __('driver.semua_selesai') }}</p>
            <p class="text-sm text-emerald-800">{{ __('driver.semua_selesai_ket') }}</p>
        </div>
    @elseif ($this->berikutnya)
        <div class="mb-4 rounded-xl border-2 border-blue-500 bg-blue-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">{{ __('driver.tujuan_berikutnya') }}</p>
            <p class="mt-1 text-lg font-semibold text-gray-900">
                {{ $this->berikutnya->urutan }}. {{ $this->berikutnya->toko->nama }}
            </p>
            <p class="text-sm text-gray-600">{{ $this->berikutnya->toko->alamat }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($this->berikutnya->toko->latitude)
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $this->berikutnya->toko->latitude }},{{ $this->berikutnya->toko->longitude }}"
                       target="_blank" rel="noopener"
                       class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        {{ __('driver.navigasi_ke_sini') }}
                    </a>
                @endif
                <button type="button" wire:click="bukaUnggah({{ $this->berikutnya->id }})"
                        class="rounded-lg border border-blue-300 bg-white px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                    {{ __('driver.upload_nota') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Daftar kunjungan --}}
    <div class="space-y-2">
        @foreach ($this->stops as $stop)
            @php
                $selesai = $stop->status === \App\Enums\StatusStop::Selesai;
                $batal = $stop->status === \App\Enums\StatusStop::Dibatalkan;
            @endphp

            <div @class([
                    'rounded-xl border bg-white p-4',
                    'border-emerald-200 bg-emerald-50/40' => $selesai,
                    'border-red-200 bg-red-50/40' => $batal,
                    'border-gray-200' => ! $selesai && ! $batal,
                ])>
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold text-white"
                          style="background: {{ $batal ? '#dc2626' : ($selesai ? '#059669' : $kendaraan->warna) }}">
                        @if ($batal)
                            <x-heroicon-o-x-mark class="size-4 inline" />
                        @elseif ($selesai)
                            <x-heroicon-o-check class="size-4 inline" />
                        @else
                            {{ $stop->urutan }}
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                            {{ $stop->toko->nama }}
                            @if ($stop->isKampas())
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">
                                    {{ __('pengiriman.label_kampas') }}
                                </span>
                            @endif
                            @if ($stop->pesanan?->kurang_kirim)
                                <span class="rounded bg-orange-100 px-1.5 py-0.5 text-xs font-medium text-orange-800">
                                    {{ __('pengiriman.kurang_kirim') }}
                                </span>
                            @endif
                        </p>
                        <p class="text-sm text-gray-600">{{ $stop->toko->alamat }}</p>

                        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>
                                <x-heroicon-o-cube class="size-4 inline" /> @angka($stop->total_dus_terkirim)/@angka($stop->total_dus) {{ __('umum.satuan_dus') }}
                            </span>
                            <span><x-heroicon-o-receipt-percent class="size-4 inline" /> {{ $stop->pesanan?->kode }}</span>
                            @if ($stop->eta && ! $stop->isKampas())
                                <span><x-heroicon-o-clock class="size-4 inline" /> {{ __('routing.tiba', ['waktu' => substr((string) $stop->eta, 0, 5)]) }}</span>
                            @endif
                            @if ($stop->toko->telepon)
                                <a href="tel:{{ $stop->toko->telepon }}" class="text-blue-600 hover:underline"><x-heroicon-o-phone class="size-4 inline" /> {{ $stop->toko->telepon }}</a>
                            @endif
                        </div>

                        @if ($stop->pesanan?->catatan)
                            <p class="mt-1.5 rounded bg-amber-50 px-2 py-1 text-xs text-amber-900">
                                {{ __('umum.catatan') }}: {{ $stop->pesanan->catatan }}
                            </p>
                        @endif

                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs font-medium text-gray-600 hover:text-gray-900">
                                {{ __('driver.rincian_barang') }}
                            </summary>
                            <ul class="mt-1 space-y-0.5 text-xs text-gray-600">
                                @foreach ($stop->pesanan?->items ?? [] as $item)
                                    <li>
                                        • {{ $item->produk->nama }} — @angka($item->terkirim) {{ __('umum.satuan_dus') }}
                                        @if ($item->sisa > 0)
                                            <span class="text-amber-700">
                                                ({{ __('pengiriman.dus_sisa') }} @angka($item->sisa))
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>

                        @if ($batal)
                            <p class="mt-2 rounded bg-red-100 px-2 py-1 text-xs text-red-900">
                                {{ $stop->alasan_batal }}
                                @if ($stop->catatan_batal) — {{ $stop->catatan_batal }} @endif
                            </p>
                        @elseif ($selesai)
                            <p class="mt-2 text-xs text-emerald-700">
                                {{ __('driver.selesai_pukul', ['waktu' => $stop->selesai_at?->format('H:i')]) }}
                                @if ($stop->catatan_driver) · {{ $stop->catatan_driver }} @endif
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-col gap-1.5">
                        @if ($stop->toko->latitude)
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $stop->toko->latitude }},{{ $stop->toko->longitude }}"
                               target="_blank" rel="noopener" title="{{ __('driver.buka_navigasi') }}"
                               class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-center text-sm hover:bg-gray-50"><x-heroicon-o-paper-airplane class="size-4 inline" /></a>
                        @endif

                        @if ($stop->foto_nota)
                            <a href="{{ $stop->url }}" target="_blank" rel="noopener"
                               title="{{ __('driver.lihat_foto_nota') }}"
                               class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1.5 text-center text-sm hover:bg-emerald-50"><x-heroicon-o-receipt-percent class="size-4 inline" /></a>
                        @endif

                        @if ($stop->status === \App\Enums\StatusStop::Pending)
                            <button type="button" wire:click="bukaUnggah({{ $stop->id }})"
                                    title="{{ __('driver.upload_nota') }}"
                                    class="rounded-lg bg-blue-600 px-2.5 py-1.5 text-sm text-white hover:bg-blue-700"><x-heroicon-o-camera class="size-4 inline" /></button>

                            <button type="button" wire:click="bukaCoret({{ $stop->id }})"
                                    title="{{ __('pengiriman.aksi_coret') }}"
                                    class="rounded-lg border border-orange-300 bg-white px-2.5 py-1.5 text-sm text-orange-700 hover:bg-orange-50"><x-heroicon-o-scissors class="size-4 inline" /></button>

                            <button type="button" wire:click="bukaBatal({{ $stop->id }})"
                                    title="{{ __('pengiriman.aksi_batalkan') }}"
                                    class="rounded-lg border border-red-300 bg-white px-2.5 py-1.5 text-sm text-red-700 hover:bg-red-50"><x-heroicon-o-x-mark class="size-4 inline" /></button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============ Unggah foto nota (pengiriman penuh) ============ --}}
    @if ($stopAktif)
        @php $stop = $this->stops->firstWhere('id', $stopAktif); @endphp
        <x-modal :judul="__('driver.judul_unggah', ['toko' => $stop?->toko->nama])" tutup="tutupUnggah">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('driver.ket_unggah') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('driver.label_foto') }}</label>
                    <input type="file" wire:model="fotoNota" accept="image/*" capture="environment"
                           class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                    @error('fotoNota') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div wire:loading wire:target="fotoNota" class="mt-2 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>

                    @if ($fotoNota)
                        <img src="{{ $fotoNota->temporaryUrl() }}" alt="{{ __('driver.label_foto') }}"
                             class="mt-3 max-h-56 rounded-lg border border-gray-200">
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.catatan_opsional') }}</label>
                    <textarea wire:model="catatanDriver" rows="2" placeholder="{{ __('driver.catatan_contoh') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupUnggah"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="kirimNota" wire:loading.attr="disabled" wire:target="kirimNota,fotoNota"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="kirimNota">{{ __('driver.simpan_selesaikan') }}</span>
                    <span wire:loading wire:target="kirimNota">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Batalkan toko ============ --}}
    @if ($stopDibatalkan)
        @php $stop = $this->stops->firstWhere('id', $stopDibatalkan); @endphp
        <x-modal :judul="__('pengiriman.judul_batalkan', ['toko' => $stop?->toko->nama])" tutup="$set('stopDibatalkan', null)">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">
                    {{ __('pengiriman.ket_batalkan', ['dus' => \App\Support\Bahasa::angka($stop?->total_dus ?? 0)]) }}
                </p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pesanan.alasan_batal') }}</label>
                    <select wire:model="alasanBatal"
                            class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('pesanan.pilih_alasan') }}</option>
                        @foreach ($this->daftarAlasan as $alasan)
                            <option value="{{ $alasan }}">{{ $alasan }}</option>
                        @endforeach
                    </select>
                    @error('alasanBatal') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pesanan.catatan_tambahan') }}</label>
                    <textarea wire:model="catatanBatal" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('stopDibatalkan', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.kembali') }}</button>
                <button type="button" wire:click="batalkanToko" wire:loading.attr="disabled"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                    {{ __('pengiriman.aksi_batalkan') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Coret nota ============ --}}
    @if ($this->stopDicoretModel)
        @php $sc = $this->stopDicoretModel; @endphp
        <x-modal :judul="__('pengiriman.judul_coret', ['toko' => $sc->toko->nama])" lebar="max-w-xl" tutup="tutupCoret">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('pengiriman.ket_coret') }}</p>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="w-24 px-3 py-2 text-right font-medium">{{ __('pengiriman.dipesan') }}</th>
                                <th class="w-28 px-3 py-2 font-medium">{{ __('pengiriman.diterima') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($sc->pesanan->items as $item)
                                <tr>
                                    <td class="px-3 py-2">{{ $item->produk->nama }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">@angka($item->jumlah_dus)</td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" max="{{ $item->jumlah_dus }}"
                                               wire:model.live="jumlahCoret.{{ $item->id }}"
                                               class="block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 text-sm font-medium">
                            <tr>
                                <td class="px-3 py-2 text-right">{{ __('pengiriman.total_diterima') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-500">@angka($sc->total_dus)</td>
                                <td class="px-3 py-2 tabular-nums">@angka($this->totalCoret)</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('driver.label_foto') }}</label>
                    <input type="file" wire:model="fotoNota" accept="image/*" capture="environment"
                           class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                    @error('fotoNota') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="fotoNota" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.catatan_opsional') }}</label>
                    <textarea wire:model="catatanDriver" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupCoret"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpanCoret" wire:loading.attr="disabled" wire:target="simpanCoret,fotoNota"
                        class="rounded-lg bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="simpanCoret">{{ __('pengiriman.simpan_coret') }}</span>
                    <span wire:loading wire:target="simpanCoret">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Kampas ============ --}}
    @if ($kampasTerbuka)
        <x-modal :judul="__('pengiriman.judul_kampas')" lebar="max-w-2xl" tutup="tutupKampas">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('pengiriman.ket_kampas') }}</p>

                {{-- Pilih toko --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pengiriman.pilih_toko_kampas') }}</label>

                    @if ($this->tokoKampas)
                        <div class="mt-1 flex items-start justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $this->tokoKampas->nama }}</p>
                                <p class="text-xs text-gray-600">
                                    {{ $this->tokoKampas->kode }}
                                    @if ($this->tokoKampas->asset_id) · {{ $this->tokoKampas->asset_id }} @endif
                                    · {{ $this->tokoKampas->wilayah?->nama }}
                                </p>
                            </div>
                            <button type="button" wire:click="batalPilihTokoKampas"
                                    class="shrink-0 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                {{ __('umum.ganti') }}
                            </button>
                        </div>
                    @else
                        <div class="mb-2 mt-1 inline-flex rounded-lg border border-gray-300 p-0.5">
                            @foreach ([['ketik', 'heroicon-o-pencil-square', __('pesanan.cara_ketik')], ['pindai', 'heroicon-o-camera', __('pesanan.cara_pindai')]] as [$cara, $ikon, $label])
                                <button type="button" wire:click="gantiCaraPilih('{{ $cara }}')"
                                        @class([
                                            'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                            'bg-blue-600 text-white' => $caraPilihToko === $cara,
                                            'text-gray-600 hover:bg-gray-50' => $caraPilihToko !== $cara,
                                        ])>
                                    @svg($ikon, 'size-4 inline mr-1') {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        @if ($caraPilihToko === 'ketik')
                            <input type="search" wire:model.live.debounce.300ms="cariToko"
                                   placeholder="{{ __('pesanan.cari_toko') }}"
                                   class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">

                            @if (mb_strlen(trim($cariToko)) >= 2)
                                <div class="mt-2 max-h-52 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
                                    @forelse ($this->hasilCariToko as $toko)
                                        <button type="button" wire:click="pilihTokoKampas({{ $toko->id }})"
                                                class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-gray-50">
                                            <span class="font-medium text-gray-900">{{ $toko->nama }}</span>
                                            <span class="truncate text-xs text-gray-500">
                                                {{ $toko->kode }}
                                                @if ($toko->asset_id) · {{ $toko->asset_id }} @endif
                                                · {{ $toko->wilayah?->nama ?? __('pesanan.toko_tanpa_wilayah') }}
                                            </span>
                                        </button>
                                    @empty
                                        <p class="px-3 py-4 text-center text-sm text-gray-500">{{ __('pesanan.tidak_ada_toko') }}</p>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                    @endif
                </div>

                {{-- Pemindai QR, selalu di DOM dan dikendalikan Alpine --}}
                <div wire:ignore id="pemindai-kampas"
                     x-data="{ get aktif() { return $wire.kampasTerbuka && $wire.caraPilihToko === 'pindai' && ! $wire.tokoKampasId } }"
                     x-show="aktif"
                     x-effect="aktif || window.hentikanPindaiKampas?.()"
                     x-cloak>
                    <div id="bidik-kampas" class="relative aspect-[4/3] w-full overflow-hidden rounded-lg bg-slate-900">
                        <video playsinline muted class="size-full object-cover"></video>
                        <div class="pointer-events-none absolute inset-0 grid place-items-center">
                            <div class="size-40 rounded-2xl border-4 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                        </div>
                        <div class="absolute right-2 top-2 flex flex-col gap-2">
                            <button type="button" id="lensa-kampas" title="{{ __('kunjungan.ganti_lensa') }}"
                                    class="hidden rounded-full bg-black/55 px-3 py-2 text-base text-white backdrop-blur"><x-heroicon-o-arrow-path class="size-4 inline" /></button>
                            <button type="button" id="senter-kampas" title="{{ __('kunjungan.senter') }}"
                                    class="hidden rounded-full bg-black/55 px-3 py-2 text-base text-white backdrop-blur"><x-heroicon-o-light-bulb class="size-4 inline" /></button>
                        </div>
                    </div>
                    <button type="button" id="mulai-pindai-kampas"
                            class="mt-2 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        <x-heroicon-o-camera class="size-4 inline" /> {{ __('kunjungan.nyalakan_kamera') }}
                    </button>
                    <p id="pesan-pindai-kampas" class="mt-2 hidden rounded-lg bg-red-50 p-2 text-xs text-red-800"></p>
                </div>

                {{-- Produk yang bisa diampaskan --}}
                <div>
                    <p class="text-sm font-medium text-gray-700">{{ __('pengiriman.jatah_kampas') }}</p>

                    @forelse ($this->jatahKampas as $baris)
                        @php
                            $pid = $baris['produk']->id;
                            $lebih = (int) ($jumlahKampas[$pid] ?? 0) > $baris['tersedia'];
                            $dipotong = array_key_exists($pid, $lewatJatah);
                        @endphp
                        <div class="mt-2 rounded-lg border p-2 {{ $lebih || $dipotong ? 'border-red-300 bg-red-50' : 'border-gray-200' }}">
                            <div class="flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $baris['produk']->nama }}</p>
                                    <p class="text-xs {{ $lebih || $dipotong ? 'text-red-700' : 'text-gray-500' }}">
                                        {{ __('pengiriman.tersedia') }} @angka($baris['tersedia']) {{ __('umum.satuan_dus') }}
                                    </p>
                                </div>
                                <input type="number" min="0" max="{{ $baris['tersedia'] }}" placeholder="0"
                                       wire:model.live="jumlahKampas.{{ $pid }}"
                                       class="w-24 rounded-lg text-sm tabular-nums shadow-sm {{ $lebih || $dipotong ? 'border-red-400 text-red-800 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500' }}">
                            </div>

                            @if ($lebih || $dipotong)
                                <p class="mt-1.5 text-xs text-red-700">
                                    {{ __('pengiriman.lewat_jatah', ['produk' => $baris['produk']->nama, 'jumlah' => \App\Support\Bahasa::angka($baris['tersedia'])]) }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <x-kosong ikon="cube" :judul="__('pengiriman.tidak_ada_sisa')" :keterangan="__('pengiriman.tidak_ada_sisa_ket')" />
                    @endforelse

                    @if ($this->jatahKampas->isNotEmpty())
                        <p class="mt-2 text-right text-sm font-medium {{ $this->kampasMelebihiJatah ? 'text-red-700' : 'text-gray-900' }}">
                            {{ __('umum.total') }}: @angka($this->totalKampas) / @angka($this->totalJatahKampas) {{ __('umum.satuan_dus') }}
                        </p>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('driver.label_foto') }}</label>
                    <input type="file" wire:model="fotoNota" accept="image/*" capture="environment"
                           class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                    @error('fotoNota') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="fotoNota" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.catatan_opsional') }}</label>
                    <textarea wire:model="catatanKampas" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupKampas"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpanKampas" wire:loading.attr="disabled" wire:target="simpanKampas,fotoNota"
                        @disabled($tokoKampasId === null || $this->totalKampas === 0 || $this->kampasMelebihiJatah)
                        class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                    <span wire:loading.remove wire:target="simpanKampas">{{ __('pengiriman.simpan_kampas') }}</span>
                    <span wire:loading wire:target="simpanKampas">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @script
    <script>
        const wadahKampas = document.getElementById('pemindai-kampas');

        if (wadahKampas) {
            const pemindai = window.pasangPemindaiQr('pemindai-kampas', {
                pesan: @js([
                    'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
                    'gagal' => __('kunjungan.kamera_gagal'),
                    'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
                    'belumSiap' => __('kunjungan.kamera_belum_siap'),
                    'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
                ]),
            });

            document.getElementById('mulai-pindai-kampas')?.addEventListener('click', () => pemindai?.mulai());
            document.getElementById('lensa-kampas')?.addEventListener('click', () => pemindai?.gantiKamera());
            document.getElementById('senter-kampas')?.addEventListener('click', () => pemindai?.alihkanSenter());

            wadahKampas.addEventListener('qr:terbaca', (e) => $wire.pilihTokoKampasDariQr(e.detail));
            wadahKampas.addEventListener('qr:galat', (e) => pesanKampas(e.detail));
            wadahKampas.addEventListener('qr:butuh-sentuhan', (e) => pesanKampas(e.detail));

            wadahKampas.addEventListener('qr:siap', (e) => {
                document.getElementById('mulai-pindai-kampas')?.classList.add('hidden');
                pesanKampas(null);
                document.getElementById('lensa-kampas')?.classList.toggle('hidden', (e.detail?.jumlahKamera ?? 0) <= 1);
                document.getElementById('senter-kampas')?.classList.toggle('hidden', ! e.detail?.adaSenter);
            });

            document.getElementById('bidik-kampas')?.addEventListener('click', function (e) {
                const kotak = this.getBoundingClientRect();

                pemindai?.cobaMainkanLagi();
                pemindai?.fokusDi(
                    Math.min(1, Math.max(0, (e.clientX - kotak.left) / kotak.width)),
                    Math.min(1, Math.max(0, (e.clientY - kotak.top) / kotak.height)),
                );
            });

            $wire.on('qr-toko-ditolak', () => pemindai?.ulangi());

            window.hentikanPindaiKampas = () => {
                pemindai?.berhenti();
                document.getElementById('mulai-pindai-kampas')?.classList.remove('hidden');
            };

            function pesanKampas(teks) {
                const el = document.getElementById('pesan-pindai-kampas');

                if (el) {
                    el.textContent = teks ?? '';
                    el.classList.toggle('hidden', !teks);
                }
            }
        }
    </script>
    @endscript
</div>
