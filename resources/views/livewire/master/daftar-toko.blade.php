<div>
    <x-judul-halaman :judul="__('master.judul_toko')" :keterangan="__('master.ket_toko')">
        <x-slot:aksi>
            <button type="button" wire:click="$set('imporTerbuka', true)"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('master.impor_csv') }}
            </button>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('master.toko_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($this->jumlahTanpaKoordinat > 0)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-sm text-amber-900">
                {!! __('master.peringatan_koordinat', ['jumlah' => '<strong>'.$this->jumlahTanpaKoordinat.'</strong>']) !!}
            </p>
            <button type="button" wire:click="geocodeMassal" wire:loading.attr="disabled" wire:target="geocodeMassal"
                    class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="geocodeMassal">{{ __('master.cari_koordinat_massal', ['jumlah' => 20]) }}</span>
                <span wire:loading wire:target="geocodeMassal">{{ __('master.mencari_tunggu') }}</span>
            </button>
        </div>
    @endif

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="min-w-56 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="{{ __('master.cari_toko') }}"
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
            <label class="flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" wire:model.live="tanpaKoordinat"
                       class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                {{ __('master.hanya_tanpa_koordinat') }}
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('kunjungan.asset_id') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.nama') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.wilayah') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.alamat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.koordinat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->tokos as $toko)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $toko->kode }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($toko->asset_id)
                                    <span class="text-xs tabular-nums text-gray-700">{{ $toko->asset_id }}</span>
                                @else
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $toko->nama }}</span>
                                @if ($toko->pesanan_aktif)
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">{{ __('master.pesanan_aktif') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $toko->wilayah->nama }}</td>
                            <td class="max-w-xs truncate px-4 py-2 text-gray-600">{{ $toko->alamat }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($toko->punya_koordinat)
                                    <span class="text-xs tabular-nums text-gray-600">
                                        {{ number_format($toko->latitude, 5, '.', '') }}, {{ number_format($toko->longitude, 5, '.', '') }}
                                    </span>
                                    <span @class([
                                        'ml-1 rounded px-1.5 py-0.5 text-xs',
                                        'bg-emerald-100 text-emerald-800' => $toko->sumber_koordinat === 'manual',
                                        'bg-blue-100 text-blue-800' => $toko->sumber_koordinat === 'geocode',
                                    ])>
                                        {{ $toko->sumber_koordinat === 'manual' ? __('master.sumber_manual') : __('master.sumber_otomatis') }}
                                    </span>
                                @else
                                    <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">{{ __('master.belum_ada_koordinat') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($toko->aktif)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('umum.aktif') }}</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <button type="button" wire:click="sunting({{ $toko->id }})"
                                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('umum.sunting') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-kosong ikon="building-storefront" :judul="__('master.toko_kosong')" :keterangan="__('master.toko_kosong_ket')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->tokos->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->tokos->links() }}</div>
        @endif
    </x-kartu>

    {{-- Formulir toko --}}
    @if ($formTerbuka)
        <x-modal :judul="$tokoId ? __('master.judul_toko_sunting') : __('master.judul_toko_baru')" lebar="max-w-4xl" tutup="tutupForm">
            <div class="grid gap-5 p-5 lg:grid-cols-2">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('umum.kode') }}</label>
                            <input type="text" wire:model="kode"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('kode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('umum.wilayah') }}</label>
                            <select wire:model="wilayahId"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                @foreach ($this->wilayahs as $w)
                                    <option value="{{ $w->id }}">{{ $w->nama }}</option>
                                @endforeach
                            </select>
                            @error('wilayahId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('master.asset_id') }}</label>
                            <input type="text" wire:model="assetId" placeholder="IDNAH202528004381"
                                   class="mt-1 block w-full font-mono uppercase rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('assetId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-500">{{ __('master.asset_id_ket') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('master.freezer_tipe') }}</label>
                            <input type="text" wire:model="freezerTipe" placeholder="SD-280"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.nama_toko') }}</label>
                        <input type="text" wire:model="nama"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('umum.alamat') }}</label>
                        <textarea wire:model="alamat" rows="2"
                                  class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                        @error('alamat') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        @foreach ([
                            ['kelurahan', __('master.kelurahan')],
                            ['kecamatan', __('master.kecamatan')],
                            ['kota', __('master.kota')],
                            ['kodePos', __('master.kode_pos')],
                        ] as [$prop, $label])
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                                <input type="text" wire:model="{{ $prop }}"
                                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('umum.telepon') }}</label>
                            <input type="text" wire:model="telepon"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('master.nama_pemilik') }}</label>
                            <input type="text" wire:model="namaPemilik"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="aktif" class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        {{ __('master.toko_aktif') }}
                    </label>
                </div>

                {{-- Koordinat --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.titik_koordinat') }}</label>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    x-data="{ mencari: false }"
                                    @click="
                                        if (!navigator.geolocation) {
                                            window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: @js(__('master.gps_tidak_didukung')), jenis: 'error' } }));
                                            return;
                                        }
                                        mencari = true;
                                        navigator.geolocation.getCurrentPosition(
                                            (pos) => {
                                                mencari = false;
                                                $wire.call('lokasiSayaDipilih', pos.coords.latitude, pos.coords.longitude);
                                            },
                                            () => {
                                                mencari = false;
                                                window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: @js(__('master.gps_gagal')), jenis: 'error' } }));
                                            },
                                            { enableHighAccuracy: true, timeout: 10000 }
                                        );
                                    "
                                    :disabled="mencari"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50 disabled:opacity-60">
                                <span x-show="!mencari">{{ __('master.lokasi_saya') }}</span>
                                <span x-show="mencari" x-cloak>{{ __('umum.mencari') }}</span>
                            </button>
                            <button type="button" wire:click="cariKoordinat" wire:loading.attr="disabled" wire:target="cariKoordinat"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50 disabled:opacity-60">
                                <span wire:loading.remove wire:target="cariKoordinat">{{ __('master.cari_dari_alamat') }}</span>
                                <span wire:loading wire:target="cariKoordinat">{{ __('umum.mencari') }}</span>
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">{{ __('master.ket_peta_pilih') }}</p>

                    <div wire:ignore id="peta-pemilih" class="peta h-72 rounded-lg border border-gray-200"></div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600">{{ __('master.tempel_koordinat') }}</label>
                        <div class="mt-1 flex gap-2">
                            <input type="text" wire:model="koordinatTempel" wire:keydown.enter.prevent="terapkanKoordinatTempel"
                                   placeholder="{{ __('master.tempel_koordinat_placeholder') }}"
                                   class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            <button type="button" wire:click="terapkanKoordinatTempel"
                                    class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                                {{ __('umum.terapkan') }}
                            </button>
                        </div>
                        @error('koordinatTempel')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">{{ __('master.ket_tempel_koordinat') }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('umum.latitude') }}</label>
                            <input type="number" step="any" wire:model="latitude"
                                   class="mt-1 block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ __('umum.longitude') }}</label>
                            <input type="number" step="any" wire:model="longitude"
                                   class="mt-1 block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    @if ($hasilGeocode)
                        <p class="rounded-lg bg-blue-50 p-2 text-xs text-blue-900">{{ $hasilGeocode }}</p>
                    @endif

                    @if ($latitude === null)
                        <p class="rounded-lg bg-amber-50 p-2 text-xs text-amber-900">{{ __('master.tanpa_koordinat_peringatan') }}</p>
                    @endif
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- Impor CSV --}}
    @if ($imporTerbuka)
        <x-modal :judul="__('master.judul_impor')" lebar="max-w-2xl" tutup="$set('imporTerbuka', false)">
            <div class="space-y-4 p-5">
                <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                    <p class="font-medium">{{ __('master.kolom_dikenali') }}</p>
                    <p class="mt-1 text-xs">
                        <code>kode</code>, <code>nama</code>, <code>alamat</code>, <code>wilayah</code>,
                        <code>kelurahan</code>, <code>kecamatan</code>, <code>kota</code>, <code>kode_pos</code>,
                        <code>telepon</code>, <code>pemilik</code>, <code>latitude</code>, <code>longitude</code>.
                    </p>
                    <p class="mt-2 text-xs">
                        {{ __('master.ket_impor', ['wajib' => 'nama, alamat, wilayah']) }}
                    </p>
                    <button type="button" wire:click="unduhContohCsv"
                            class="mt-2 text-xs font-semibold text-blue-600 underline">{{ __('master.unduh_contoh') }}</button>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('master.berkas_csv') }}</label>
                    <input type="file" wire:model="berkasCsv" accept=".csv,text/csv"
                           class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                    @error('berkasCsv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="berkasCsv" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>
                </div>

                @if ($hasilImpor)
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm">
                        <p class="font-medium text-emerald-900">
                            {{ __('master.hasil_impor', ['baru' => $hasilImpor['baru'], 'diperbarui' => $hasilImpor['diperbarui']]) }}
                        </p>
                        @if ($hasilImpor['dilewati'])
                            <p class="mt-2 font-medium text-amber-900">
                                {{ __('master.baris_dilewati', ['jumlah' => count($hasilImpor['dilewati'])]) }}
                            </p>
                            <ul class="mt-1 max-h-32 space-y-0.5 overflow-y-auto text-xs text-amber-800">
                                @foreach ($hasilImpor['dilewati'] as $baris)
                                    <li>• {{ $baris }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('imporTerbuka', false)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.tutup') }}</button>
                <button type="button" wire:click="imporCsv" wire:loading.attr="disabled" wire:target="imporCsv,berkasCsv"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="imporCsv">{{ __('master.proses_impor') }}</span>
                    <span wire:loading wire:target="imporCsv">{{ __('umum.memproses') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @script
    <script>
        let pemilih = null;

        // Peta pemilih hanya ada ketika formulir terbuka, jadi pemasangannya
        // ditunda sampai elemennya benar-benar muncul di halaman.
        const pasang = () => {
            const wadah = document.getElementById('peta-pemilih');

            if (!wadah || pemilih) {
                return;
            }

            pemilih = window.pasangPemilihTitik('peta-pemilih', @js($this->konfigPeta));
        };

        const lepas = () => { pemilih = null; };

        pasang();
        Livewire.hook('morphed', () => document.getElementById('peta-pemilih') ? pasang() : lepas());

        window.addEventListener('titik-dipilih', (e) => {
            $wire.call('titikDipilih', e.detail.lat, e.detail.lng);
        });

        $wire.on('pindahkan-penanda', (payload) => {
            const d = payload.lat !== undefined ? payload : payload[0];
            pemilih?.pindah(d.lat, d.lng);
        });
    </script>
    @endscript
</div>
