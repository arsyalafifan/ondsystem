@props([
    'produks',
    // Nama properti daftar di komponen Livewire induk ('baris', 'barisBonus', ...).
    'daftar',
    // Isi daftar saat ini — cuma untuk tampilan awal sebelum Alpine siap.
    'isi' => [],
    // Item bonus: harga tidak ditampilkan (selalu gratis).
    'gratis' => false,
])

@php
    $awal = [];
    foreach ($isi as $b) {
        $id = (int) ($b['produk_id'] ?? 0);
        if ($id > 0) {
            $awal[$id] = ($awal[$id] ?? 0) + (int) ($b['jumlah_dus'] ?: 0);
        }
    }
    $teksCari = $produks->mapWithKeys(fn ($p) => [$p->id => mb_strtolower(trim($p->nama.' '.$p->kode.' '.$p->barcode))]);
@endphp

{{--
    Kartu pemilihan produk ala aplikasi pesan-antar makanan: foto, nama,
    harga, tombol tambah/kurang, dan angka yang tetap bisa diketik manual.

    Kartu ini TIDAK menyimpan apa pun sendiri — setiap perubahan jumlah
    diteruskan ke aturJumlah() (App\Livewire\Concerns\PunyaPemilihProdukKartu)
    yang menulis ke daftar yang sama persis dengan sebelumnya, jadi
    validasi & penyimpanan pesanan tidak berubah.

    Tiap kartu wire:ignore: Livewire tidak pernah menyentuh isinya setelah
    tergambar, Alpine yang mengatur tampilannya dengan membaca $wire secara
    reaktif — jadi jumlah tetap benar walau daftarnya berubah dari tempat
    lain (pindai barcode, reset sesudah simpan). wire:key memuat stok supaya
    kartunya digambar ulang begitu stok berubah.
--}}
<div x-data="{
        cari: '',
        hanyaDipilih: false,
        teks: @js($teksCari),
        get kunci() { return this.cari.trim().toLowerCase() },
        get adaCocok() {
            const q = this.kunci;
            return ! q || Object.values(this.teks).some((t) => t.includes(q));
        },
        get jumlahDipilih() {
            return ($wire.{{ $daftar }} || []).filter((b) => parseInt(b.produk_id) > 0 && parseInt(b.jumlah_dus) > 0).length;
        },
     }">
    <div class="flex flex-wrap items-center gap-2 p-3 sm:p-4">
        <div class="relative min-w-0 flex-1">
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
            <input type="search" x-model="cari" placeholder="{{ __('pesanan.kartu_cari') }}"
                   class="block w-full rounded-full border-gray-300 bg-gray-50 py-2 pl-9 pr-4 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div class="inline-flex rounded-full border border-gray-200 bg-gray-50 p-0.5 text-xs font-medium">
            <button type="button" @click="hanyaDipilih = false"
                    :class="! hanyaDipilih ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500'"
                    class="rounded-full px-3 py-1.5">{{ __('umum.semua') }}</button>
            <button type="button" @click="hanyaDipilih = true"
                    :class="hanyaDipilih ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500'"
                    class="rounded-full px-3 py-1.5">
                {{ __('pesanan.kartu_dipilih') }}
                <span x-show="jumlahDipilih > 0" x-text="jumlahDipilih"
                      class="ml-0.5 rounded-full bg-blue-600 px-1.5 text-[10px] text-white"></span>
            </button>
        </div>
    </div>

    <div class="grid gap-2 px-3 pb-3 sm:grid-cols-2 sm:gap-3 sm:px-4 sm:pb-4">
        @foreach ($produks as $p)
            @php
                $stok = $p->stok_tersedia;
                $jumlahAwal = $awal[$p->id] ?? 0;
            @endphp
            <div wire:ignore wire:key="kartu-{{ $daftar }}-{{ $p->id }}-{{ $stok }}"
                 x-data="{
                    id: {{ $p->id }},
                    maks: {{ $stok }},
                    lokal: null,
                    jeda: null,
                    get server() {
                        let n = 0;
                        for (const b of ($wire.{{ $daftar }} || [])) {
                            if (parseInt(b.produk_id) === this.id) n += parseInt(b.jumlah_dus) || 0;
                        }
                        return n;
                    },
                    get qty() { return this.lokal ?? this.server },
                    get tampil() {
                        return (! kunci || teks[this.id].includes(kunci)) && (! hanyaDipilih || this.qty > 0);
                    },
                    atur(n) {
                        n = Math.max(0, Math.min(this.maks, parseInt(n) || 0));
                        this.lokal = n;
                        clearTimeout(this.jeda);
                        this.jeda = setTimeout(() => {
                            const kirim = n;
                            $wire.aturJumlah('{{ $daftar }}', this.id, kirim).then(() => {
                                if (this.lokal === kirim) this.lokal = null;
                            });
                        }, 250);
                    },
                 }"
                 x-show="tampil"
                 :class="qty > 0 ? 'border-blue-500 ring-1 ring-blue-500 bg-blue-50/40' : 'border-gray-200 bg-white'"
                 class="flex gap-3 rounded-xl border p-2.5 transition">
                <x-foto-produk :url="$p->foto_url" class="size-20 shrink-0 rounded-lg" />

                <div class="flex min-w-0 flex-1 flex-col">
                    <p class="line-clamp-2 text-sm font-medium leading-snug text-gray-900">{{ $p->nama }}</p>
                    <p class="mt-0.5 text-xs {{ $stok > 0 ? 'text-gray-500' : 'font-medium text-red-600' }}">
                        {{ $stok > 0 ? __('pesanan.kartu_stok', ['jumlah' => \App\Support\Bahasa::angka($stok)]) : __('pesanan.kartu_habis') }}
                    </p>

                    <div class="mt-auto flex flex-wrap items-end justify-between gap-x-2 gap-y-1.5 pt-1.5">
                        <span class="whitespace-nowrap text-sm font-semibold {{ $gratis ? 'text-emerald-700' : 'text-gray-900' }}">
                            {{ $gratis ? __('pesanan.kartu_bonus') : \App\Support\Bahasa::rupiah((float) $p->harga) }}
                        </span>

                        {{-- Belum dipilih: satu tombol tambah --}}
                        <button type="button" x-show="qty === 0" @click="atur(1)"
                                @disabled($stok <= 0)
                                @style(['display: none' => $jumlahAwal > 0])
                                aria-label="{{ __('pesanan.kartu_tambah') }}"
                                class="grid size-8 shrink-0 place-items-center rounded-full bg-blue-600 text-white shadow-sm hover:bg-blue-700 disabled:bg-gray-200 disabled:text-gray-400">
                            <x-heroicon-m-plus class="size-5" />
                        </button>

                        {{-- Sudah dipilih: kurang · angka (bisa diketik) · tambah --}}
                        <div x-show="qty > 0" @style(['display: none' => $jumlahAwal === 0])
                             class="ml-auto flex shrink-0 items-center gap-1">
                            <button type="button" @click="atur(qty - 1)" aria-label="{{ __('pesanan.kartu_kurangi') }}"
                                    class="grid size-8 place-items-center rounded-full border border-blue-600 text-blue-600 hover:bg-blue-50">
                                <x-heroicon-m-minus class="size-4" />
                            </button>
                            <input type="number" inputmode="numeric" min="0" :max="maks"
                                   :value="qty" value="{{ $jumlahAwal }}"
                                   @focus="$event.target.select()"
                                   @change="atur($event.target.value); $event.target.value = qty"
                                   @keydown.enter.prevent="$event.target.blur()"
                                   class="h-8 w-12 rounded-lg border-gray-300 px-1 text-center text-sm font-semibold tabular-nums text-gray-900 [appearance:textfield] focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            <button type="button" @click="atur(qty + 1)" :disabled="qty >= maks" aria-label="{{ __('pesanan.kartu_tambah') }}"
                                    class="grid size-8 place-items-center rounded-full bg-blue-600 text-white hover:bg-blue-700 disabled:bg-gray-200 disabled:text-gray-400">
                                <x-heroicon-m-plus class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p x-show="! adaCocok" style="display: none" class="px-4 pb-6 text-center text-sm text-gray-500">{{ __('pesanan.kartu_tidak_ada') }}</p>
    @if ($produks->isEmpty())
        <p class="px-4 pb-6 text-center text-sm text-gray-500">{{ __('pesanan.kartu_tidak_ada') }}</p>
    @endif
</div>
