@php
    $warnaStatus = [
        'hadir' => 'bg-emerald-100 text-emerald-800',
        'terlambat' => 'bg-red-100 text-red-800',
        'selesai' => 'bg-blue-100 text-blue-800',
        'belum_absen' => 'bg-gray-100 text-gray-600',
        'alfa' => 'bg-red-100 text-red-800',
        'libur' => 'bg-gray-100 text-gray-500',
        'izin' => 'bg-violet-100 text-violet-800',
        'izin_paruh_pertama' => 'bg-violet-100 text-violet-800',
        'izin_paruh_kedua' => 'bg-violet-100 text-violet-800',
        'sakit' => 'bg-sky-100 text-sky-800',
    ];
    $inputKelas = 'mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
@endphp

<div>
    <x-judul-halaman :judul="__('hr.judul_monitoring')" :keterangan="__('hr.ket_monitoring')" />

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                <input type="date" wire:model.live="tanggal" class="{{ $inputKelas }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('hr.atr_penempatan') }}</label>
                <select wire:model.live="filterDepot" class="{{ $inputKelas }}">
                    <option value="">{{ __('umum.semua') }}</option>
                    @foreach ($this->depots as $depot)
                        <option value="{{ $depot->id }}">{{ $depot->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('hr.atr_posisi') }}</label>
                <select wire:model.live="filterPosisi" class="{{ $inputKelas }}">
                    <option value="">{{ __('umum.semua') }}</option>
                    @foreach ($this->posisis as $posisi)
                        <option value="{{ $posisi->id }}">{{ $posisi->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.status') }}</label>
                <select wire:model.live="filterStatus" class="{{ $inputKelas }}">
                    <option value="">{{ __('umum.semua') }}</option>
                    @foreach (['hadir', 'terlambat', 'selesai', 'izin', 'izin_paruh_pertama', 'izin_paruh_kedua', 'sakit', 'belum_absen', 'alfa', 'libur'] as $status)
                        <option value="{{ $status }}">{{ __('hr.status_harian_'.$status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('hr.cari_karyawan') }}"
                       class="{{ $inputKelas }} w-full">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4 lg:grid-cols-8">
            @foreach ($this->ringkasan as $status => $jumlah)
                <div class="rounded-xl border border-gray-200 bg-white p-3">
                    <p class="text-xs text-gray-500">{{ __('hr.status_harian_'.$status) }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($jumlah)</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_nama_lengkap') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_posisi') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.absen_masuk') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.absen_istirahat') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.absen_pulang') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.durasi_kerja') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('lembur.kolom_lembur') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.tempat_absen') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->baris as $baris)
                        @php $karyawan = $baris['karyawan']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $karyawan->nama_lengkap }}</span>
                                <span class="block text-xs text-gray-500">{{ $karyawan->kode_karyawan }} · {{ $karyawan->depot?->nama }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-600">
                                {{ $karyawan->posisi?->nama ?? '—' }}
                                @if ($karyawan->shift !== null)
                                    <span class="block text-xs text-indigo-700">{{ $karyawan->shift->nama }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium {{ $warnaStatus[$baris['status']] }}"
                                      @if ($baris['izin']) title="{{ $baris['izin']->alasan }}" @endif>
                                    {{ __('hr.status_harian_'.$baris['status']) }}
                                </span>
                                @if ($baris['izin'])
                                    <span class="mt-0.5 block text-xs text-gray-500">{{ $baris['izin']->tanggalTeks() }}</span>
                                @endif
                            </td>
                            @foreach (['masuk', 'istirahat', 'pulang'] as $jenis)
                                <td class="whitespace-nowrap px-4 py-2">
                                    @if ($baris[$jenis] !== null)
                                        <button type="button" wire:click="$set('fotoDilihat', {{ $baris[$jenis]->id }})"
                                                class="group flex items-center gap-2 text-left">
                                            <img src="{{ $baris[$jenis]->url_foto }}" alt=""
                                                 class="size-8 shrink-0 rounded object-cover ring-1 ring-gray-200 group-hover:ring-blue-400">
                                            <span>
                                                <span class="block tabular-nums text-gray-900">{{ $baris[$jenis]->waktu->format('H:i') }}</span>
                                                @if ($baris[$jenis]->telat_menit > 0)
                                                    <span class="block text-xs text-red-600">+{{ $baris[$jenis]->telat_menit }} {{ __('umum.menit') }}</span>
                                                @endif
                                            </span>
                                        </button>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-600">
                                @if ($baris['durasi_menit'] !== null)
                                    {{ intdiv($baris['durasi_menit'], 60) }}{{ __('umum.jam_singkat') }}
                                    {{ $baris['durasi_menit'] % 60 }}{{ __('umum.menit_singkat') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-600">
                                @forelse ($baris['lembur'] as $lb)
                                    <span class="block tabular-nums">{{ $lb['lembur']->jamTeks() }}</span>
                                    <span @class(['block', 'font-medium text-emerald-700' => $lb['hitung']['diakui'] !== null, 'text-gray-500' => $lb['hitung']['diakui'] === null])>
                                        {{ $lb['hitung']['diakui'] === null
                                            ? __('lembur.belum_terealisasi_singkat')
                                            : __('lembur.diakui', ['durasi' => \App\Models\PengajuanLembur::formatMenit($lb['hitung']['diakui'])]) }}
                                    </span>
                                @empty
                                    <span class="text-gray-300">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-2 text-gray-600">
                                {{ $baris['masuk']?->toko?->nama ?? $baris['masuk']?->depot?->nama ?? '—' }}
                                @if ($baris['masuk']?->jarak_m !== null)
                                    <span class="block text-xs text-gray-500">{{ $baris['masuk']->jarak_m }} m</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-kosong ikon="identification" :judul="__('hr.monitoring_kosong')" :keterangan="__('hr.ket_monitoring_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($this->foto !== null)
        <x-modal :judul="$this->foto->karyawan->nama_lengkap.' · '.$this->foto->jenis->label()" tutup="$set('fotoDilihat', null)" lebar="max-w-xl">
            <div class="space-y-3 p-5">
                <img src="{{ $this->foto->url_foto }}" alt="" class="w-full rounded-lg">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('umum.jam') }}</dt>
                        <dd class="tabular-nums text-gray-900">{{ $this->foto->waktu->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('umum.status') }}</dt>
                        <dd class="text-gray-900">{{ $this->foto->status->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('hr.tempat_absen') }}</dt>
                        <dd class="text-gray-900">{{ $this->foto->toko?->nama ?? $this->foto->depot?->nama ?? __('hr.lokasi_absen_bebas') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('kunjungan.wm_lokasi') }}</dt>
                        <dd class="text-gray-900">
                            @if ($this->foto->latitude !== null)
                                <a href="https://www.google.com/maps?q={{ $this->foto->latitude }},{{ $this->foto->longitude }}"
                                   target="_blank" rel="noopener" class="text-blue-700 underline">
                                    {{ $this->foto->latitude }}, {{ $this->foto->longitude }}
                                </a>
                                @if ($this->foto->jarak_m !== null) · {{ $this->foto->jarak_m }} m @endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </x-modal>
    @endif
</div>
