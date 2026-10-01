<div x-data="{ kameraTerbuka: false, kameraSlot: null }">
    <x-judul-halaman :judul="__('pengantaran_rider.judul')" :keterangan="__('pengantaran_rider.ket')" />

    @if ($depotBelumDipilih)
        <x-butuh-depot-terkunci />
    @else
        {{-- ============ Pesanan aktif ============ --}}
        <div class="mb-5">
            <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ __('pengantaran_rider.judul_aktif') }}</h2>

            @if ($this->aktif)
                @php $a = $this->aktif; @endphp
                <x-kartu>
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div>
                            <p class="font-medium text-gray-900">{{ $a->pesanan->toko->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $a->pesanan->toko->alamat }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $a->pesanan->kode }} · @angka($a->pesanan->total_dus) {{ __('umum.dus') }}</p>
                        </div>
                        <div class="flex gap-2">
                            @if ($a->pesanan->toko->latitude)
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $a->pesanan->toko->latitude }},{{ $a->pesanan->toko->longitude }}"
                                   target="_blank" rel="noopener"
                                   class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                    {{ __('driver.navigasi_ke_sini') }}
                                </a>
                            @endif
                            <button type="button" wire:click="lepas({{ $a->id }})"
                                    wire:confirm="{{ __('pengantaran_rider.konfirmasi_lepas') }}"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-gray-50">
                                {{ __('pengantaran_rider.tombol_lepas') }}
                            </button>
                            <button type="button" wire:click="bukaKonfirmasi({{ $a->id }})"
                                    class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                {{ __('pengantaran_rider.tombol_selesaikan') }}
                            </button>
                        </div>
                    </div>
                </x-kartu>
            @else
                <x-kartu>
                    <p class="p-4 text-sm text-gray-500">{{ __('pengantaran_rider.ket_tidak_ada_aktif') }}</p>
                </x-kartu>
            @endif
        </div>

        {{-- ============ Pool tersedia ============ --}}
        <div class="mb-5">
            <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ __('pengantaran_rider.judul_pool') }}</h2>

            <x-kartu>
                <div class="divide-y divide-gray-100">
                    @forelse ($this->pool as $p)
                        <div class="flex flex-wrap items-center justify-between gap-3 p-4" wire:key="pool-{{ $p->id }}">
                            <div>
                                <p class="font-medium text-gray-900">{{ $p->pesanan->toko->nama }}</p>
                                <p class="text-xs text-gray-500">{{ $p->pesanan->toko->alamat }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $p->pesanan->kode }} · @angka($p->pesanan->total_dus) {{ __('umum.dus') }}</p>
                            </div>
                            <div class="flex gap-2">
                                @if ($p->pesanan->toko->latitude)
                                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $p->pesanan->toko->latitude }},{{ $p->pesanan->toko->longitude }}"
                                       target="_blank" rel="noopener" title="{{ __('driver.buka_navigasi') }}"
                                       class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-center text-sm hover:bg-gray-50">
                                        <x-heroicon-o-paper-airplane class="size-4 inline" />
                                    </a>
                                @endif
                                <button type="button" wire:click="ambil({{ $p->id }})" wire:loading.attr="disabled"
                                        @disabled($this->aktif)
                                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                                    {{ __('pengantaran_rider.tombol_ambil') }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <x-kosong ikon="map-pin" :judul="__('pengantaran_rider.pool_kosong')" :keterangan="__('pengantaran_rider.pool_kosong_ket')" />
                    @endforelse
                </div>
            </x-kartu>

            @if ($this->aktif)
                <p class="mt-2 text-xs text-amber-700">{{ __('pengantaran_rider.ket_satu_aktif') }}</p>
            @endif
        </div>

        {{-- ============ Riwayat ============ --}}
        @if ($this->riwayat->isNotEmpty())
            <div>
                <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ __('pengantaran_rider.judul_riwayat') }}</h2>
                <x-kartu>
                    <div class="divide-y divide-gray-100">
                        @foreach ($this->riwayat as $r)
                            <div class="flex items-center justify-between gap-3 p-3 text-sm" wire:key="riwayat-{{ $r->id }}">
                                <span class="text-gray-900">{{ $r->pesanan->toko->nama }}</span>
                                <span class="text-xs text-gray-500">{{ $r->selesai_at?->isoFormat('ll HH:mm') }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-kartu>
            </div>
        @endif
    @endif

    {{-- ============ Konfirmasi penyelesaian ============ --}}
    @if ($this->pengantaranKonfirmasiModel)
        @php $pk = $this->pengantaranKonfirmasiModel; @endphp
        <x-modal :judul="__('pengantaran_rider.judul_konfirmasi', ['toko' => $pk->pesanan->toko->nama])" lebar="max-w-2xl" tutup="tutupKonfirmasi">
            <div class="space-y-4 p-5">
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="w-10 px-3 py-2 font-medium"><span class="sr-only">{{ __('driver.kolom_cek') }}</span></th>
                                <th class="px-3 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="w-24 px-3 py-2 text-right font-medium">{{ __('pengiriman.dipesan') }}</th>
                                <th class="w-28 px-3 py-2 font-medium">{{ __('pengiriman.diterima') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($pk->pesanan->items as $item)
                                @php $kurang = (int) ($jumlahKonfirmasi[$item->id] ?? $item->jumlah_dus) < $item->jumlah_dus; @endphp
                                <tr @class(['bg-amber-50' => $kurang])>
                                    <td class="px-3 py-2">
                                        <input type="checkbox" wire:model.live="dicekKonfirmasi.{{ $item->id }}"
                                               aria-label="{{ __('driver.kolom_cek') }}"
                                               class="size-5 rounded text-emerald-600 focus:ring-emerald-500">
                                    </td>
                                    <td class="px-3 py-2">{{ $item->produk->nama }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">@angka($item->jumlah_dus)</td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" max="{{ $item->jumlah_dus }}"
                                               wire:model.live="jumlahKonfirmasi.{{ $item->id }}"
                                               class="block w-full tabular-nums rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 text-sm font-medium">
                            <tr>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2 text-right">{{ __('pengiriman.total_diterima') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-500">@angka($pk->pesanan->total_dus)</td>
                                <td class="px-3 py-2 tabular-nums">@angka($this->totalKonfirmasi)</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if (! $this->semuaTercekKonfirmasi)
                    <p class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">{{ __('driver.galat_belum_tercek') }}</p>
                @endif

                @if ($this->totalKonfirmasi < $pk->pesanan->total_dus)
                    <p class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                        {{ __('pengantaran_rider.ket_kekurangan_dilepas', ['dus' => \App\Support\Bahasa::angka($pk->pesanan->total_dus - $this->totalKonfirmasi)]) }}
                    </p>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('driver.label_foto') }}</label>
                    <input type="file" wire:model="fotoNota" accept="image/*"
                           class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                    @error('fotoNota') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="fotoNota" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>

                    @if ($fotoNota)
                        <img src="{{ $fotoNota->temporaryUrl() }}" alt="{{ __('driver.label_foto') }}"
                             class="mt-3 max-h-56 rounded-lg border border-gray-200">
                    @endif
                </div>

                {{-- ============ Bukti pengiriman tambahan ============ --}}
                <div>
                    <p class="text-sm font-medium text-gray-700">{{ __('pengiriman.judul_bukti_tambahan') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (\App\Enums\JenisBuktiPengiriman::wajibFoto() as $jenisBukti)
                        @php $gambarBukti = $buktiFoto[$jenisBukti->value] ?? null; @endphp
                        <div class="rounded-lg border border-gray-200 p-2 text-center" wire:key="bukti-{{ $jenisBukti->value }}">
                            <p class="truncate text-xs font-medium text-gray-700" title="{{ $jenisBukti->petunjuk() }}">{{ $jenisBukti->label() }}</p>
                            @if ($gambarBukti)
                                <img src="{{ $gambarBukti }}" alt="{{ $jenisBukti->label() }}" class="mx-auto mt-1.5 h-20 w-full rounded-md object-cover">
                                <button type="button" wire:click="hapusBuktiFoto('{{ $jenisBukti->value }}')"
                                        class="mt-1.5 w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('pengiriman.ambil_ulang') }}
                                </button>
                            @else
                                <button type="button"
                                        @click="kameraSlot = '{{ $jenisBukti->value }}'; kameraTerbuka = true; $nextTick(() => window._kameraBuktiPengantaranRider?.nyalakan())"
                                        class="mt-1.5 flex h-20 w-full flex-col items-center justify-center gap-1 rounded-md border border-dashed border-gray-300 text-gray-500 hover:bg-gray-50">
                                    <x-heroicon-o-camera class="size-5" />
                                    <span class="text-xs">{{ __('pengiriman.ambil_foto') }}</span>
                                </button>
                                <label class="mt-1 block cursor-pointer text-center text-xs font-medium text-blue-600 hover:underline">
                                    <x-heroicon-o-arrow-up-tray class="size-3 inline" /> {{ __('kunjungan.unggah_foto') }}
                                    <input type="file" accept="image/*" class="hidden"
                                           onchange="window.unggahBuktiPengantaranRider('{{ $jenisBukti->value }}', this)">
                                </label>
                            @endif
                            @error('buktiFoto.'.$jenisBukti->value) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                {{-- Freezer disusun rider, ATAU toko menyusun sendiri + tanda tangan --}}
                <div class="rounded-lg border border-gray-200 p-3">
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="tokoSusunSendiri"
                               class="mt-0.5 size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                        <span>{{ __('pengiriman.atr_toko_susun_sendiri') }}</span>
                    </label>

                    @if (! $tokoSusunSendiri)
                        @php $jenisFreezer = \App\Enums\JenisBuktiPengiriman::FreezerDisusun; $gambarFreezer = $buktiFoto[$jenisFreezer->value] ?? null; @endphp
                        <div class="mt-3">
                            <p class="text-xs font-medium text-gray-700">{{ $jenisFreezer->label() }}</p>
                            <p class="text-xs text-gray-500">{{ $jenisFreezer->petunjuk() }}</p>
                            @if ($gambarFreezer)
                                <img src="{{ $gambarFreezer }}" alt="{{ $jenisFreezer->label() }}" class="mt-1.5 max-h-40 rounded-lg border border-gray-200">
                                <button type="button" wire:click="hapusBuktiFoto('{{ $jenisFreezer->value }}')"
                                        class="mt-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('pengiriman.ambil_ulang') }}
                                </button>
                            @else
                                <div class="mt-1.5 flex gap-2">
                                    <button type="button"
                                            @click="kameraSlot = '{{ $jenisFreezer->value }}'; kameraTerbuka = true; $nextTick(() => window._kameraBuktiPengantaranRider?.nyalakan())"
                                            class="flex flex-1 items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                        <x-heroicon-o-camera class="size-4" /> {{ __('pengiriman.ambil_foto') }}
                                    </button>
                                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                        <x-heroicon-o-arrow-up-tray class="size-4" /> {{ __('kunjungan.unggah_foto') }}
                                        <input type="file" accept="image/*" class="hidden"
                                               onchange="window.unggahBuktiPengantaranRider('{{ $jenisFreezer->value }}', this)">
                                    </label>
                                </div>
                            @endif
                            @error('buktiFoto.'.$jenisFreezer->value) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="mt-3 space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700">{{ __('pengiriman.atr_nama_penandatangan') }}</label>
                                <input type="text" wire:model="namaPenandatanganToko" placeholder="{{ __('pengiriman.nama_penandatangan_contoh') }}"
                                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                @error('namaPenandatanganToko') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <p class="text-xs font-medium text-gray-700">{{ __('pengiriman.atr_tanda_tangan_toko') }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ __('pengiriman.ket_tanda_tangan_toko') }}</p>

                                @if ($tandaTanganToko)
                                    <img src="{{ $tandaTanganToko }}" alt="{{ __('pengiriman.atr_tanda_tangan_toko') }}"
                                         class="mt-1.5 max-h-32 rounded-lg border border-gray-200 bg-white">
                                    <button type="button" wire:click="hapusTandaTangan"
                                            class="mt-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('pengiriman.tanda_tangan_ulang') }}
                                    </button>
                                @else
                                    <div wire:ignore id="ttd-toko-rider" class="mt-1.5">
                                        <canvas id="kanvas-ttd-toko-rider" width="500" height="200"
                                                class="w-full touch-none rounded-lg border border-gray-300 bg-white" style="height: 160px"></canvas>
                                        <div class="mt-1.5 flex gap-2">
                                            <button type="button" id="bersihkan-ttd-toko-rider"
                                                    class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                                {{ __('pengiriman.bersihkan_tanda_tangan') }}
                                            </button>
                                            <button type="button" id="simpan-ttd-toko-rider"
                                                    class="rounded-md bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                                {{ __('pengiriman.simpan_tanda_tangan') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.catatan_opsional') }}</label>
                    <textarea wire:model="catatanRider" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupKonfirmasi"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpanKonfirmasi" wire:loading.attr="disabled" wire:target="simpanKonfirmasi,fotoNota"
                        @disabled(! $this->semuaTercekKonfirmasi || ! $this->semuaBuktiLengkap)
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">
                    <span wire:loading.remove wire:target="simpanKonfirmasi">{{ __('pengantaran_rider.tombol_selesaikan') }}</span>
                    <span wire:loading wire:target="simpanKonfirmasi">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Jendela kamera (satu dipakai bergantian oleh semua slot bukti) ============ --}}
    <div wire:ignore id="kamera-bukti-pengantaran-rider" x-show="kameraTerbuka" x-cloak
         class="fixed inset-0 z-50 flex flex-col bg-black">
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <span class="text-sm font-medium" x-text="window._labelBuktiPengantaranRider?.[kameraSlot] ?? ''"></span>
            <button type="button" @click="kameraTerbuka = false; window._kameraBuktiPengantaranRider?.matikan()"
                    class="rounded p-2 text-white/70 hover:bg-white/10">
                <x-heroicon-o-x-mark class="size-4 inline" />
            </button>
        </div>

        <div class="relative flex-1 overflow-hidden">
            <video playsinline muted class="size-full object-contain"></video>

            <button type="button" id="tombol-ganti-lensa-bukti-rider" title="{{ __('hr.ganti_kamera') }}"
                    class="absolute right-3 top-3 rounded-full bg-white/20 px-3 py-2 text-white backdrop-blur">
                <x-heroicon-o-arrow-path class="size-4 inline" />
            </button>
        </div>

        <p id="pesan-kamera-bukti-rider" class="mx-4 mb-2 hidden rounded-lg bg-red-500/90 p-2 text-center text-xs text-white"></p>

        <div class="flex items-center justify-center gap-6 pb-8 pt-2">
            <button type="button"
                    @click="window._kameraBuktiPengantaranRider?.jepret(kameraSlot); kameraTerbuka = false"
                    class="grid size-20 place-items-center rounded-full border-4 border-white bg-white/20 text-white active:scale-95">
                <x-heroicon-o-camera class="size-6" />
            </button>
        </div>
    </div>

    @script
    <script>
        window._labelBuktiPengantaranRider = @js(collect(\App\Enums\JenisBuktiPengiriman::wajibFoto())
            ->push(\App\Enums\JenisBuktiPengiriman::FreezerDisusun)
            ->mapWithKeys(fn ($j) => [$j->value => $j->label()]));

        const kameraBuktiRider = window.pasangKamera('kamera-bukti-pengantaran-rider', {
            pesan: @js([
                'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
                'gagal' => __('kunjungan.kamera_gagal'),
                'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
                'belumSiap' => __('kunjungan.kamera_belum_siap'),
                'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
            ]),
        });

        window._kameraBuktiPengantaranRider = kameraBuktiRider;

        document.getElementById('tombol-ganti-lensa-bukti-rider')
            ?.addEventListener('click', () => kameraBuktiRider?.gantiLensa());

        const wadahBuktiRider = document.getElementById('kamera-bukti-pengantaran-rider');

        wadahBuktiRider?.addEventListener('kamera:jepretan', (e) => {
            $wire.terimaBuktiFoto(e.detail.jenis, e.detail.gambar);
        });

        wadahBuktiRider?.addEventListener('kamera:galat', (e) => {
            const pesan = document.getElementById('pesan-kamera-bukti-rider');

            if (pesan) {
                pesan.textContent = e.detail ?? '';
                pesan.classList.toggle('hidden', !e.detail);
            }
        });

        const pesanUnggahanRider = @js([
            'bukanGambar' => __('kunjungan.galat_unggahan_bukan_gambar'),
            'kebesaran' => __('kunjungan.galat_unggahan_kebesaran'),
        ]);
        const ukuranMaksBuktiRider = @js((int) config('visit.foto.ukuran_maks_kb')) * 1024;

        window.unggahBuktiPengantaranRider = (jenis, inputEl) => {
            const berkas = inputEl.files?.[0];
            inputEl.value = '';

            if (!berkas) {
                return;
            }

            if (!berkas.type.startsWith('image/')) {
                window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: pesanUnggahanRider.bukanGambar, jenis: 'error' } }));

                return;
            }

            if (berkas.size > ukuranMaksBuktiRider) {
                window.dispatchEvent(new CustomEvent('notifikasi', { detail: { pesan: pesanUnggahanRider.kebesaran, jenis: 'error' } }));

                return;
            }

            const pembaca = new FileReader();
            pembaca.onload = () => $wire.terimaBuktiFoto(jenis, pembaca.result);
            pembaca.readAsDataURL(berkas);
        };
    </script>
    @endscript

    @script
    <script>
        const pasangTandaTanganRider = () => {
            const kanvas = document.getElementById('kanvas-ttd-toko-rider');

            if (!kanvas || kanvas.dataset.terpasang) {
                return;
            }

            kanvas.dataset.terpasang = '1';

            const ctx = kanvas.getContext('2d');
            let menggambar = false;
            let x = 0;
            let y = 0;
            let sudahMenggambar = false;

            function posisi(e) {
                const kotak = kanvas.getBoundingClientRect();
                const titik = e.touches?.[0] ?? e;

                return {
                    x: (titik.clientX - kotak.left) * (kanvas.width / kotak.width),
                    y: (titik.clientY - kotak.top) * (kanvas.height / kotak.height),
                };
            }

            function mulai(e) {
                e.preventDefault();
                menggambar = true;
                ({ x, y } = posisi(e));
            }

            function gambar(e) {
                if (!menggambar) {
                    return;
                }

                e.preventDefault();

                const titik = posisi(e);

                ctx.strokeStyle = '#1e293b';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(titik.x, titik.y);
                ctx.stroke();

                x = titik.x;
                y = titik.y;
                sudahMenggambar = true;
            }

            function selesai() {
                menggambar = false;
            }

            kanvas.addEventListener('mousedown', mulai);
            kanvas.addEventListener('mousemove', gambar);
            window.addEventListener('mouseup', selesai);
            kanvas.addEventListener('touchstart', mulai, { passive: false });
            kanvas.addEventListener('touchmove', gambar, { passive: false });
            kanvas.addEventListener('touchend', selesai);

            document.getElementById('bersihkan-ttd-toko-rider')?.addEventListener('click', () => {
                ctx.clearRect(0, 0, kanvas.width, kanvas.height);
                sudahMenggambar = false;
            });

            document.getElementById('simpan-ttd-toko-rider')?.addEventListener('click', () => {
                if (!sudahMenggambar) {
                    return;
                }

                $wire.terimaTandaTangan(kanvas.toDataURL('image/png'));
            });
        };

        pasangTandaTanganRider();

        Livewire.hook('morphed', () => pasangTandaTanganRider());
    </script>
    @endscript
</div>
