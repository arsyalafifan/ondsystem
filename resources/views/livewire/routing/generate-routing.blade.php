<div>
    @php $batch = $this->batch; @endphp

    <x-judul-halaman :judul="__('routing.judul')" :keterangan="__('routing.ket')">
        <x-slot:aksi>
            @if ($batch)
                <button type="button" wire:click="unduhCsv"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('routing.unduh_csv') }}
                </button>
            @endif
            <a href="{{ route('routing.riwayat') }}" wire:navigate
               class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('routing.riwayat') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($peringatan)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">{{ __('routing.perlu_perhatian') }}</p>
            <ul class="mt-1.5 space-y-1 text-sm text-amber-800">
                @foreach ($peringatan as $p)
                    <li class="flex gap-2"><span>•</span><span>{{ $p }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- =============== Belum ada rute: panel pengaturan =============== --}}
    @if (! $batch)
        @php $r = $this->ringkasanMenunggu; @endphp

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-1">
                <x-kartu :judul="__('routing.menunggu')">
                    <div class="grid grid-cols-2 gap-px bg-gray-200">
                        <div class="bg-white p-4">
                            <p class="text-xs text-gray-500">{{ __('routing.siap_dirutekan') }}</p>
                            <p class="text-2xl font-semibold tabular-nums text-gray-900">{{ $r['layak'] }}</p>
                            <p class="text-xs text-gray-500">{{ __('umum.toko') }}</p>
                        </div>
                        <div class="bg-white p-4">
                            <p class="text-xs text-gray-500">{{ __('routing.total_muatan') }}</p>
                            <p class="text-2xl font-semibold tabular-nums text-gray-900">@angka($r['total_dus'])</p>
                            <p class="text-xs text-gray-500">{{ __('umum.satuan_dus') }}</p>
                        </div>
                    </div>

                    @if ($r['tanpa_koordinat'] > 0)
                        <div class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            {{ __('routing.tanpa_koordinat_lewat', ['jumlah' => $r['tanpa_koordinat']]) }}
                            <a href="{{ route('master.toko') }}?tanpaKoordinat=1" wire:navigate class="font-semibold underline">
                                {{ __('routing.lengkapi_sekarang') }}
                            </a>
                        </div>
                    @endif

                    @if ($r['layak'] > 0)
                        <div class="border-t border-gray-200 px-4 py-3 text-sm text-gray-600">
                            {!! __('routing.perkiraan_kendaraan', ['jumlah' => '<strong class="text-gray-900">'.$r['perkiraan_kendaraan'].'</strong>']) !!}
                        </div>
                    @endif
                </x-kartu>

                <x-kartu :judul="__('routing.aturan')">
                    <div class="space-y-4 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600">{{ __('routing.maks_toko') }}</label>
                                <input type="number" min="1" wire:model.live="maxToko"
                                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">{{ __('routing.maks_dus') }}</label>
                                <input type="number" min="1" wire:model.live="maxDus"
                                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">{{ __('routing.ket_batas') }}</p>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('umum.wilayah') }}</label>
                            <div class="mt-1 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
                                @foreach ($this->wilayahs as $w)
                                    <label class="flex items-center gap-2 rounded px-1 py-0.5 text-sm hover:bg-gray-50">
                                        <input type="checkbox" wire:model.live="wilayahDipilih" value="{{ $w->id }}"
                                               class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                        {{ $w->nama }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ __('routing.kosongkan_semua_wilayah') }}</p>
                        </div>

                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" wire:model.live="pisahPerWilayah"
                                   class="mt-0.5 rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            <span>
                                {{ __('routing.satu_wilayah') }}
                                <span class="block text-xs text-gray-500">{{ __('routing.ket_satu_wilayah') }}</span>
                            </span>
                        </label>

                        <button type="button" wire:click="generate" wire:loading.attr="disabled"
                                @disabled($r['layak'] === 0)
                                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                            <span wire:loading.remove wire:target="generate">{{ __('routing.tombol_generate') }}</span>
                            <span wire:loading wire:target="generate">{{ __('routing.menyusun') }}</span>
                        </button>

                        @if ($r['layak'] === 0)
                            <p class="text-center text-xs text-gray-500">
                                {!! __('routing.belum_ada_process', [
                                    'tautan' => '<a href="'.route('pesanan.daftar').'" wire:navigate class="underline">'.e(__('pesanan.judul_daftar')).'</a>',
                                ]) !!}
                            </p>
                        @endif
                    </div>
                </x-kartu>
            </div>

            <div class="lg:col-span-2">
                <x-kartu :judul="__('routing.sebaran_menunggu')">
                    <x-slot:aksi>
                        <x-tombol-lokasi-saya />
                    </x-slot:aksi>

                    <div wire:ignore id="peta-routing" class="peta h-[560px] rounded-b-xl"></div>
                </x-kartu>
            </div>
        </div>
    @else
        {{-- =============== Sudah ada rute =============== --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <div>
                    <span class="font-semibold text-gray-900">{{ $batch->kode }}</span>
                    @if ($batch->isDraft())
                        <span class="ml-1 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('routing.draft') }}</span>
                    @else
                        <span class="ml-1 rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('routing.disetujui') }}</span>
                    @endif
                </div>
                <span class="text-gray-600"><x-heroicon-o-truck class="size-4 inline" /> {{ $batch->total_kendaraan }} {{ __('umum.mobil') }}</span>
                <span class="text-gray-600"><x-heroicon-o-building-storefront class="size-4 inline" /> {{ $batch->total_toko }} {{ __('umum.toko') }}</span>
                <span class="text-gray-600"><x-heroicon-o-cube class="size-4 inline" /> @angka($batch->total_dus) {{ __('umum.satuan_dus') }}</span>
                <span class="text-gray-600"><x-heroicon-o-arrows-right-left class="size-4 inline" /> @angka($batch->total_jarak_m / 1000, 1) km</span>
                <span class="text-gray-600">
                    <x-heroicon-o-signal class="size-4 inline" /> {{ $batch->sumber_jarak === 'osrm' ? __('routing.jarak_osrm') : __('routing.jarak_lurus') }}
                </span>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($batch->isDraft())
                    <button type="button" wire:click="tambahKendaraan"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        {{ __('routing.mobil_kosong') }}
                    </button>
                    <button type="button" wire:click="$set('konfirmasiHapus', true)"
                            class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                        {{ __('routing.hapus_draft') }}
                    </button>
                    <button type="button" wire:click="setujui" wire:loading.attr="disabled"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                        {{ __('routing.tombol_setujui') }}
                    </button>
                @else
                    <span class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        {{ __('routing.disetujui_oleh_pada', [
                            'nama' => $batch->penyetuju?->name,
                            'waktu' => $batch->disetujui_at?->isoFormat('ll').' '.$batch->disetujui_at?->format('H:i'),
                        ]) }}
                    </span>
                @endif
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-5">
            {{-- Daftar kendaraan --}}
            <div class="space-y-3 lg:col-span-2">
                @foreach ($batch->kendaraans as $kendaraan)
                    @php $dibuka = $kendaraanDibuka === $kendaraan->id; @endphp

                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <div class="flex items-center gap-3 border-l-4 px-4 py-3" style="border-left-color: {{ $kendaraan->warna }}">
                            <button type="button"
                                    wire:click="$set('kendaraanDibuka', {{ $dibuka ? 'null' : $kendaraan->id }})"
                                    class="min-w-0 flex-1 text-left">
                                <p class="flex items-center gap-2 font-semibold text-gray-900">
                                    {{ $kendaraan->nama }}
                                    @if ($kendaraan->wilayah)
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-normal text-gray-600">
                                            {{ $kendaraan->wilayah->nama }}
                                        </span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    {{ $kendaraan->total_toko }} {{ __('umum.toko') }} ·
                                    @angka($kendaraan->total_dus) {{ __('umum.satuan_dus') }} ·
                                    @angka($kendaraan->jarak_km, 1) km ·
                                    {{ $kendaraan->durasi_jam }}
                                    @if ($kendaraan->estimasi_selesai)
                                        · {{ __('routing.selesai_pukul', ['waktu' => substr((string) $kendaraan->estimasi_selesai, 0, 5)]) }}
                                    @endif
                                </p>
                            </button>

                            <button type="button" onclick="window.sorotKendaraan({{ $kendaraan->id }})"
                                    title="{{ __('routing.lihat_di_peta') }}"
                                    class="rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50"><x-heroicon-o-magnifying-glass class="size-4 inline" /></button>

                            <span class="text-gray-400">
                                @if($dibuka)
                                    <x-heroicon-o-chevron-down class="size-3 inline" />
                                @else
                                    <x-heroicon-o-chevron-right class="size-3 inline" />
                                @endif
                            </span>
                        </div>

                        {{-- Bar muatan terhadap kedua batas --}}
                        <div class="flex gap-2 px-4 pb-2 text-xs text-gray-500">
                            @foreach ([
                                [__('umum.toko'), $kendaraan->total_toko, $batch->max_toko],
                                [__('umum.dus'), $kendaraan->total_dus, $batch->max_dus],
                            ] as [$label, $nilai, $batas])
                                @php $persen = $batas > 0 ? min(100, (int) round($nilai / $batas * 100)) : 0; @endphp
                                <div class="flex-1">
                                    <div class="flex justify-between"><span>{{ $label }}</span><span>{{ $nilai }}/{{ $batas }}</span></div>
                                    <div class="mt-0.5 h-1.5 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full rounded-full {{ $persen >= 95 ? 'bg-amber-500' : 'bg-blue-500' }}"
                                             style="width: {{ $persen }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($dibuka)
                            <div class="border-t border-gray-100">
                                @forelse ($kendaraan->stops as $stop)
                                    <div @class([
                                            'baris-stop flex items-start gap-2 border-b border-gray-50 px-3 py-2 text-sm last:border-0',
                                            'ditandai' => $stopDipilih === $stop->id,
                                        ])>
                                        <button type="button" onclick="window.sorotStop({{ $stop->id }})"
                                                class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold text-white"
                                                style="background: {{ $stop->status->tuntas() ? '#059669' : $kendaraan->warna }}">
                                            @if($stop->status->tuntas())
                                                <x-heroicon-o-check class="size-4 inline" />
                                            @else
                                                {{ $stop->urutan }}
                                            @endif
                                        </button>

                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-medium text-gray-900">{{ $stop->toko->nama }}</p>
                                            <p class="truncate text-xs text-gray-500">
                                                @angka($stop->total_dus) {{ __('umum.satuan_dus') }} ·
                                                {{ __('routing.dari_sebelumnya', ['jarak' => \App\Support\Bahasa::angka($stop->jarak_dari_sebelumnya_m / 1000, 1)]) }}
                                                @if ($stop->eta)
                                                    · {{ __('routing.tiba', ['waktu' => substr((string) $stop->eta, 0, 5)]) }}
                                                @endif
                                            </p>
                                        </div>

                                        @if ($batch->isDraft())
                                            <div class="flex shrink-0 items-center gap-0.5">
                                                <button type="button" wire:click="geserUrutan({{ $stop->id }}, -1)"
                                                        @disabled($loop->first) title="{{ __('routing.naikkan_urutan') }}"
                                                        class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30"><x-heroicon-o-chevron-up class="size-3 inline" /></button>
                                                <button type="button" wire:click="geserUrutan({{ $stop->id }}, 1)"
                                                        @disabled($loop->last) title="{{ __('routing.turunkan_urutan') }}"
                                                        class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30"><x-heroicon-o-chevron-down class="size-3 inline" /></button>

                                                @if ($batch->kendaraans->count() > 1)
                                                    <select onchange="if(this.value){ @this.call('pindahStop', {{ $stop->id }}, this.value); this.value=''; }"
                                                            title="{{ __('routing.pindah_ke_mobil') }}"
                                                            class="ml-1 w-16 rounded py-0.5 text-xs rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                                        <option value="">→</option>
                                                        @foreach ($batch->kendaraans as $tujuan)
                                                            @continue($tujuan->id === $kendaraan->id)
                                                            <option value="{{ $tujuan->id }}">{{ $tujuan->nama }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-gray-500">{{ __('routing.mobil_kosong_ket') }}</p>
                                @endforelse

                                @if ($batch->isDraft())
                                    <div class="flex gap-2 bg-gray-50 px-3 py-2">
                                        <button type="button" wire:click="optimalkanUlang({{ $kendaraan->id }})"
                                                wire:loading.attr="disabled"
                                                @disabled($kendaraan->total_toko < 3)
                                                class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50 disabled:opacity-50">
                                            {{ __('routing.hitung_ulang') }}
                                        </button>
                                        @if ($kendaraan->total_toko === 0)
                                            <button type="button" wire:click="hapusKendaraanKosong({{ $kendaraan->id }})"
                                                    class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                                {{ __('routing.hapus_mobil') }}
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Peta --}}
            <div class="lg:col-span-3">
                <div class="lg:sticky lg:top-6">
                    <x-kartu :judul="__('routing.peta_rute')">
                        <x-slot:aksi>
                            <x-tombol-lokasi-saya />
                            <button type="button" onclick="window.tampilkanSemuaRute()"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                {{ __('routing.tampilkan_semua') }}
                            </button>
                        </x-slot:aksi>

                        <div wire:ignore id="peta-routing" class="peta h-[620px]"></div>

                        <div class="flex flex-wrap gap-2 border-t border-gray-200 p-3">
                            @foreach ($batch->kendaraans as $k)
                                <button type="button" onclick="window.alihkanKendaraan({{ $k->id }}); this.classList.toggle('opacity-40')"
                                        class="flex items-center gap-1.5 rounded-full border border-gray-200 px-2.5 py-1 text-xs hover:bg-gray-50">
                                    <span class="size-2.5 rounded-full" style="background: {{ $k->warna }}"></span>
                                    {{ $k->nama }}
                                </button>
                            @endforeach
                        </div>
                    </x-kartu>
                </div>
            </div>
        </div>
    @endif

    {{-- Konfirmasi hapus --}}
    @if ($konfirmasiHapus)
        <x-modal :judul="__('routing.judul_hapus')" tutup="$set('konfirmasiHapus', false)">
            <p class="p-5 text-sm text-gray-600">{{ __('routing.ket_hapus') }}</p>
            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', false)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.kembali') }}</button>
                <button type="button" wire:click="hapusDraft"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('umum.hapus') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @script
    <script>
        const peta = window.pasangPetaRute('peta-routing', @js($this->konfigPeta));

        if (peta) {
            peta.gambar(@js($this->dataPeta));

            // Data baru dikirim setiap kali admin mengubah rute, supaya peta
            // tidak perlu digambar ulang dari nol oleh Livewire.
            $wire.on('peta-diperbarui', (payload) => {
                peta.gambar(payload.data ?? payload[0]?.data ?? payload);
            });

            // Dipanggil dari tombol-tombol di daftar kendaraan.
            window.sorotStop = (id) => peta.sorot(id);
            window.sorotKendaraan = (id) => peta.sorotKendaraan(id);
            window.alihkanKendaraan = (id) => peta.alihkanKendaraan(id);
            window.tampilkanSemuaRute = () => peta.tampilkanSemua();
            window.pusatkanLokasiSaya = (lat, lng) => peta.pusatkanKe(lat, lng);
        }

        // Klik penanda di peta menandai barisnya di daftar.
        window.Livewire.on('stop-dipilih', ({ stopId }) => {
            $wire.set('stopDipilih', stopId);
        });
    </script>
    @endscript
</div>
