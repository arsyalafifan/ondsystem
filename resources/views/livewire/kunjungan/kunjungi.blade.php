<div x-data="{ jenisAktif: null, kameraTerbuka: false, lokasi: null }"
     x-on:kamera:lokasi.window="lokasi = $event.detail">

    @php $k = $this->kunjungan; $p = $this->progres; @endphp

    <x-judul-halaman :judul="__('kunjungan.judul_kunjungi')" :keterangan="__('kunjungan.ket_pindai')">
        <x-slot:aksi>
            <a href="{{ route('kunjungan.tugas') }}" wire:navigate
               class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('nav.tugas_saya') }}
            </a>
        </x-slot:aksi>
    </x-judul-halaman>

    {{-- Progres ringkas minggu ini --}}
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-sm text-gray-600">
                <strong class="text-lg text-gray-900">{{ $p['selesai'] }}</strong> / {{ $p['target_efektif'] }}
                {{ __('umum.toko') }}
                @if ($p['tutup'] > 0)
                    <span class="text-xs text-gray-500">· {{ __('kunjungan.toko_tutup') }} {{ $p['tutup'] }}</span>
                @endif
            </p>
            <span class="shrink-0 text-lg font-semibold tabular-nums text-gray-900">{{ $p['persen'] }}%</span>
        </div>
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $p['persen'] }}%"></div>
        </div>
    </div>

    {{-- Penanda lokasi, selalu terlihat supaya sales tahu GPS-nya sudah dapat --}}
    <div class="mb-4 flex items-center gap-2 rounded-lg border px-3 py-2 text-xs"
         :class="lokasi ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
        <span x-text="lokasi ? '<x-heroicon-o-map-pin class="size-4 inline" />' : '<x-heroicon-o-signal class="size-4 inline" />'"></span>
        <span x-show="lokasi" x-cloak
              x-text="'{{ __('kunjungan.lokasi_terbaca', ['akurasi' => '__A__']) }}'.replace('__A__', lokasi?.akurasi ?? '?')"></span>
        <span x-show="!lokasi">{{ __('kunjungan.lokasi_belum') }}</span>
    </div>

    {{-- ============ Pemindai QR ============ --}}
    {{-- Wadah video selalu ada di DOM dan diabaikan Livewire; kalau ikut
         digambar ulang, aliran kameranya putus.

         Tampil-sembunyinya diatur Alpine, bukan kelas dari Blade: wire:ignore
         membuat Livewire melewati elemen ini sepenuhnya, termasuk atribut
         class-nya. Kalau mengandalkan kelas dari server, pemindai tidak akan
         pernah muncul lagi setelah sales melanjutkan kunjungan yang tertunda. --}}
    <div wire:ignore id="pemindai-qr"
         x-data="{ get aktif() { return $wire.tahap === 'pindai' } }"
         x-show="aktif"
         x-effect="aktif || window.hentikanPindaiKunjungan?.()"
         x-cloak>
        <x-kartu :judul="__('kunjungan.pindai_qr')">
            {{-- Menyentuh gambar memfokuskan kamera ke titik itu. Ini yang
                 paling menolong ketika stiker QR terlihat buram. --}}
            <div id="bidik-qr" class="relative aspect-[4/3] w-full overflow-hidden bg-slate-900">
                <video playsinline muted class="size-full object-cover"></video>

                {{-- Bingkai bidik --}}
                <div class="pointer-events-none absolute inset-0 grid place-items-center">
                    <div class="size-48 rounded-2xl border-4 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                </div>

                {{-- Tombol lensa dan senter, muncul hanya bila perangkatnya mendukung --}}
                <div class="absolute right-2 top-2 flex flex-col gap-2">
                    <button type="button" id="tombol-ganti-lensa" title="{{ __('kunjungan.ganti_lensa') }}"
                            class="hidden rounded-full bg-black/55 px-3 py-2 text-base text-white backdrop-blur"><x-heroicon-o-arrow-path class="size-4 inline" /></button>
                    <button type="button" id="tombol-senter" title="{{ __('kunjungan.senter') }}"
                            class="hidden rounded-full bg-black/55 px-3 py-2 text-base text-white backdrop-blur"><x-heroicon-o-light-bulb class="size-4 inline" /></button>
                </div>
            </div>

            <div class="space-y-2 p-4">
                <button type="button" id="tombol-mulai-pindai"
                        class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    <x-heroicon-o-camera class="size-4 inline" /> {{ __('kunjungan.nyalakan_kamera') }}
                </button>
                <p id="pesan-pindai" class="hidden rounded-lg bg-red-50 p-2 text-xs text-red-800"></p>
                <p class="text-xs text-gray-500">{{ __('kunjungan.ket_pindai') }}</p>
                {{-- <p id="petunjuk-pindai" class="hidden rounded-lg bg-amber-50 p-2 text-xs text-amber-900">
                    <x-heroicon-o-information-circle class="size-4 inline" /> {{ __('kunjungan.qr_sulit_terbaca') }}
                </p> --}}
            </div>
        </x-kartu>
    </div>

    {{-- Mode uji: menempel isi QR, agar alur bisa dicoba dari laptop.
         Jalurnya sama persis dengan hasil pemindaian kamera. --}}
    @if ($this->modeUji && $tahap === 'pindai')
        <div class="mt-4 rounded-xl border-2 border-dashed border-violet-300 bg-violet-50 p-4">
            <p class="flex items-center gap-2 text-sm font-semibold text-violet-900">
                🧪 {{ __('kunjungan.mode_uji') }}
            </p>
            <p class="mt-0.5 text-xs text-violet-800">{{ __('kunjungan.mode_uji_ket') }}</p>

            <label class="mt-3 block text-xs font-medium text-violet-900">{{ __('kunjungan.tempel_qr') }}</label>
            <textarea wire:model="qrManual" rows="3"
                      placeholder="资产编号：IDNAH202528004381"
                      class="mt-1 block w-full rounded-lg border-violet-300 font-mono text-xs shadow-sm focus:border-violet-500 focus:ring-violet-500"></textarea>
            <p class="mt-1 text-xs text-violet-700">{{ __('kunjungan.tempel_qr_ket') }}</p>

            @if ($this->contohQr !== [])
                <div class="mt-2">
                    <label class="block text-xs font-medium text-violet-900">{{ __('kunjungan.pilih_toko_contoh') }}</label>
                    <select onchange="if(this.value){ @this.set('qrManual', this.value); }"
                            class="mt-1 block w-full rounded-lg border-violet-300 text-xs shadow-sm focus:border-violet-500 focus:ring-violet-500">
                        <option value="">—</option>
                        @foreach ($this->contohQr as $contoh)
                            <option value="{{ $contoh['qr'] }}">{{ $contoh['nama'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button type="button" wire:click="prosesQrManual"
                    class="mt-3 w-full rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                {{ __('kunjungan.proses_qr') }}
            </button>
        </div>
    @endif

    {{-- ============ Pengambilan foto ============ --}}
    @if ($tahap === 'kunjungan' && $k)
        @php $sudah = $k->fotos->keyBy(fn ($f) => $f->jenis->value); @endphp

        <x-kartu>
            <div class="border-b border-gray-200 p-4">
                <p class="text-lg font-semibold text-gray-900">{{ $k->toko->nama }}</p>
                <p class="text-sm text-gray-600">{{ $k->toko->alamat }}</p>
                <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-gray-500">
                    <span>{{ $k->toko->kode }}</span>
                    <span>{{ __('kunjungan.asset_id') }}: {{ $k->toko->asset_id }}</span>
                    @if ($k->toko->wilayah)<span>{{ $k->toko->wilayah->nama }}</span>@endif
                </p>
            </div>

            <div class="border-b border-gray-200 bg-gray-50 px-4 py-2.5 text-sm">
                <span class="font-medium text-gray-900">
                    {{ __('kunjungan.foto_terkumpul', ['sudah' => $sudah->count(), 'total' => $k->jumlah_foto_wajib]) }}
                </span>
                <span class="block text-xs text-gray-500">{{ __('kunjungan.ket_foto_wajib') }}</span>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($this->jenisFoto as $jenis)
                    @php $foto = $sudah->get($jenis->value); @endphp
                    <div class="flex items-start gap-3 p-3">
                        <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg text-lg
                                     {{ $foto ? 'bg-emerald-100' : 'bg-gray-100' }}">
                            @if($foto)
                                <x-heroicon-s-check-circle class="size-5 text-emerald-500 inline" />
                            @else
                                @svg($jenis->ikon(), 'size-5 text-gray-500 inline')
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900">{{ $jenis->label() }}</p>
                            <p class="text-xs text-gray-500">{{ $jenis->petunjuk() }}</p>

                            @if ($foto)
                                <a href="{{ $foto->url }}" target="_blank" rel="noopener" class="mt-2 inline-block">
                                    <img src="{{ $foto->url }}" alt="{{ $jenis->label() }}"
                                         class="h-24 rounded-lg border border-gray-200">
                                </a>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-col gap-1.5">
                            <button type="button"
                                    @click="jenisAktif = '{{ $jenis->value }}'; kameraTerbuka = true; $nextTick(() => window._kameraKunjungan?.nyalakan())"
                                    @class([
                                        'rounded-lg px-3 py-2 text-sm font-semibold',
                                        'bg-blue-600 text-white hover:bg-blue-700' => ! $foto,
                                        'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => (bool) $foto,
                                    ])>
                                {{ $foto ? __('kunjungan.ulangi_foto') : __('kunjungan.ambil_foto') }}
                            </button>

                            @if ($this->modeUji)
                                <button type="button" wire:click="jepretUji('{{ $jenis->value }}')"
                                        class="rounded-lg border border-violet-300 bg-violet-50 px-3 py-1.5 text-xs font-medium text-violet-800 hover:bg-violet-100">
                                    🧪 {{ __('kunjungan.foto_contoh') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->modeUji && ! $k->foto_lengkap)
                <div class="border-t border-violet-200 bg-violet-50 px-4 py-3">
                    <button type="button" wire:click="isiSemuaFotoUji" wire:loading.attr="disabled"
                            class="w-full rounded-lg border border-violet-300 bg-white px-3 py-2 text-sm font-semibold text-violet-800 hover:bg-violet-100 disabled:opacity-60">
                        <span wire:loading.remove wire:target="isiSemuaFotoUji">🧪 {{ __('kunjungan.isi_semua_foto') }}</span>
                        <span wire:loading wire:target="isiSemuaFotoUji">{{ __('umum.memproses') }}</span>
                    </button>
                </div>
            @endif

            <div class="space-y-3 border-t border-gray-200 p-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('kunjungan.catatan_kunjungan') }}</label>
                    <textarea wire:model="catatan" rows="2" placeholder="{{ __('kunjungan.catatan_kunjungan_contoh') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>

                <button type="button" wire:click="selesaikan" wire:loading.attr="disabled"
                        @disabled(! $k->foto_lengkap)
                        class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                    <span wire:loading.remove wire:target="selesaikan"><x-heroicon-s-check-circle class="size-5 text-emerald-500 inline" /> {{ __('kunjungan.selesaikan_kunjungan') }}</span>
                    <span wire:loading wire:target="selesaikan">{{ __('umum.menyimpan') }}</span>
                </button>

                <div class="flex gap-2">
                    <button type="button" wire:click="$set('formTutupTerbuka', true)"
                            class="flex-1 rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50">
                        <x-heroicon-o-arrow-right-start-on-rectangle class="size-4 inline" /> {{ __('kunjungan.lapor_toko_tutup') }}
                    </button>
                    <button type="button" wire:click="kembaliPindai"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        {{ __('kunjungan.pindai_lagi') }}
                    </button>
                </div>
            </div>
        </x-kartu>
    @elseif ($tahap === 'pindai' && $kunjunganId)
        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            {{ __('kunjungan.status_berjalan') }} — {{ $k?->toko->nama }}
            <button type="button" wire:click="lanjutkanKunjungan" class="ml-1 font-semibold underline">
                {{ __('umum.lihat') }}
            </button>
        </div>
    @endif

    {{-- ============ Jendela kamera ============ --}}
    {{-- Wadah kamera juga selalu ada di DOM. Livewire tidak boleh menyentuhnya
         supaya aliran video tidak terputus setiap kali foto tersimpan. --}}
    <div wire:ignore id="kamera" x-show="kameraTerbuka" x-cloak
         class="fixed inset-0 z-50 flex flex-col bg-black">
        <div class="flex items-center justify-between px-4 py-3 text-white">
            <span class="text-sm font-medium" x-text="jenisAktif ? window._labelJenis?.[jenisAktif] : ''"></span>
            <button type="button" @click="kameraTerbuka = false; window._kameraKunjungan?.matikan()"
                    class="rounded p-2 text-white/70 hover:bg-white/10"><x-heroicon-o-x-mark class="size-4 inline" /></button>
        </div>

        <div id="bidik-foto" class="relative flex-1 overflow-hidden">
            <video playsinline muted class="size-full object-contain"></video>

            <div class="absolute right-3 top-3 flex flex-col gap-2">
                <button type="button" id="tombol-ganti-lensa-foto" title="{{ __('kunjungan.ganti_lensa') }}"
                        class="hidden rounded-full bg-white/20 px-3 py-2 text-base text-white backdrop-blur"><x-heroicon-o-arrow-path class="size-4 inline" /></button>
                <button type="button" id="tombol-senter-foto" title="{{ __('kunjungan.senter') }}"
                        class="hidden rounded-full bg-white/20 px-3 py-2 text-base text-white backdrop-blur"><x-heroicon-o-light-bulb class="size-4 inline" /></button>
            </div>
        </div>

        <p class="px-4 pt-1 text-center text-xs text-white/50">{{ __('kunjungan.sentuh_fokus') }}</p>

        <p class="px-4 pb-2 text-center text-xs text-white/70" x-text="jenisAktif ? window._petunjukJenis?.[jenisAktif] : ''"></p>
        <p id="pesan-kamera" class="mx-4 mb-2 hidden rounded-lg bg-red-500/90 p-2 text-center text-xs text-white"></p>

        <div class="flex items-center justify-center gap-6 pb-8 pt-2">
            <button type="button"
                    @click="window._kameraKunjungan?.jepret(jenisAktif); kameraTerbuka = false"
                    class="grid size-20 place-items-center rounded-full border-4 border-white bg-white/20 text-3xl text-white active:scale-95">
                <x-heroicon-o-camera class="size-4 inline" />
            </button>
        </div>
    </div>

    {{-- ============ Laporan toko tutup ============ --}}
    @if ($formTutupTerbuka)
        <x-modal :judul="__('kunjungan.judul_lapor_tutup')" tutup="$set('formTutupTerbuka', false)">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">{{ __('kunjungan.ket_lapor_tutup') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('kunjungan.alasan_tutup') }}</label>
                    <textarea wire:model="catatanTutup" rows="3" placeholder="{{ __('kunjungan.alasan_tutup_contoh') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('formTutupTerbuka', false)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="ajukanTutup" wire:loading.attr="disabled"
                        class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60">
                    {{ __('kunjungan.kirim_laporan') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @script
    <script>
        // Label dan petunjuk dipakai oleh jendela kamera, yang berada di luar
        // jangkauan Livewire karena wadahnya wire:ignore.
        window._labelJenis = @js(collect($this->jenisFoto)->mapWithKeys(fn ($j) => [$j->value => $j->label()]));
        window._petunjukJenis = @js(collect($this->jenisFoto)->mapWithKeys(fn ($j) => [$j->value => $j->petunjuk()]));

        const pesan = @js([
            'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
            'gagal' => __('kunjungan.kamera_gagal'),
            'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
            'belumSiap' => __('kunjungan.kamera_belum_siap'),
            'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
        ]);

        // --- Pemindai QR ---
        const wadahQr = document.getElementById('pemindai-qr');
        const pemindai = window.pasangPemindaiQr('pemindai-qr', { pesan });

        document.getElementById('tombol-mulai-pindai')?.addEventListener('click', () => pemindai?.mulai());

        wadahQr?.addEventListener('qr:terbaca', (e) => $wire.prosesQr(e.detail));
        wadahQr?.addEventListener('qr:siap', (e) => {
            document.getElementById('tombol-mulai-pindai')?.classList.add('hidden');
            document.getElementById('petunjuk-pindai')?.classList.remove('hidden');
            tampilkanPesan('pesan-pindai', null);

            // Tombol hanya ditampilkan bila perangkatnya memang mendukung,
            // supaya tidak ada tombol yang ditekan lalu tidak terjadi apa-apa.
            tampilkanBila('tombol-ganti-lensa', (e.detail?.jumlahKamera ?? 0) > 1);
            tampilkanBila('tombol-senter', Boolean(e.detail?.adaSenter));
        });
        wadahQr?.addEventListener('qr:galat', (e) => tampilkanPesan('pesan-pindai', e.detail));

        // Safari iOS kadang menolak memutar video sampai ada sentuhan baru.
        wadahQr?.addEventListener('qr:butuh-sentuhan', (e) => {
            tampilkanPesan('pesan-pindai', e.detail);
            document.getElementById('bidik-qr')?.classList.toggle('ring-4', Boolean(e.detail));
            document.getElementById('bidik-qr')?.classList.toggle('ring-amber-400', Boolean(e.detail));
        });

        document.getElementById('tombol-ganti-lensa')?.addEventListener('click', () => pemindai?.gantiKamera());
        document.getElementById('tombol-senter')?.addEventListener('click', () => pemindai?.alihkanSenter());

        // Dipanggil Alpine ketika pemindai tersembunyi, agar kamera tidak
        // terus menyala selama sales mengambil foto.
        window.hentikanPindaiKunjungan = () => {
            pemindai?.berhenti();
            document.getElementById('tombol-mulai-pindai')?.classList.remove('hidden');
            document.getElementById('petunjuk-pindai')?.classList.add('hidden');
        };

        pasangSentuhFokus('bidik-qr', (x, y) => {
            // Sentuhan yang sama dipakai untuk dua hal: memulai pemutaran yang
            // tertahan Safari, lalu memfokuskan ke titik yang disentuh.
            pemindai?.cobaMainkanLagi();
            pemindai?.fokusDi(x, y);
        });

        // QR yang ditolak server boleh dipindai ulang; tanpa ini kode yang sama
        // dianggap sudah terbaca dan diabaikan selamanya.
        $wire.on('qr-ditolak', () => pemindai?.ulangi());
        $wire.on('kunjungan-selesai', () => pemindai?.ulangi());

        // --- Kamera foto ---
        const wadahKamera = document.getElementById('kamera');
        const kamera = window.pasangKamera('kamera', { pesan });

        window._kameraKunjungan = kamera;
        kamera?.pantauLokasi();

        wadahKamera?.addEventListener('kamera:galat', (e) => tampilkanPesan('pesan-kamera', e.detail));
        wadahKamera?.addEventListener('kamera:siap', (e) => {
            tampilkanBila('tombol-ganti-lensa-foto', (e.detail?.jumlahKamera ?? 0) > 1);
            tampilkanBila('tombol-senter-foto', Boolean(e.detail?.adaSenter));
        });

        document.getElementById('tombol-ganti-lensa-foto')?.addEventListener('click', () => kamera?.gantiKamera());
        document.getElementById('tombol-senter-foto')?.addEventListener('click', () => kamera?.alihkanSenter());

        pasangSentuhFokus('bidik-foto', (x, y) => kamera?.fokusDi(x, y));
        wadahKamera?.addEventListener('kamera:lokasi', (e) => {
            const l = e.detail;
            $wire.catatLokasi(l?.lat ?? null, l?.lng ?? null, l?.akurasi ?? null);
        });

        wadahKamera?.addEventListener('kamera:jepretan', (e) => {
            const { jenis, gambar, lokasi } = e.detail;
            $wire.simpanJepretan(jenis, gambar, lokasi);
        });

        /**
         * Menyentuh gambar memfokuskan kamera ke titik itu. Nilai yang dikirim
         * berupa rasio 0..1 dari sudut kiri atas, sesuai yang diminta peramban.
         */
        function pasangSentuhFokus(id, saatDisentuh) {
            const el = document.getElementById(id);

            el?.addEventListener('click', (e) => {
                const kotak = el.getBoundingClientRect();

                saatDisentuh(
                    Math.min(1, Math.max(0, (e.clientX - kotak.left) / kotak.width)),
                    Math.min(1, Math.max(0, (e.clientY - kotak.top) / kotak.height)),
                );

                // Titik kecil sebagai tanda bahwa sentuhan diterima.
                const tanda = document.createElement('div');
                tanda.className = 'pointer-events-none absolute size-12 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white';
                tanda.style.left = (e.clientX - kotak.left) + 'px';
                tanda.style.top = (e.clientY - kotak.top) + 'px';

                el.appendChild(tanda);
                setTimeout(() => tanda.remove(), 600);
            });
        }

        function tampilkanBila(id, tampil) {
            document.getElementById(id)?.classList.toggle('hidden', !tampil);
        }

        function tampilkanPesan(id, teks) {
            const el = document.getElementById(id);

            if (!el) {
                return;
            }

            el.textContent = teks ?? '';
            el.classList.toggle('hidden', !teks);
        }
    </script>
    @endscript
</div>
