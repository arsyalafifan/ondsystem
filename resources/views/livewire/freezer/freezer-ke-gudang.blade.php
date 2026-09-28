<div>
    <x-judul-halaman :judul="__('freezer_gudang.judul')" :keterangan="__('freezer_gudang.ket')">
        <x-slot:aksi>
            <a href="{{ route('master.freezer') }}" wire:navigate
               class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('freezer_gudang.kembali') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    <div class="mx-auto max-w-xl space-y-4">
        {{-- ============ Langkah 1: cari freezer ============ --}}
        @if ($idn === null)
            <x-kartu>
                <div class="p-4">
                    <div class="mb-3 inline-flex rounded-lg border border-gray-300 p-0.5 bg-gray-50">
                        @foreach ([['pindai', 'camera', __('freezer_gudang.cara_pindai')], ['ketik', 'pencil-square', __('freezer_gudang.cara_ketik')]] as [$c, $ikon, $label])
                            <button type="button" wire:click="gantiCara('{{ $c }}')"
                                    @class([
                                        'rounded-md px-3 py-2 text-sm font-medium transition-all flex items-center gap-2',
                                        'bg-white text-blue-600 shadow-sm ring-1 ring-gray-200' => $cara === $c,
                                        'text-gray-500 hover:text-gray-900' => $cara !== $c,
                                    ])>
                                @svg('heroicon-o-'.$ikon, ['class' => 'size-4'])
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    @if ($cara === 'ketik')
                        <form wire:submit="cariKetik" class="flex gap-2">
                            <input type="text" wire:model="idnKetik" autocomplete="off"
                                   placeholder="{{ __('freezer_gudang.placeholder_ketik') }}"
                                   class="block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 font-mono text-sm uppercase text-gray-900 shadow-sm placeholder:normal-case placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            <button type="submit"
                                    class="shrink-0 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                                {{ __('freezer_gudang.tombol_cari') }}
                            </button>
                        </form>
                    @endif
                </div>
            </x-kartu>
        @endif

        {{-- Wadah pemindai selalu ada di DOM dan diabaikan Livewire supaya aliran
             kameranya tidak terputus; tampil-sembunyinya diatur Alpine (lihat
             penjelasan di pesanan/buat-pesanan.blade.php). --}}
        <div wire:ignore id="pemindai-freezer"
             x-data="{ get aktif() { return $wire.cara === 'pindai' && ! $wire.idn } }"
             x-show="aktif"
             x-effect="aktif || window.hentikanPindaiFreezer?.()"
             x-cloak>
            <p class="mb-2 text-xs text-gray-500">{{ __('freezer_gudang.ket_pindai') }}</p>

            <div id="bidik-freezer" class="relative aspect-[4/3] w-full overflow-hidden rounded-lg bg-slate-900">
                <video playsinline muted class="size-full object-cover"></video>

                <div class="pointer-events-none absolute inset-0 grid place-items-center">
                    <div class="size-44 rounded-2xl border-4 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                </div>

                <div class="absolute right-2 top-2 flex flex-col gap-2">
                    <button type="button" id="lensa-freezer" title="{{ __('kunjungan.ganti_lensa') }}"
                            class="hidden rounded-full bg-black/50 p-2.5 text-white backdrop-blur hover:bg-black/70 transition">
                        <x-heroicon-o-arrow-path class="size-5" />
                    </button>
                    <button type="button" id="senter-freezer" title="{{ __('kunjungan.senter') }}"
                            class="hidden rounded-full bg-black/50 p-2.5 text-white backdrop-blur hover:bg-black/70 transition">
                        <x-heroicon-o-light-bulb class="size-5" />
                    </button>
                </div>
            </div>

            <button type="button" id="mulai-pindai-freezer"
                    class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                <x-heroicon-o-camera class="size-5" />
                {{ __('kunjungan.nyalakan_kamera') }}
            </button>
            <p id="pesan-pindai-freezer" class="mt-2 hidden rounded-lg bg-red-50 p-2 text-xs text-red-800"></p>
        </div>

        {{-- ============ Langkah 2: info freezer + pilih gudang ============ --}}
        @if ($this->freezer)
            @php $f = $this->freezer; @endphp
            <x-kartu :judul="__('freezer_gudang.judul_info')">
                <div class="space-y-4 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-mono text-lg font-semibold text-gray-900">{{ $f->idn }}</p>
                            <p class="text-sm text-gray-600">{{ $f->tipe }}</p>
                            @if ($f->keterangan)
                                <p class="mt-0.5 text-xs text-gray-500">{{ $f->keterangan }}</p>
                            @endif
                        </div>
                        @unless ($f->aktif)
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                        @endunless
                    </div>

                    @if ($f->toko)
                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
                            <p class="font-semibold">{{ __('freezer_gudang.status_di_toko', ['toko' => $f->toko->nama]) }}</p>
                            <p class="mt-0.5 text-xs">{{ $f->toko->kode }} · {{ __('master.kolom_gudang') }}: {{ $f->toko->depot?->nama ?? '—' }}</p>
                            <p class="mt-2 text-xs text-sky-800">{{ __('freezer_gudang.ket_terpasang_toko') }}</p>
                        </div>

                        <button type="button" wire:click="ulang"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                            {{ __('freezer_gudang.tombol_pindai_lagi') }}
                        </button>
                    @else
                        @if ($f->depotSimpan)
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                                <p class="font-semibold">{{ __('freezer_gudang.status_di_gudang', ['gudang' => $f->depotSimpan->nama]) }}</p>
                                <p class="mt-0.5 text-xs">
                                    {{ __('freezer_gudang.dicatat_oleh', ['nama' => $f->pencatatGudang?->name ?? '—', 'waktu' => $f->gudang_dicatat_at?->isoFormat('lll') ?? '—']) }}
                                </p>
                            </div>
                        @else
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                <p class="font-semibold">{{ __('freezer_gudang.status_belum_diketahui') }}</p>
                                <p class="mt-0.5 text-xs">{{ __('freezer_gudang.ket_belum_diketahui') }}</p>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('freezer_gudang.atr_gudang') }}</label>
                            <select wire:model="depotId"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ __('freezer_gudang.pilih_gudang') }}</option>
                                @foreach ($this->opsiGudang as $g)
                                    <option value="{{ $g->id }}">{{ $g->nama }}</option>
                                @endforeach
                            </select>
                            @error('depotId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex gap-2">
                            <button type="button" wire:click="ulang"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                                {{ __('umum.batal') }}
                            </button>
                            <button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan"
                                    class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                                <span wire:loading.remove wire:target="simpan">{{ __('freezer_gudang.tombol_simpan') }}</span>
                                <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                            </button>
                        </div>
                    @endif
                </div>
            </x-kartu>
        @endif
    </div>

    @script
    <script>
        const wadah = document.getElementById('pemindai-freezer');
        const pemindai = window.pasangPemindaiQr('pemindai-freezer', {
            pesan: @js([
                'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
                'gagal' => __('kunjungan.kamera_gagal'),
                'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
                'belumSiap' => __('kunjungan.kamera_belum_siap'),
                'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
            ]),
        });

        document.getElementById('mulai-pindai-freezer')?.addEventListener('click', () => pemindai?.mulai());
        document.getElementById('lensa-freezer')?.addEventListener('click', () => pemindai?.gantiKamera());
        document.getElementById('senter-freezer')?.addEventListener('click', () => pemindai?.alihkanSenter());

        wadah?.addEventListener('qr:terbaca', (e) => $wire.pindaiQr(e.detail));

        wadah?.addEventListener('qr:siap', (e) => {
            document.getElementById('mulai-pindai-freezer')?.classList.add('hidden');
            tampilkanPesan(null);

            tampilkanBila('lensa-freezer', (e.detail?.jumlahKamera ?? 0) > 1);
            tampilkanBila('senter-freezer', Boolean(e.detail?.adaSenter));
        });

        wadah?.addEventListener('qr:galat', (e) => tampilkanPesan(e.detail));
        wadah?.addEventListener('qr:butuh-sentuhan', (e) => tampilkanPesan(e.detail));

        // Menyentuh gambar memfokuskan kamera ke titik itu.
        document.getElementById('bidik-freezer')?.addEventListener('click', function (e) {
            const kotak = this.getBoundingClientRect();

            pemindai?.cobaMainkanLagi();
            pemindai?.fokusDi(
                Math.min(1, Math.max(0, (e.clientX - kotak.left) / kotak.width)),
                Math.min(1, Math.max(0, (e.clientY - kotak.top) / kotak.height)),
            );
        });

        // QR yang ditolak (atau freezer yang selesai dicatat) boleh dipindai
        // ulang; tanpa ini kode yang sama dianggap sudah terbaca selamanya.
        $wire.on('qr-freezer-ditolak', () => pemindai?.ulangi());

        // Dipanggil Alpine begitu pemindai tersembunyi, supaya lampu kamera mati.
        window.hentikanPindaiFreezer = () => {
            pemindai?.berhenti();
            document.getElementById('mulai-pindai-freezer')?.classList.remove('hidden');
        };

        function tampilkanBila(id, tampil) {
            document.getElementById(id)?.classList.toggle('hidden', !tampil);
        }

        function tampilkanPesan(teks) {
            const el = document.getElementById('pesan-pindai-freezer');

            if (!el) {
                return;
            }

            el.textContent = teks ?? '';
            el.classList.toggle('hidden', !teks);
        }
    </script>
    @endscript
</div>
