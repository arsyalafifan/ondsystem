<div>
    <x-judul-halaman :judul="__('noo.judul_persetujuan')" :keterangan="__('noo.ket_persetujuan')" />

    @if ($depotBelumDipilih)
        <x-butuh-depot-terkunci />
    @else
        <x-kartu>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('noo.kolom_kode') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('noo.atr_nama_toko') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('master.atr_wilayah') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('noo.atr_paket') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('noo.kolom_pengaju') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->antrean as $noo)
                            <tr class="hover:bg-gray-50" wire:key="antre-{{ $noo->id }}">
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                    {{ $noo->kode }}
                                    <span class="block text-xs font-normal text-gray-500">{{ $noo->diajukan_at?->isoFormat('ll') }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-gray-900">{{ $noo->nama }}</span>
                                    <span class="block text-xs text-gray-500">{{ $noo->nama_pemilik }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $noo->wilayah?->nama }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $noo->paket?->nama }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $noo->pengaju?->name }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button type="button" wire:click="pilih({{ $noo->id }})"
                                            class="rounded-md bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                        {{ __('noo.periksa') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-kosong ikon="check-badge" :judul="__('noo.antrean_kosong')" :keterangan="__('noo.ket_antrean_kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->antrean->hasPages())
                <div class="border-t border-gray-100 p-4">{{ $this->antrean->links() }}</div>
            @endif
        </x-kartu>
    @endif

    {{-- ============ Periksa & putuskan ============ --}}
    @if ($this->noo)
        @php $n = $this->noo; @endphp
        <x-modal :judul="__('noo.judul_periksa', ['kode' => $n->kode])" tutup="tutup" lebar="max-w-3xl">
            <div class="space-y-5 p-5">
                <div class="flex flex-wrap items-center gap-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                    <span>{{ __('noo.kolom_pengaju') }}: <span class="font-medium text-gray-900">{{ $n->pengaju?->name }}</span></span>
                    <span>·</span>
                    <span>{{ $n->diajukan_at?->isoFormat('lll') }}</span>
                </div>

                @if ($this->peringatanJarak)
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3">
                        <p class="text-sm font-semibold text-amber-900">
                            <x-heroicon-o-exclamation-triangle class="size-4 inline" />
                            {{ __('noo.peringatan_jarak_judul') }}
                        </p>
                        <p class="mt-1 text-sm text-amber-900">
                            {{ __('noo.peringatan_jarak', [
                                'jarak' => $this->peringatanJarak['jarak'],
                                'toko' => $this->peringatanJarak['toko']->nama,
                                'kode' => $this->peringatanJarak['toko']->kode,
                            ]) }}
                        </p>
                    </div>
                @endif

                {{-- --- Bukti foto dari sales --- --}}
                @if ($n->fotos->isNotEmpty())
                    <div>
                        <p class="mb-2 text-sm font-semibold text-gray-900">{{ __('noo.judul_bukti') }}</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($n->fotos as $foto)
                                <div>
                                    <a href="{{ route('noo.foto', $foto) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('noo.foto', $foto) }}" alt="{{ $foto->jenis->label() }}"
                                             class="h-28 w-full rounded-lg border border-gray-200 object-cover transition hover:opacity-90">
                                    </a>
                                    <p class="mt-1 text-xs font-medium text-gray-700">{{ $foto->jenis->label() }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="rounded-lg bg-blue-50 p-3 text-xs text-blue-900">{{ __('noo.ket_koreksi_admin') }}</p>

                @include('livewire.noo.partials.form-data')

                @if ($formTolakTerbuka)
                    <div class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-3">
                        <label class="block text-sm font-medium text-red-900">{{ __('noo.alasan_tolak') }}</label>
                        <textarea wire:model="alasanTolak" rows="2" placeholder="{{ __('noo.alasan_tolak_contoh') }}"
                                  class="block w-full rounded-lg border-red-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20"></textarea>
                        @error('alasanTolak') <p class="text-xs text-red-700">{{ $message }}</p> @enderror

                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('formTolakTerbuka', false)"
                                    class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50">
                                {{ __('umum.batal') }}
                            </button>
                            <button type="button" wire:click="tolak" wire:loading.attr="disabled" wire:target="tolak"
                                    class="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                                {{ __('noo.tombol_tolak') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('formTolakTerbuka', true)"
                        class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                    {{ __('noo.tombol_tolak') }}
                </button>
                <button type="button" wire:click="simpanPerubahan" wire:loading.attr="disabled" wire:target="simpanPerubahan"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-60">
                    {{ __('noo.tombol_simpan_perubahan') }}
                </button>
                <button type="button" wire:click="setujui" wire:loading.attr="disabled" wire:target="setujui"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="setujui">{{ __('noo.tombol_setujui') }}</span>
                    <span wire:loading wire:target="setujui">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Konfirmasi jarak terlalu dekat ============ --}}
    @if ($peringatanTerbuka && $this->peringatanJarak)
        <x-modal :judul="__('noo.peringatan_jarak_judul')" tutup="$set('peringatanTerbuka', false)">
            <div class="space-y-3 p-5 text-sm text-gray-700">
                <p>
                    {{ __('noo.peringatan_jarak', [
                        'jarak' => $this->peringatanJarak['jarak'],
                        'toko' => $this->peringatanJarak['toko']->nama,
                        'kode' => $this->peringatanJarak['toko']->kode,
                    ]) }}
                </p>
                <p class="text-gray-500">{{ __('noo.ket_peringatan_jarak') }}</p>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('peringatanTerbuka', false)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="setujuiTetap" wire:loading.attr="disabled" wire:target="setujuiTetap"
                        class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60">
                    {{ __('noo.tombol_tetap_setujui') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- Peta pemilih hanya ada saat satu NOO sedang diperiksa, jadi
         pemasangannya ditunda sampai elemennya muncul. Sama persis dengan
         layar pendataan sales; lihat catatan di sana soal lat/lng yang
         diambil hidup dari $wire. --}}
    @script
    <script>
        let pemilih = null;

        const pasang = () => {
            const wadah = document.getElementById('peta-pemilih-noo');

            if (!wadah || pemilih) {
                return;
            }

            pemilih = window.pasangPemilihTitik('peta-pemilih-noo', {
                ...@js($this->konfigPeta),
                lat: $wire.latitude,
                lng: $wire.longitude,
            });
        };

        const lepas = () => { pemilih = null; };

        pasang();
        Livewire.hook('morphed', () => document.getElementById('peta-pemilih-noo') ? pasang() : lepas());

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
