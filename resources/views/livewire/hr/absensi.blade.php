@php
    $karyawan = $this->karyawan;
    $posisi = $karyawan?->posisi;
    $terakhir = $this->absensiTerakhir;
@endphp

<div x-data="{ jenisAktif: null, kameraTerbuka: false, lokasi: null, galatKamera: null }"
     x-on:kamera:lokasi.window="lokasi = $event.detail"
     x-on:kamera:galat.window="galatKamera = $event.detail">

    <x-judul-halaman :judul="__('hr.judul_absensi')" :keterangan="__('hr.ket_absensi')" />

    @if ($karyawan === null)
        <x-kartu>
            <x-kosong ikon="identification" :judul="__('hr.galat_tanpa_karyawan')" :keterangan="__('hr.ket_tanpa_karyawan')" />
        </x-kartu>
    @elseif ($posisi === null)
        <x-kartu>
            <x-kosong ikon="user-group" :judul="__('hr.galat_posisi_kosong')" :keterangan="__('hr.ket_posisi_kosong')" />
        </x-kartu>
    @else
        {{-- Identitas & aturan yang berlaku untuk karyawan ini --}}
        <x-kartu>
            <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-4">
                <div>
                    <p class="text-xs text-gray-500">{{ __('hr.atr_nama_lengkap') }}</p>
                    <p class="mt-0.5 font-semibold text-gray-900">{{ $karyawan->nama_lengkap }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('hr.atr_posisi') }}</p>
                    <p class="mt-0.5 font-medium text-gray-900">{{ $posisi->nama }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('hr.jam_kerja') }}</p>
                    <p class="mt-0.5 font-medium tabular-nums text-gray-900">
                        {{ substr($karyawan->shift?->jam_masuk ?? $posisi->jam_masuk, 0, 5) }}
                        –
                        {{ substr($karyawan->shift?->jam_pulang ?? $posisi->jam_pulang, 0, 5) }}
                    </p>
                    <p class="text-xs text-gray-500">{{ $karyawan->shift?->nama ?? __('hr.shift_normal') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('hr.atr_kondisi_absen') }}</p>
                    <p class="mt-0.5 font-medium text-gray-900">{{ $posisi->lokasi_jenis->label() }}</p>
                    @if ($posisi->lokasi_jenis !== \App\Enums\LokasiAbsensi::Bebas)
                        <p class="text-xs text-gray-500">{{ __('hr.maks_jarak', ['jarak' => $posisi->radius_meter]) }}</p>
                    @endif
                </div>
            </div>

            {{-- Status GPS: absen di luar radius akan ditolak, jadi keadaannya
                 ditampilkan sebelum karyawan menekan tombol. --}}
            <div class="border-t border-gray-200 px-4 py-2 text-xs">
                <span x-show="lokasi" x-cloak class="text-emerald-700">
                    {{ __('hr.lokasi_terbaca') }} <span x-text="lokasi ? '(±' + lokasi.akurasi + ' m)' : ''"></span>
                </span>
                <span x-show="! lokasi" class="text-amber-700">{{ __('hr.lokasi_belum') }}</span>
            </div>
        </x-kartu>

        {{-- Hasil absen terakhir: statusnya tepat waktu atau terlambat --}}
        @if ($terakhir !== null)
            <div class="mt-4">
                <x-kartu>
                    <div class="flex flex-wrap items-center gap-4 p-4">
                        <img src="{{ $terakhir->url_foto }}" alt="{{ $terakhir->jenis->label() }}"
                             class="size-20 shrink-0 rounded-lg object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900">
                                {{ $terakhir->jenis->label() }} · {{ $terakhir->waktu->format('H:i') }}
                            </p>
                            <p class="mt-1">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-800' => $terakhir->status === \App\Enums\StatusAbsensi::TepatWaktu,
                                    'bg-red-100 text-red-800' => $terakhir->status === \App\Enums\StatusAbsensi::Terlambat,
                                    'bg-amber-100 text-amber-800' => $terakhir->status === \App\Enums\StatusAbsensi::PulangCepat,
                                ])>
                                    {{ $terakhir->status->label() }}
                                    @if ($terakhir->telat_menit > 0)
                                        · {{ $terakhir->telat_menit }} {{ __('umum.menit') }}
                                    @endif
                                </span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ $terakhir->toko?->nama ?? $terakhir->depot?->nama ?? __('hr.lokasi_absen_bebas') }}
                                @if ($terakhir->jarak_m !== null) · {{ $terakhir->jarak_m }} m @endif
                            </p>
                        </div>
                    </div>
                </x-kartu>
            </div>
        @endif

        {{-- Izin/sakit yang sudah disetujui HR untuk hari ini --}}
        @if ($izin = $this->izinHariIni)
            @php
                $aturanIzin = app(\App\Services\Absensi\AturanAbsensi::class);
                $hariIzin = \Carbon\CarbonImmutable::today();
            @endphp
            <div class="mt-4 flex items-start gap-2 rounded-xl border border-violet-200 bg-violet-50 p-3 text-sm text-violet-900">
                <x-heroicon-o-document-check class="size-5 shrink-0" />
                <p>
                    @if ($izin->porsi === \App\Enums\PorsiIzin::ParuhPertama)
                        {{ __('izin.absen_info_paruh_pertama', ['jam' => $aturanIzin->jamAcuan($karyawan, \App\Enums\JenisAbsensi::Masuk, $hariIzin, $izin)?->format('H:i')]) }}
                    @elseif ($izin->porsi === \App\Enums\PorsiIzin::ParuhKedua)
                        {{ __('izin.absen_info_paruh_kedua', ['jam' => $aturanIzin->jamAcuan($karyawan, \App\Enums\JenisAbsensi::Pulang, $hariIzin, $izin)?->format('H:i')]) }}
                    @else
                        {{ __('izin.absen_info_penuh', ['jenis' => mb_strtolower($izin->jenis->label())]) }}
                    @endif
                </p>
            </div>
        @endif

        @foreach ($this->lemburHariIni as $lb)
            <div class="mt-4 flex items-start gap-2 rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900">
                <x-heroicon-o-clock class="size-5 shrink-0" />
                <p>{{ __('lembur.info_absen', ['jam' => $lb->jamTeks()]) }}</p>
            </div>
        @endforeach

        {{-- Tombol absen per tahap --}}
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ($this->langkah as $langkah)
                @php $sudah = $langkah['sudah']; @endphp
                <x-kartu>
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $langkah['jenis']->label() }}</p>
                                @if ($langkah['acuan'])
                                    <p class="text-xs text-gray-500">{{ __('hr.jam_acuan', ['jam' => $langkah['acuan']]) }}</p>
                                @endif
                            </div>
                            @if ($sudah !== null)
                                <span @class([
                                    'shrink-0 rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-800' => $sudah->status === \App\Enums\StatusAbsensi::TepatWaktu,
                                    'bg-red-100 text-red-800' => $sudah->status === \App\Enums\StatusAbsensi::Terlambat,
                                    'bg-amber-100 text-amber-800' => $sudah->status === \App\Enums\StatusAbsensi::PulangCepat,
                                ])>{{ $sudah->status->label() }}</span>
                            @endif
                        </div>

                        @if ($sudah !== null)
                            <p class="mt-3 text-2xl font-semibold tabular-nums text-gray-900">{{ $sudah->waktu->format('H:i') }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $sudah->toko?->nama ?? $sudah->depot?->nama ?? __('hr.lokasi_absen_bebas') }}
                            </p>
                        @else
                            <button type="button"
                                    @disabled(! $langkah['bisa'])
                                    @click="jenisAktif = '{{ $langkah['jenis']->value }}'; kameraTerbuka = true; $nextTick(() => window._kameraAbsensi?.nyalakan())"
                                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400">
                                <x-heroicon-o-camera class="size-4" />
                                {{ __('hr.tombol_absen') }}
                            </button>
                            @if (! $langkah['bisa'])
                                <p class="mt-1 text-center text-xs text-gray-500">{{ __('hr.ket_absen_masuk_dulu') }}</p>
                            @endif
                        @endif
                    </div>
                </x-kartu>
            @endforeach
        </div>

        {{-- Sales: daftar toko yang masih boleh dipakai absen minggu ini --}}
        @if ($posisi->lokasi_jenis === \App\Enums\LokasiAbsensi::TokoTanggungan)
            <div class="mt-4">
                <x-kartu>
                    <div class="border-b border-gray-200 px-4 py-3">
                        <p class="text-sm font-semibold text-gray-900">{{ __('hr.toko_boleh_absen') }}</p>
                        <p class="text-xs text-gray-500">{{ __('hr.ket_toko_boleh_absen') }}</p>
                    </div>
                    @if ($this->tokoTersisa->isEmpty())
                        <x-kosong ikon="building-storefront" :judul="__('hr.galat_tanpa_toko_tersisa')" />
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($this->tokoTersisa as $toko)
                                <li class="px-4 py-2 text-sm">
                                    <span class="font-medium text-gray-900">{{ $toko->nama }}</span>
                                    <span class="block text-xs text-gray-500">{{ $toko->alamat }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-kartu>
            </div>
        @endif

        {{-- Riwayat absen sendiri --}}
        <div class="mt-4">
            <x-kartu>
                <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900">{{ __('hr.riwayat_absen') }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('umum.tanggal') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('hr.jenis_absen') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('umum.jam') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('hr.tempat_absen') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($this->riwayat as $absen)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-2">{{ $absen->tanggal->isoFormat('D MMM Y') }}</td>
                                    <td class="px-4 py-2">{{ $absen->jenis->label() }}</td>
                                    <td class="px-4 py-2 tabular-nums">{{ $absen->waktu->format('H:i') }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800' => $absen->status === \App\Enums\StatusAbsensi::TepatWaktu,
                                            'bg-red-100 text-red-800' => $absen->status === \App\Enums\StatusAbsensi::Terlambat,
                                            'bg-amber-100 text-amber-800' => $absen->status === \App\Enums\StatusAbsensi::PulangCepat,
                                        ])>{{ $absen->status->label() }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">
                                        {{ $absen->toko?->nama ?? $absen->depot?->nama ?? __('hr.lokasi_absen_bebas') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-kosong ikon="clock" :judul="__('hr.riwayat_kosong')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-kartu>
        </div>

        {{-- ============ Jendela kamera ============ --}}
        {{-- wire:ignore: aliran video tidak boleh terputus setiap kali Livewire
             menggambar ulang halaman (pola yang sama dengan layar kunjungan). --}}
        <div wire:ignore id="kamera-absensi" x-show="kameraTerbuka" x-cloak
             class="fixed inset-0 z-50 flex flex-col bg-black">
            <div class="flex items-center justify-between px-4 py-3 text-white">
                <span class="text-sm font-medium" x-text="jenisAktif ? window._labelAbsen?.[jenisAktif] : ''"></span>
                <button type="button" @click="kameraTerbuka = false; window._kameraAbsensi?.matikan()"
                        class="rounded p-2 text-white/70 hover:bg-white/10">
                    <x-heroicon-o-x-mark class="size-4 inline" />
                </button>
            </div>

            <div class="relative flex-1 overflow-hidden">
                {{-- Kamera depan dicerminkan supaya gerakannya terasa seperti
                     bercermin; hasil jepretannya sendiri tidak ikut dicermin. --}}
                <video playsinline muted class="size-full -scale-x-100 object-contain"></video>

                <button type="button" id="tombol-ganti-lensa-absensi" title="{{ __('hr.ganti_kamera') }}"
                        class="absolute right-3 top-3 rounded-full bg-white/20 px-3 py-2 text-white backdrop-blur">
                    <x-heroicon-o-arrow-path class="size-4 inline" />
                </button>
            </div>

            <p id="pesan-kamera-absensi" class="mx-4 mb-2 hidden rounded-lg bg-red-500/90 p-2 text-center text-xs text-white"></p>

            <div class="flex items-center justify-center gap-6 pb-8 pt-2">
                <button type="button"
                        @click="window._kameraAbsensi?.jepret(jenisAktif); kameraTerbuka = false"
                        class="grid size-20 place-items-center rounded-full border-4 border-white bg-white/20 text-white active:scale-95">
                    <x-heroicon-o-camera class="size-6" />
                </button>
            </div>
        </div>

        @script
        <script>
            // Label dipakai jendela kamera yang berada di luar jangkauan
            // Livewire karena wadahnya wire:ignore.
            window._labelAbsen = @js(collect(\App\Enums\JenisAbsensi::cases())->mapWithKeys(fn ($j) => [$j->value => $j->label()]));

            const kamera = window.pasangKamera('kamera-absensi', {
                // Absensi memakai foto selfie, jadi kamera depan yang dinyalakan
                // lebih dulu; tombol ganti tetap ada untuk kamera belakang.
                lensa: 'depan',
                pesan: @js([
                    'izinDitolak' => __('kunjungan.kamera_izin_ditolak'),
                    'gagal' => __('kunjungan.kamera_gagal'),
                    'tidakDidukung' => __('kunjungan.kamera_tidak_didukung'),
                    'belumSiap' => __('kunjungan.kamera_belum_siap'),
                    'sentuhUntukMulai' => __('kunjungan.sentuh_untuk_mulai'),
                ]),
            });

            window._kameraAbsensi = kamera;
            kamera?.pantauLokasi();

            document.getElementById('tombol-ganti-lensa-absensi')
                ?.addEventListener('click', () => kamera?.gantiLensa());

            const wadah = document.getElementById('kamera-absensi');

            wadah?.addEventListener('kamera:jepretan', (e) => {
                $wire.simpanJepretan(e.detail.jenis, e.detail.gambar, e.detail.lokasi);
            });

            wadah?.addEventListener('kamera:galat', (e) => {
                const pesan = document.getElementById('pesan-kamera-absensi');

                if (pesan) {
                    pesan.textContent = e.detail ?? '';
                    pesan.classList.toggle('hidden', !e.detail);
                }
            });
        </script>
        @endscript
    @endif
</div>
