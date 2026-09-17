<div>
    <x-judul-halaman :judul="__('hr.judul_jam_kerja')" :keterangan="__('hr.ket_jam_kerja')" />

    <x-kartu>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_nama_posisi') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.jam_kerja') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_toleransi') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_hari_kerja') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_kondisi_absen') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.absen_istirahat') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->posisis as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $p->nama }}</span>
                                <span class="block text-xs text-gray-500">{{ $p->kode }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 tabular-nums">
                                {{ substr($p->jam_masuk, 0, 5) }} – {{ substr($p->jam_pulang, 0, 5) }}
                            </td>
                            <td class="px-4 py-2 tabular-nums text-gray-600">
                                {{ $p->toleransi_telat_menit > 0 ? $p->toleransi_telat_menit.' '.__('umum.menit') : '—' }}
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-600">
                                @foreach ($p->hariKerja() as $hari)
                                    <span class="mr-1 inline-block rounded bg-gray-100 px-1.5 py-0.5">{{ mb_substr($this->namaHari[$hari], 0, 3) }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-2">
                                <span class="text-gray-900">{{ $p->lokasi_jenis->label() }}</span>
                                @if ($p->lokasi_jenis !== \App\Enums\LokasiAbsensi::Bebas)
                                    <span class="block text-xs text-gray-500">{{ __('hr.maks_jarak', ['jarak' => $p->radius_meter]) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600">
                                {{ $p->pakai_absen_istirahat
                                    ? __('hr.paling_lambat', ['jam' => substr((string) $p->istirahat_paling_lambat, 0, 5)])
                                    : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <button type="button" wire:click="sunting({{ $p->id }})"
                                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                    {{ __('umum.sunting') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="clock" :judul="__('hr.posisi_kosong')" :keterangan="__('hr.ket_jam_kerja_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="__('hr.judul_jam_kerja_sunting')" tutup="tutupForm" lebar="max-w-2xl">
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_jam_masuk') }}</label>
                        <input type="time" wire:model="jamMasuk"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('jamMasuk') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_jam_pulang') }}</label>
                        <input type="time" wire:model="jamPulang"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('jamPulang') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_toleransi') }}</label>
                        <input type="number" min="0" max="240" wire:model="toleransiTelatMenit"
                               class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @error('toleransiTelatMenit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_hari_kerja') }}</label>
                    <div class="mt-2 flex flex-wrap gap-3">
                        @foreach ($this->namaHari as $nomor => $nama)
                            <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                <input type="checkbox" value="{{ $nomor }}" wire:model="hariKerja"
                                       class="size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                                {{ $nama }}
                            </label>
                        @endforeach
                    </div>
                    @error('hariKerja') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-gray-200 p-3">
                    <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_kondisi_absen') }}</label>
                    <div class="mt-2 space-y-2">
                        @foreach ($this->lokasiCases as $lokasi)
                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input type="radio" value="{{ $lokasi->value }}" wire:model.live="lokasiJenis"
                                       class="mt-0.5 size-4 border-gray-400 text-blue-600 focus:ring-blue-500">
                                <span>
                                    {{ $lokasi->label() }}
                                    <span class="block text-xs text-gray-500">{{ $lokasi->keterangan() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @if ($lokasiJenis !== \App\Enums\LokasiAbsensi::Bebas->value)
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_radius') }}</label>
                            <input type="number" min="10" max="5000" wire:model="radiusMeter"
                                   class="mt-1 block w-40 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('radiusMeter') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 p-3">
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="pakaiAbsenIstirahat"
                               class="mt-0.5 size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                        <span>
                            {{ __('hr.atr_pakai_istirahat') }}
                            <span class="block text-xs text-gray-500">{{ __('hr.ket_pakai_istirahat') }}</span>
                        </span>
                    </label>

                    @if ($pakaiAbsenIstirahat)
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_istirahat_paling_lambat') }}</label>
                            <input type="time" wire:model="istirahatPalingLambat"
                                   class="mt-1 block w-40 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('istirahatPalingLambat') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <p class="text-xs text-gray-500">{{ __('hr.ket_shift_menimpa') }}</p>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('umum.simpan') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
