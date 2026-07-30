<div>
    @php $p = $this->progres; @endphp

    <x-judul-halaman :judul="$kendaraan->nama"
                     :keterangan="($kendaraan->wilayah?->nama ?? __('driver.semua_wilayah')).' · '.\App\Support\Bahasa::angka($kendaraan->total_dus).' '.__('umum.satuan_dus').' · '.\App\Support\Bahasa::angka($kendaraan->jarak_km, 1).' km'">
        <x-slot:aksi>
            <a href="{{ route('driver.pilih-mobil') }}" wire:navigate
               class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('driver.ganti_mobil') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    {{-- Ringkasan progres --}}
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-sm text-gray-600">
                {{ __('driver.progres', ['selesai' => $p['selesai'], 'total' => $p['total'], 'dus' => \App\Support\Bahasa::angka($p['dus_terkirim'])]) }}
            </p>
            <span class="shrink-0 text-lg font-semibold tabular-nums text-gray-900">{{ $p['persen'] }}%</span>
        </div>
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $p['persen'] }}%"></div>
        </div>
    </div>

    @if ($p['belum'] === 0)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
            <p class="text-2xl">🎉</p>
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
            @php $selesai = $stop->status === 'selesai'; @endphp

            <div @class([
                    'rounded-xl border bg-white p-4',
                    'border-emerald-200 bg-emerald-50/40' => $selesai,
                    'border-gray-200' => ! $selesai,
                ])>
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold text-white"
                          style="background: {{ $selesai ? '#059669' : $kendaraan->warna }}">
                        {{ $selesai ? '✓' : $stop->urutan }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900">{{ $stop->toko->nama }}</p>
                        <p class="text-sm text-gray-600">{{ $stop->toko->alamat }}</p>

                        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>📦 @angka($stop->total_dus) {{ __('umum.satuan_dus') }}</span>
                            <span>🧾 {{ $stop->pesanan->kode }}</span>
                            @if ($stop->eta)
                                <span>🕐 {{ __('routing.tiba', ['waktu' => substr((string) $stop->eta, 0, 5)]) }}</span>
                            @endif
                            @if ($stop->toko->telepon)
                                <a href="tel:{{ $stop->toko->telepon }}" class="text-blue-600 hover:underline">📞 {{ $stop->toko->telepon }}</a>
                            @endif
                        </div>

                        @if ($stop->pesanan->catatan)
                            <p class="mt-1.5 rounded bg-amber-50 px-2 py-1 text-xs text-amber-900">
                                {{ __('umum.catatan') }}: {{ $stop->pesanan->catatan }}
                            </p>
                        @endif

                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs font-medium text-gray-600 hover:text-gray-900">
                                {{ __('driver.rincian_barang') }}
                            </summary>
                            <ul class="mt-1 space-y-0.5 text-xs text-gray-600">
                                @foreach ($stop->pesanan->items as $item)
                                    <li>• {{ $item->produk->nama }} — @angka($item->jumlah_dus) {{ __('umum.satuan_dus') }}</li>
                                @endforeach
                            </ul>
                        </details>

                        @if ($selesai)
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
                               class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-center text-sm hover:bg-gray-50">🧭</a>
                        @endif

                        @if ($selesai)
                            <a href="{{ Storage::disk('public')->url($stop->foto_nota) }}" target="_blank" rel="noopener"
                               title="{{ __('driver.lihat_foto_nota') }}"
                               class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1.5 text-center text-sm hover:bg-emerald-50">🧾</a>
                        @else
                            <button type="button" wire:click="bukaUnggah({{ $stop->id }})"
                                    class="rounded-lg bg-blue-600 px-2.5 py-1.5 text-sm text-white hover:bg-blue-700">📷</button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Unggah foto nota --}}
    @if ($stopAktif)
        @php $stop = $this->stops->firstWhere('id', $stopAktif); @endphp
        <x-modal :judul="__('driver.judul_unggah', ['toko' => $stop?->toko->nama])" tutup="tutupUnggah">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('driver.ket_unggah') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('driver.label_foto') }}</label>
                    {{-- capture="environment" membuka kamera belakang langsung di ponsel --}}
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
                              class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
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
</div>
