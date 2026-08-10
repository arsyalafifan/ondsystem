{{-- Pesan sesaat dari Livewire maupun dari pengalihan halaman biasa.

     Pesan dari Livewire ditampilkan melayang di atas segalanya, bukan menyatu
     di puncak halaman. Di ponsel, layar kerap sedang menampilkan bagian bawah
     halaman — misalnya jendela kamera — sehingga pesan yang tergambar di
     puncak berada jauh di luar pandangan. Bagi pengguna, hasilnya terlihat
     seperti tombolnya tidak melakukan apa pun. --}}
<div x-data="{ pesan: null, jenis: 'sukses', waktu: null }"
     x-on:notifikasi.window="
        pesan = $event.detail.pesan;
        jenis = $event.detail.jenis ?? 'sukses';
        clearTimeout(waktu);
        waktu = setTimeout(() => pesan = null, 6000);
     ">

    {{-- Pesan dari sesi tetap menyatu dengan halaman: ia muncul bersamaan
         dengan halaman baru, jadi pasti terlihat sejak awal. --}}
    @foreach (['sukses' => 'emerald', 'error' => 'red', 'info' => 'blue'] as $kunci => $warna)
        @if (session($kunci))
            <div class="mb-3 rounded-lg border border-{{ $warna }}-200 bg-{{ $warna }}-50 px-4 py-3 text-sm text-{{ $warna }}-800">
                {{ session($kunci) }}
            </div>
        @endif
    @endforeach

    <template x-if="pesan">
        <div x-cloak
             class="fixed inset-x-3 top-3 z-[60] mx-auto max-w-lg sm:inset-x-auto sm:right-4 sm:left-auto">
            <div :class="{
                    'border-emerald-300 bg-emerald-50 text-emerald-900': jenis === 'sukses',
                    'border-red-300 bg-red-50 text-red-900': jenis === 'error',
                    'border-blue-300 bg-blue-50 text-blue-900': jenis === 'info',
                 }"
                 class="flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
                 x-transition.opacity>
                <span class="flex-1" x-text="pesan"></span>
                <button type="button" @click="pesan = null"
                        class="shrink-0 opacity-60 hover:opacity-100">✕</button>
            </div>
        </div>
    </template>
</div>

{{-- Kelas warna di atas dibentuk secara dinamis, jadi disebut sekali di sini
     supaya Tailwind ikut menyertakannya saat membangun berkas gaya:
     border-emerald-200 bg-emerald-50 text-emerald-800
     border-red-200 bg-red-50 text-red-800
     border-blue-200 bg-blue-50 text-blue-800
     border-emerald-300 bg-emerald-50 text-emerald-900
     border-red-300 bg-red-50 text-red-900
     border-blue-300 bg-blue-50 text-blue-900 --}}
