<div>
    <x-judul-halaman :judul="__('noo.judul_routing')" :keterangan="__('noo.ket_routing')" />

    @if ($depotBelumDipilih)
        <x-butuh-depot-terkunci />
    @else
        {{-- ============ Yang menunggu dirutekan ============ --}}
        <x-kartu>
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-100 p-4">
                <div>
                    <p class="text-sm font-semibold text-gray-900">
                        {{ __('noo.menunggu_rute', ['jumlah' => $this->siapRouting->count()]) }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ __('noo.ket_menunggu_rute') }}</p>
                </div>

                <div class="flex items-end gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">{{ __('routing.tanggal_keberangkatan') }}</label>
                        <input type="date" wire:model="tanggalKeberangkatan"
                               class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate"
                            @disabled(count($terpilih) === 0)
                            class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                        <span wire:loading.remove wire:target="generate">
                            {{ count($terpilih) > 0 ? __('noo.tombol_susun_rute_terpilih', ['jumlah' => count($terpilih)]) : __('noo.tombol_susun_rute') }}
                        </span>
                        <span wire:loading wire:target="generate">{{ __('umum.memproses') }}</span>
                    </button>
                </div>
            </div>

            @if ($this->siapRouting->isNotEmpty())
                <label class="flex items-center gap-2 border-b border-gray-100 px-4 py-2 text-xs font-medium text-gray-600">
                    <input type="checkbox" wire:model.live="pilihSemua"
                           class="rounded border-gray-400 text-blue-600 focus:ring-blue-500/20">
                    {{ __('noo.pilih_semua_menunggu') }}
                </label>

                <div class="divide-y divide-gray-100">
                    @foreach ($this->siapRouting as $noo)
                        <label class="flex flex-wrap items-center gap-3 px-4 py-2 text-sm hover:bg-gray-50" wire:key="siap-{{ $noo->id }}">
                            <input type="checkbox" wire:model.live="terpilih" value="{{ $noo->id }}"
                                   class="rounded border-gray-400 text-blue-600 focus:ring-blue-500/20">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900">{{ $noo->nama }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ $noo->kode }} · {{ $noo->toko?->kode }}</span>
                            </div>
                            <span class="text-xs text-gray-500">{{ $noo->alamat }}</span>
                        </label>
                    @endforeach
                </div>
            @else
                <x-kosong ikon="truck" :judul="__('noo.tidak_ada_siap_rute')" :keterangan="__('noo.ket_tidak_ada_siap_rute')" />
            @endif
        </x-kartu>

        {{-- ============ Peta rute ============ --}}
        <x-kartu :judul="__('routing.peta_rute')" class="mt-4">
            <div wire:ignore id="peta-routing-noo" class="peta h-[480px]"></div>
        </x-kartu>

        {{-- ============ Draf/rute yang sudah disusun ============ ---
             Beberapa kartu sekaligus, satu per batch — bukan hanya yang
             terakhir dibuat, karena tanggal keberangkatannya boleh berbeda
             satu sama lain (lihat docblock App\Livewire\Noo\RoutingFreezer). --}}
        @foreach ($this->batches as $b)
            <x-kartu class="mt-4" wire:key="batch-{{ $b->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $b->kode }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $b->tanggal?->isoFormat('ll') }} ·
                            {{ __('noo.ringkas_rute', ['mobil' => $b->total_kendaraan, 'freezer' => $b->total_dus]) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($b->isDraft())
                            <button type="button" wire:click="$set('konfirmasiHapus', {{ $b->id }})"
                                    class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                                {{ __('routing.hapus_draft') }}
                            </button>
                            <button type="button" wire:click="setujui({{ $b->id }})" wire:loading.attr="disabled" wire:target="setujui({{ $b->id }})"
                                    class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                                <span wire:loading.remove wire:target="setujui({{ $b->id }})">{{ __('noo.tombol_setujui_rute') }}</span>
                                <span wire:loading wire:target="setujui({{ $b->id }})">{{ __('umum.menyimpan') }}</span>
                            </button>
                        @else
                            <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">
                                {{ __('noo.rute_sudah_disetujui') }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach ($b->kendaraans as $kendaraan)
                        <div class="p-4" wire:key="mobil-{{ $kendaraan->id }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="size-3 rounded-full" style="background: {{ $kendaraan->warna }}"></span>
                                    <span class="text-sm font-semibold text-gray-900">{{ $kendaraan->nama }}</span>
                                    <span class="text-xs text-gray-500">
                                        {{ __('noo.ringkas_mobil', ['freezer' => $kendaraan->total_dus, 'km' => number_format($kendaraan->total_jarak_m / 1000, 1)]) }}
                                    </span>
                                </div>

                                @if ($b->isDraft())
                                    <select wire:change="ubahDriver({{ $kendaraan->id }}, $event.target.value)"
                                            class="rounded-lg border-gray-400 bg-gray-50 px-3 py-1.5 text-xs text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                        <option value="">{{ __('routing.driver_belum_ditentukan') }}</option>
                                        @foreach ($this->drivers as $driver)
                                            <option value="{{ $driver->id }}" @selected($kendaraan->driver_id === $driver->id)>{{ $driver->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-xs text-gray-600">{{ $kendaraan->driver?->name ?? __('routing.driver_belum_ditentukan') }}</span>
                                @endif
                            </div>

                            <ol class="mt-2 space-y-1">
                                @foreach ($kendaraan->stops as $stop)
                                    <li id="stop-{{ $stop->id }}" class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 text-xs scroll-mt-4" wire:key="stop-{{ $stop->id }}">
                                        <span class="grid size-5 shrink-0 place-items-center rounded-full bg-white text-[10px] font-semibold text-gray-700">
                                            {{ $stop->urutan }}
                                        </span>
                                        <span class="font-medium text-gray-900">{{ $stop->noo?->nama }}</span>
                                        <span class="text-gray-500">{{ $stop->noo?->kode }}</span>
                                        @if ($stop->noo?->freezer_tipe)
                                            <span class="rounded bg-white px-1.5 py-0.5 text-gray-600">{{ $stop->noo->freezer_tipe }}</span>
                                        @endif
                                        <span class="ml-auto text-gray-500">{{ $stop->eta }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </x-kartu>
        @endforeach
    @endif

    @if ($konfirmasiHapus)
        <x-modal :judul="__('routing.hapus_draft')" tutup="$set('konfirmasiHapus', null)">
            <p class="p-5 text-sm text-gray-600">{{ __('noo.ket_hapus_draft_rute') }}</p>
            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="hapusDraft"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('umum.hapus') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif
    {{-- Peta rute freezer — pola yang sama dengan Generate Routing pesanan
         (resources/views/livewire/routing/generate-routing.blade.php),
         cuma titiknya dari NOO. Komentar JS TIDAK ditaruh di baris pertama
         <script> ini dengan sengaja; lihat penjelasan soal itu di
         daftar-kunjungan.blade.php. --}}
    @script
    <script>
        const petaNoo = window.pasangPetaRute('peta-routing-noo', @js($this->konfigPeta));

        if (petaNoo) {
            petaNoo.gambar(@js($this->dataPeta));

            $wire.on('peta-diperbarui', (payload) => {
                petaNoo.gambar(payload.data ?? payload[0]?.data ?? payload);
            });

            window.Livewire.on('stop-dipilih', ({ stopId }) => {
                document.getElementById('stop-' + stopId)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
    </script>
    @endscript
</div>