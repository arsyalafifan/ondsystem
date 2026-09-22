{{-- Formulir data calon toko. Dipakai dua layar: pendataan sales
     (daftar-noo) dan koreksi admin sebelum menyetujui (persetujuan-noo).
     Nama propertinya dijaga sama lewat App\Livewire\Noo\Concerns\PunyaFormNoo,
     jadi partial ini tidak perlu tahu sedang dipakai siapa. --}}

{{-- --- Data toko --- --}}
<div class="space-y-3">
    <p class="text-sm font-semibold text-gray-900">{{ __('noo.judul_data_toko') }}</p>

    <div>
        <label class="block text-sm font-medium text-gray-700">{{ __('noo.atr_nama_toko') }}</label>
        <input type="text" wire:model="nama"
               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
        @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">{{ __('master.atr_alamat') }}</label>
        <textarea wire:model="alamat" rows="2"
                  class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
        @error('alamat') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('master.atr_wilayah') }}</label>
            <select wire:model="wilayahId"
                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                @foreach ($this->wilayahs as $wilayah)
                    <option value="{{ $wilayah->id }}">{{ $wilayah->nama }}</option>
                @endforeach
            </select>
            @error('wilayahId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('master.kategori') }}</label>
            <select wire:model="kategori"
                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                <option value="">{{ __('master.pilih_kategori') }}</option>
                @foreach (\App\Enums\KategoriToko::cases() as $kat)
                    <option value="{{ $kat->value }}">{{ $kat->label() }}</option>
                @endforeach
            </select>
            @error('kategori') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([['kelurahan', 'master.kelurahan'], ['kecamatan', 'master.atr_kecamatan'], ['kota', 'master.atr_kota'], ['provinsi', 'master.atr_provinsi']] as [$field, $label])
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __($label) }}</label>
                <input type="text" wire:model="{{ $field }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
</div>

{{-- --- Pemilik --- --}}
<div class="space-y-3 border-t border-gray-100 pt-4">
    <p class="text-sm font-semibold text-gray-900">{{ __('noo.judul_data_pemilik') }}</p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('master.atr_nama_pemilik') }}</label>
            <input type="text" wire:model="namaPemilik"
                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            @error('namaPemilik') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('master.atr_nik_pemilik') }}</label>
            <input type="text" inputmode="numeric" wire:model="nikPemilik"
                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            @error('nikPemilik') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('master.atr_telepon') }}</label>
            <input type="text" inputmode="tel" wire:model="telepon"
                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            @error('telepon') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

{{-- --- Paket & freezer --- --}}
<div class="space-y-3 border-t border-gray-100 pt-4">
    <p class="text-sm font-semibold text-gray-900">{{ __('noo.judul_paket_dipilih') }}</p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('noo.atr_paket') }}</label>
            <select wire:model.live="paketNooId"
                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                @foreach ($this->paketTersedia as $paket)
                    <option value="{{ $paket->id }}">
                        {{ $paket->nama }} — {{ __('noo.ringkas_paket', ['reguler' => $paket->dus_reguler, 'bonus' => $paket->dus_bonus]) }}
                    </option>
                @endforeach
            </select>
            @error('paketNooId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('noo.atr_freezer_tipe') }}</label>
            <input type="text" wire:model="freezerTipe" placeholder="{{ __('noo.freezer_tipe_contoh') }}"
                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            @error('freezerTipe') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    @php $paketDipilih = $this->paketTersedia->firstWhere('id', (int) $paketNooId); @endphp
    @if ($paketDipilih)
        <p class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
            {{ $paketDipilih->items->map(fn ($i) => $i->produk->nama.' '.$i->jumlah_dus.($i->is_bonus ? ' ('.__('noo.bonus').')' : ''))->join(', ') }}
        </p>
    @endif
</div>

{{-- --- Titik lokasi --- --}}
<div class="space-y-3 border-t border-gray-100 pt-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-gray-900">{{ __('noo.judul_titik_lokasi') }}</p>
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
    </div>

    <p class="text-xs text-gray-500">{{ __('noo.ket_titik_lokasi') }}</p>

    <div wire:ignore id="peta-pemilih-noo" class="peta h-64 rounded-lg border border-gray-200"></div>

    <div class="text-xs">
        @if ($latitude !== null)
            <span class="text-gray-600">{{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }}</span>
            @if ($asalKoordinat)
                <span class="text-gray-400">· {{ $asalKoordinat }}</span>
            @endif
        @else
            <span class="text-amber-700">{{ __('noo.titik_belum_dipilih') }}</span>
        @endif
    </div>
    @error('latitude') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

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
        @error('koordinatTempel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
