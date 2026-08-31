@props(['opsi', 'nilai' => '', 'set', 'placeholder' => ''])

@php
    // firstWhere() membandingkan dengan == (longgar), jadi id yang datang
    // sebagai string dari properti Livewire (mis. '' atau '5') tetap cocok
    // dengan value integer di daftar opsi tanpa perlu disamakan tipenya dulu.
    $terpilih = collect($opsi)->firstWhere('value', $nilai);
@endphp

{{--
    Kombinasi kotak teks + daftar pilihan yang disaring sambil mengetik —
    dipakai di layar yang daftarnya panjang (produk) supaya tidak perlu
    menggulir <select> biasa. Dibuat sendiri lewat Alpine, bukan menambah
    pustaka seperti Select2/Choices.js: daftarnya sudah ada di halaman
    (dikirim sekali lewat @js), jadi penyaringannya cukup di sisi klien
    tanpa permintaan tambahan ke server.

    Nilai yang benar-benar tersimpan tetap properti Livewire biasa
    (`produk_id`), diperbarui lewat $wire.set() setiap kali sebuah pilihan
    diklik — bukan wire:model, karena kotak teks yang tampil menunjukkan
    LABEL produk, bukan id-nya.

    Daftar pilihannya diteleport ke <body> (x-teleport), bukan digambar
    langsung di bawah kotak teks: kotak ini biasanya dipakai di dalam tabel
    yang dibungkus overflow-x-auto (supaya bisa digulir ke samping di layar
    sempit), dan overflow-x yang bukan "visible" membuat overflow-y ikut
    dianggap "auto" oleh peramban (aturan CSS, bukan bug) — daftar yang
    ditaruh position:absolute di dalamnya jadi terpotong rapi di tepi tabel
    walau z-index-nya sudah tinggi. Meneleport ke <body> dan menghitung
    posisinya sendiri lewat getBoundingClientRect() adalah cara baku
    menghindarinya (dipakai juga oleh pustaka combobox seperti Headless UI).

    Konsekuensinya: daftar itu secara DOM sudah "di luar" kotak teksnya,
    jadi x-on:click.outside di sini tidak bisa dipakai untuk menutupnya.
    Ditutup lewat blur saja — mousedown.prevent pada tiap baris pilihan
    (dan pada seluruh panelnya) mencegah kotak teks kehilangan fokus sama
    sekali saat sebuah baris diklik, jadi blur tidak keburu menutup
    daftarnya sebelum pilih() sempat jalan.
--}}
<div x-data="{
        terbuka: false,
        sorot: -1,
        teks: @js($terpilih['label'] ?? ''),
        opsi: @js(array_values($opsi)),
        posisi: { top: 0, left: 0, width: 0 },
        get hasil() {
            const q = this.teks.trim().toLowerCase();
            return q === '' ? this.opsi : this.opsi.filter((o) => o.label.toLowerCase().includes(q));
        },
        letakkan() {
            const r = this.$refs.masukan.getBoundingClientRect();
            this.posisi = { top: r.bottom + window.scrollY, left: r.left + window.scrollX, width: r.width };
        },
        buka() {
            this.terbuka = true;
            this.teks = '';
            this.sorot = -1;
            this.letakkan();
        },
        tutup() {
            this.terbuka = false;

            // Ketikan yang tidak sampai memilih apa pun dibuang, kembali ke
            // label yang sudah tersimpan — bukan dibiarkan kosong begitu
            // saja seolah-olah pilihannya hilang.
            if (this.teks.trim() === '') {
                this.teks = @js($terpilih['label'] ?? '');
            }
        },
        pilih(o) {
            this.teks = o.label;
            this.terbuka = false;
            $wire.set(@js($set), o.value);
        },
        turun() { this.sorot = Math.min(this.sorot + 1, this.hasil.length - 1); },
        naik() { this.sorot = Math.max(this.sorot - 1, -1); },
        pilihSorot() {
            if (this.hasil[this.sorot]) {
                this.pilih(this.hasil[this.sorot]);
            }
        },
     }"
     x-effect="if (! terbuka) { teks = @js($terpilih['label'] ?? '') }"
     class="relative">
    <input type="text" x-ref="masukan" x-model="teks" autocomplete="off"
           x-on:focus="buka()"
           x-on:input="terbuka = true; sorot = -1; letakkan()"
           x-on:blur="tutup()"
           x-on:keydown.escape="tutup()"
           x-on:keydown.down.prevent="turun()"
           x-on:keydown.up.prevent="naik()"
           x-on:keydown.enter.prevent="pilihSorot()"
           placeholder="{{ $placeholder }}"
           {{ $attributes->merge(['class' => 'block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20']) }}>

    <template x-teleport="body">
        <div x-show="terbuka" x-cloak
             x-on:mousedown.prevent=""
             :style="`top: ${posisi.top}px; left: ${posisi.left}px; width: ${posisi.width}px;`"
             class="absolute z-50 mt-1 max-h-56 overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
            <template x-for="(o, i) in hasil" :key="o.value">
                <button type="button" x-text="o.label"
                        x-on:mousedown.prevent="pilih(o)"
                        x-on:mouseenter="sorot = i"
                        :class="sorot === i ? 'bg-blue-50 text-blue-700' : 'text-gray-900'"
                        class="block w-full px-3 py-2 text-left text-sm"></button>
            </template>
            <p x-show="hasil.length === 0" class="px-3 py-2 text-sm text-gray-500">{{ __('umum.tidak_ada_hasil') }}</p>
        </div>
    </template>
</div>
