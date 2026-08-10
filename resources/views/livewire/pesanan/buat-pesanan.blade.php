<div>
    <x-judul-halaman :judul="__('pesanan.judul_buat')" :keterangan="__('pesanan.ket_buat')" />

    @if ($kodeTerakhir)
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {!! __('pesanan.tersimpan', ['kode' => '<strong>'.e($kodeTerakhir).'</strong>']) !!}
            <a href="{{ route('pesanan.daftar') }}" wire:navigate class="font-semibold underline">{{ __('pesanan.lihat_daftar') }}</a>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">

            {{-- Langkah 1: toko --}}
            <x-kartu :judul="__('pesanan.langkah_toko')">
                <div class="p-4">
                    @if ($this->toko)
                        <div class="flex items-start justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $this->toko->nama }}</p>
                                <p class="text-sm text-gray-600">{{ $this->toko->kode }} · {{ $this->toko->wilayah->nama }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ $this->toko->alamat }}</p>
                                @unless ($this->toko->punya_koordinat)
                                    <p class="mt-1 text-sm text-amber-700">⚠ {{ __('pesanan.toko_tanpa_koordinat') }}</p>
                                @endunless
                            </div>
                            <button type="button" wire:click="batalPilihToko"
                                    class="shrink-0 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                                {{ __('umum.ganti') }}
                            </button>
                        </div>
                    @else
                        {{-- Dua cara memilih toko: mengetik, atau memindai QR freezer --}}
                        <div class="mb-3 inline-flex rounded-lg border border-gray-300 p-0.5">
                            @foreach ([['ketik', '⌨️', __('pesanan.cara_ketik')], ['pindai', '📷', __('pesanan.cara_pindai')]] as [$cara, $ikon, $label])
                                <button type="button" wire:click="gantiCaraPilih('{{ $cara }}')"
                                        @class([
                                            'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                            'bg-blue-600 text-white' => $caraPilihToko === $cara,
                                            'text-gray-600 hover:bg-gray-50' => $caraPilihToko !== $cara,
                                        ])>
                                    {{ $ikon }} {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        @if ($caraPilihToko === 'ketik')
                            <input type="search" wire:model.live.debounce.300ms="cariToko"
                                   placeholder="{{ __('pesanan.cari_toko') }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">

                            @if (mb_strlen(trim($cariToko)) >= 2)
                                <div class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200">
                                    @forelse ($this->hasilCari as $toko)
                                        <button type="button"
                                                @disabled($toko->punya_pesanan_aktif)
                                                wire:click="pilihToko({{ $toko->id }})"
                                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm enabled:hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:opacity-60">
                                            <span class="min-w-0">
                                                <span class="block font-medium text-gray-900">{{ $toko->nama }}</span>
                                                <span class="block truncate text-xs text-gray-500">
                                                    {{ $toko->kode }} ·
                                                    @if ($toko->asset_id)
                                                        <span class="font-mono">{{ $toko->asset_id }}</span> ·
                                                    @else
                                                        <span class="text-amber-700">{{ __('pesanan.toko_tanpa_aset') }}</span> ·
                                                    @endif
                                                    {{ $toko->wilayah->nama }} · {{ $toko->alamat }}
                                                </span>
                                            </span>
                                            @if ($toko->punya_pesanan_aktif)
                                                <span class="shrink-0 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                                                    {{ __('pesanan.ada_pesanan_aktif') }}
                                                </span>
                                            @endif
                                        </button>
                                    @empty
                                        <p class="px-3 py-4 text-center text-sm text-gray-500">{{ __('pesanan.tidak_ada_toko') }}</p>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                    @endif

                    {{-- Wadah pemindai selalu ada di DOM dan diabaikan Livewire,
                         supaya aliran kameranya tidak terputus setiap kali
                         halaman digambar ulang.

                         Tampil-sembunyinya diatur Alpine, bukan kelas dari
                         Blade: wire:ignore membuat Livewire melewati elemen ini
                         sepenuhnya, termasuk atribut class-nya, sehingga kelas
                         yang dihitung di sisi server tidak akan pernah berubah
                         setelah halaman pertama kali digambar. --}}
                    <div wire:ignore id="pemindai-toko"
                         x-data="{ get aktif() { return $wire.caraPilihToko === 'pindai' && ! $wire.tokoId } }"
                         x-show="aktif"
                         x-effect="aktif || window.hentikanPindaiToko?.()"
                         x-cloak>
                        <p class="mb-2 text-xs text-gray-500">{{ __('pesanan.ket_pindai_qr_toko') }}</p>

                        <div id="bidik-toko" class="relative aspect-[4/3] w-full overflow-hidden rounded-lg bg-slate-900">
                            <video playsinline muted class="size-full object-cover"></video>

                            <div class="pointer-events-none absolute inset-0 grid place-items-center">
                                <div class="size-44 rounded-2xl border-4 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                            </div>

                            <div class="absolute right-2 top-2 flex flex-col gap-2">
                                <button type="button" id="lensa-toko" title="{{ __('kunjungan.ganti_lensa') }}"
                                        class="hidden rounded-full bg-black/55 px-3 py-2 text-base text-white backdrop-blur">🔄</button>
                                <button type="button" id="senter-toko" title="{{ __('kunjungan.senter') }}"
                                        class="hidden rounded-full bg-black/55 px-3 py-2 text-base text-white backdrop-blur">🔦</button>
                            </div>
                        </div>

                        <button type="button" id="mulai-pindai-toko"
                                class="mt-2 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                            📷 {{ __('kunjungan.nyalakan_kamera') }}
                        </button>
                        <p id="pesan-pindai-toko" class="mt-2 hidden rounded-lg bg-red-50 p-2 text-xs text-red-800"></p>
                        {{-- <p id="tips-pindai-toko" class="mt-2 hidden rounded-lg bg-amber-50 p-2 text-xs text-amber-900">
                            💡 {{ __('kunjungan.qr_sulit_terbaca') }}
                        </p> --}}
                    </div>

                    @error('tokoId') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </x-kartu>

            {{-- Langkah 2: produk --}}
            <x-kartu :judul="__('pesanan.langkah_produk')">
                <x-slot:aksi>
                    <button type="button" wire:click="tambahBaris"
                            class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium hover:bg-gray-50">
                        {{ __('pesanan.tambah_baris') }}
                    </button>
                </x-slot:aksi>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('umum.produk') }}</th>
                                <th class="w-32 px-4 py-2 font-medium">{{ __('pesanan.jumlah_dus') }}</th>
                                <th class="w-28 px-4 py-2 text-right font-medium">{{ __('pesanan.tersedia') }}</th>
                                <th class="w-32 px-4 py-2 text-right font-medium">{{ __('umum.subtotal') }}</th>
                                <th class="w-10 px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($baris as $i => $b)
                                @php $produk = $this->produks->firstWhere('id', (int) $b['produk_id']); @endphp
                                <tr>
                                    <td class="px-4 py-2">
                                        <select wire:model.live="baris.{{ $i }}.produk_id"
                                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">{{ __('pesanan.pilih_produk') }}</option>
                                            @foreach ($this->produks as $p)
                                                <option value="{{ $p->id }}">{{ $p->nama }} ({{ $p->kode }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="1" wire:model.live.debounce.400ms="baris.{{ $i }}.jumlah_dus"
                                               class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-2 text-right {{ $produk && (int) ($b['jumlah_dus'] ?: 0) > $produk->stok_tersedia ? 'font-semibold text-red-600' : 'text-gray-500' }}">
                                        {{ $produk ? \App\Support\Bahasa::angka($produk->stok_tersedia) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">
                                        {{ $produk ? \App\Support\Bahasa::rupiah((float) $produk->harga * (int) ($b['jumlah_dus'] ?: 0)) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button type="button" wire:click="hapusBaris({{ $i }})"
                                                class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600">✕</button>
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

            <x-kartu :judul="__('pesanan.langkah_catatan')">
                <div class="p-4">
                    <textarea wire:model="catatan" rows="2" placeholder="{{ __('pesanan.catatan_contoh') }}"
                              class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
            </x-kartu>
        </div>

        {{-- Panel validasi --}}
        <div class="lg:sticky lg:top-6 lg:self-start">
            <x-kartu :judul="__('pesanan.validasi_sistem')">
                <div class="space-y-3 p-4">
                    @php
                        $minDus = (int) config('ond.min_dus_per_toko');
                        $periksa = [
                            ['lulus' => ! $this->adaHalangan('min_dus'), 'teks' => __('pesanan.periksa_min_dus', ['jumlah' => $minDus])],
                            ['lulus' => ! $this->adaHalangan('stok'), 'teks' => __('pesanan.periksa_stok')],
                            ['lulus' => ! $this->adaHalangan('toko') && ! $this->adaHalangan('pesanan_aktif'), 'teks' => __('pesanan.periksa_pesanan_aktif')],
                        ];
                    @endphp

                    @foreach ($periksa as $p)
                        <div class="flex items-center gap-2 text-sm {{ $p['lulus'] ? 'text-emerald-700' : 'text-gray-500' }}">
                            <span>{{ $p['lulus'] ? '✅' : '⬜' }}</span>
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
                        <span wire:loading.remove wire:target="simpan">{{ __('pesanan.tombol_simpan') }}</span>
                        <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                    </button>

                    <p class="text-xs text-gray-500">
                        {{ __('pesanan.catatan_simpan', ['nama' => auth()->user()->name]) }}
                    </p>
                </div>
            </x-kartu>
        </div>
    </div>

    @script
    <script>
        const wadah = document.getElementById('pemindai-toko');
        const pemindai = window.pasangPemindaiQr('pemindai-toko', {
            pesan: @js([
                'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
                'gagal' => __('kunjungan.kamera_gagal'),
                'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
                'belumSiap' => __('kunjungan.kamera_belum_siap'),
                'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
            ]),
        });

        document.getElementById('mulai-pindai-toko')?.addEventListener('click', () => pemindai?.mulai());
        document.getElementById('lensa-toko')?.addEventListener('click', () => pemindai?.gantiKamera());
        document.getElementById('senter-toko')?.addEventListener('click', () => pemindai?.alihkanSenter());

        wadah?.addEventListener('qr:terbaca', (e) => $wire.pilihTokoDariQr(e.detail));

        wadah?.addEventListener('qr:siap', (e) => {
            document.getElementById('mulai-pindai-toko')?.classList.add('hidden');
            document.getElementById('tips-pindai-toko')?.classList.remove('hidden');
            tampilkanPesan(null);

            tampilkanBila('lensa-toko', (e.detail?.jumlahKamera ?? 0) > 1);
            tampilkanBila('senter-toko', Boolean(e.detail?.adaSenter));
        });

        wadah?.addEventListener('qr:galat', (e) => tampilkanPesan(e.detail));

        // Safari iOS kadang menolak memutar video sampai ada sentuhan baru.
        wadah?.addEventListener('qr:butuh-sentuhan', (e) => tampilkanPesan(e.detail));

        // Menyentuh gambar memfokuskan kamera ke titik itu — jalan tercepat
        // menajamkan stiker QR yang terlihat buram.
        document.getElementById('bidik-toko')?.addEventListener('click', function (e) {
            const kotak = this.getBoundingClientRect();

            // Sentuhan yang sama dipakai untuk dua hal: memulai pemutaran yang
            // tertahan Safari, lalu memfokuskan ke titik yang disentuh.
            pemindai?.cobaMainkanLagi();

            pemindai?.fokusDi(
                Math.min(1, Math.max(0, (e.clientX - kotak.left) / kotak.width)),
                Math.min(1, Math.max(0, (e.clientY - kotak.top) / kotak.height)),
            );

            const tanda = document.createElement('div');
            tanda.className = 'pointer-events-none absolute size-12 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white';
            tanda.style.left = (e.clientX - kotak.left) + 'px';
            tanda.style.top = (e.clientY - kotak.top) + 'px';

            this.appendChild(tanda);
            setTimeout(() => tanda.remove(), 600);
        });

        // QR yang ditolak server boleh dipindai ulang; tanpa ini kode yang sama
        // dianggap sudah terbaca dan diabaikan selamanya.
        $wire.on('qr-toko-ditolak', () => pemindai?.ulangi());

        // Dipanggil Alpine begitu pemindai tersembunyi — entah karena toko
        // sudah terpilih atau pengguna kembali ke cara ketik. Tanpa ini lampu
        // kamera tetap menyala dan baterai terkuras.
        window.hentikanPindaiToko = () => {
            pemindai?.berhenti();
            document.getElementById('mulai-pindai-toko')?.classList.remove('hidden');
            document.getElementById('tips-pindai-toko')?.classList.add('hidden');
        };

        function tampilkanBila(id, tampil) {
            document.getElementById(id)?.classList.toggle('hidden', !tampil);
        }

        function tampilkanPesan(teks) {
            const el = document.getElementById('pesan-pindai-toko');

            if (!el) {
                return;
            }

            el.textContent = teks ?? '';
            el.classList.toggle('hidden', !teks);
        }
    </script>
    @endscript
</div>
