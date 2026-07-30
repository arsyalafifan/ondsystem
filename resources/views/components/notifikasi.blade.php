{{-- Pesan sesaat dari Livewire maupun dari pengalihan halaman biasa. --}}
<div x-data="{ pesan: null, jenis: 'sukses' }"
     x-on:notifikasi.window="pesan = $event.detail.pesan; jenis = $event.detail.jenis ?? 'sukses'; setTimeout(() => pesan = null, 6000)"
     class="space-y-3">

    @foreach (['sukses' => 'emerald', 'error' => 'red', 'info' => 'blue'] as $kunci => $warna)
        @if (session($kunci))
            <div class="rounded-lg border border-{{ $warna }}-200 bg-{{ $warna }}-50 px-4 py-3 text-sm text-{{ $warna }}-800">
                {{ session($kunci) }}
            </div>
        @endif
    @endforeach

    <template x-if="pesan">
        <div x-cloak
             :class="{
                'border-emerald-200 bg-emerald-50 text-emerald-800': jenis === 'sukses',
                'border-red-200 bg-red-50 text-red-800': jenis === 'error',
                'border-blue-200 bg-blue-50 text-blue-800': jenis === 'info',
             }"
             class="flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm">
            <span x-text="pesan"></span>
            <button type="button" @click="pesan = null" class="shrink-0 opacity-60 hover:opacity-100">✕</button>
        </div>
    </template>
</div>

{{-- Kelas warna di atas dibentuk secara dinamis, jadi disebut sekali di sini
     supaya Tailwind ikut menyertakannya saat membangun berkas gaya:
     border-emerald-200 bg-emerald-50 text-emerald-800
     border-red-200 bg-red-50 text-red-800
     border-blue-200 bg-blue-50 text-blue-800 --}}
